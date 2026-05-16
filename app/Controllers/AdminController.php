<?php

namespace App\Controllers;

use App\Models\MensajeAdminModel;
use App\Models\NotaDetalleModel;
use App\Models\NotaModel;
use App\Models\ProductoModel;
use App\Models\UsuarioModel;

/**
 * AdminController
 *
 * Módulo de Administrador (acceso = 1).
 *
 * Migrado desde:
 *   AppNissi/Yazbek/admin/index.php
 *   AppNissi/Yazbek/admin/usuarios.php
 *   AppNissi/Yazbek/admin/inventario.php
 *   AppNissi/Yazbek/admin/ajax.php
 *   AppNissi/Yazbek/admin/ajaxUsuarios.php
 *   AppNissi/Yazbek/admin/mensajes.php
 *   AppNissi/Yazbek/admin/reportediario.php
 *   AppNissi/Yazbek/admin/reportediario_dia.php
 *   AppNissi/Yazbek/admin/videos.php
 *   AppNissi/Yazbek/admin/eliminar_usuario.php
 *   AppNissi/Yazbek/admin/liberar_usuario.php
 *   AppNissi/Yazbek/admin/venta_1.php
 */
class AdminController extends BaseController
{
    protected UsuarioModel      $usuarioModel;
    protected ProductoModel     $productoModel;
    protected NotaModel         $notaModel;
    protected NotaDetalleModel  $notaDetalleModel;
    protected MensajeAdminModel $mensajeModel;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->usuarioModel     = new UsuarioModel();
        $this->productoModel    = new ProductoModel();
        $this->notaModel        = new NotaModel();
        $this->notaDetalleModel = new NotaDetalleModel();
        $this->mensajeModel     = new MensajeAdminModel();
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin  —  Dashboard principal del admin
    // Migrado desde: admin/index.php
    // ──────────────────────────────────────────────────────────────
    public function index(): string
    {
        $hoy        = date('Y-m-d');
        $anio       = (int) date('Y');
        $anioInicio = "{$anio}-01-01 00:00:00";
        $anioFin    = ($anio + 1) . "-01-01 00:00:00";
        $mesInicio  = date('Y-m-01 00:00:00');
        $db         = \Config\Database::connect();

        // ── KPIs del día (1 sola query en vez de 5) ───────────────
        $statsHoy = $db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status=3) AS cancelado,
                SUM(status=4) AS anticipo,
                SUM(status=5) AS pagado,
                COALESCE(SUM(CASE WHEN status IN (4,5) THEN total ELSE 0 END),0) AS ingresos
             FROM notas_1
             WHERE fecha_inicial >= ? AND fecha_inicial < ?",
            ["{$hoy} 00:00:00", "{$hoy} 23:59:59"]
        )->getRow();

        $totalHoy       = (int)($statsHoy->total    ?? 0);
        $totalAnticipo  = (int)($statsHoy->anticipo ?? 0);
        $totalCancelado = (int)($statsHoy->cancelado?? 0);
        $totalPagado    = (int)($statsHoy->pagado   ?? 0);
        $ingresosHoy    = (float)($statsHoy->ingresos ?? 0);

        // ── KPIs de mes y año (1 query) ───────────────────────────
        $statsPeriodo = $db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN fecha_inicial >= ? THEN total ELSE 0 END),0) AS mes,
                COALESCE(SUM(CASE WHEN fecha_inicial >= ? THEN total ELSE 0 END),0) AS anio
             FROM notas_1
             WHERE status IN (4,5) AND fecha_inicial >= ? AND fecha_inicial < ?",
            [$mesInicio, $anioInicio, $anioInicio, $anioFin]
        )->getRow();

        $ingresosMes  = (float)($statsPeriodo->mes  ?? 0);
        $ingresosAnio = (float)($statsPeriodo->anio ?? 0);

        $clientesActivos = (int) $db->query("SELECT COUNT(*) AS t FROM clientes WHERE eliminado=0")->getRow()->t;

        // ── Ventas mensuales del año (rangos de fecha = usa índice) ─
        $ventasMensualesRaw = $db->query(
            "SELECT MONTH(fecha_inicial) AS mes,
                    COALESCE(SUM(total),0) AS total,
                    COUNT(*) AS notas
             FROM notas_1
             WHERE status IN (4,5)
               AND fecha_inicial >= ? AND fecha_inicial < ?
             GROUP BY MONTH(fecha_inicial)
             ORDER BY mes ASC",
            [$anioInicio, $anioFin]
        )->getResultArray();

        $mesesLabels     = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $ventasMensuales = array_fill(0, 12, 0);
        $notasMensuales  = array_fill(0, 12, 0);
        foreach ($ventasMensualesRaw as $row) {
            $idx = (int)$row['mes'] - 1;
            $ventasMensuales[$idx] = round((float)$row['total'], 2);
            $notasMensuales[$idx]  = (int)$row['notas'];
        }

        // ── Histórico anual: todos los años con cualquier actividad ──
        $ventasAnualesRaw = $db->query(
            "SELECT
                YEAR(fecha_inicial) AS anio,
                COALESCE(SUM(CASE WHEN status IN (4,5) THEN total ELSE 0 END),0) AS total,
                COUNT(*) AS notas,
                SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) AS notas_pagadas
             FROM notas_1
             WHERE fecha_inicial IS NOT NULL
               AND YEAR(fecha_inicial) >= 2000
             GROUP BY YEAR(fecha_inicial)
             ORDER BY anio ASC"
        )->getResultArray();

        $aniosLabels   = array_column($ventasAnualesRaw, 'anio');
        $aniosTotales  = array_map(fn($r) => round((float)$r['total'], 2), $ventasAnualesRaw);
        $aniosNotas    = array_map(fn($r) => (int)$r['notas'], $ventasAnualesRaw);
        $aniosPagadas  = array_map(fn($r) => (int)$r['notas_pagadas'], $ventasAnualesRaw);

        // ── Productos con stock bajo (< 10 piezas) ───────────────
        $stockProductos = $db->query(
            "SELECT sku, Descripcion_Larga AS nombre, Color, Talla, piezas
             FROM productosyazbek WHERE piezas < 10
             ORDER BY piezas ASC LIMIT 60"
        )->getResultArray();

        // ── Últimas 15 notas del día ──────────────────────────────
        $recientes = $db->query(
            "SELECT n.folio, n.fecha_inicial, n.total, n.status AS idstatus,
                    COALESCE(c.nombre,'—') AS nombre
             FROM notas_1 n LEFT JOIN clientes c ON n.idCliente = c.id
             WHERE n.fecha_inicial >= ? AND n.fecha_inicial < ?
             ORDER BY n.Id_Notas_1 DESC LIMIT 15",
            ["{$hoy} 00:00:00", "{$hoy} 23:59:59"]
        )->getResultArray();

        return view('admin/index', [
            'usuario'         => $this->getUsuarioSesion(),
            'totalHoy'        => $totalHoy,
            'totalAnticipo'   => $totalAnticipo,
            'totalCancelado'  => $totalCancelado,
            'totalPagado'     => $totalPagado,
            'ingresosHoy'     => $ingresosHoy,
            'ingresosMes'     => $ingresosMes,
            'ingresosAnio'    => $ingresosAnio,
            'clientesActivos' => $clientesActivos,
            'mesesLabels'     => $mesesLabels,
            'ventasMensuales' => $ventasMensuales,
            'notasMensuales'  => $notasMensuales,
            'aniosLabels'     => $aniosLabels,
            'aniosTotales'    => $aniosTotales,
            'aniosNotas'      => $aniosNotas,
            'aniosPagadas'    => $aniosPagadas,
            'stockProductos'  => $stockProductos,
            'recientes'       => $recientes,
            'anio'            => $anio,
            'error'           => session()->getFlashdata('error'),
            'success'         => session()->getFlashdata('success'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/dashboard/datos  —  AJAX: datos pesados para gráficas
    // ──────────────────────────────────────────────────────────────
    public function dashboardDatos(): \CodeIgniter\HTTP\Response
    {
        $anio       = (int) date('Y');
        $anioInicio = "{$anio}-01-01 00:00:00";
        $anioFin    = ($anio + 1) . "-01-01 00:00:00";
        $db         = \Config\Database::connect();

        // Top 10 productos más vendidos (año en curso, por piezas)
        // Filtramos notas_1 primero con rango de fecha para usar índice
        $topProductos = $db->query(
            "SELECT nd.estilo AS sku,
                    COALESCE(MAX(p.Descripcion_Larga), nd.estilo) AS nombre,
                    SUM(nd.cantidad) AS piezas,
                    SUM(nd.importe)  AS importe
             FROM notas_1 n
             INNER JOIN notas_2 nd ON nd.folio = n.folio
             LEFT  JOIN productosyazbek p ON p.sku = nd.estilo
             WHERE n.status IN (4,5)
               AND n.fecha_inicial >= ? AND n.fecha_inicial < ?
             GROUP BY nd.estilo
             ORDER BY piezas DESC
             LIMIT 10",
            [$anioInicio, $anioFin]
        )->getResultArray();

        // Ingresos por vendedor (año)
        $ventasVendedor = $db->query(
            "SELECT u.usuario AS vendedor,
                    COALESCE(SUM(n.total),0) AS total,
                    COUNT(*) AS notas
             FROM notas_1 n
             INNER JOIN usuarios u ON u.Id = n.idVendedor
             WHERE n.status IN (4,5)
               AND n.fecha_inicial >= ? AND n.fecha_inicial < ?
             GROUP BY n.idVendedor
             ORDER BY total DESC
             LIMIT 8",
            [$anioInicio, $anioFin]
        )->getResultArray();

        // Distribución por tipo de pago (año)
        $tipoPago = $db->query(
            "SELECT tp.descripcion AS tipo,
                    COALESCE(SUM(mn.monto),0) AS total
             FROM notas_1 n
             INNER JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             INNER JOIN tipopago tp    ON tp.id = mn.idTipoPago
             WHERE n.status IN (4,5)
               AND n.fecha_inicial >= ? AND n.fecha_inicial < ?
             GROUP BY mn.idTipoPago
             ORDER BY total DESC",
            [$anioInicio, $anioFin]
        )->getResultArray();

        return $this->response
            ->setContentType('application/json')
            ->setJSON([
                'topProductos'   => $topProductos,
                'ventasVendedor' => $ventasVendedor,
                'tipoPago'       => $tipoPago,
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/usuarios  —  Lista y formulario de usuarios
    // Migrado desde: admin/usuarios.php
    // ──────────────────────────────────────────────────────────────
    public function usuarios(): string
    {
        return view('admin/usuarios', [
            'usuario'  => $this->getUsuarioSesion(),
            'usuarios' => $this->usuarioModel->getTodos(),
            'error'    => session()->getFlashdata('error'),
            'success'  => session()->getFlashdata('success'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/usuarios/crear  —  Crea un nuevo usuario
    // Migrado desde: admin/usuarios.php (bloque MM_insert)
    // ──────────────────────────────────────────────────────────────
    public function crearUsuario(): \CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'nombre' => 'required|min_length[2]',
            'mail'   => 'required|valid_email',
            'pass'   => 'required|min_length[3]',
            'acceso' => 'required|in_list[1,2,3,4]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/admin/usuarios')
                             ->with('error', implode(', ', $this->validator->getErrors()));
        }

        $this->usuarioModel->insert([
            'nombre'  => $this->request->getPost('nombre'),
            'usuario' => $this->request->getPost('nombre'),
            'mail'    => $this->request->getPost('mail'),
            'pass'    => $this->request->getPost('pass'),
            'acceso'  => $this->request->getPost('acceso'),
        ]);

        return redirect()->to('/admin/usuarios')->with('success', 'Usuario creado correctamente.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/usuarios/eliminar/:id  —  Elimina un usuario
    // Migrado desde: admin/eliminar_usuario.php
    // ──────────────────────────────────────────────────────────────
    public function eliminarUsuario(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->usuarioModel->delete($id);
        return redirect()->to('/admin/usuarios')->with('success', 'Usuario eliminado.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/usuarios/liberar/:id  —  Libera la bandera del usuario
    // Migrado desde: admin/liberar_usuario.php
    // ──────────────────────────────────────────────────────────────
    public function liberarUsuario(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->usuarioModel->liberarBandera($id);
        return redirect()->to('/admin/usuarios')->with('success', 'Usuario liberado correctamente.');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/ajax/usuarios  —  Edición inline de contraseña (AJAX)
    // Migrado desde: admin/ajaxUsuarios.php
    // ──────────────────────────────────────────────────────────────
    public function ajaxUsuarios(): \CodeIgniter\HTTP\Response
    {
        if (empty($this->request->getPost())) {
            return $this->response->setBody('No editado');
        }

        foreach ($this->request->getPost() as $fieldName => $val) {
            $fieldName = strip_tags(trim($fieldName));
            $val       = strip_tags(trim($val));

            // formato: campo:id  (ej: pass:5)
            $partes   = explode(':', $fieldName);
            $userId   = (int) ($partes[1] ?? 0);
            $campo    = $partes[0] ?? '';

            // Solo se permite editar el campo 'pass'
            if ($campo === 'pass' && $userId > 0 && $val !== '') {
                $this->usuarioModel->cambiarPass($userId, $val);
                return $this->response->setBody('Contraseña actualizada');
            }
        }

        return $this->response->setBody('No editado');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/inventario  —  Lista de productos
    // Migrado desde: admin/inventario.php
    // ──────────────────────────────────────────────────────────────
    public function inventario(): string
    {
        return view('admin/inventario', [
            'usuario'   => $this->getUsuarioSesion(),
            'productos' => $this->productoModel->getInventario(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/inventario/exportar  —  Descarga inventario completo en XLS
    // ──────────────────────────────────────────────────────────────
    public function exportarInventario(): \CodeIgniter\HTTP\Response
    {
        $productos = $this->productoModel->getInventario();

        $html  = '<html><head><meta charset="utf-8"></head><body>';
        $html .= '<table border="1">';
        $html .= '<tr>'
               . '<td><strong>SKU</strong></td>'
               . '<td><strong>Estilo / Descripción / Color / Talla</strong></td>'
               . '<td><strong>P. Mayoreo</strong></td>'
               . '<td><strong>P. Menudeo</strong></td>'
               . '<td><strong>Piezas</strong></td>'
               . '</tr>';

        foreach ($productos as $p) {
            $desc = trim(
                htmlspecialchars($p['estilo']           ?? '') . ' - ' .
                htmlspecialchars($p['Descripcion_Larga'] ?? '') . ' - ' .
                htmlspecialchars($p['Color']            ?? '') . ' - ' .
                htmlspecialchars($p['Talla']            ?? '')
            );
            $html .= '<tr>'
                   . '<td>' . htmlspecialchars($p['sku']     ?? '') . '</td>'
                   . '<td>' . $desc . '</td>'
                   . '<td>' . number_format((float)($p['pMayoreo'] ?? 0), 2) . '</td>'
                   . '<td>' . number_format((float)($p['pMenudeo'] ?? 0), 2) . '</td>'
                   . '<td>' . (int)($p['piezas'] ?? 0) . '</td>'
                   . '</tr>';
        }

        $html .= '</table></body></html>';

        $fecha = date('Y-m-d');
        return $this->response
            ->setHeader('Last-Modified',       gmdate('D,d M Y H:i:s') . ' GMT')
            ->setHeader('Cache-Control',       'no-cache, must-revalidate')
            ->setHeader('Pragma',              'no-cache')
            ->setHeader('Content-Type',        'application/vnd.ms-excel')
            ->setHeader('Content-Disposition', 'attachment; filename=inventario_' . $fecha . '.xls')
            ->setBody($html);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/inventario/buscar-sku?sku=XXX  —  Devuelve JSON del producto
    // ──────────────────────────────────────────────────────────────
    public function buscarProductoSku(): \CodeIgniter\HTTP\Response
    {
        $sku = trim($this->request->getGet('sku') ?? '');
        if ($sku === '') {
            return $this->response->setJSON(['ok' => false, 'error' => 'SKU vacío.']);
        }

        $db = \Config\Database::connect();
        $p  = $db->query(
            "SELECT id, sku, estilo, Descripcion_corta, Descripcion_Larga, Color, Talla, pMayoreo, pMenudeo, piezas
             FROM productosyazbek WHERE sku = ? LIMIT 1",
            [$sku]
        )->getRowArray();

        if (! $p) {
            return $this->response->setJSON(['ok' => false, 'error' => 'SKU no encontrado: ' . esc($sku)]);
        }

        return $this->response->setJSON(['ok' => true, 'producto' => $p]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/inventario/actualizar-producto  —  Actualiza un producto por id
    // ──────────────────────────────────────────────────────────────
    public function actualizarProducto(): \CodeIgniter\HTTP\Response
    {
        $id = (int) $this->request->getPost('id');
        if (! $id) {
            return $this->response->setJSON(['ok' => false, 'error' => 'ID inválido.']);
        }

        $db = \Config\Database::connect();
        try {
            $db->query(
                "UPDATE productosyazbek
                 SET estilo = ?, Descripcion_corta = ?, Descripcion_Larga = ?,
                     Color = ?, Talla = ?, pMayoreo = ?, pMenudeo = ?, piezas = ?
                 WHERE id = ?",
                [
                    trim($this->request->getPost('estilo')            ?? ''),
                    trim($this->request->getPost('Descripcion_corta') ?? ''),
                    trim($this->request->getPost('Descripcion_Larga') ?? ''),
                    trim($this->request->getPost('Color')             ?? ''),
                    trim($this->request->getPost('Talla')             ?? ''),
                    (float) $this->request->getPost('pMayoreo'),
                    (float) $this->request->getPost('pMenudeo'),
                    (int)   $this->request->getPost('piezas'),
                    $id,
                ]
            );
            return $this->response->setJSON(['ok' => true, 'msg' => 'Producto actualizado correctamente.']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // POST /admin/inventario/eliminar  —  Elimina un producto por id
    // ──────────────────────────────────────────────────────────────
    public function eliminarProducto(): \CodeIgniter\HTTP\Response
    {
        $id = (int) $this->request->getPost('id');
        if (! $id) {
            return $this->response->setJSON(['ok' => false, 'error' => 'ID inválido.']);
        }

        $db = \Config\Database::connect();
        try {
            $db->query("DELETE FROM productosyazbek WHERE id = ?", [$id]);
            return $this->response->setJSON(['ok' => true]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers privados para importación CSV
    // ──────────────────────────────────────────────────────────────

    /**
     * Lee el archivo CSV subido, valida extensión y estructura de columnas.
     * Devuelve ['ok'=>true, 'handle'=>resource, 'ruta'=>string, 'headers'=>array]
     * o        ['ok'=>false, 'error'=>string]
     */
    private function abrirCsv(string $campo, array $requeridas): array
    {
        $archivo = $this->request->getFile($campo);

        if (! $archivo || ! $archivo->isValid()) {
            return ['ok' => false, 'error' => 'No se recibió ningún archivo válido.'];
        }

        $ext = strtolower($archivo->getClientExtension());
        if (! in_array($ext, ['csv', 'txt'])) {
            return ['ok' => false, 'error' => 'El archivo debe ser CSV (.csv). Recibido: .' . $ext];
        }

        $nuevoNombre = $archivo->getRandomName();
        $archivo->move(WRITEPATH . 'uploads', $nuevoNombre);
        $ruta = WRITEPATH . 'uploads/' . $nuevoNombre;

        // Abrir eliminando BOM de UTF-8 que agrega WPS Office / Excel
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            return ['ok' => false, 'error' => 'No se pudo leer el archivo.'];
        }
        // Quitar BOM UTF-8 (\xEF\xBB\xBF) si existe al inicio
        $contenido = ltrim($contenido, "\xEF\xBB\xBF");
        file_put_contents($ruta, $contenido);

        $handle = fopen($ruta, 'r');
        if (! $handle) {
            return ['ok' => false, 'error' => 'No se pudo abrir el archivo.'];
        }

        // Auto-detectar separador: leer primera línea y contar comas vs punto y coma
        $primeraLinea = fgets($handle);
        rewind($handle);
        $separador = (substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',')) ? ';' : ',';

        // Leer encabezados (primera fila)
        $rawHeaders = fgetcsv($handle, 0, $separador);
        if (! $rawHeaders) {
            fclose($handle); unlink($ruta);
            return ['ok' => false, 'error' => 'El archivo está vacío o no tiene encabezados.'];
        }

        // Limpiar cada encabezado: trim + quitar caracteres invisibles / comillas
        $headers = array_map(function ($h) {
            $h = trim($h);
            $h = trim($h, '"\'');          // quitar comillas si las hay
            $h = preg_replace('/[^\x20-\x7E]/', '', $h); // quitar no-ASCII residual
            return $h;
        }, $rawHeaders);

        $headersLower = array_map('strtolower', $headers);

        $faltantes = [];
        foreach ($requeridas as $col) {
            if (! in_array(strtolower($col), $headersLower)) {
                $faltantes[] = $col;
            }
        }

        if (! empty($faltantes)) {
            fclose($handle); unlink($ruta);
            return [
                'ok'    => false,
                'error' => 'Columnas faltantes en el CSV: ' . implode(', ', $faltantes)
                         . '. Columnas encontradas: ' . implode(', ', $headers),
            ];
        }

        return ['ok' => true, 'handle' => $handle, 'ruta' => $ruta, 'headers' => $headers, 'sep' => $separador];
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/inventario/importar/producto  —  Insertar nuevos productos
    // Formato CSV: Estilo,SKU,Descripcion Corta,Descripcion Larga,Talla,Color,Precio Menudeo,Precio Mayoreo,Piezas
    // (mismo formato que exporta BaseCompleta / EBD)
    // ──────────────────────────────────────────────────────────────
    public function importarProducto(): \CodeIgniter\HTTP\RedirectResponse
    {
        $requeridas = ['SKU', 'Estilo', 'Descripcion Larga', 'Talla', 'Color', 'Precio Menudeo', 'Precio Mayoreo', 'Piezas'];
        $csv = $this->abrirCsv('archivo_csv', $requeridas);

        if (! $csv['ok']) {
            return redirect()->to('/admin/inventario')->with('error', $csv['error']);
        }

        $handle  = $csv['handle'];
        $ruta    = $csv['ruta'];
        $headers = $csv['headers'];
        $sep     = $csv['sep'];
        $headLow = array_map('strtolower', $headers);

        $db         = \Config\Database::connect();
        $insertados = 0;
        $omitidos   = 0;
        $errores    = 0;

        while (($fila = fgetcsv($handle, 0, $sep)) !== false) {
            if (count($fila) < count($headers)) continue;
            $di = array_combine($headLow, $fila);

            $sku = trim($di['sku'] ?? '');
            if ($sku === '') { $omitidos++; continue; }

            // No insertar si el SKU ya existe
            $existe = $db->query("SELECT id FROM productosyazbek WHERE sku = ? LIMIT 1", [$sku])->getRow();
            if ($existe) { $omitidos++; continue; }

            try {
                $db->query(
                    "INSERT INTO productosyazbek
                        (estilo, sku, Descripcion_corta, Descripcion_Larga, Talla, Color, pMenudeo, pMayoreo, piezas)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        trim($di['estilo']            ?? ''),
                        $sku,
                        trim($di['descripcion corta'] ?? $di['descripcion_corta'] ?? ''),
                        trim($di['descripcion larga'] ?? $di['descripcion_larga'] ?? ''),
                        trim($di['talla']             ?? ''),
                        trim($di['color']             ?? ''),
                        (float)($di['precio menudeo'] ?? $di['pmenudeo'] ?? 0),
                        (float)($di['precio mayoreo'] ?? $di['pmayoreo'] ?? 0),
                        (int)($di['piezas']           ?? 0),
                    ]
                );
                $insertados++;
            } catch (\Exception $e) {
                $errores++;
            }
        }

        fclose($handle);
        unlink($ruta);

        return redirect()->to('/admin/inventario')
            ->with('success', "Productos nuevos insertados: {$insertados}. Omitidos (SKU ya existe): {$omitidos}. Errores: {$errores}.");
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/inventario/importar/precios  —  Actualizar solo precios
    // Formato CSV: SKU,pMayoreo,pMenudeo
    // ──────────────────────────────────────────────────────────────
    public function importarPrecios(): \CodeIgniter\HTTP\RedirectResponse
    {
        $requeridas = ['SKU', 'pMayoreo', 'pMenudeo'];
        $csv = $this->abrirCsv('archivo_csv', $requeridas);

        if (! $csv['ok']) {
            return redirect()->to('/admin/inventario')->with('error', $csv['error']);
        }

        $handle  = $csv['handle'];
        $ruta    = $csv['ruta'];
        $headers = $csv['headers'];
        $sep     = $csv['sep'];
        $headLow = array_map('strtolower', $headers);

        $db          = \Config\Database::connect();
        $actualizados = 0;
        $noEncontrados = 0;

        while (($fila = fgetcsv($handle, 0, $sep)) !== false) {
            if (count($fila) < count($headers)) continue;
            $di = array_combine($headLow, $fila);

            $sku = trim($di['sku'] ?? '');
            if ($sku === '') continue;

            $filas = $db->query(
                "UPDATE productosyazbek SET pMayoreo = ?, pMenudeo = ? WHERE sku = ?",
                [(float)($di['pmayoreo'] ?? 0), (float)($di['pmenudeo'] ?? 0), $sku]
            )->resultID;

            $afectadas = $db->affectedRows();
            if ($afectadas > 0) { $actualizados++; }
            else                { $noEncontrados++; }
        }

        fclose($handle);
        unlink($ruta);

        return redirect()->to('/admin/inventario')
            ->with('success', "Precios actualizados: {$actualizados}. SKUs no encontrados: {$noEncontrados}.");
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/inventario/importar/stock  —  Actualizar solo piezas
    // Formato CSV: SKU,piezas
    // ──────────────────────────────────────────────────────────────
    public function importarStock(): \CodeIgniter\HTTP\RedirectResponse
    {
        $requeridas = ['SKU', 'piezas'];
        $csv = $this->abrirCsv('archivo_csv', $requeridas);

        if (! $csv['ok']) {
            return redirect()->to('/admin/inventario')->with('error', $csv['error']);
        }

        $handle  = $csv['handle'];
        $ruta    = $csv['ruta'];
        $headers = $csv['headers'];
        $sep     = $csv['sep'];
        $headLow = array_map('strtolower', $headers);

        $db          = \Config\Database::connect();
        $actualizados = 0;
        $noEncontrados = 0;

        while (($fila = fgetcsv($handle, 0, $sep)) !== false) {
            if (count($fila) < count($headers)) continue;
            $di = array_combine($headLow, $fila);

            $sku = trim($di['sku'] ?? '');
            if ($sku === '') continue;

            $db->query(
                "UPDATE productosyazbek SET piezas = ? WHERE sku = ?",
                [(int)($di['piezas'] ?? 0), $sku]
            );

            if ($db->affectedRows() > 0) { $actualizados++; }
            else                         { $noEncontrados++; }
        }

        fclose($handle);
        unlink($ruta);

        return redirect()->to('/admin/inventario')
            ->with('success', "Stock actualizado: {$actualizados} productos. SKUs no encontrados: {$noEncontrados}.");
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/inventario/ajax  —  Edición inline de producto (AJAX)
    // Migrado desde: admin/ajax.php
    // ──────────────────────────────────────────────────────────────
    public function ajaxInventario(): \CodeIgniter\HTTP\Response
    {
        if (empty($this->request->getPost())) {
            return $this->response->setBody('No editado');
        }

        foreach ($this->request->getPost() as $fieldName => $val) {
            $fieldName = strip_tags(trim($fieldName));
            $val       = strip_tags(trim($val));

            // formato: campo:id  (ej: pMenudeo:42)
            $partes    = explode(':', $fieldName);
            $productoId = (int) ($partes[1] ?? 0);
            $campo      = $partes[0] ?? '';

            if ($productoId > 0 && $campo !== '' && $val !== '') {
                $ok = $this->productoModel->actualizarCampo($productoId, $campo, $val);
                return $this->response->setBody($ok ? 'El producto ha sido editado' : 'No editado');
            }
        }

        return $this->response->setBody('No editado');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/mensajes  —  Avisos del admin
    // Migrado desde: admin/mensajes.php
    // ──────────────────────────────────────────────────────────────
    public function mensajes(): string
    {
        return view('admin/mensajes', [
            'usuario'  => $this->getUsuarioSesion(),
            'mensajes' => $this->mensajeModel->getTodos(),
            'error'    => session()->getFlashdata('error'),
            'success'  => session()->getFlashdata('success'),
        ]);
    }

    // POST /admin/mensajes/guardar
    public function guardarMensaje(): \CodeIgniter\HTTP\RedirectResponse
    {
        $id    = $this->request->getPost('Id');
        $datos = [
            'imagen'    => $this->request->getPost('imagen_nueva_principal0'),
            'fecha'     => $this->request->getPost('fecha'),
            't_mensaje' => $this->request->getPost('t_mensaje'),
            'texto'     => $this->request->getPost('texto'),
        ];

        if ($id) {
            $this->mensajeModel->update((int) $id, $datos);
        } else {
            $this->mensajeModel->insert($datos);
        }

        return redirect()->to('/admin/mensajes')->with('success', 'Mensaje guardado.');
    }

    // POST /admin/mensajes/subir  —  AJAX: sube imagen del banner (igual que blog_subir.php original)
    public function subirImagenMensaje(): \CodeIgniter\HTTP\Response
    {
        $orden = $this->request->getPost('orden') ?? '0';

        try {
            // Itera $_FILES igual que el original blog_subir.php
            foreach ($this->request->getFiles() as $files) {
                if (! is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $img) {
                    if ($img && $img->isValid() && ! $img->hasMoved()) {
                        $fecha   = date('dmY_His');
                        $newName = $fecha . '_' . $img->getClientName();
                        $nombre  = 'imgAdmin/' . $newName;
                        $destino = FCPATH . 'imgAdmin/';

                        if (! is_dir($destino)) {
                            mkdir($destino, 0777, true);
                        }

                        $img->move($destino, $newName);

                        return $this->response
                            ->setStatusCode(200)
                            ->setContentType('text/plain')
                            ->setBody($nombre . '*-' . $orden);
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'subirImagenMensaje: ' . $e->getMessage());
            return $this->response
                ->setStatusCode(200)
                ->setContentType('text/plain')
                ->setBody('no *-' . $orden);
        }

        return $this->response
            ->setStatusCode(200)
            ->setContentType('text/plain')
            ->setBody('no *-' . $orden);
    }

    // GET /admin/mensajes/eliminar/:id
    public function eliminarMensaje(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->mensajeModel->delete($id);
        return redirect()->to('/admin/mensajes')->with('success', 'Mensaje eliminado.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/clientes  —  Lista de clientes (acceso admin)
    // Migrado desde: mostrador/clientes_add.php (accesible por rol 1,2,3,4 en original)
    // ──────────────────────────────────────────────────────────────
    public function clientes(): string
    {
        return view('mostrador/clientes', [
            'usuario'  => $this->getUsuarioSesion(),
            'rutaBase' => 'admin',
        ]);
    }

    // GET /admin/clientes/datatable  —  AJAX server-side
    public function clientesDatatable(): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteModel = new \App\Models\ClienteModel();
        $draw   = (int) $this->request->getGet('draw');
        $start  = (int) $this->request->getGet('start');
        $length = (int) $this->request->getGet('length');
        $search = $this->request->getGet('search')['value'] ?? '';
        $orderCol = $this->request->getGet('order')[0]['column'] ?? 0;
        $orderDir = $this->request->getGet('order')[0]['dir'] ?? 'asc';

        $result = $clienteModel->getDatatable($start, $length, $search, $orderCol, $orderDir);

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $result['data'],
        ]);
    }

    // POST /admin/clientes/crear
    public function crearCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $clienteModel = new \App\Models\ClienteModel();

        $clienteModel->insert([
            'nombre'        => strtoupper(trim($this->request->getPost('nombre') ?? '')),
            'telefono'      => trim($this->request->getPost('telefono') ?? ''),
            'celular'       => trim($this->request->getPost('celular') ?? ''),
            'mail'          => trim($this->request->getPost('mail') ?? ''),
            'RFC'           => strtoupper(trim($this->request->getPost('RFC') ?? '')),
            'direccion'     => strtoupper(trim($this->request->getPost('direccion') ?? '')),
            'CP'            => trim($this->request->getPost('CP') ?? ''),
            'estado'        => strtoupper(trim($this->request->getPost('estado') ?? '')),
            'ciudad'        => strtoupper(trim($this->request->getPost('ciudad') ?? '')),
            'NombreEmpresa' => strtoupper(trim($this->request->getPost('NombreEmpresa') ?? '')),
            'razonSocial'   => strtoupper(trim($this->request->getPost('razonSocial') ?? '')),
            'comoNosConoce' => $this->request->getPost('comoNosConoce'),
            'fechaIngreso'  => date('Y-m-d'),
        ]);

        return redirect()->to('/admin/clientes')->with('success', 'Cliente registrado correctamente.');
    }

    // POST /admin/clientes/actualizar/:id
    public function actualizarCliente(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $clienteModel = new \App\Models\ClienteModel();

        $clienteModel->update($id, [
            'nombre'        => strtoupper(trim($this->request->getPost('nombre') ?? '')),
            'telefono'      => trim($this->request->getPost('telefono') ?? ''),
            'celular'       => trim($this->request->getPost('celular') ?? ''),
            'mail'          => trim($this->request->getPost('mail') ?? ''),
            'RFC'           => strtoupper(trim($this->request->getPost('RFC') ?? '')),
            'direccion'     => strtoupper(trim($this->request->getPost('direccion') ?? '')),
            'CP'            => trim($this->request->getPost('CP') ?? ''),
            'estado'        => strtoupper(trim($this->request->getPost('estado') ?? '')),
            'ciudad'        => strtoupper(trim($this->request->getPost('ciudad') ?? '')),
            'NombreEmpresa' => strtoupper(trim($this->request->getPost('NombreEmpresa') ?? '')),
            'razonSocial'   => strtoupper(trim($this->request->getPost('razonSocial') ?? '')),
            'comoNosConoce' => $this->request->getPost('comoNosConoce'),
        ]);

        return redirect()->to('/admin/clientes')->with('success', 'Cliente actualizado.');
    }

    // POST /admin/clientes/eliminar
    public function eliminarCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $id = (int) $this->request->getPost('clienteDelete');
        if ($id) {
            $clienteModel = new \App\Models\ClienteModel();
            $clienteModel->softDelete($id);
        }
        return redirect()->to('/admin/clientes')->with('success', 'Cliente eliminado.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/clientes/eliminados  —  Lista de clientes soft-deleted
    // ──────────────────────────────────────────────────────────────
    public function clientesEliminados(): string
    {
        return view('admin/clientes_eliminados', [
            'usuario' => $this->getUsuarioSesion(),
        ]);
    }

    // GET /admin/clientes/eliminados/datatable
    public function clientesEliminadosDatatable(): \CodeIgniter\HTTP\Response
    {
        $clienteModel = new \App\Models\ClienteModel();
        $draw     = (int) $this->request->getGet('draw');
        $start    = (int) $this->request->getGet('start');
        $length   = (int) $this->request->getGet('length');
        $search   = $this->request->getGet('search')['value'] ?? '';
        $orderDir = $this->request->getGet('order')[0]['dir'] ?? 'asc';

        $result = $clienteModel->getDatatableEliminados($start, $length, $search, $orderDir);

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $result['data'],
        ]);
    }

    // GET /admin/clientes/eliminados/(:num)/historial  —  Notas de un cliente eliminado
    public function historialClienteEliminado(int $id): \CodeIgniter\HTTP\Response
    {
        $clienteModel = new \App\Models\ClienteModel();
        $cliente = $clienteModel->find($id);
        if (! $cliente || ! $cliente['eliminado']) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Cliente no encontrado.']);
        }

        $db = \Config\Database::connect();
        $notas = $db->query(
            "SELECT n.folio, n.fecha_inicial, n.total, n.subTotal, s.nombre AS status
             FROM notas_1 n
             LEFT JOIN status s ON s.id = n.status
             WHERE n.idCliente = ?
             ORDER BY n.folio DESC",
            [$id]
        )->getResultArray();

        return $this->response->setJSON([
            'ok'      => true,
            'cliente' => $cliente,
            'notas'   => $notas,
        ]);
    }

    // POST /admin/clientes/restaurar/(:num)  —  Revive un cliente eliminado
    public function restaurarCliente(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $clienteModel = new \App\Models\ClienteModel();
        $clienteModel->restaurar($id);
        return redirect()->to('/admin/clientes/eliminados')->with('success', 'Cliente restaurado correctamente.');
    }

    // POST /admin/clientes/eliminar-nota  —  Elimina permanentemente una nota de un cliente eliminado
    public function eliminarNotaCliente(): \CodeIgniter\HTTP\Response
    {
        $folio = (int) $this->request->getPost('folio');
        if (! $folio) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Folio inválido.']);
        }
        $db = \Config\Database::connect();
        try {
            $nota = $db->query("SELECT Id_Notas_1 FROM notas_1 WHERE folio = ? LIMIT 1", [$folio])->getRowArray();
            if ($nota) {
                $db->query("DELETE FROM notas_2 WHERE folio = ?", [$folio]);
                $db->query("DELETE FROM montosnotas WHERE idNotas = ?", [$nota['Id_Notas_1']]);
                $db->query("DELETE FROM notas_1 WHERE folio = ?", [$folio]);
            }
            return $this->response->setJSON(['ok' => true]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // POST /admin/clientes/eliminar-definitivo/(:num)  —  Elimina permanentemente el cliente (solo si no tiene notas)
    public function eliminarClienteDefinitivo(int $id): \CodeIgniter\HTTP\Response
    {
        $db = \Config\Database::connect();
        $totalNotas = (int) $db->query(
            "SELECT COUNT(*) AS total FROM notas_1 WHERE idCliente = ?", [$id]
        )->getRow()->total;

        if ($totalNotas > 0) {
            return $this->response->setJSON([
                'ok'    => false,
                'error' => "El cliente aún tiene {$totalNotas} nota(s). Elimínalas primero.",
            ]);
        }

        try {
            $db->query("DELETE FROM clientes WHERE id = ?", [$id]);
            return $this->response->setJSON(['ok' => true]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // POST /admin/clientes/datos  —  AJAX: datos de un cliente
    public function obtieneDatosCliente(): \CodeIgniter\HTTP\Response
    {
        $id           = (int) ($this->request->getPost('idCliente') ?: $this->request->getPost('id'));
        $clienteModel = new \App\Models\ClienteModel();
        $cliente      = $clienteModel->find($id);

        $resp = $cliente ? [
            'success'   => true,
            'direccion' => $cliente['direccion'] ?? '',
            'telefono'  => $cliente['telefono'] ?? '',
            'email'     => $cliente['mail'] ?? '',
        ] : ['success' => false];

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode($resp));
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/reportediario  —  Muestra formulario de rango de fechas
    // ──────────────────────────────────────────────────────────────
    public function reporteDiarioPage(): string
    {
        return view('admin/reportediario', [
            'usuario' => $this->getUsuarioSesion(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/reportediario  —  Exporta XLS con rango fecha+hora (igual que reportediario.php original)
    // GET  /admin/reportediario/dia  —  Exporta XLS de hoy (igual que reportediario_dia.php original)
    // ──────────────────────────────────────────────────────────────
    private function exportarReporteDiarioXls(string $fecha1, string $h1, string $m1, string $s1,
                                               string $fecha2, string $h2, string $m2, string $s2): \CodeIgniter\HTTP\Response
    {
        $db = \Config\Database::connect();

        $desde = "{$fecha1} {$h1}:{$m1}:{$s1}";
        $hasta  = "{$fecha2} {$h2}:{$m2}:{$s2}";

        $filas = $db->query(
            "SELECT n1.fecha_inicial, n1.folio,
                    SUM(n2.cantidad) AS totalPiezas,
                    SUM(COALESCE(NULLIF(n2.importe, 0), n2.cantidad * n2.pUnitario, 0)) AS totalImporte,
                    COALESCE(u.usuario, '—') AS vendedor
             FROM notas_1 n1
             INNER JOIN notas_2 n2 ON n1.folio = n2.folio
             LEFT JOIN usuarios u ON u.Id = n1.idVendedor
             WHERE n1.fecha_inicial >= ? AND n1.fecha_inicial <= ? AND n1.status != 3
             GROUP BY n1.Id_Notas_1, n1.fecha_inicial, n1.folio, u.usuario
             ORDER BY n1.folio ASC",
            [$desde, $hasta]
        )->getResultArray();

        $tdH = 'style="background:#145388;color:#fff;font-family:Calibri,Arial,sans-serif;font-size:11pt;padding:4px 8px;font-weight:bold;"';
        $tdS = 'style="font-family:Calibri,Arial,sans-serif;font-size:10pt;padding:3px 7px;"';
        $tdT = 'style="font-family:Calibri,Arial,sans-serif;font-size:9pt;padding:3px 7px;color:#555;"';

        $html  = '<html><head><meta charset="utf-8"></head><body>';
        $html .= '<table border="1" cellspacing="0" cellpadding="0">';
        $html .= '<tr><td colspan="5" ' . $tdT . '>Reporte del ' . $desde . ' al ' . $hasta . '</td></tr>';
        $html .= '<tr>';
        $html .= '<td ' . $tdH . '>Fecha</td>';
        $html .= '<td ' . $tdH . '>Folio</td>';
        $html .= '<td ' . $tdH . '>Piezas</td>';
        $html .= '<td ' . $tdH . '>Importe</td>';
        $html .= '<td ' . $tdH . '>Vendedor</td>';
        $html .= '</tr>';
        foreach ($filas as $f) {
            $html .= '<tr>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['fecha_inicial']) . '</td>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['folio']) . '</td>';
            $html .= '<td ' . $tdS . ' align="center">' . (int)$f['totalPiezas'] . '</td>';
            $html .= '<td ' . $tdS . ' align="right">$ ' . number_format((float)($f['totalImporte'] ?? 0), 2) . '</td>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['vendedor']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';

        return $this->response
            ->setHeader('Last-Modified', gmdate('D,d M Y H:i:s') . ' GMT')
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Content-Type', 'application/vnd.ms-excel')
            ->setHeader('Content-Disposition', 'attachment; filename=reportediario.xls')
            ->setBody($html);
    }

    public function reporteDiario(): \CodeIgniter\HTTP\Response
    {
        // POST desde el formulario de rango de fechas
        $fecha1 = $this->request->getPost('fecha1') ?? date('Y-m-d');
        $h1     = str_pad($this->request->getPost('horas')    ?? '0', 2, '0', STR_PAD_LEFT);
        $m1     = str_pad($this->request->getPost('minutos')  ?? '0', 2, '0', STR_PAD_LEFT);
        $s1     = str_pad($this->request->getPost('segundos') ?? '0', 2, '0', STR_PAD_LEFT);
        $fecha2 = $this->request->getPost('fecha2') ?? date('Y-m-d');
        $h2     = str_pad($this->request->getPost('horas2')   ?? '23', 2, '0', STR_PAD_LEFT);
        $m2     = str_pad($this->request->getPost('minutos2') ?? '59', 2, '0', STR_PAD_LEFT);
        $s2     = str_pad($this->request->getPost('segundos2')  ?? '59', 2, '0', STR_PAD_LEFT);

        return $this->exportarReporteDiarioXls($fecha1, $h1, $m1, $s1, $fecha2, $h2, $m2, $s2);
    }

    // GET /admin/reportediario/dia — exporta solo el día de hoy (igual que reportediario_dia.php original)
    public function reporteDiarioDia(): \CodeIgniter\HTTP\Response
    {
        $hoy = date('Y-m-d');
        return $this->exportarReporteDiarioXls($hoy, '00', '00', '00', $hoy, '23', '59', '59');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/videos  —  Gestión de videos
    // Migrado desde: admin/videos.php
    // ──────────────────────────────────────────────────────────────
    public function videos(): string
    {
        $db     = \Config\Database::connect();
        $videos = $db->query("SELECT * FROM videos ORDER BY id DESC")->getResultArray();

        return view('admin/videos', [
            'usuario' => $this->getUsuarioSesion(),
            'videos'  => $videos,
        ]);
    }

    // POST /admin/videos/subir
    public function subirVideo(): \CodeIgniter\HTTP\RedirectResponse
    {
        // Lógica de subida de video (se implementará en la vista)
        return redirect()->to('/admin/videos')->with('success', 'Video guardado.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/caja  —  Verificar Caja (igual que caja2.php original)
    // ──────────────────────────────────────────────────────────────
    public function caja(): string
    {
        return view('admin/caja', [
            'usuario' => $this->getUsuarioSesion(),
        ]);
    }

    // POST /admin/caja/ajax  —  Devuelve detalles de un folio (AJAX)
    // Migrado desde: mostrador/llamadasAjax.php (tipo=3)
    public function cajaAjax(): string
    {
        $folio = (int) $this->request->getPost('folio');

        try {
            $db = \Config\Database::connect();

            // Columna real: u.usuario (NO u.nombre)
            $nota = $db->query(
                "SELECT n.Id_Notas_1, n.fecha_inicial,
                        c.nombre  AS NombreCliente,
                        u.usuario AS vendedor,
                        n.folio, n.verificado, n.factura, n.descuento,
                        s.nombre AS status, s.id AS statusId,
                        n.sumaImportes, n.subTotal, n.tipoPago,
                        n.cargoTarjeta, n.subTotal2, n.iva, n.total,
                        n.tipoImpresion, n.cargoPorImpresion,
                        n.montoTCTD, n.totalPiezas
                 FROM notas_1 n
                 LEFT JOIN status   s ON n.status    = s.id
                 LEFT JOIN clientes c ON n.idCliente = c.id
                 LEFT JOIN usuarios u ON u.Id        = n.idVendedor
                 WHERE n.folio = ?
                 ORDER BY n.Id_Notas_1 DESC
                 LIMIT 1",
                [$folio]
            )->getRowArray();

            if (! $nota) {
                return '<p class="text-danger">FOLIO NO ENCONTRADO</p>';
            }

            // Pagos (Id_Notas_1 directo, igual al original)
            $pagos = $db->query(
                "SELECT m.idTipoPago, t.descripcion AS tipopago,
                        m.monto, m.cargos AS cargo, m.anticipo
                 FROM montosnotas m
                 INNER JOIN tipopago t ON m.idTipoPago = t.id
                 WHERE m.idNotas = ?",
                [$nota['Id_Notas_1']]
            )->getResultArray();

            // Productos: pUnitario=precio real, estilo=sku en notas_2
            $detalles = $db->query(
                "SELECT n.cantidad,
                        CONCAT(p.estilo,'-',p.Descripcion_Larga,'-',p.Talla,'-',p.Color) AS descripcion,
                        n.pUnitario, n.importe
                 FROM notas_2 n
                 LEFT JOIN productosyazbek p ON p.sku = n.estilo
                 WHERE n.folio = ?",
                [$folio]
            )->getResultArray();

        } catch (\Exception $e) {
            log_message('error', 'cajaAjax folio=' . $folio . ': ' . $e->getMessage());
            return '<p class="text-danger">Error al consultar el folio.<br><small>' . esc($e->getMessage()) . '</small></p>';
        }

        // Número a letras (igual al original AifLibNumber::toCurrency)
        $letras = '';
        try {
            $letras = mb_strtoupper(\App\Libraries\AifLibNumber::toCurrency($nota['total']));
        } catch (\Throwable $e) {
            $letras = '';
        }

        $total = $nota['total'] ?? 0;

        // HTML idéntico al original (llamadasAjax.php tipo=3)
        $html  = '<div style="overflow-x:auto"><table style="width:100%"><tr>';
        $html .= '<td><strong>Fecha:&nbsp;</strong></td>';
        $html .= '<td>' . date('Y-m-d h:i A', strtotime($nota['fecha_inicial'])) . '</td>';
        $html .= '<td>&nbsp;</td><td><strong>Nombre Cliente:&nbsp;</strong></td>';
        $html .= '<td>' . esc($nota['NombreCliente']) . '</td>';
        $html .= '</tr><tr>';
        $html .= '<td><strong>Estatus:&nbsp;</strong></td><td>' . esc($nota['status']) . '</td>';
        $html .= '<td>&nbsp;</td><td><strong>Folio:&nbsp;</strong></td>';
        $html .= '<td>' . $nota['folio'] . '</td>';
        $html .= '</tr></table></div>';

        // Calculadora
        $html .= '<div class="col-sm-4 col-lg-4"><div class="font-w600 push-5">Calculadora</div>';
        $html .= '<table><tr><td>Ingresa la cantidad</td><td>&nbsp;</td>';
        $html .= '<td>Total</td><td>&nbsp;</td><td>Cambio</td></tr><tr>';
        $html .= '<td><input class="font-w600 push-5" type="text" id="importe_cj"';
        $html .= ' placeholder="Ingresa la cantidad" onblur="blurFnCj()" onchange="blurFnCj()" /></td>';
        $html .= '<td></td>';
        $html .= '<td><input class="font-w600 push-5" type="text" id="pagar_cj" disabled value="' . $total . '" /></td>';
        $html .= '<td></td><td><input type="text" id="resultado_cj" disabled /></td>';
        $html .= '</tr></table>';
        $html .= '<input type="hidden" id="pagar2_cj" value="' . $total . '" /></div>';

        // Productos
        $html .= '<div class="line mt-3"></div><div class="line"></div>';
        $html .= '<div style="overflow-x:auto"><table class="table" id="tabla_cj"><thead><tr>';
        $html .= '<th width="60">CANTIDAD</th><th>DESCRIPCION</th>';
        $html .= '<th width="180">PRECIO UNITARIO</th><th width="90">IMPORTE</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($detalles as $d) {
            $html .= '<tr><td>' . $d['cantidad'] . '</td>';
            $html .= '<td>' . esc($d['descripcion'] ?? '') . '</td>';
            $html .= '<td>$ ' . number_format($d['pUnitario'] ?? 0, 2) . '</td>';
            $html .= '<td>$ ' . number_format($d['importe']   ?? 0, 2) . '</td></tr>';
        }

        // Totales
        $ti    = (int)($nota['tipoImpresion'] ?? 0);
        $otros = $ti === 1 ? 'Ninguno' : ($ti === 2 ? 'Impresion' : ($ti === 3 ? 'Bordado' : ''));
        $html .= '<tr><td colspan="3" class="text-right"><strong>Otros</strong></td><td id="cargo">' . $otros . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo por otros</strong></td><td>$ ' . number_format($nota['cargoPorImpresion'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Descuento</strong></td><td>$ ' . number_format($nota['descuento'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right no-border"><strong>SubTotal</strong></td><td id="subtotal">$ ' . number_format($nota['subTotal'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo TC/TD</strong></td><td>$ ' . number_format($nota['montoTCTD'] ?? $nota['cargoTarjeta'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>SubTotal2</strong></td><td>$ ' . number_format($nota['subTotal2'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>IVA</strong></td><td>$ ' . number_format($nota['iva'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Total</strong></td><td id="total_cj">$ ' . number_format($total, 2) . '</td></tr>';

        if ($letras) {
            $html .= '<tr><td colspan="4" class="text-right no-border" align="left">';
            $html .= '<strong>Cantidad con letra( ' . esc($letras) . ')</strong></td></tr>';
        }

        foreach ($pagos as $p) {
            $html .= '<tr><td colspan="4" class="text-right no-border">';
            $html .= '<strong>Forma de Pago:</strong> ' . esc($p['tipopago']);
            $html .= ' <strong>Monto: </strong>$ ' . number_format($p['monto'] ?? 0, 2);
            if (in_array($p['idTipoPago'] ?? 0, [2, 3])) {
                $html .= ' <strong>Cargo: </strong>$ ' . number_format($p['cargo'] ?? 0, 2);
            }
            $html .= ' <strong>Es anticipo:</strong> ' . (($p['anticipo'] ?? 0) == 1 ? 'Si' : 'No');
            $html .= '</td></tr>';
        }

        $statusId   = (int)($nota['statusId']  ?? 0);
        $verificado = $nota['verificado'] ?? '';

        // Si tiene pagos con anticipo y el monto pagado es menor al total → mostrar "Abierta"
        $sumPagado   = array_sum(array_column($pagos, 'monto'));
        $hayAnticipo = !empty(array_filter($pagos, fn($p) => ($p['anticipo'] ?? 0) == 1));
        $displayStatus = ($hayAnticipo && $sumPagado < $nota['total'])
            ? 'Abierta'
            : esc($nota['status']);

        $html .= '<tr><td colspan="2" class="text-right no-border" align="left"><strong>Estatus:</strong></td>';
        $html .= '<td colspan="2" align="left" id="estatusPago">' . $displayStatus . '</td></tr>';
        $html .= '<tr><td colspan="2" class="text-right no-border" align="left"><strong>Factura:</strong></td>';
        $html .= '<td colspan="2" align="left">' . (($nota['factura'] ?? 0) == 1 ? 'Si requiere' : 'No requiere') . '</td></tr>';

        $html .= '<tr><input type="hidden" id="folio_input" value="' . $nota['folio'] . '" />';
        if ($statusId !== 3 && $statusId !== 5 && $verificado !== 'Pagado') {
            $html .= '<td></td>';
            $html .= '<td colspan="2" class="text-right no-border"><button type="button" class="btn btn-danger" onclick="fn_modal_calcelar_nota()">Cancelar Nota</button></td>';
            $html .= '<td colspan="2" class="text-right no-border"><button type="button" class="btn btn-success" onclick="fn_muestra_modal()">Pago Verificado</button></td>';
        }
        $html .= '</tr></tbody></table></div>';
        $html .= '<script>function blurFnCj(){var p=parseFloat(document.getElementById("pagar2_cj").value)||0;var i=parseFloat(document.getElementById("importe_cj").value)||0;document.getElementById("resultado_cj").value=(i-p).toFixed(2);}</script>';

        return $html;
    }
    // ──────────────────────────────────────────────────────────────
    // POST /admin/caja/verificar  —  Marca nota como verificada/pagada
    // Migrado desde: mostrador/verificarPago.php
    // ──────────────────────────────────────────────────────────────
    public function cajaVerificar(): \CodeIgniter\HTTP\Response
    {
        $folio = (int) $this->request->getPost('folio');
        if (! $folio) {
            return $this->response->setBody('mal');
        }
        try {
            $db = \Config\Database::connect();
            $db->query("UPDATE notas_1 SET verificado = 'Pagado' WHERE folio = ?", [$folio]);
            return $this->response->setBody('bien');
        } catch (\Exception $e) {
            log_message('error', 'cajaVerificar folio=' . $folio . ': ' . $e->getMessage());
            return $this->response->setBody('mal');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/caja/cancelar  —  Cancela nota y restaura inventario
    // Migrado desde: mostrador/cancelarPago.php + caja/cancelar_nota.php
    // ──────────────────────────────────────────────────────────────
    public function cajaCancelar(): \CodeIgniter\HTTP\Response
    {
        $folio = (int) $this->request->getPost('folio');
        if (! $folio) {
            return $this->response->setBody('0');
        }
        try {
            $db = \Config\Database::connect();

            $nota = $db->query(
                "SELECT Id_Notas_1, totalPiezas, referencia FROM notas_1 WHERE folio = ? LIMIT 1",
                [$folio]
            )->getRowArray();

            if (! $nota) {
                return $this->response->setBody('0');
            }

            // Siempre actualizar status = 3 (cancelado) para el folio y su referencia
            $db->query(
                "UPDATE notas_1 SET status = 3 WHERE folio = ? OR referencia = ?",
                [$folio, $folio]
            );

            // Restaurar piezas en inventario si hay productos vinculados
            if (($nota['totalPiezas'] ?? 0) > 0 && empty($nota['referencia'])) {
                $productos = $db->query(
                    "SELECT cantidad, estilo FROM notas_2 WHERE Id_Notas_1 = ?",
                    [$nota['Id_Notas_1']]
                )->getResultArray();

                foreach ($productos as $p) {
                    $db->query(
                        "UPDATE productosyazbek SET piezas = piezas + ? WHERE sku = ?",
                        [(int)$p['cantidad'], $p['estilo']]
                    );
                }
            }

            return $this->response->setBody('1');
        } catch (\Exception $e) {
            log_message('error', 'cajaCancelar folio=' . $folio . ': ' . $e->getMessage());
            return $this->response->setBody('0');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/caja/corte  —  Corte de Caja (igual que corte2.php original)
    // Filtros: fecha (dd/mm/yyyy), estatus, tipopago
    // ──────────────────────────────────────────────────────────────
    public function cajaCorte(): string
    {
        $db = \Config\Database::connect();

        // Filtros del formulario (POST igual que el original)
        $fecha    = $this->request->getPost('fecha')    ?? date('d/m/Y');
        $estatus  = (int)($this->request->getPost('estatus')  ?? 0);
        $tipopago = (int)($this->request->getPost('tipopago') ?? 0);

        // Catálogos para los selects
        $listaEstatus  = $db->query("SELECT * FROM status ORDER BY Id ASC")->getResultArray();
        $listaTipoPago = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();

        // Query principal — igual que corte2.php
        $where = "WHERE 1=1";
        if ($fecha !== '') {
            $where .= " AND DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') = " . $db->escape($fecha);
        }
        if ($estatus > 0) {
            $where .= " AND s.id = " . $estatus;
        }
        if ($tipopago > 0) {
            $where .= " AND tp.id = " . $tipopago;
        }

        $filas = $db->query(
            "SELECT n.folio, n.referencia, DATE_FORMAT(mn.fecha, '%d/%m/%Y') AS fecha,
                    c.nombre AS cliente, u.usuario AS vendedor,
                    tp.descripcion AS tipopago, mn.monto AS total, mn.cargos,
                    s.nombre AS status, n.verificado
             FROM notas_1 n
             LEFT JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             LEFT JOIN clientes    c  ON n.idCliente  = c.id
             INNER JOIN usuarios   u  ON u.Id = n.idVendedor
             LEFT JOIN tipopago    tp ON mn.idTipoPago = tp.id
             LEFT JOIN status      s  ON n.status = s.id
             {$where}
             ORDER BY DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y'), n.folio"
        )->getResultArray();

        // Agrupar pagos por folio (igual que la lógica $createRow del original)
        $agrupadas = [];
        foreach ($filas as $row) {
            $f = $row['folio'];
            if (! isset($agrupadas[$f])) {
                $agrupadas[$f] = [
                    'folio'     => $f,
                    'referencia'=> $row['referencia'],
                    'fecha'     => $row['fecha'],
                    'cliente'   => $row['cliente'],
                    'vendedor'  => $row['vendedor'],
                    'status'    => $row['status'],
                    'verificado'=> $row['verificado'],
                    'pagos'     => [],
                ];
            }
            if ($row['tipopago']) {
                $monto = $row['total'] != '' ? '$ ' . number_format($row['total'], 2) : '';
                $agrupadas[$f]['pagos'][] = $row['tipopago'] . ' / ' . $monto;
                if (in_array($row['tipopago'], ['T.Credito','T.Debito']) && $row['cargos']) {
                    $agrupadas[$f]['pagos'][] = 'Cargo / $' . number_format($row['cargos'], 2);
                }
            }
        }

        return view('admin/caja_corte', [
            'usuario'       => $this->getUsuarioSesion(),
            'fecha'         => $fecha,
            'estatus'       => $estatus,
            'tipopago'      => $tipopago,
            'listaEstatus'  => $listaEstatus,
            'listaTipoPago' => $listaTipoPago,
            'notas'         => array_values($agrupadas),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/caja/corte/exportar  —  Exporta XLS del corte de caja con los mismos filtros
    // ──────────────────────────────────────────────────────────────
    public function exportarCorteXls(): \CodeIgniter\HTTP\Response
    {
        $db       = \Config\Database::connect();
        $fecha    = $this->request->getGet('fecha')    ?? date('d/m/Y');
        $estatus  = (int)($this->request->getGet('estatus')  ?? 0);
        $tipopago = (int)($this->request->getGet('tipopago') ?? 0);

        $where  = "WHERE 1=1";
        $params = [];

        if ($fecha !== '') {
            $where .= " AND DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') = ?";
            $params[] = $fecha;
        }
        if ($estatus > 0) {
            $where .= " AND n.status = ?";
            $params[] = $estatus;
        }
        if ($tipopago > 0) {
            $where .= " AND EXISTS (SELECT 1 FROM montosnotas mn WHERE mn.idNotas = n.Id_Notas_1 AND mn.idTipoPago = ?)";
            $params[] = $tipopago;
        }

        $filas = $db->query(
            "SELECT n.folio,
                    n.fecha_inicial,
                    COALESCE(NULLIF(c.nombre,''), 'PUBLICO GENERAL') AS cliente,
                    COALESCE(u.usuario, '—') AS vendedor,
                    GROUP_CONCAT(DISTINCT tp.descripcion ORDER BY tp.id SEPARATOR ' / ') AS tipoPago,
                    COALESCE(s.nombre, '—') AS estatus
             FROM notas_1 n
             LEFT JOIN clientes   c  ON c.id          = n.idCliente
             LEFT JOIN usuarios   u  ON u.Id           = n.idVendedor
             LEFT JOIN montosnotas mn ON mn.idNotas    = n.Id_Notas_1
             LEFT JOIN tipopago   tp ON tp.id          = mn.idTipoPago
             LEFT JOIN status     s  ON s.id           = n.status
             {$where}
             GROUP BY n.Id_Notas_1, n.folio, n.fecha_inicial, c.nombre, u.usuario, s.nombre
             ORDER BY n.folio ASC",
            $params
        )->getResultArray();

        $tdH = 'style="background:#145388;color:#fff;font-family:Calibri,Arial,sans-serif;font-size:11pt;padding:4px 8px;font-weight:bold;"';
        $tdS = 'style="font-family:Calibri,Arial,sans-serif;font-size:10pt;padding:3px 7px;"';
        $tdT = 'style="font-family:Calibri,Arial,sans-serif;font-size:9pt;padding:3px 7px;color:#555;"';

        $html  = '<html><head><meta charset="utf-8"></head><body>';
        $html .= '<table border="1" cellspacing="0" cellpadding="0">';
        $html .= '<tr><td colspan="6" ' . $tdT . '>Corte de Caja — Fecha: ' . htmlspecialchars($fecha) . '</td></tr>';
        $html .= '<tr>';
        $html .= '<td ' . $tdH . '>Folio</td>';
        $html .= '<td ' . $tdH . '>Fecha y Hora</td>';
        $html .= '<td ' . $tdH . '>Cliente</td>';
        $html .= '<td ' . $tdH . '>Vendedor</td>';
        $html .= '<td ' . $tdH . '>Tipo de Pago</td>';
        $html .= '<td ' . $tdH . '>Estatus</td>';
        $html .= '</tr>';
        foreach ($filas as $f) {
            $html .= '<tr>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['folio']) . '</td>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['fecha_inicial']) . '</td>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['cliente']) . '</td>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['vendedor']) . '</td>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['tipoPago'] ?? '—') . '</td>';
            $html .= '<td ' . $tdS . '>' . htmlspecialchars($f['estatus']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';

        return $this->response
            ->setHeader('Last-Modified', gmdate('D,d M Y H:i:s') . ' GMT')
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Content-Type', 'application/vnd.ms-excel')
            ->setHeader('Content-Disposition', 'attachment; filename=corte_caja.xls')
            ->setBody($html);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/importar  —  Vista importar CSV
    // Migrado desde: admin/importar.php (y variantes)
    // ──────────────────────────────────────────────────────────────
    public function importar(): string
    {
        return view('admin/importar', [
            'usuario' => $this->getUsuarioSesion(),
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    // POST /admin/importar/procesar  (también usado desde /admin/importar/subir)
    public function procesarImportacion(): \CodeIgniter\HTTP\RedirectResponse
    {
        // El form de inventario usa 'dataCliente'; el de importar usa 'archivo_csv'
        $archivo = $this->request->getFile('dataCliente')
                ?? $this->request->getFile('archivo_csv');

        // Detectar desde dónde se llamó para redirigir correctamente
        $referer      = $this->request->getServer('HTTP_REFERER') ?? '';
        $redirectBack = str_contains($referer, 'inventario') ? '/admin/inventario' : '/admin/importar';

        if (! $archivo || ! $archivo->isValid()) {
            return redirect()->to($redirectBack)->with('error', 'Archivo inválido.');
        }

        // Mover archivo al directorio de uploads
        $nuevoNombre = $archivo->getRandomName();
        $archivo->move(WRITEPATH . 'uploads', $nuevoNombre);
        $ruta = WRITEPATH . 'uploads/' . $nuevoNombre;

        $db          = \Config\Database::connect();
        $insertados  = 0;
        $errores     = 0;

        if (($handle = fopen($ruta, 'r')) !== false) {
            $encabezado = null;
            while (($fila = fgetcsv($handle, 1000, ',')) !== false) {
                if ($encabezado === null) {
                    $encabezado = $fila;
                    continue;
                }
                $datos = array_combine($encabezado, $fila);
                try {
                    $this->productoModel->insert([
                        'estilo'           => $datos['estilo']           ?? '',
                        'sku'              => $datos['sku']              ?? '',
                        'Descripcion_corta'=> $datos['Descripcion_corta']?? '',
                        'Descripcion_Larga'=> $datos['Descripcion_Larga']?? '',
                        'Color'            => $datos['Color']            ?? '',
                        'Talla'            => $datos['Talla']            ?? '',
                        'pMenudeo'         => $datos['pMenudeo']         ?? 0,
                        'pMayoreo'         => $datos['pMayoreo']         ?? 0,
                        'piezas'           => $datos['piezas']           ?? 0,
                    ]);
                    $insertados++;
                } catch (\Exception $e) {
                    $errores++;
                }
            }
            fclose($handle);
        }

        unlink($ruta);

        return redirect()->to($redirectBack)
                         ->with('success', "Importación completa: {$insertados} insertados, {$errores} errores.");
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/venta  —  Vista de venta desde admin
    // Migrado desde: admin/venta_1.php
    // ──────────────────────────────────────────────────────────────
    public function venta(): string
    {
        $hoy   = date('Y-m-d');
        $db    = \Config\Database::connect();

        $notas = $db->query(
            "SELECT n.*, n.status AS idstatus, c.nombre AS nombreCliente
             FROM notas_1 n
             LEFT JOIN clientes c ON n.idCliente = c.id
             WHERE n.fecha_inicial LIKE ?
             ORDER BY n.Id_Notas_1 DESC",
            ["{$hoy}%"]
        )->getResultArray();

        return view('admin/venta', [
            'usuario' => $this->getUsuarioSesion(),
            'notas'   => $notas,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/exportar  —  Exporta la base completa de productos a Excel
    // Migrado desde: admin/BaseCompleta.php
    // ──────────────────────────────────────────────────────────────
    // GET /admin/exportar  —  Descarga XLS base completa, igual que BaseCompleta.php del original
    public function exportar(): \CodeIgniter\HTTP\Response
    {
        $db       = \Config\Database::connect();
        $productos = $db->query(
            "SELECT estilo, sku, Descripcion_corta, Descripcion_Larga,
                    Talla, Color, pMenudeo, pMayoreo, piezas
             FROM productosyazbek ORDER BY id ASC"
        )->getResultArray();

        // HTML table con header XLS — exactamente igual al BaseCompleta.php original
        $html  = '<html><head><meta charset="utf-8"></head><body>';
        $html .= '<table border="1">';
        $html .= '<tr><td>Estilo</td><td>SKU</td><td>Descripcion Corta</td>'
               . '<td>Descripcion Larga</td><td>Talla</td><td>Color</td>'
               . '<td>Precio Menudeo</td><td>Precio Mayoreo</td><td>Piezas</td></tr>';
        foreach ($productos as $p) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($p['estilo']           ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['sku']              ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['Descripcion_corta'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['Descripcion_Larga'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['Talla']            ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['Color']            ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['pMenudeo']         ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['pMayoreo']         ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($p['piezas']           ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';

        return $this->response
            ->setHeader('Last-Modified', gmdate('D,d M Y H:i:s') . ' GMT')
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Content-Type', 'application/x-msexcel')
            ->setHeader('Content-Disposition', 'attachment; filename=BaseCompleta.xls')
            ->setBody($html);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/consulta  —  Vista: consultar todos los folios (admin)
    // Soporta filtro ?tipo=mayoreo|menudeo
    // ──────────────────────────────────────────────────────────────
    public function consulta(): string
    {
        $tipo = $this->request->getGet('tipo') ?? 'todos';

        return view('admin/consulta', [
            'usuario' => $this->getUsuarioSesion(),
            'tipo'    => $tipo,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/consulta/datatable  —  AJAX server-side DataTables
    // Copia exacta de CajaController::consultaDatatable() + filtro tipo
    // ──────────────────────────────────────────────────────────────
    public function consultaDatatable(): \CodeIgniter\HTTP\ResponseInterface
    {
        $draw        = (int) $this->request->getGet('draw');
        $start       = (int) $this->request->getGet('start');
        $length      = (int) $this->request->getGet('length');
        $search      = $this->request->getGet('search')['value'] ?? '';
        $orderColIdx = (int) ($this->request->getGet('order')[0]['column'] ?? 0);
        $orderDir    = $this->request->getGet('order')[0]['dir'] ?? 'desc';
        $tipo        = $this->request->getGet('tipo') ?? '';

        $cols     = ['n.folio', 'n.fecha_inicial', 'c.nombre', 'u.usuario', 'n.tipoPago', 'n.total', 'n.status'];
        $orderCol = $cols[$orderColIdx] ?? 'n.folio';
        $orderDir = $orderDir === 'asc' ? 'ASC' : 'DESC';

        try {
            $db = \Config\Database::connect();

            $total = (int) $db->query("SELECT COUNT(*) AS total FROM notas_1")->getRow()->total;

            $baseSql = "FROM notas_1 n
                        LEFT JOIN clientes c ON c.id = n.idCliente
                        LEFT JOIN usuarios u ON u.Id = n.idVendedor
                        LEFT JOIN status s ON s.id = n.status";

            $whereClauses = [];
            $params       = [];

            // Filtro mayoreo/menudeo con placeholder ? igual que el resto de params
            if ($tipo === 'mayoreo') {
                $whereClauses[] = "n.precioMayoreo = ?";
                $params[]       = 1;
            } elseif ($tipo === 'menudeo') {
                $whereClauses[] = "COALESCE(n.precioMayoreo, 0) = ?";
                $params[]       = 0;
            }

            if ($search !== '') {
                $s = '%' . $search . '%';
                $whereClauses[] = "(n.folio LIKE ? OR c.nombre LIKE ? OR u.usuario LIKE ?)";
                $params[]       = $s;
                $params[]       = $s;
                $params[]       = $s;
            }

            $where    = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";
            $filtered = (int) $db->query("SELECT COUNT(*) AS cnt {$baseSql} {$where}", $params)->getRow()->cnt;

            $data = $db->query(
                "SELECT n.folio, n.fecha_inicial,
                        COALESCE(c.nombre, '—')   AS cliente,
                        COALESCE(u.usuario, '—')  AS vendedor,
                        COALESCE(n.tipoPago, '—') AS tipopago,
                        n.total, n.status AS idstatus,
                        COALESCE(s.nombre, '')    AS status_nombre,
                        n.verificado
                 {$baseSql} {$where}
                 ORDER BY {$orderCol} {$orderDir}
                 LIMIT ? OFFSET ?",
                array_merge($params, [$length, $start])
            )->getResultArray();

            return $this->response->setJSON([
                'draw'            => $draw,
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $data,
            ]);

        } catch (\Exception $e) {
            log_message('error', 'AdminController::consultaDatatable — ' . $e->getMessage());
            return $this->response->setJSON([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Error al consultar los folios: ' . $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: datos del usuario logueado desde la sesión
    // ──────────────────────────────────────────────────────────────
    private function getUsuarioSesion(): array
    {
        $session = session();
        return [
            'Id'     => $session->get('user_id'),
            'nombre' => $session->get('user_nombre'),
            'mail'   => $session->get('user_email'),
            'acceso' => $session->get('user_acceso'),
        ];
    }
}
