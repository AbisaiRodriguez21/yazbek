<?php

namespace App\Controllers;

/**
 * CredencialesController
 *
 * Permite al administrador ver y editar las credenciales de producción
 * del servidor (archivo .env) directamente desde el panel de admin.
 *
 * Funcionalidades:
 *  - Muestra los valores actuales del .env en un formulario.
 *  - Guarda cambios campo por campo (POST manual).
 *  - Acepta la subida de un archivo .env para fusionar / reemplazar claves.
 */
class CredencialesController extends BaseController
{
    /** Ruta absoluta al .env raíz del proyecto */
    private string $envPath;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->envPath = ROOTPATH . '.env';
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /admin/credenciales
    // ─────────────────────────────────────────────────────────────────────
    public function index(): string
    {
        $env = $this->parseEnv($this->envPath);

        return view('admin/credenciales', [
            'env'     => $env,
            'campos'  => $this->camposEditables(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /admin/credenciales/guardar  — edición manual del formulario
    // ─────────────────────────────────────────────────────────────────────
    public function guardar(): \CodeIgniter\HTTP\RedirectResponse
    {
        $post   = $this->request->getPost();
        $campos = $this->camposEditables();

        // Construir mapa key => valor a partir del POST
        $nuevos = [];
        foreach ($campos as $grupo) {
            foreach ($grupo['campos'] as $key => $meta) {
                if (isset($post[$key])) {
                    $nuevos[$key] = trim($post[$key]);
                }
            }
        }

        try {
            $this->escribirEnv($nuevos);
            return redirect()->to(base_url('admin/credenciales'))
                ->with('success', 'Credenciales actualizadas correctamente.');
        } catch (\Throwable $e) {
            return redirect()->to(base_url('admin/credenciales'))
                ->with('error', 'Error al guardar: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /admin/credenciales/subir  — carga de archivo .env
    // ─────────────────────────────────────────────────────────────────────
    public function subir(): \CodeIgniter\HTTP\RedirectResponse
    {
        $archivo = $this->request->getFile('env_file');

        if (!$archivo || !$archivo->isValid() || $archivo->hasMoved()) {
            return redirect()->to(base_url('admin/credenciales'))
                ->with('error', 'Archivo inválido o no recibido.');
        }

        // Solo aceptar archivos de texto / sin extensión (típico de .env)
        $ext  = strtolower($archivo->getExtension());
        $mime = $archivo->getMimeType();
        $permitidos = ['', 'env', 'txt'];

        if (!in_array($ext, $permitidos)) {
            return redirect()->to(base_url('admin/credenciales'))
                ->with('error', 'Solo se permiten archivos .env o .txt.');
        }

        $contenido = file_get_contents($archivo->getTempName());
        if ($contenido === false) {
            return redirect()->to(base_url('admin/credenciales'))
                ->with('error', 'No se pudo leer el archivo subido.');
        }

        // Parsear el archivo subido y fusionar con el .env actual
        $nuevos = $this->parseEnvString($contenido);

        // Filtrar: solo aplicar las claves que reconocemos como editables
        $camposPermitidos = [];
        foreach ($this->camposEditables() as $grupo) {
            foreach ($grupo['campos'] as $key => $meta) {
                $camposPermitidos[] = $key;
            }
        }

        $modoReemplazo = (bool) $this->request->getPost('modo_reemplazo');

        if ($modoReemplazo) {
            // Reemplazar SOLO las claves del archivo subido (sin filtrar)
            $aplicar = $nuevos;
        } else {
            // Fusionar: solo las claves reconocidas que vengan en el archivo
            $aplicar = array_intersect_key($nuevos, array_flip($camposPermitidos));
        }

        if (empty($aplicar)) {
            return redirect()->to(base_url('admin/credenciales'))
                ->with('error', 'El archivo no contenía claves reconocidas.');
        }

        try {
            $this->escribirEnv($aplicar);
            $n = count($aplicar);
            return redirect()->to(base_url('admin/credenciales'))
                ->with('success', "Archivo aplicado correctamente. Se actualizaron {$n} claves.");
        } catch (\Throwable $e) {
            return redirect()->to(base_url('admin/credenciales'))
                ->with('error', 'Error al aplicar archivo: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Lee el archivo .env y devuelve un array asociativo key => valor.
     * Ignora líneas vacías y comentarios (#).
     */
    private function parseEnv(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        return $this->parseEnvString(file_get_contents($path));
    }

    private function parseEnvString(string $contenido): array
    {
        $resultado = [];
        foreach (explode("\n", $contenido) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            if (!str_contains($linea, '=')) {
                continue;
            }
            [$key, $valor] = explode('=', $linea, 2);
            $key   = trim($key);
            $valor = trim($valor, " \t\r\n'\"");
            if ($key !== '') {
                $resultado[$key] = $valor;
            }
        }
        return $resultado;
    }

    /**
     * Actualiza (o agrega) claves en el .env.
     * Preserva comentarios y formato del archivo original.
     */
    private function escribirEnv(array $nuevos): void
    {
        if (!file_exists($this->envPath)) {
            throw new \RuntimeException('Archivo .env no encontrado en: ' . $this->envPath);
        }

        // Hacer backup antes de modificar
        $backupPath = $this->envPath . '.bak_' . date('Ymd_His');
        copy($this->envPath, $backupPath);

        $lineas    = file($this->envPath, FILE_IGNORE_NEW_LINES);
        $aplicadas = [];

        // 1) Reemplazar las líneas que ya existen
        foreach ($lineas as &$linea) {
            $trim = trim($linea);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }
            if (!str_contains($trim, '=')) {
                continue;
            }
            [$key] = explode('=', $trim, 2);
            $key   = trim($key);

            if (array_key_exists($key, $nuevos)) {
                $valor   = $nuevos[$key];
                // Envolver en comillas si contiene espacios
                if (str_contains($valor, ' ')) {
                    $valor = "'{$valor}'";
                }
                $linea     = "{$key} = {$valor}";
                $aplicadas[$key] = true;
            }
        }
        unset($linea);

        // 2) Agregar las claves que no existían en el archivo
        $nuevasLineas = [];
        $hayNuevas    = false;
        foreach ($nuevos as $key => $valor) {
            if (!isset($aplicadas[$key])) {
                if (!$hayNuevas) {
                    $nuevasLineas[] = '';
                    $nuevasLineas[] = '# Claves agregadas desde el panel de administración';
                    $hayNuevas = true;
                }
                if (str_contains((string) $valor, ' ')) {
                    $valor = "'{$valor}'";
                }
                $nuevasLineas[] = "{$key} = {$valor}";
            }
        }

        $contenidoFinal = implode("\n", array_merge($lineas, $nuevasLineas));
        file_put_contents($this->envPath, $contenidoFinal);
    }

    /**
     * Define qué campos del .env son editables desde el panel,
     * agrupados por sección.
     */
    private function camposEditables(): array
    {
        return [
            'Entorno' => [
                'label'  => 'Entorno',
                'icon'   => 'simple-icon-settings',
                'campos' => [
                    'CI_ENVIRONMENT' => [
                        'label'   => 'Ambiente',
                        'tipo'    => 'select',
                        'opciones'=> ['development' => 'Desarrollo', 'production' => 'Producción', 'testing' => 'Testing'],
                        'ayuda'   => 'En producción desactiva mensajes de error visibles.',
                    ],
                    'app.baseURL' => [
                        'label'   => 'URL Base',
                        'tipo'    => 'text',
                        'placeholder' => 'https://tudominio.com/',
                        'ayuda'   => 'Debe terminar con /.',
                    ],
                ],
            ],
            'Base de Datos' => [
                'label'  => 'Base de Datos',
                'icon'   => 'simple-icon-layers',
                'campos' => [
                    'database.default.hostname' => [
                        'label'       => 'Host',
                        'tipo'        => 'text',
                        'placeholder' => 'localhost',
                    ],
                    'database.default.database' => [
                        'label'       => 'Nombre de BD',
                        'tipo'        => 'text',
                        'placeholder' => 'mi_base_datos',
                    ],
                    'database.default.username' => [
                        'label'       => 'Usuario',
                        'tipo'        => 'text',
                        'placeholder' => 'root',
                    ],
                    'database.default.password' => [
                        'label'       => 'Contraseña',
                        'tipo'        => 'password',
                        'placeholder' => '••••••••',
                    ],
                    'database.default.port' => [
                        'label'       => 'Puerto',
                        'tipo'        => 'number',
                        'placeholder' => '3306',
                    ],
                    'database.default.DBDriver' => [
                        'label'   => 'Driver',
                        'tipo'    => 'select',
                        'opciones'=> ['MySQLi' => 'MySQLi', 'Postgre' => 'PostgreSQL', 'SQLite3' => 'SQLite3'],
                    ],
                ],
            ],
            'Sesión' => [
                'label'  => 'Sesión',
                'icon'   => 'simple-icon-lock',
                'campos' => [
                    'session.driver' => [
                        'label'   => 'Driver de sesión',
                        'tipo'    => 'select',
                        'opciones'=> [
                            'CodeIgniter\Session\Handlers\FileHandler'     => 'Archivo',
                            'CodeIgniter\Session\Handlers\DatabaseHandler' => 'Base de datos',
                        ],
                    ],
                    'session.savePath' => [
                        'label'       => 'Ruta de sesiones',
                        'tipo'        => 'text',
                        'placeholder' => 'writable/session',
                        'ayuda'       => 'Ruta relativa o absoluta donde se guardan los archivos de sesión.',
                    ],
                    'session.expiration' => [
                        'label'       => 'Expiración (segundos)',
                        'tipo'        => 'number',
                        'placeholder' => '7200',
                    ],
                ],
            ],
        ];
    }
}
