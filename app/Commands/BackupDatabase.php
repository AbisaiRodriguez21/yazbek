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
        $dbCfg  = config('Database')->default;
        $host   = $dbCfg['hostname'] ?? 'localhost';
        $port   = (int)($dbCfg['port'] ?? 3306);
        $user   = $dbCfg['username'] ?? 'root';
        $pass   = $dbCfg['password'] ?? '';
        $dbname = $dbCfg['database'];

        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // ── Limpieza de lunes ────────────────────────────────────────
        if ((int)date('N') === 1) {
            CLI::write('[Lunes] Limpiando respaldos de la semana anterior...', 'yellow');
            foreach (glob($backupDir . 'backup_*.sql') as $file) {
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

        CLI::write("Generando respaldo: {$filename}", 'green');

        // Intentar mysqldump primero
        $mysqldump = $this->findMysqldump();
        $usedPhp   = false;

        if ($mysqldump && function_exists('proc_open')) {
            $args = [$mysqldump, "-h{$host}", "-P{$port}", "-u{$user}"];
            if ($pass !== '') $args[] = "-p{$pass}";

            $db        = \Config\Database::connect();
            $corruptas = $db->query(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND ENGINE IS NULL",
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
                if (file_exists($outputPath)) unlink($outputPath);
                CLI::write('mysqldump falló, usando fallback PHP...', 'yellow');
                if ($stderr) CLI::write($stderr, 'yellow');
                $mysqldump = null; // caer en fallback
            }
        } else {
            $mysqldump = null;
        }

        // ── Fallback PHP puro (Hostinger / hosting compartido) ───────
        if ($mysqldump === null) {
            CLI::write('Usando método PHP nativo (PDO)...', 'cyan');
            [$ok, $errMsg] = $this->generateBackupPhp($outputPath, $host, $port, $user, $pass, $dbname);
            if (!$ok) {
                CLI::error('Error al generar el respaldo: ' . $errMsg);
                if (file_exists($outputPath)) unlink($outputPath);
                return;
            }
            $usedPhp = true;
        }

        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            CLI::error('El respaldo generado está vacío o es inválido.');
            if (file_exists($outputPath)) unlink($outputPath);
            return;
        }

        $size   = round(filesize($outputPath) / 1024, 1);
        $method = $usedPhp ? ' [PHP nativo]' : ' [mysqldump]';
        CLI::write("Respaldo creado: {$filename} ({$size} KB){$method}", 'green');
    }

    /**
     * Genera un dump SQL usando PDO — sin mysqldump ni exec().
     * Funciona en hosting compartido donde proc_open/exec están deshabilitados.
     *
     * @return array{bool, string}
     */
    private function generateBackupPhp(
        string $outputPath,
        string $host,
        int    $port,
        string $user,
        string $pass,
        string $dbname
    ): array {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);

            $fp = fopen($outputPath, 'w');
            if (!$fp) {
                return [false, 'No se pudo crear el archivo: ' . $outputPath];
            }

            fwrite($fp, "-- Yazbek Database Backup (PHP native)\n");
            fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "-- Database: {$dbname}\n\n");
            fwrite($fp, "SET NAMES utf8mb4;\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $pdo->query(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = " . $pdo->quote($dbname) . "
                   AND ENGINE IS NOT NULL
                 ORDER BY TABLE_NAME"
            )->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                CLI::write("  Respaldando tabla: {$table}", 'white');

                $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                fwrite($fp, "-- --------------------------------------------------------\n");
                fwrite($fp, "-- Table: `{$table}`\n");
                fwrite($fp, "-- --------------------------------------------------------\n\n");
                fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fp, $createRow[1] . ";\n\n");

                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $cols = $stmt->columnCount();
                if ($cols === 0) continue;

                $colNames = [];
                for ($i = 0; $i < $cols; $i++) {
                    $meta       = $stmt->getColumnMeta($i);
                    $colNames[] = '`' . $meta['name'] . '`';
                }
                $colList   = implode(', ', $colNames);
                $batchSize = 200;
                $batch     = [];

                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $values  = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                    $batch[] = '(' . implode(', ', $values) . ')';
                    if (count($batch) >= $batchSize) {
                        fwrite($fp, "INSERT INTO `{$table}` ({$colList}) VALUES\n"
                            . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if (!empty($batch)) {
                    fwrite($fp, "INSERT INTO `{$table}` ({$colList}) VALUES\n"
                        . implode(",\n", $batch) . ";\n");
                }
                fwrite($fp, "\n");
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);

            return [true, ''];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
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
            '/usr/local/mysql/bin/mysqldump',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }
        if (function_exists('exec')) {
            exec('where mysqldump 2>NUL', $out, $code);
            if ($code === 0 && !empty($out[0])) return trim($out[0]);
            exec('which mysqldump 2>/dev/null', $out2, $code2);
            if ($code2 === 0 && !empty($out2[0])) return trim($out2[0]);
        }
        return null;
    }
}
