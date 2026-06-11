<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Comando: php spark backup:db
 *
 * - Genera un respaldo con mysqldump y lo guarda en writable/backups/
 * - Si hoy es lunes, elimina primero todos los respaldos de la semana anterior
 *   (archivos .sql con más de 1 día de antigüedad) para no llenar el disco.
 * - Diseñado para ejecutarse de lunes a sábado a medianoche mediante
 *   el Programador de Tareas de Windows.
 */
class BackupDatabase extends BaseCommand
{
    protected $group       = 'Yazbek';
    protected $name        = 'backup:db';
    protected $description = 'Genera respaldo de la BD. Los lunes limpia respaldos anteriores.';

    public function run(array $params)
    {
        $db      = config('Database')->default;
        $host    = $db['hostname']  ?? 'localhost';
        $port    = $db['port']      ?? 3306;
        $user    = $db['username']  ?? 'root';
        $pass    = $db['password']  ?? '';
        $dbname  = $db['database'];

        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // ── Limpieza de lunes ────────────────────────────────────────
        // Si hoy es lunes (1) borramos todos los respaldos anteriores
        if ((int)date('N') === 1) {
            CLI::write('[Lunes] Limpiando respaldos de la semana anterior...', 'yellow');
            foreach (glob($backupDir . 'backup_*.sql') as $file) {
                // Borrar cualquier respaldo que no sea de hoy
                if (date('Y-m-d', filemtime($file)) !== date('Y-m-d')) {
                    unlink($file);
                    CLI::write('  Eliminado: ' . basename($file), 'red');
                }
            }
        }

        // ── Generar respaldo ─────────────────────────────────────────
        $timestamp  = date('Y-m-d_H-i-s');
        $filename   = "backup_{$dbname}_{$timestamp}.sql";
        $outputPath = $backupDir . $filename;

        // Ruta a mysqldump (XAMPP en Windows)
        $mysqldump = $this->findMysqldump();

        if (!$mysqldump) {
            CLI::error('No se encontró mysqldump. Verifica la instalación de XAMPP/MySQL.');
            return;
        }

        $args = [$mysqldump, "-h{$host}", "-P{$port}", "-u{$user}"];
        if ($pass !== '') $args[] = "-p{$pass}";
        // Omitir tablas sin engine (corruptas) para que el respaldo no falle
        $db = \Config\Database::connect();
        $corruptas = $db->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND ENGINE IS NULL",
            [$dbname]
        )->getResultArray();
        foreach ($corruptas as $row) {
            $args[] = "--ignore-table={$dbname}.{$row['TABLE_NAME']}";
            CLI::write('  Omitiendo tabla corrompida: ' . $row['TABLE_NAME'], 'yellow');
        }
        array_push($args, '--single-transaction', '--routines', '--triggers', $dbname);

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['file', $outputPath, 'w'],
            2 => ['pipe', 'w'],
        ];

        CLI::write("Generando respaldo: {$filename}", 'green');
        $pipes    = [];
        $proc     = proc_open($args, $descriptorspec, $pipes);
        $stderr   = '';
        $exitCode = 1;

        if (is_resource($proc)) {
            fclose($pipes[0]);
            $stderr   = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);
        }

        if ($exitCode !== 0 || !file_exists($outputPath) || filesize($outputPath) < 100) {
            CLI::error('Error al generar el respaldo.');
            if ($stderr) CLI::write($stderr);
            if (file_exists($outputPath)) unlink($outputPath);
            return;
        }

        $size = round(filesize($outputPath) / 1024, 1);
        CLI::write("Respaldo creado: {$filename} ({$size} KB)", 'green');
    }

    /** Busca mysqldump en las rutas habituales de XAMPP y MySQL */
    private function findMysqldump(): ?string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }

        // Intentar con PATH del sistema
        exec('where mysqldump 2>NUL', $out, $code);
        if ($code === 0 && !empty($out[0])) return trim($out[0]);

        exec('which mysqldump 2>/dev/null', $out, $code);
        if ($code === 0 && !empty($out[0])) return trim($out[0]);

        return null;
    }
}
