<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 * CfdiConfigController
 * Administración de credenciales CFDI desde el panel admin.
 * Solo accesible por administradores.
 */
class CfdiConfigController extends BaseController
{
    // ── Mostrar formulario ────────────────────────────────────────────────
    public function index(): string
    {
        $cerPath = APPPATH . 'ThirdParty/csd/csd.cer';
        $keyPath = APPPATH . 'ThirdParty/csd/csd.key';

        $cerInfo = file_exists($cerPath)
            ? ['existe' => true, 'tamaño' => filesize($cerPath), 'fecha' => date('d/m/Y H:i', filemtime($cerPath))]
            : ['existe' => false];
        $keyInfo = file_exists($keyPath)
            ? ['existe' => true, 'tamaño' => filesize($keyPath), 'fecha' => date('d/m/Y H:i', filemtime($keyPath))]
            : ['existe' => false];

        return view('admin/cfdi_config', [
            'cfg'     => [],
            'cerInfo' => $cerInfo,
            'keyInfo' => $keyInfo,
        ]);
    }

    // ── Guardar configuración ─────────────────────────────────────────────
    public function guardar(): \CodeIgniter\HTTP\RedirectResponse
    {
        $errors = [];

        // ── Contraseña CSD → .env ────────────────────────────────────────
        $csdPass = trim($this->request->getPost('csd_password') ?? '');
        if ($csdPass !== '') {
            $envPath = ROOTPATH . '.env';
            if (!file_exists($envPath)) {
                $errors[] = 'No se encontró el archivo .env en: ' . $envPath;
            } elseif (!is_writable($envPath)) {
                $errors[] = 'El archivo .env no tiene permisos de escritura. Ejecuta: chmod 664 .env';
            } else {
                $this->escribirEnv('csd.password', $csdPass);
            }
        }

        // ── Archivo CSD .cer ─────────────────────────────────────────────
        $cerFile = $this->request->getFile('csd_cer');
        if ($cerFile && $cerFile->isValid() && !$cerFile->hasMoved()) {
            if (strtolower($cerFile->getClientExtension()) === 'cer') {
                $cerFile->move(APPPATH . 'ThirdParty/csd/', 'csd.cer', true);
            } else {
                $errors[] = 'El archivo .cer debe tener extensión .cer';
            }
        }

        // ── Archivo CSD .key ─────────────────────────────────────────────
        $keyFile = $this->request->getFile('csd_key');
        if ($keyFile && $keyFile->isValid() && !$keyFile->hasMoved()) {
            if (strtolower($keyFile->getClientExtension()) === 'key') {
                $keyFile->move(APPPATH . 'ThirdParty/csd/', 'csd.key', true);
            } else {
                $errors[] = 'El archivo .key debe tener extensión .key';
            }
        }

        if ($errors) {
            return redirect()->to('/admin/cfdi-config')->with('error', implode('<br>', $errors));
        }

        return redirect()->to('/admin/cfdi-config')->with('success', 'Configuración guardada correctamente.');
    }

    // ── Escribe/actualiza una clave en el .env ────────────────────────────
    private function escribirEnv(string $clave, string $valor): void
    {
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath) || !is_writable($envPath)) return;

        $contenido = file_get_contents($envPath);
        $eol       = str_contains($contenido, "\r\n") ? "\r\n" : "\n";
        $lineas    = explode($eol, $contenido);
        $encontrado = false;

        foreach ($lineas as &$linea) {
            $trim = trim($linea);
            if (str_starts_with($trim, '#') || !str_contains($trim, '=')) continue;
            [$k] = explode('=', $trim, 2);
            if (trim($k) === $clave) {
                $linea      = $clave . ' = ' . (str_contains($valor, ' ') ? "'{$valor}'" : $valor);
                $encontrado = true;
                break;
            }
        }
        unset($linea);

        if (!$encontrado) {
            $lineas[] = $clave . ' = ' . (str_contains($valor, ' ') ? "'{$valor}'" : $valor);
        }

        file_put_contents($envPath, implode($eol, $lineas));
    }
}
