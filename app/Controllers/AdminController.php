<?php

namespace App\Controllers;

use App\Models\MensajeAdminModel;
use App\Models\NotaDetalleModel;
use App\Models\NotaModel;
use App\Models\ProductoModel;
use App\Models\UsuarioModel;
use App\Libraries\AuditService;

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
                SUM(status=6) AS liquidado,
                COALESCE(SUM(CASE WHEN status IN (4,5,6) THEN total ELSE 0 END),0) AS ingresos
             FROM notas_1
             WHERE fecha_inicial >= ? AND fecha_inicial < ?",
            ["{$hoy} 00:00:00", "{$hoy} 23:59:59"]
        )->getRow();

        $totalHoy       = (int)($statsHoy->total      ?? 0);
        $totalAnticipo  = (int)($statsHoy->anticipo   ?? 0);
        $totalCancelado = (int)($statsHoy->cancelado  ?? 0);
        $totalPagado    = (int)($statsHoy->pagado     ?? 0) + (int)($statsHoy->liquidado ?? 0);
        $ingresosHoy    = (float)($statsHoy->ingresos ?? 0);

        // ── KPIs de mes y año (1 query) ───────────────────────────
        $statsPeriodo = $db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN fecha_inicial >= ? THEN total ELSE 0 END),0) AS mes,
                COALESCE(SUM(CASE WHEN fecha_inicial >= ? THEN total ELSE 0 END),0) AS anio
             FROM notas_1
             WHERE status IN (4,5,6) AND fecha_inicial >= ? AND fecha_inicial < ?",
            [$mesInicio, $anioInicio, $anioInicio, $anioFin]
        )->getRow();

        $ingresosMes  = (float)($statsPeriodo->mes  ?? 0);
        $ingresosAnio = (float)($statsPeriodo->anio ?? 0);

        // Defensivo: si la columna eliminado no existe aún en la BD local, cuenta todos
        $camposClientes  = $db->getFieldNames('clientes');
        $whereEliminado  = in_array('eliminado', $camposClientes) ? ' WHERE eliminado=0' : '';
        $clientesActivos = (int) $db->query("SELECT COUNT(*) AS t FROM clientes{$whereEliminado}")->getRow()->t;

        // ── Ventas mensuales del año (rangos de fecha = usa índice) ─
        $ventasMensualesRaw = $db->query(
            "SELECT MONTH(fecha_inicial) AS mes,
                    COALESCE(SUM(total),0) AS total,
                    COUNT(*) AS notas
             FROM notas_1
             WHERE status IN (4,5,6)
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
                COALESCE(SUM(CASE WHEN status IN (4,5,6) THEN total ELSE 0 END),0) AS total,
                COUNT(*) AS notas,
                SUM(CASE WHEN status IN (4,5,6) THEN 1 ELSE 0 END) AS notas_pagadas
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

        // ── Comparativa mes actual vs mes anterior ────────────────
        $mesActualInicio   = date('Y-m-01 00:00:00');
        $mesActualFin      = date('Y-m-01 00:00:00', strtotime('+1 month'));
        $mesAnteriorInicio = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $mesAnteriorFin    = $mesActualInicio;

        $statusLabels = [1=>'Abierta', 2=>'En proceso', 3=>'Cancelada', 4=>'Anticipo', 5=>'Pagada', 6=>'Liquidado'];

        $rawActual   = $db->query(
            "SELECT status, COUNT(*) AS notas, COALESCE(SUM(total),0) AS total
             FROM notas_1 WHERE fecha_inicial >= ? AND fecha_inicial < ?
             GROUP BY status",
            [$mesActualInicio, $mesActualFin]
        )->getResultArray();

        $rawAnterior = $db->query(
            "SELECT status, COUNT(*) AS notas, COALESCE(SUM(total),0) AS total
             FROM notas_1 WHERE fecha_inicial >= ? AND fecha_inicial < ?
             GROUP BY status",
            [$mesAnteriorInicio, $mesAnteriorFin]
        )->getResultArray();

        $comp = [];
        foreach ([1,2,3,4,5,6] as $s) {
            $comp[$s] = ['label'=>$statusLabels[$s], 'actual_n'=>0, 'actual_t'=>0.0, 'anterior_n'=>0, 'anterior_t'=>0.0];
        }
        foreach ($rawActual   as $r) { $s=(int)$r['status']; if(isset($comp[$s])){ $comp[$s]['actual_n']=(int)$r['notas']; $comp[$s]['actual_t']=round((float)$r['total'],2); } }
        foreach ($rawAnterior as $r) { $s=(int)$r['status']; if(isset($comp[$s])){ $comp[$s]['anterior_n']=(int)$r['notas']; $comp[$s]['anterior_t']=round((float)$r['total'],2); } }

        $comparativa = array_values($comp);

        // ── Productos con stock bajo (< 10 piezas) ───────────────
        $stockProductos = $db->query(
            "SELECT sku, Descripcion_Larga AS nombre, Color, Talla, piezas
             FROM productosyazbek WHERE piezas < 10
             ORDER BY piezas ASC LIMIT 60"
        )->getResultArray();

        // ── Últimas 15 notas del día ──────────────────────────────
        $recientes = $this->fixRows($db->query(
            "SELECT n.folio, n.fecha_inicial, n.total, n.status AS idstatus,
                    COALESCE(c.nombre,'—') AS nombre
             FROM notas_1 n LEFT JOIN clientes c ON n.idCliente = c.id
             WHERE n.fecha_inicial >= ? AND n.fecha_inicial < ?
             ORDER BY n.Id_Notas_1 DESC LIMIT 15",
            ["{$hoy} 00:00:00", "{$hoy} 23:59:59"]
        )->getResultArray());

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
            'comparativa'     => $comparativa,
            'mesNombreActual'   => (function() use ($anio) {
                $nm = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                $m  = (int)date('n');
                return $nm[$m] . ' ' . $anio;
            })(),
            'mesNombreAnterior' => (function() use ($anio) {
                $nm = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                $m  = (int)date('n');
                $ma = $m === 1 ? 12 : $m - 1;
                $ya = $m === 1 ? $anio - 1 : $anio;
                return $nm[$ma] . ' ' . $ya;
            })(),
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
    // GET /admin/dashboard/datos  —  AJAX: todos los datos de gráficas
    // Params GET: mes (1-12), anio (4 dígitos)
    // ──────────────────────────────────────────────────────────────
    public function dashboardDatos(): \CodeIgniter\HTTP\Response
    {
        $mes  = (int)($this->request->getGet('mes')  ?: date('n'));
        $anio = (int)($this->request->getGet('anio') ?: date('Y'));

        // Validaciones básicas
        if ($mes < 1 || $mes > 12)     $mes  = (int)date('n');
        if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');

        $db = \Config\Database::connect();

        // ── Rangos de fecha ───────────────────────────────────────
        $anioInicio = "{$anio}-01-01 00:00:00";
        $anioFin    = ($anio + 1) . "-01-01 00:00:00";

        $mesActualInicio   = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
        $mesActualFin      = date('Y-m-d 00:00:00', strtotime($mesActualInicio . ' +1 month'));
        $mesAnteriorInicio = date('Y-m-d 00:00:00', strtotime($mesActualInicio . ' -1 month'));
        $mesAnteriorFin    = $mesActualInicio;

        // ── Nombres de mes en español ─────────────────────────────
        $nm               = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $mesNombreActual  = $nm[$mes] . ' ' . $anio;
        $ma               = $mes === 1 ? 12 : $mes - 1;
        $ya               = $mes === 1 ? $anio - 1 : $anio;
        $mesNombreAnterior = $nm[$ma] . ' ' . $ya;

        // ── Top 10 productos del año ──────────────────────────────
        $topProductos = $db->query(
            "SELECT nd.estilo AS sku,
                    COALESCE(MAX(p.Descripcion_Larga), nd.estilo) AS nombre,
                    SUM(nd.cantidad) AS piezas,
                    SUM(nd.importe)  AS importe
             FROM notas_1 n
             INNER JOIN notas_2 nd ON nd.folio = n.folio
             LEFT  JOIN productosyazbek p ON p.sku = nd.estilo
             WHERE n.status IN (4,5,6)
               AND n.fecha_inicial >= ? AND n.fecha_inicial < ?
             GROUP BY nd.estilo
             ORDER BY piezas DESC
             LIMIT 10",
            [$anioInicio, $anioFin]
        )->getResultArray();

        // ── Ingresos por vendedor (año) ───────────────────────────
        $ventasVendedor = $db->query(
            "SELECT u.usuario AS vendedor,
                    COALESCE(SUM(CASE WHEN n.status IN (4,5,6) THEN n.total ELSE 0 END),0) AS total,
                    SUM(CASE WHEN n.status IN (4,5,6) THEN 1 ELSE 0 END) AS notas,
                    SUM(CASE WHEN n.status = 3 THEN 1 ELSE 0 END) AS canceladas
             FROM notas_1 n
             INNER JOIN usuarios u ON u.Id = n.idVendedor
             WHERE n.status IN (3,4,5,6)
               AND n.fecha_inicial >= ? AND n.fecha_inicial < ?
             GROUP BY n.idVendedor
             ORDER BY total DESC
             LIMIT 8",
            [$anioInicio, $anioFin]
        )->getResultArray();

        // ── Forma de pago (año) ───────────────────────────────────
        $tipoPago = $db->query(
            "SELECT tp.descripcion AS tipo,
                    COALESCE(SUM(mn.monto),0) AS total
             FROM notas_1 n
             INNER JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             INNER JOIN tipopago tp    ON tp.id = mn.idTipoPago
             WHERE n.status IN (4,5,6)
               AND n.fecha_inicial >= ? AND n.fecha_inicial < ?
             GROUP BY mn.idTipoPago
             ORDER BY total DESC",
            [$anioInicio, $anioFin]
        )->getResultArray();

        // ── Top productos por mes (actual y anterior) ─────────────
        $topMes = function($ini, $fin) use ($db) {
            return $db->query(
                "SELECT nd.estilo AS sku,
                        COALESCE(MAX(p.Descripcion_Larga), nd.estilo) AS nombre,
                        SUM(nd.cantidad) AS piezas,
                        SUM(nd.importe)  AS importe
                 FROM notas_1 n
                 INNER JOIN notas_2 nd ON nd.folio = n.folio
                 LEFT  JOIN productosyazbek p ON p.sku = nd.estilo
                 WHERE n.status IN (4,5,6)
                   AND n.fecha_inicial >= ? AND n.fecha_inicial < ?
                 GROUP BY nd.estilo
                 ORDER BY piezas DESC LIMIT 8",
                [$ini, $fin]
            )->getResultArray();
        };

        $topMesActual   = $topMes($mesActualInicio, $mesActualFin);
        $topMesAnterior = $topMes($mesAnteriorInicio, $mesAnteriorFin);

        // ── Comparativa mensual por estatus ───────────────────────
        $statusLabels = [1=>'Abierta', 2=>'En proceso', 3=>'Cancelada', 4=>'Anticipo', 5=>'Pagada', 6=>'Liquidado'];
        $rawActual   = $db->query(
            "SELECT status, COUNT(*) AS notas, COALESCE(SUM(total),0) AS total
             FROM notas_1 WHERE fecha_inicial >= ? AND fecha_inicial < ? GROUP BY status",
            [$mesActualInicio, $mesActualFin]
        )->getResultArray();
        $rawAnterior = $db->query(
            "SELECT status, COUNT(*) AS notas, COALESCE(SUM(total),0) AS total
             FROM notas_1 WHERE fecha_inicial >= ? AND fecha_inicial < ? GROUP BY status",
            [$mesAnteriorInicio, $mesAnteriorFin]
        )->getResultArray();
        $comp = [];
        foreach ([1,2,3,4,5,6] as $s) {
            $comp[$s] = ['label'=>$statusLabels[$s], 'actual_n'=>0, 'actual_t'=>0.0, 'anterior_n'=>0, 'anterior_t'=>0.0];
        }
        foreach ($rawActual   as $r) { $s=(int)$r['status']; if(isset($comp[$s])){ $comp[$s]['actual_n']=(int)$r['notas'];   $comp[$s]['actual_t']=round((float)$r['total'],2); } }
        foreach ($rawAnterior as $r) { $s=(int)$r['status']; if(isset($comp[$s])){ $comp[$s]['anterior_n']=(int)$r['notas']; $comp[$s]['anterior_t']=round((float)$r['total'],2); } }
        $comparativa = array_values($comp);

        // ── Ventas mensuales del año ──────────────────────────────
        $ventasMensualesRaw = $db->query(
            "SELECT MONTH(fecha_inicial) AS mes,
                    COALESCE(SUM(total),0) AS total,
                    COUNT(*) AS notas
             FROM notas_1
             WHERE status IN (4,5,6)
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

        // ── Histórico anual (todos los años, siempre completo) ────
        $ventasAnualesRaw = $db->query(
            "SELECT
                YEAR(fecha_inicial) AS anio,
                COALESCE(SUM(CASE WHEN status IN (4,5,6) THEN total ELSE 0 END),0) AS total,
                COUNT(*) AS notas,
                SUM(CASE WHEN status IN (4,5,6) THEN 1 ELSE 0 END) AS notas_pagadas
             FROM notas_1
             WHERE fecha_inicial IS NOT NULL
               AND YEAR(fecha_inicial) >= 2000
             GROUP BY YEAR(fecha_inicial)
             ORDER BY anio ASC"
        )->getResultArray();

        // ── Vendedores hoy (solo los que tienen actividad hoy) ────────
        $hoy = date('Y-m-d');
        $vendedoresHoy = $db->query(
            "SELECT u.usuario AS vendedor,
                    COALESCE(SUM(CASE WHEN n.status IN (4,5,6) THEN n.total ELSE 0 END),0) AS total,
                    COALESCE(SUM(CASE WHEN n.status IN (4,5,6) THEN 1 ELSE 0 END),0) AS notas,
                    COALESCE(SUM(CASE WHEN n.status = 3 THEN 1 ELSE 0 END),0) AS canceladas
             FROM notas_1 n
             INNER JOIN usuarios u ON u.Id = n.idVendedor
             WHERE DATE(n.fecha_inicial) = ?
               AND n.status IN (3,4,5,6)
             GROUP BY u.Id, u.usuario
             HAVING total > 0 OR canceladas > 0
             ORDER BY total DESC",
            [$hoy]
        )->getResultArray();

        // ── Forma de pago hoy ─────────────────────────────────
        $tipoPagoHoy = $db->query(
            "SELECT tp.descripcion AS tipo,
                    COALESCE(SUM(mn.monto),0) AS total
             FROM notas_1 n
             INNER JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             INNER JOIN tipopago tp    ON tp.id = mn.idTipoPago
             WHERE n.status IN (4,5,6)
               AND DATE(n.fecha_inicial) = ?
             GROUP BY mn.idTipoPago
             ORDER BY total DESC",
            [$hoy]
        )->getResultArray();

        // ── Corregir encoding en textos con caracteres especiales ──
        $topProductos   = $this->fixRows($topProductos);
        $ventasVendedor = $this->fixRows($ventasVendedor);
        $tipoPago       = $this->fixRows($tipoPago);
        $topMesActual   = $this->fixRows($topMesActual);
        $topMesAnterior = $this->fixRows($topMesAnterior);
        $vendedoresHoy  = $this->fixRows($vendedoresHoy);
        $tipoPagoHoy    = $this->fixRows($tipoPagoHoy);

        return $this->response
            ->setContentType('application/json')
            ->setJSON([
                'topProductos'   => $topProductos,
                'ventasVendedor' => $ventasVendedor,
                'tipoPago'       => $tipoPago,
                'topMesActual'   => $topMesActual,
                'topMesAnterior' => $topMesAnterior,
                'comparativa'    => $comparativa,
                'ventasMensuales'=> $ventasMensuales,
                'notasMensuales' => $notasMensuales,
                'mesesLabels'    => $mesesLabels,
                'mesActual'      => $mesNombreActual,
                'mesAnterior'    => $mesNombreAnterior,
                'anio'           => $anio,
                'aniosLabels'    => array_column($ventasAnualesRaw, 'anio'),
                'aniosTotales'   => array_map(fn($r) => round((float)$r['total'], 2), $ventasAnualesRaw),
                'aniosNotas'     => array_map(fn($r) => (int)$r['notas'], $ventasAnualesRaw),
                'aniosPagadas'   => array_map(fn($r) => (int)$r['notas_pagadas'], $ventasAnualesRaw),
                'vendedoresHoy'  => $vendedoresHoy,
                'tipoPagoHoy'    => $tipoPagoHoy,
                'fechaHoy'       => $hoy,
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

        $nombre = $this->request->getPost('nombre');
        $mail   = $this->request->getPost('mail');
        $acceso = $this->request->getPost('acceso');
        $this->usuarioModel->insert([
            'nombre'  => $nombre,
            'usuario' => $nombre,
            'mail'    => $mail,
            'pass'    => $this->request->getPost('pass'),
            'acceso'  => $acceso,
        ]);
        AuditService::log(AuditService::USUARIO_CREADO, 'usuarios', null,
            "Usuario creado: {$nombre}, correo: {$mail}, rol: {$acceso}");
        return redirect()->to('/admin/usuarios')->with('success', 'Usuario creado correctamente.');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/usuarios/eliminar/:id  —  Soft-delete un usuario
    // ──────────────────────────────────────────────────────────────
    public function eliminarUsuario(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->usuarioModel->softDelete($id);
        AuditService::log(AuditService::USUARIO_ELIMINADO, 'usuarios', $id,
            "Usuario {$id} eliminado");
        return redirect()->to('/admin/usuarios')->with('success', 'Usuario eliminado.');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/usuarios/editar/:id  —  Edita datos de un usuario
    // ──────────────────────────────────────────────────────────────
    public function editarUsuario(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $data = [
            'nombre' => trim($this->request->getPost('nombre') ?? ''),
            'mail'   => trim($this->request->getPost('mail')   ?? ''),
            'pass'   => trim($this->request->getPost('pass')   ?? ''),
            'acceso' => (int) $this->request->getPost('acceso'),
        ];

        if (empty($data['nombre']) || empty($data['mail']) || empty($data['pass']) || ! $data['acceso']) {
            return redirect()->to('/admin/usuarios')->with('error', 'Todos los campos son obligatorios.');
        }

        $this->usuarioModel->update($id, $data);
        AuditService::log(AuditService::USUARIO_EDITADO, 'usuarios', $id,
            "Usuario {$id} actualizado: {$data['nombre']} / {$data['mail']} / rol {$data['acceso']}");
        return redirect()->to('/admin/usuarios')->with('success', 'Usuario actualizado correctamente.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/usuarios/eliminados  —  Lista de usuarios eliminados
    // ──────────────────────────────────────────────────────────────
    public function usuariosEliminados(): string
    {
        return view('admin/usuarios_eliminados', [
            'usuario' => $this->getUsuarioSesion(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/usuarios/eliminados/datatable
    // ──────────────────────────────────────────────────────────────
    public function usuariosEliminadosDatatable(): \CodeIgniter\HTTP\Response
    {
        $draw     = (int) $this->request->getGet('draw');
        $start    = (int) $this->request->getGet('start');
        $length   = (int) $this->request->getGet('length');
        $search   = $this->request->getGet('search')['value'] ?? '';
        $orderDir = $this->request->getGet('order')[0]['dir'] ?? 'asc';

        $result = $this->usuarioModel->getDatatableEliminados($start, $length, $search, $orderDir);

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $result['data'],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/usuarios/restaurar/:id  —  Restaura un usuario eliminado
    // ──────────────────────────────────────────────────────────────
    public function restaurarUsuario(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->usuarioModel->restaurar($id);
        AuditService::log(AuditService::USUARIO_RESTAURADO, 'usuarios', $id,
            "Usuario {$id} restaurado");
        return redirect()->to('/admin/usuarios/eliminados')->with('success', 'Usuario restaurado correctamente.');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/usuarios/eliminar-definitivo/:id  —  Elimina permanentemente
    // ──────────────────────────────────────────────────────────────
    public function eliminarUsuarioDefinitivo(int $id): \CodeIgniter\HTTP\Response
    {
        $db = \Config\Database::connect();
        try {
            $db->query("DELETE FROM usuarios WHERE Id = ? AND eliminado = 1", [$id]);
            AuditService::log(AuditService::USUARIO_ELIMINADO, 'usuarios', $id,
                "Usuario {$id} ELIMINADO DEFINITIVAMENTE");
            return $this->response->setJSON(['ok' => true]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/usuarios/liberar/:id  —  Libera la bandera del usuario
    // Migrado desde: admin/liberar_usuario.php
    // ──────────────────────────────────────────────────────────────
    public function liberarUsuario(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->usuarioModel->liberarBandera($id);
        AuditService::log(AuditService::USUARIO_LIBERADO, 'usuarios', $id,
            "Bandera liberada para usuario {$id}");
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
                AuditService::log(AuditService::PASSWORD_CAMBIADO, 'usuarios', $userId,
                    "Contraseña cambiada para usuario {$userId}");
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
            AuditService::log(AuditService::PRODUCTO_ACTUALIZADO, 'productosyazbek', $id,
                "Producto id={$id} actualizado desde admin",
                null,
                ['estilo'=>trim($this->request->getPost('estilo') ?? ''),
                 'pMayoreo'=>(float)$this->request->getPost('pMayoreo'),
                 'pMenudeo'=>(float)$this->request->getPost('pMenudeo'),
                 'piezas'=>(int)$this->request->getPost('piezas')]);
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
            AuditService::log(AuditService::PRODUCTO_ELIMINADO, 'productosyazbek', $id,
                "Producto id={$id} eliminado del catálogo");
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
        AuditService::log(AuditService::PRODUCTOS_IMPORTADOS, 'productosyazbek', null,
            "Importación CSV productos: {$insertados} insertados, {$omitidos} omitidos, {$errores} errores");
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
        AuditService::log(AuditService::PRECIOS_IMPORTADOS, 'productosyazbek', null,
            "Importación CSV precios: {$actualizados} actualizados, {$noEncontrados} no encontrados");
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

        AuditService::log(AuditService::STOCK_IMPORTADO, 'productosyazbek', null,
            "Importación CSV stock: {$actualizados} actualizados, {$noEncontrados} no encontrados");

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
            AuditService::log(AuditService::MENSAJE_GUARDADO, 'mensajes', $id,
                "Mensaje actualizado: ID {$id}");
        } else {
            $this->mensajeModel->insert($datos);
            AuditService::log(AuditService::MENSAJE_GUARDADO, 'mensajes', null,
                "Nuevo mensaje creado: {$datos['t_mensaje']}");
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
        AuditService::log(AuditService::MENSAJE_ELIMINADO, 'mensajes', $id,
            "Mensaje eliminado: ID {$id}");
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
        $nombreCliente = strtoupper(trim($this->request->getPost('nombre') ?? ''));
        AuditService::log(AuditService::CLIENTE_CREADO, 'clientes', null,
            "Cliente creado: {$nombreCliente}");

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
        AuditService::log(AuditService::CLIENTE_EDITADO, 'clientes', $id,
            "Cliente actualizado: ID {$id}");

        return redirect()->to('/admin/clientes')->with('success', 'Cliente actualizado.');
    }

    // POST /admin/clientes/eliminar
    public function eliminarCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $id = (int) $this->request->getPost('clienteDelete');
        if ($id) {
            $clienteModel = new \App\Models\ClienteModel();
            $clienteModel->softDelete($id);
            AuditService::log(AuditService::CLIENTE_ELIMINADO, 'clientes', $id,
                "Cliente eliminado: ID {$id}");
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

    // ──────────────────────────────────────────────────────────────
    // POST /admin/folio/:folio/liquidar  —  Liquida un anticipo
    // ──────────────────────────────────────────────────────────────
    public function liquidarAnticipo(int $folio): \CodeIgniter\HTTP\Response
    {
        $notaModel = new \App\Models\NotaModel();
        $nota      = $notaModel->getPorFolio($folio);

        if (! $nota) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Folio no encontrado.']);
        }

        $notaModel->liquidarAnticipo($folio);
        AuditService::log(AuditService::VENTA_LIQUIDADA, 'notas_1', $folio,
            "Folio #{$folio} liquidado por admin");
        return $this->response->setJSON(['ok' => true, 'mensaje' => "Folio #{$folio} liquidado correctamente."]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/folio/:folio/datos-fiscales
    // Devuelve los datos SAT para pre-llenar el modal de facturación.
    // Prioridad: datos guardados en notas_1 → datos del cliente → vacío.
    // ──────────────────────────────────────────────────────────────
    public function datosFiscales(int $folio): \CodeIgniter\HTTP\Response
    {
        $db   = \Config\Database::connect();
        $nota = $db->query(
            "SELECT n.rfc_receptor, n.razon_social_receptor, n.cp_receptor,
                    n.uso_cfdi, n.regimen_fiscal_receptor, n.forma_pago_cfdi,
                    n.idCliente
             FROM notas_1 n WHERE n.folio = ? LIMIT 1",
            [$folio]
        )->getRowArray();

        if (! $nota) {
            return $this->response->setJSON(['error' => 'Folio no encontrado']);
        }

        // Cargar datos del cliente como respaldo
        $cliente = [];
        if (! empty($nota['idCliente'])) {
            $cliente = $db->query(
                "SELECT RFC, razonSocial, CP, mail FROM clientes WHERE id = ? LIMIT 1",
                [(int)$nota['idCliente']]
            )->getRowArray() ?? [];
        }

        // Prioridad: dato de nota → dato de cliente → vacío
        $rfc    = $nota['rfc_receptor']          ?: ($cliente['RFC']         ?? '');
        $rs     = $nota['razon_social_receptor'] ?: ($cliente['razonSocial'] ?? '');
        $cp     = $nota['cp_receptor']           ?: ($cliente['CP']          ?? '');
        $uso    = $nota['uso_cfdi']              ?: 'S01';
        $reg    = $nota['regimen_fiscal_receptor'] ?: '616';
        $forma  = $nota['forma_pago_cfdi']       ?: '01';

        return $this->response->setJSON([
            'rfcReceptor'           => strtoupper(trim($rfc)),
            'razonSocialReceptor'   => strtoupper(trim($rs)),
            'cpReceptor'            => trim($cp),
            'usoCFDI'               => $uso,
            'regimenFiscalReceptor' => $reg,
            'formaPagoCFDI'         => $forma,
            'metodoPagoCFDI'        => 'PUE',
            'idCliente'             => (int)($nota['idCliente'] ?? 0),
            'correoCliente'         => trim($cliente['mail'] ?? ''),
        ]);
    }

    /**
     * POST /admin/clientes/:id/guardar-correo
     * Guarda el correo electrónico de un cliente (solo si estaba vacío).
     */
    public function guardarCorreoCliente(int $id): \CodeIgniter\HTTP\Response
    {
        $mail = trim($this->request->getPost('mail') ?? '');
        if (!$id || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Correo inválido.']);
        }
        $db = \Config\Database::connect();
        $db->query("UPDATE clientes SET mail = ? WHERE id = ?", [$mail, $id]);
        return $this->response->setJSON(['success' => true]);
    }

    /**
     * GET /admin/clientes/:id/datos-fiscales-cliente
     * Devuelve RFC, Razón Social y CP del cliente para pre-llenar el modal consolidado.
     */
    public function datosFiscalesCliente(int $id): \CodeIgniter\HTTP\Response
    {
        $db  = \Config\Database::connect();
        $row = $db->query(
            "SELECT RFC, razonSocial, CP, nombre FROM clientes WHERE id = ? LIMIT 1",
            [$id]
        )->getRowArray();
        if (!$row) {
            return $this->response->setJSON([]);
        }
        return $this->response->setJSON([
            'RFC'          => strtoupper(trim($row['RFC']         ?? '')),
            'razonSocial'  => strtoupper(trim($row['razonSocial'] ?? '')),
            'CP'           => trim($row['CP']   ?? ''),
            'nombre'       => trim($row['nombre'] ?? ''),
            'regimenFiscal'=> '616',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/folio/:folio/facturar
    // Genera la factura CFDI para un folio ya cerrado.
    // Escenario 1: los datos fiscales ya están en notas_1 (factura=1 al cerrar).
    // Escenario 2: el admin proporciona los datos vía modal (factura=0 al cerrar).
    // Solo Admin (acceso=1) puede llamar este endpoint.
    // ──────────────────────────────────────────────────────────────
    public function facturarFolio(int $folio): \CodeIgniter\HTTP\Response
    {
        try {
            $req = $this->request;

            // Si vienen datos del modal (Escenario 2), los pasamos al servicio
            $rfcModal = trim($req->getPost('rfcReceptor') ?? '');
            $datosModal = null;
            if ($rfcModal !== '') {
                $datosModal = [
                    'rfcReceptor'           => $rfcModal,
                    'razonSocialReceptor'   => trim($req->getPost('razonSocialReceptor') ?? ''),
                    'cpReceptor'            => trim($req->getPost('cpReceptor')          ?? ''),
                    'usoCFDI'               => trim($req->getPost('usoCFDI')             ?? 'S01'),
                    'regimenFiscalReceptor' => trim($req->getPost('regimenFiscalReceptor') ?? '616'),
                    'formaPago'             => trim($req->getPost('formaPagoCFDI')       ?? '01'),
                    'metodoPago'            => trim($req->getPost('metodoPagoCFDI')      ?? 'PUE'),
                ];
            }

            $svc    = new \App\Libraries\FacturacionService();
            $result = $svc->procesar($folio, $datosModal);

            if ($result['success']) {
                return $this->response->setJSON([
                    'success' => true,
                    'uuid'    => $result['uuid'],
                    'mensaje' => "Folio #{$folio} facturado correctamente. UUID: {$result['uuid']}",
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'mensaje' => $result['message'],
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', "AdminController::facturarFolio folio={$folio}: " . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'mensaje' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/folio/:folio/ticket  —  Ticket de impresión (popup)
    // Equivalente a venta_stp_3.php del sistema original
    // ──────────────────────────────────────────────────────────────
    public function verTicket(int $folio): string
    {
        $db = \Config\Database::connect();

        $nota = $db->query(
            "SELECT n.Id_Notas_1, n.fecha_inicial, n.fecha_final,
                    c.nombre AS NombreCliente, c.direccion, c.telefono, c.mail AS email,
                    u.usuario AS vendedor,
                    n.folio, n.referencia, n.factura, n.descuento,
                    n.status AS statusId, n.verificado,
                    n.sumaImportes, n.subTotal, n.tipoPago,
                    n.cargoTarjeta, n.subTotal2, n.iva, n.total,
                    n.tipoImpresion, n.cargoPorImpresion, n.montoTCTD,
                    n.montoEfectivo, n.montoEfectivoIva, n.totalPiezas,
                    n.precioMayoreo, COALESCE(n.uuid_fiscal, '') AS uuid_fiscal
             FROM notas_1 n
             LEFT JOIN clientes c ON c.id = n.idCliente
             LEFT JOIN usuarios u ON u.Id = n.idVendedor
             WHERE n.folio = ?
             LIMIT 1",
            [$folio]
        )->getRowArray();

        if (! $nota) {
            return '<h3>Folio no encontrado</h3>';
        }

        // Mapa de status (independiente del valor en la tabla status de BD)
        $statusMap = [1=>'Abierta', 2=>'En proceso', 3=>'Cancelada', 4=>'Anticipo', 5=>'Pagada', 6=>'Liquidado'];
        $statusId  = (int)($nota['statusId'] ?? 0);
        // Liquidado: status=6 en BD O verificado='Pagado'
        $esLiquidadoTicket = ($nota['verificado'] === 'Pagado' || $statusId === 6);
        $statusDisplay     = $esLiquidadoTicket ? 'Liquidado' : ($statusMap[$statusId] ?? 'Desconocido');
        // Para comparaciones en la vista que usan el string
        $nota['status'] = $statusDisplay;

        $idNota     = (int)$nota['Id_Notas_1'];
        $referencia = (int)($nota['referencia'] ?? 0);

        // Si es nota hija (referencia != 0) los pagos vienen del padre
        $idNotaPagos = ($referencia !== 0) ? $referencia : $idNota;

        // Pagos directos del folio (del padre o del hijo si es hijo)
        $pagos = $db->query(
            "SELECT m.idTipoPago, t.descripcion AS tipopago,
                    m.monto, m.cargos AS cargo, m.anticipo, m.montoEfectivoIva
             FROM montosnotas m
             INNER JOIN tipopago t ON t.id = m.idTipoPago
             WHERE m.idNotas = ?",
            [$idNotaPagos]
        )->getResultArray();

        // Folios hijos no cancelados (anticipos) con sus pagos
        $pagosHijos = [];
        if ($referencia === 0) {
            // Solo aplica para notas padre (referencia = 0)
            $hijos = $db->query(
                "SELECT n.folio, n.Id_Notas_1, n.fecha_inicial
                 FROM notas_1 n
                 WHERE n.referencia = ? AND n.status != 3
                 ORDER BY n.folio ASC",
                [$folio]
            )->getResultArray();

            foreach ($hijos as $hijo) {
                $pgHijo = $db->query(
                    "SELECT m.monto, m.cargos AS cargo, t.descripcion AS tipopago, m.anticipo, m.fecha
                     FROM montosnotas m
                     INNER JOIN tipopago t ON t.id = m.idTipoPago
                     WHERE m.idNotas = ?",
                    [$hijo['Id_Notas_1']]
                )->getResultArray();

                if (! empty($pgHijo)) {
                    $pagosHijos[] = [
                        'folio'  => $hijo['folio'],
                        'fecha'  => $hijo['fecha_inicial'],
                        'pagos'  => $pgHijo,
                    ];
                }
            }
        }

        // Los folios hijo (anticipo) no tienen productos propios; usar los del padre
        $folioProductos = ($referencia !== 0) ? $referencia : $folio;

        $productos = $db->query(
            "SELECT n2.cantidad,
                    n2.estilo AS sku,
                    CONCAT(COALESCE(p.estilo,''),'-',COALESCE(p.Descripcion_Larga,''),
                           '-',COALESCE(p.Talla,''),'-',COALESCE(p.Color,'')) AS descripcion,
                    n2.pUnitario,
                    n2.pUnitarioM,
                    (n2.cantidad * n2.pUnitario)  AS importeMenudeo,
                    (n2.cantidad * n2.pUnitarioM) AS importeMayoreo
             FROM notas_2 n2
             LEFT JOIN productosyazbek p ON p.sku = n2.estilo
             WHERE n2.folio = ?
             ORDER BY n2.Id_Notas_2 ASC",
            [$folioProductos]
        )->getResultArray();

        // Para folios hijo, usar sumaImportes y precioMayoreo del padre para detectar el tipo de precio
        if ($referencia !== 0) {
            $notaPadre = $db->query(
                "SELECT sumaImportes, precioMayoreo FROM notas_1 WHERE folio = ? LIMIT 1",
                [$referencia]
            )->getRowArray();
            $storedSuma = (float)($notaPadre['sumaImportes'] ?? 0);
            if ($storedSuma <= 0) {
                $nota['precioMayoreo'] = $notaPadre['precioMayoreo'] ?? $nota['precioMayoreo'];
            }
        } else {
            $storedSuma = (float)($nota['sumaImportes'] ?? 0);
        }

        // Determinar qué precio se usó realmente comparando contra sumaImportes guardado.
        // Esto es confiable tanto para notas históricas (sistema antiguo) como nuevas.
        if ($storedSuma > 0) {
            $sumMenudeo = array_sum(array_map(fn($p) => $p['cantidad'] * (float)$p['pUnitario'], $productos));
            $sumMayoreo = array_sum(array_map(fn($p) => $p['cantidad'] * (float)($p['pUnitarioM'] ?: $p['pUnitario']), $productos));
            $esMayoreoNota = abs($sumMayoreo - $storedSuma) < abs($sumMenudeo - $storedSuma);
        } else {
            $esMayoreoNota = (int)($nota['precioMayoreo'] ?? 0) === 1;
        }

        foreach ($productos as &$prod) {
            $useMayoreo = $esMayoreoNota && !empty($prod['pUnitarioM']) && (float)$prod['pUnitarioM'] > 0;
            $prod['pUnitario'] = $useMayoreo ? (float)$prod['pUnitarioM'] : (float)$prod['pUnitario'];
            $prod['importe']   = $useMayoreo ? (float)$prod['importeMayoreo'] : (float)$prod['importeMenudeo'];
        }
        unset($prod);

        // totalPiezas: calcular desde productos (notas_1 puede tener 0 si fue guardado antes)
        $totalPiezasCalc = array_sum(array_column($productos, 'cantidad'));
        if ($totalPiezasCalc > 0 && (int)($nota['totalPiezas'] ?? 0) === 0) {
            $nota['totalPiezas'] = $totalPiezasCalc;
        }

        $anticipos = $db->query(
            "SELECT monto FROM montosnotas WHERE idNotas = ? AND anticipo = 1",
            [$idNotaPagos]
        )->getResultArray();

        $letras = '';
        try {
            $letras = mb_strtoupper(\App\Libraries\AifLibNumber::toCurrency($nota['total']));
        } catch (\Throwable $e) {
            $letras = '';
        }

        return view('admin/ticket', [
            'nota'        => $nota,
            'pagos'       => $pagos,
            'pagosHijos'  => $pagosHijos,
            'productos'   => $productos,
            'anticipos'   => $anticipos,
            'letras'      => $letras,
            'config'      => $this->getTicketConfig(),
        ]);
    }

    // POST /admin/clientes/restaurar/(:num)  —  Revive un cliente eliminado
    // POST /admin/clientes/guardar-email  —  AJAX: guarda email faltante del cliente
    public function guardarEmailCliente(): \CodeIgniter\HTTP\Response
    {
        $id    = (int) $this->request->getPost('id');
        $email = trim($this->request->getPost('email') ?? '');

        if (!$id || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Datos inválidos']);
        }

        $db = \Config\Database::connect();
        $db->query("UPDATE clientes SET mail = ? WHERE id = ?", [$email, $id]);

        return $this->response->setJSON(['ok' => true]);
    }

    public function restaurarCliente(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $clienteModel = new \App\Models\ClienteModel();
        $clienteModel->restaurar($id);
        AuditService::log(AuditService::CLIENTE_RESTAURADO, 'clientes', $id,
            "Cliente restaurado: ID {$id}");
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
                AuditService::log(AuditService::VENTA_ELIMINADA, 'notas_1', $folio,
                    "Nota eliminada definitivamente (cliente eliminado): folio {$folio}");
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
            AuditService::log(AuditService::CLIENTE_ELIMINADO, 'clientes', $id,
                "Cliente eliminado definitivamente: ID {$id}");
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
            'success'       => true,
            'direccion'     => $cliente['direccion']     ?? '',
            'telefono'      => $cliente['telefono']      ?? '',
            'celular'       => $cliente['celular']       ?? '',
            'email'         => $cliente['mail']          ?? '',
            'RFC'           => $cliente['RFC']           ?? '',
            'razonSocial'   => $cliente['razonSocial']   ?? '',
            'NombreEmpresa' => $cliente['NombreEmpresa'] ?? '',
            'CP'            => $cliente['CP']            ?? '',
            'ciudad'        => $cliente['ciudad']        ?? '',
            'estado'        => $cliente['estado']        ?? '',
            'comoNosConoce' => $cliente['comoNosConoce'] ?? '',
            'fechaIngreso'  => $cliente['fechaIngreso']  ?? '',
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
        $db    = \Config\Database::connect();
        $desde = "{$fecha1} {$h1}:{$m1}:{$s1}";
        $hasta = "{$fecha2} {$h2}:{$m2}:{$s2}";

        $rows = $db->query(
            "SELECT n.folio,
                    DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') AS fecha,
                    COALESCE(NULLIF(c.nombre,''), 'PUBLICO GENERAL') AS cliente,
                    COALESCE(u.usuario, '—')       AS vendedor,
                    COALESCE(tp.descripcion, '')   AS tipopago,
                    COALESCE(mn.monto, 0)          AS monto,
                    n.total                        AS total_nota,
                    COALESCE(s.nombre, '—')        AS estatus,
                    COALESCE(mn.referencia, '')    AS referencia
             FROM notas_1 n
             LEFT JOIN clientes    c  ON c.id         = n.idCliente
             LEFT JOIN usuarios    u  ON u.Id          = n.idVendedor
             LEFT JOIN montosnotas mn ON mn.idNotas    = n.Id_Notas_1
             LEFT JOIN tipopago    tp ON tp.id         = mn.idTipoPago
             LEFT JOIN status      s  ON s.id          = n.status
             WHERE n.fecha_inicial >= ? AND n.fecha_inicial <= ? AND n.status != 3
             ORDER BY n.folio ASC",
            [$desde, $hasta]
        )->getResultArray();

        $fechaLabel = date('d/m/Y', strtotime($fecha1)) . ' — ' . date('d/m/Y', strtotime($fecha2));
        $content    = \App\Libraries\CorteCajaExporter::build($rows, $fechaLabel);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="reporte_diario_' . date('dmY') . '.xlsx"')
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
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
                        n.status AS statusId,
                        n.sumaImportes, n.subTotal, n.tipoPago,
                        n.cargoTarjeta, n.subTotal2, n.iva, n.total,
                        n.tipoImpresion, n.cargoPorImpresion,
                        n.montoTCTD, n.totalPiezas, n.precioMayoreo,
                        COALESCE(n.uuid_fiscal, '') AS uuid_fiscal
                 FROM notas_1 n
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

            // Pagos: del folio padre + de todos sus folios hijos (con referencia = folio)
            $pagos = $db->query(
                "SELECT m.idTipoPago, t.descripcion AS tipopago,
                        m.monto, m.cargos AS cargo, m.anticipo,
                        n2.folio AS folio_pago,
                        n2.status AS folio_status
                 FROM notas_1 n2
                 INNER JOIN montosnotas m ON m.idNotas = n2.Id_Notas_1
                 INNER JOIN tipopago t ON t.id = m.idTipoPago
                 WHERE n2.folio = ? OR n2.referencia = ?
                 ORDER BY n2.folio ASC",
                [$folio, $folio]
            )->getResultArray();

            // Productos con precios correctos (mismo criterio que getCarritoData)
            $detalles = $db->query(
                "SELECT n.cantidad,
                        n.estilo AS sku,
                        CONCAT(p.estilo,'-',p.Descripcion_Larga,'-',p.Talla,'-',p.Color) AS descripcion,
                        COALESCE(NULLIF(n.pUnitario,  0), p.pMenudeo, 0)                      AS pMenudeo,
                        COALESCE(NULLIF(n.pUnitarioM, 0), p.pMayoreo, n.pUnitario, 0)         AS pMayoreo
                 FROM notas_2 n
                 LEFT JOIN productosyazbek p ON p.sku = n.estilo
                 WHERE n.folio = ?",
                [$folio]
            )->getResultArray();

            // Determinar qué precio se usó realmente comparando contra sumaImportes guardado.
            $storedSuma = (float)($nota['sumaImportes'] ?? 0);
            if ($storedSuma > 0) {
                $sumMenu = array_sum(array_map(fn($d) => $d['cantidad'] * (float)$d['pMenudeo'], $detalles));
                $sumMay  = array_sum(array_map(fn($d) => $d['cantidad'] * (float)$d['pMayoreo'],  $detalles));
                $esMayoreoNota = abs($sumMay - $storedSuma) < abs($sumMenu - $storedSuma);
            } else {
                $esMayoreoNota = (int)($nota['precioMayoreo'] ?? 0) === 1;
            }
            foreach ($detalles as &$d) {
                $d['pUnitario'] = $esMayoreoNota ? (float)$d['pMayoreo'] : (float)$d['pMenudeo'];
                $d['importe']   = $d['cantidad'] * $d['pUnitario'];
            }
            unset($d);

        } catch (\Throwable $e) {
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

        // Mapa de status legibles (independiente de lo que diga la tabla status en BD)
        $statusMap = [
            1 => 'Abierta', 2 => 'En proceso', 3 => 'Cancelada',
            4 => 'Anticipo', 5 => 'Pagada', 6 => 'Liquidado',
        ];
        $verificado   = $nota['verificado'] ?? '';
        $statusId     = (int)($nota['statusId'] ?? 0);
        $statusNombre = $statusMap[$statusId] ?? 'Desconocido';
        $esLiquidado  = ($verificado === 'Pagado' || $statusId === 6);
        $headerStatus = $esLiquidado ? 'Liquidado' : $statusNombre;

        // HTML idéntico al original (llamadasAjax.php tipo=3)
        $html  = '<div style="overflow-x:auto"><table style="width:100%"><tr>';
        $html .= '<td><strong>Fecha:&nbsp;</strong></td>';
        $html .= '<td>' . date('Y-m-d h:i A', strtotime($nota['fecha_inicial'])) . '</td>';
        $html .= '<td>&nbsp;</td><td><strong>Nombre Cliente:&nbsp;</strong></td>';
        $html .= '<td>' . esc($nota['NombreCliente']) . '</td>';
        $html .= '</tr><tr>';
        $html .= '<td><strong>Estatus:&nbsp;</strong></td><td>' . esc($headerStatus) . '</td>';
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
            $html .= '<td><span style="font-size:0.8em;color:#888;font-weight:600;">' . esc($d['sku'] ?? '') . '</span><br>' . esc($d['descripcion'] ?? '') . '</td>';
            $html .= '<td>$&nbsp;' . number_format($d['pUnitario'] ?? 0, 2) . '</td>';
            $html .= '<td>$&nbsp;' . number_format($d['importe']   ?? 0, 2) . '</td></tr>';
        }

        // Totales
        $ti    = (int)($nota['tipoImpresion'] ?? 0);
        $otros = $ti === 1 ? 'Ninguno' : ($ti === 2 ? 'Impresion' : ($ti === 3 ? 'Bordado' : ''));
        $html .= '<tr><td colspan="3" class="text-right"><strong>Otros</strong></td><td id="cargo">' . $otros . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo por otros</strong></td><td>$&nbsp;' . number_format($nota['cargoPorImpresion'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Descuento</strong></td><td>$&nbsp;' . number_format($nota['descuento'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right no-border"><strong>SubTotal</strong></td><td id="subtotal">$&nbsp;' . number_format($nota['subTotal'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo TC/TD</strong></td><td>$&nbsp;' . number_format($nota['montoTCTD'] ?? $nota['cargoTarjeta'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>IVA</strong></td><td>$&nbsp;' . number_format($nota['iva'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>SubTotal2</strong></td><td>$&nbsp;' . number_format($nota['subTotal2'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Total</strong></td><td id="total_cj">$&nbsp;' . number_format($total, 2) . '</td></tr>';

        if ($letras) {
            $html .= '<tr><td colspan="4" class="text-right no-border" align="left">';
            $html .= '<strong>Cantidad con letra( ' . esc($letras) . ')</strong></td></tr>';
        }

        // Detectar si hay pagos de folios hijos (distintos al folio padre)
        $hayFoliosHijos = !empty(array_filter($pagos, fn($p) => (int)($p['folio_pago'] ?? 0) !== $folio));

        foreach ($pagos as $p) {
            $esCancelado = (int)($p['folio_status'] ?? 0) === 3;
            $rowStyle    = $esCancelado ? 'opacity:.45;text-decoration:line-through;' : '';
            $html .= '<tr style="' . $rowStyle . '"><td colspan="4" class="text-right no-border">';
            // Mostrar número de folio solo si el pago viene de un folio hijo diferente
            if ($hayFoliosHijos && (int)($p['folio_pago'] ?? 0) !== $folio) {
                $html .= '<strong>Folio #' . $p['folio_pago'] . '</strong>';
                if ($esCancelado) {
                    $html .= ' <span style="background:#dc3545;color:#fff;font-size:.7em;padding:1px 6px;border-radius:4px;text-decoration:none;vertical-align:middle;">CANCELADO</span>';
                }
                $html .= ' — ';
            }
            $html .= '<strong>Forma de Pago:</strong> ' . esc($p['tipopago']);
            $html .= ' <strong>Monto: </strong>$&nbsp;' . number_format($p['monto'] ?? 0, 2);
            if (in_array($p['idTipoPago'] ?? 0, [2, 3])) {
                $html .= ' <strong>Cargo: </strong>$&nbsp;' . number_format($p['cargo'] ?? 0, 2);
            }
            $html .= ' <strong>Es anticipo:</strong> ' . (($p['anticipo'] ?? 0) == 1 ? 'Si' : 'No');
            $html .= '</td></tr>';
        }

        // Estatus mostrado en el pie del modal (ignorar pagos de folios cancelados)
        $pagosActivos = array_filter($pagos, fn($p) => (int)($p['folio_status'] ?? 0) !== 3);
        $sumPagado    = array_sum(array_column($pagosActivos, 'monto'));
        $hayAnticipo  = !empty(array_filter($pagosActivos, fn($p) => ($p['anticipo'] ?? 0) == 1));
        if ($esLiquidado) {
            $displayStatus = 'Liquidado';
        } elseif ($hayAnticipo && $sumPagado < ($nota['total'] ?? 0)) {
            $displayStatus = 'Abierta';
        } else {
            $displayStatus = $statusNombre;
        }

        $html .= '<tr><td colspan="2" class="text-right no-border" align="left"><strong>Estatus:</strong></td>';
        $html .= '<td colspan="2" align="left" id="estatusPago">' . esc($displayStatus) . '</td></tr>';
        $html .= '<tr><td colspan="2" class="text-right no-border" align="left"><strong>Factura:</strong></td>';
        // Consultar status_facturacion para el modal
        $sfRow = $db->query(
            "SELECT COALESCE(status_facturacion,0) AS sf, COALESCE(uuid_fiscal,'') AS uuid FROM notas_1 WHERE folio = ? LIMIT 1",
            [$folio]
        )->getRowArray();
        $statusFact = (int)($sfRow['sf']   ?? 0);
        $uuidFact   =       $sfRow['uuid'] ?? '';

        $facturaLabel = !empty($uuidFact) ? 'Facturado ✓' : (($nota['factura'] ?? 0) == 1 ? 'Pendiente por facturar' : 'No requerida');
        $html .= '<td colspan="2" align="left">' . $facturaLabel . '</td></tr>';

        $html .= '<tr><input type="hidden" id="folio_input" value="' . $nota['folio'] . '" />';
        if ($statusId !== 3) {
            $html .= '<td></td>';
            $html .= '<td colspan="2" class="text-right no-border"><button type="button" class="btn btn-danger" onclick="fn_modal_calcelar_nota()">Cancelar Nota</button></td>';
            if (! $esLiquidado) {
                $html .= '<td colspan="2" class="text-right no-border"><button type="button" class="btn btn-success" onclick="fn_liquidar_modal()">Liquidar</button></td>';
            }
        }

        // Botón Facturar en modal (solo nota pagada/liquidada, no cancelada, no ya facturada)
        if ($statusId !== 3 && ($statusId === 5 || $esLiquidado) && empty($uuidFact)) {
            $btnLabel = $statusFact === 1 ? 'Facturar' : 'Solicitar Factura';
            $html .= '<tr><td colspan="4" class="text-right pt-2">'
                   . '<button type="button" class="btn btn-primary" '
                   . 'onclick="$(\'#modalVerFolio\').modal(\'hide\'); adminAbrirModalFactura(' . $folio . ');">'
                   . '<i class=\'simple-icon-doc mr-1\'></i> ' . $btnLabel . '</button>'
                   . '</td></tr>';
        }

        $html .= '</tbody></table></div>';
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
            AuditService::log(AuditService::VENTA_VERIFICADA, 'notas_1', $folio,
                "Folio #{$folio} marcado como verificado/pagado");
            return $this->response->setBody('bien');
        } catch (\Throwable $e) {
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
                "SELECT Id_Notas_1, referencia, status FROM notas_1 WHERE folio = ? LIMIT 1",
                [$folio]
            )->getRowArray();

            if (! $nota) {
                return $this->response->setBody('0');
            }

            // Cancelar el folio y todos sus hijos (folios con referencia = folio)
            $db->query(
                "UPDATE notas_1 SET status = 3 WHERE folio = ? OR referencia = ?",
                [$folio, $folio]
            );
            AuditService::log(AuditService::VENTA_CANCELADA, 'notas_1', $folio,
                "Folio #{$folio} cancelado");

            // Restaurar stock del folio padre (notas_2 usa columna 'folio', no 'Id_Notas_1')
            // Solo el padre tiene productos — los hijos (anticipos) no tienen notas_2
            $esHijo = !empty($nota['referencia']);
            if (! $esHijo) {
                $productos = $db->query(
                    "SELECT cantidad, estilo AS sku FROM notas_2 WHERE folio = ?",
                    [$folio]
                )->getResultArray();

                foreach ($productos as $p) {
                    if (empty($p['sku']) || (int)$p['cantidad'] <= 0) continue;
                    $db->query(
                        "UPDATE productosyazbek SET piezas = piezas + ? WHERE sku = ?",
                        [(int)$p['cantidad'], $p['sku']]
                    );
                    // Broadcast del nuevo stock en tiempo real
                    $stockRow = $db->query(
                        "SELECT piezas FROM productosyazbek WHERE sku = ? LIMIT 1",
                        [$p['sku']]
                    )->getRowArray();
                    try {
                        $db->query(
                            "INSERT INTO stock_eventos (sku, nuevo_stock) VALUES (?, ?)",
                            [$p['sku'], (int)($stockRow['piezas'] ?? 0)]
                        );
                    } catch (\Throwable $se) {
                        log_message('warning', 'stock_eventos INSERT skipped: ' . $se->getMessage());
                    }
                }
            }

            return $this->response->setBody('1');
        } catch (\Throwable $e) {
            log_message('error', 'cajaCancelar folio=' . $folio . ': ' . $e->getMessage());
            return $this->response->setBody('0');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/folio/(:num)/revivir  —  Reactiva una nota cancelada (solo admin)
    // Restablece status=1 (Abierta) y vuelve a descontar el stock
    // ──────────────────────────────────────────────────────────────
    public function revivirNota(int $folio): \CodeIgniter\HTTP\Response
    {
        try {
            $db = \Config\Database::connect();

            $nota = $db->query(
                "SELECT Id_Notas_1, status, referencia, tipoPago FROM notas_1 WHERE folio = ? LIMIT 1",
                [$folio]
            )->getRowArray();

            if (! $nota) {
                return $this->response->setContentType('application/json')
                    ->setJSON(['ok' => false, 'error' => 'Folio no encontrado']);
            }
            if ((int)$nota['status'] !== 3) {
                return $this->response->setContentType('application/json')
                    ->setJSON(['ok' => false, 'error' => 'La nota no está cancelada']);
            }

            // Validar que el tipo de pago permite revivir
            $tipoPagoLower = mb_strtolower(trim($nota['tipoPago'] ?? ''));
            $bloqueado = (
                str_contains($tipoPagoLower, 'sin pagar') ||
                str_contains($tipoPagoLower, 'crédito')   ||
                str_contains($tipoPagoLower, 'credito')   ||
                str_contains($tipoPagoLower, 'tarjeta')
            );
            if ($bloqueado) {
                return $this->response->setContentType('application/json')
                    ->setJSON(['ok' => false, 'error' => 'No se puede revivir una nota con método de pago "' . $nota['tipoPago'] . '". Solo se permiten: Contado, Cheque, Transferencia, Depósito, etc.']);
            }

            $advertencias = [];

            // Solo notas padre tienen stock que restar (hijos=anticipos no tienen notas_2)
            $esHijo = !empty($nota['referencia']);
            if (! $esHijo) {
                $productos = $db->query(
                    "SELECT cantidad, estilo AS sku FROM notas_2 WHERE folio = ?",
                    [$folio]
                )->getResultArray();

                foreach ($productos as $p) {
                    if (empty($p['sku']) || (int)$p['cantidad'] <= 0) continue;

                    $cantNecesaria = (int)$p['cantidad'];
                    $stockActual   = (int)($db->query(
                        "SELECT piezas FROM productosyazbek WHERE sku = ? LIMIT 1",
                        [$p['sku']]
                    )->getRowArray()['piezas'] ?? 0);

                    if ($stockActual < $cantNecesaria) {
                        // Advertir pero no bloquear — ajustar notas_2 a lo disponible
                        $advertencias[] = "SKU {$p['sku']}: disponible {$stockActual}, necesario {$cantNecesaria}";
                        // Actualizar cantidad e importe en notas_2 al stock real disponible
                        $db->query(
                            "UPDATE notas_2 SET cantidad = ? WHERE folio = ? AND estilo = ?",
                            [$stockActual, $folio, $p['sku']]
                        );
                        if ($stockActual > 0) {
                            $db->query(
                                "UPDATE productosyazbek SET piezas = 0 WHERE sku = ?",
                                [$p['sku']]
                            );
                            try {
                                $db->query(
                                    "INSERT INTO stock_eventos (sku, nuevo_stock) VALUES (?, 0)",
                                    [$p['sku']]
                                );
                            } catch (\Throwable $se) {
                                log_message('warning', 'stock_eventos INSERT skipped: ' . $se->getMessage());
                            }
                        }
                    } else {
                        $db->query(
                            "UPDATE productosyazbek SET piezas = piezas - ? WHERE sku = ? AND piezas >= ?",
                            [$cantNecesaria, $p['sku'], $cantNecesaria]
                        );
                        $stockRow = $db->query(
                            "SELECT piezas FROM productosyazbek WHERE sku = ? LIMIT 1",
                            [$p['sku']]
                        )->getRowArray();
                        try {
                            $db->query(
                                "INSERT INTO stock_eventos (sku, nuevo_stock) VALUES (?, ?)",
                                [$p['sku'], (int)($stockRow['piezas'] ?? 0)]
                            );
                        } catch (\Throwable $se) {
                            log_message('warning', 'stock_eventos INSERT skipped: ' . $se->getMessage());
                        }
                    }
                }
            }

            // Reactivar nota padre → Abierta (1)
            // También reactivar hijos cancelados (anticipos) del mismo padre
            $db->query(
                "UPDATE notas_1 SET status = 1 WHERE folio = ? OR (referencia = ? AND status = 3)",
                [$folio, $folio]
            );
            AuditService::log(AuditService::VENTA_REACTIVADA, 'notas_1', $folio,
                "Folio #{$folio} reactivado");

            return $this->response->setContentType('application/json')
                ->setJSON(['ok' => true, 'folio' => $folio, 'esHijo' => $esHijo, 'advertencias' => $advertencias]);

        } catch (\Throwable $e) {
            log_message('error', 'revivirNota folio=' . $folio . ': ' . $e->getMessage());
            return $this->response->setContentType('application/json')
                ->setJSON(['ok' => false, 'error' => $e->getMessage()]);
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
        $fecha      = $this->request->getPost('fecha')       ?? date('d/m/Y');
        $fechaHasta = $this->request->getPost('fecha_hasta') ?? '';
        $estatus    = (int)($this->request->getPost('estatus')  ?? 0);
        $tipopago   = (int)($this->request->getPost('tipopago') ?? 0);

        // Catálogos para los selects
        $listaEstatus  = $db->query("SELECT * FROM status ORDER BY Id ASC")->getResultArray();
        $listaTipoPago = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();

        // Query principal — igual que corte2.php
        $where = "WHERE 1=1";
        if ($fecha !== '') {
            $dtDesde = \DateTime::createFromFormat('d/m/Y', $fecha);
            $dtHasta = $fechaHasta !== '' ? \DateTime::createFromFormat('d/m/Y', $fechaHasta) : null;
            if ($dtDesde && $dtHasta) {
                $where .= " AND DATE(n.fecha_inicial) BETWEEN " . $db->escape($dtDesde->format('Y-m-d'))
                        . " AND " . $db->escape($dtHasta->format('Y-m-d'));
            } else {
                $where .= " AND DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') = " . $db->escape($fecha);
            }
        }
        if ($estatus > 0) {
            $where .= " AND s.id = " . $estatus;
        }
        if ($tipopago > 0) {
            $where .= " AND tp.id = " . $tipopago;
        }

        $filas = $db->query(
            "SELECT n.folio, n.referencia, DATE_FORMAT(mn.fecha, '%d/%m/%Y') AS fecha,
                    c.nombre       AS cliente,
                    u.usuario      AS vendedor,
                    tp.descripcion AS tipopago,
                    mn.id          AS mn_id,
                    mn.monto       AS total,
                    mn.cargos,
                    mn.referencia  AS mn_referencia,
                    s.nombre       AS status,
                    n.verificado
             FROM notas_1 n
             LEFT JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             LEFT JOIN clientes    c  ON n.idCliente  = c.id
             INNER JOIN usuarios   u  ON u.Id = n.idVendedor
             LEFT JOIN tipopago    tp ON mn.idTipoPago = tp.id
             LEFT JOIN status      s  ON n.status = s.id
             {$where}
             ORDER BY DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y'), n.folio"
        )->getResultArray();

        $metodosSinRef = ['transferencia', 'deposito', 'cargo con tarjeta'];

        // Agrupar pagos por folio
        $agrupadas = [];
        foreach ($filas as $row) {
            $f = $row['folio'];
            if (! isset($agrupadas[$f])) {
                $agrupadas[$f] = [
                    'folio'      => $f,
                    'referencia' => $row['referencia'],
                    'fecha'      => $row['fecha'],
                    'cliente'    => $row['cliente'],
                    'vendedor'   => $row['vendedor'],
                    'status'     => $row['status'],
                    'verificado' => $row['verificado'],
                    'pagos'      => [],
                ];
            }
            if ($row['tipopago']) {
                $monto       = $row['total'] != '' ? '$ ' . number_format((float)$row['total'], 2) : '';
                $descLower   = strtolower($row['tipopago']);
                $editableRef = in_array($descLower, $metodosSinRef);

                $agrupadas[$f]['pagos'][] = [
                    'texto'      => $row['tipopago'] . ' / ' . $monto,
                    'tipopago'   => $row['tipopago'],
                    'mn_id'      => $row['mn_id'],
                    'referencia' => $row['mn_referencia'] ?? '',
                    'editable'   => $editableRef,
                ];
                if (in_array($row['tipopago'], ['T.Credito','T.Debito']) && $row['cargos']) {
                    $agrupadas[$f]['pagos'][] = [
                        'texto'      => 'Cargo / $' . number_format((float)$row['cargos'], 2),
                        'tipopago'   => '',
                        'mn_id'      => null,
                        'referencia' => '',
                        'editable'   => false,
                    ];
                }
            }
        }

        return view('admin/caja_corte', [
            'usuario'       => $this->getUsuarioSesion(),
            'fecha'         => $fecha,
            'fechaHasta'    => $fechaHasta,
            'estatus'       => $estatus,
            'tipopago'      => $tipopago,
            'listaEstatus'  => $listaEstatus,
            'listaTipoPago' => $listaTipoPago,
            'notas'         => array_values($agrupadas),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/caja/monto/referencia  —  Guarda referencia de pago (AJAX)
    // ──────────────────────────────────────────────────────────────
    public function guardarReferenciaMontosnotas(): \CodeIgniter\HTTP\ResponseInterface
    {
        $idNotas    = (int)$this->request->getPost('mn_id');      // idNotas de montosnotas
        $idTipoPago = (int)$this->request->getPost('mn_tipo_id'); // idTipoPago de montosnotas
        $folio      = (int)$this->request->getPost('folio');
        $referencia = trim($this->request->getPost('referencia') ?? '');

        $db = \Config\Database::connect();

        // Caso 1: tenemos idNotas + idTipoPago → UPDATE directo
        if ($idNotas > 0 && $idTipoPago > 0) {
            $db->query(
                "UPDATE montosnotas SET referencia = ? WHERE idNotas = ? AND idTipoPago = ?",
                [$referencia, $idNotas, $idTipoPago]
            );
            return $this->response->setJSON(['ok' => true]);
        }

        // Caso 2: fallback por folio (nota sin montosnotas con id)
        if ($folio > 0) {
            $row = $db->query(
                "SELECT mn.idNotas, mn.idTipoPago FROM montosnotas mn
                 INNER JOIN notas_1 n   ON n.Id_Notas_1 = mn.idNotas
                 INNER JOIN tipopago tp ON tp.id = mn.idTipoPago
                 WHERE n.folio = ?
                   AND TRIM(LOWER(tp.descripcion)) IN ('transferencia','deposito','depósito','cargo con tarjeta')
                 LIMIT 1",
                [$folio]
            )->getRowArray();

            if ($row) {
                $db->query(
                    "UPDATE montosnotas SET referencia = ? WHERE idNotas = ? AND idTipoPago = ?",
                    [$referencia, $row['idNotas'], $row['idTipoPago']]
                );
                return $this->response->setJSON(['ok' => true]);
            }

            // Caso 3: no existe montosnotas, crear el registro
            $nota = $db->query(
                "SELECT n.Id_Notas_1, n.total, n.fecha_inicial,
                        (SELECT tp2.id FROM tipopago tp2
                         WHERE TRIM(LOWER(tp2.descripcion)) IN ('transferencia','deposito','depósito','cargo con tarjeta')
                           AND TRIM(LOWER(tp2.descripcion)) = TRIM(LOWER(n.tipoPago))
                         LIMIT 1) AS id_tipo_pago
                 FROM notas_1 n WHERE n.folio = ? LIMIT 1",
                [$folio]
            )->getRowArray();

            if ($nota && $nota['id_tipo_pago']) {
                $db->query(
                    "INSERT INTO montosnotas (idNotas, idTipoPago, monto, referencia, fecha)
                     VALUES (?, ?, ?, ?, ?)",
                    [$nota['Id_Notas_1'], $nota['id_tipo_pago'], $nota['total'], $referencia, $nota['fecha_inicial']]
                );
                return $this->response->setJSON(['ok' => true]);
            }
        }

        return $this->response->setJSON(['ok' => false, 'msg' => 'No se encontró el registro de pago']);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/caja/corte/exportar  —  Exporta XLSX del corte de caja
    // ──────────────────────────────────────────────────────────────
    public function exportarCorteXls(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db       = \Config\Database::connect();
        $fecha      = $this->request->getGet('fecha')       ?? date('d/m/Y');
        $fechaHasta = $this->request->getGet('fecha_hasta') ?? '';
        $estatus    = (int)($this->request->getGet('estatus')  ?? 0);
        $tipopago   = (int)($this->request->getGet('tipopago') ?? 0);

        $where  = "WHERE 1=1";
        $params = [];

        if ($fecha !== '') {
            $dtDesde = \DateTime::createFromFormat('d/m/Y', $fecha);
            $dtHasta = $fechaHasta !== '' ? \DateTime::createFromFormat('d/m/Y', $fechaHasta) : null;

            if ($dtDesde && $dtHasta) {
                $where   .= " AND DATE(n.fecha_inicial) BETWEEN ? AND ?";
                $params[] = $dtDesde->format('Y-m-d');
                $params[] = $dtHasta->format('Y-m-d');
                // Label de rango para la hoja
                $fecha = $fecha . ' - ' . $fechaHasta;
            } else {
                $where   .= " AND DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') = ?";
                $params[] = $fecha;
            }
        }
        if ($estatus > 0) {
            $where .= " AND n.status = ?";
            $params[] = $estatus;
        }
        if ($tipopago > 0) {
            $where .= " AND EXISTS (SELECT 1 FROM montosnotas mn2 WHERE mn2.idNotas = n.Id_Notas_1 AND mn2.idTipoPago = ?)";
            $params[] = $tipopago;
        }

        $rows = $db->query(
            "SELECT n.folio,
                    DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') AS fecha,
                    COALESCE(NULLIF(c.nombre,''), 'PUBLICO GENERAL') AS cliente,
                    COALESCE(u.usuario, '—')       AS vendedor,
                    COALESCE(tp.descripcion, '')   AS tipopago,
                    COALESCE(mn.monto, 0)          AS monto,
                    n.total                        AS total_nota,
                    COALESCE(s.nombre, '—')        AS estatus,
                    COALESCE(mn.referencia, '')    AS referencia
             FROM notas_1 n
             LEFT JOIN clientes    c  ON c.id         = n.idCliente
             LEFT JOIN usuarios    u  ON u.Id          = n.idVendedor
             LEFT JOIN montosnotas mn ON mn.idNotas    = n.Id_Notas_1
             LEFT JOIN tipopago    tp ON tp.id         = mn.idTipoPago
             LEFT JOIN status      s  ON s.id          = n.status
             {$where}
             ORDER BY n.folio ASC",
            $params
        )->getResultArray();

        $content = \App\Libraries\CorteCajaExporter::build($rows, $fecha);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="corte_caja_' . date('dmY') . '.xlsx"')
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
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
    // GET /admin/venta/nueva  —  Paso 1: Nueva venta normal desde admin
    // ──────────────────────────────────────────────────────────────
    public function ventaNueva(): string
    {
        return view('mostrador/venta_stp_1', [
            'usuario'   => $this->getUsuarioSesion(),
            'tipoVenta' => 'normal',
            'base'      => 'admin',
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/venta/nueva  —  Crea la nota y redirige al paso 2
    // ──────────────────────────────────────────────────────────────
    public function ventaNuevaPost(): \CodeIgniter\HTTP\RedirectResponse
    {
        $idCliente  = (int) $this->request->getPost('idCliente');
        $idVendedor = (int) session()->get('user_id');

        if (! $idCliente) {
            return redirect()->to('/admin/venta/nueva')->with('error', 'Debes seleccionar un cliente.');
        }

        $folio = $this->notaModel->siguienteFolio();

        $this->notaModel->insert([
            'idCliente'     => $idCliente,
            'idVendedor'    => $idVendedor,
            'folio'         => $folio,
            'status'        => 3,
            'precioMayoreo' => 0,
        ]);

        $this->usuarioModel->activarBandera($idVendedor);

        return redirect()->to("/admin/venta/{$folio}/productos");
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/venta/mayoreo  —  Paso 1: Nueva venta mayoreo desde admin
    // (precio mayoreo sin importar cantidad de piezas)
    // ──────────────────────────────────────────────────────────────
    public function ventaMayoreo(): string
    {
        return view('mostrador/venta_stp_1', [
            'usuario'   => $this->getUsuarioSesion(),
            'tipoVenta' => 'mayoreo',
            'base'      => 'admin',
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /admin/venta/mayoreo  —  Crea la nota mayoreo y redirige al paso 2
    // ──────────────────────────────────────────────────────────────
    public function ventaMayoreoPost(): \CodeIgniter\HTTP\RedirectResponse
    {
        $idCliente  = (int) $this->request->getPost('idCliente');
        $idVendedor = (int) session()->get('user_id');

        if (! $idCliente) {
            return redirect()->to('/admin/venta/mayoreo')->with('error', 'Debes seleccionar un cliente.');
        }

        $folio = $this->notaModel->siguienteFolio();

        $this->notaModel->insert([
            'idCliente'     => $idCliente,
            'idVendedor'    => $idVendedor,
            'folio'         => $folio,
            'status'        => 3,
            'precioMayoreo' => 1,
        ]);

        $this->usuarioModel->activarBandera($idVendedor);

        // Marcar esta nota como MAYOREO en sesión (precio mayoreo siempre)
        session()->set("nota_{$folio}_tipo", 'mayoreo');

        return redirect()->to("/admin/venta/{$folio}/productos");
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
        $db   = \Config\Database::connect();
        $tiposPago = $db->query("SELECT id, descripcion AS tipo FROM tipopago ORDER BY id ASC")->getResultArray();

        return view('admin/consulta', [
            'usuario'   => $this->getUsuarioSesion(),
            'tipo'      => $tipo,
            'tiposPago' => $tiposPago,
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
                        LEFT JOIN status s ON s.id = n.status
                        LEFT JOIN (
                            SELECT mn.idNotas,
                                   GROUP_CONCAT(DISTINCT tp.descripcion ORDER BY tp.id SEPARATOR '<br>') AS tipos_pago
                            FROM montosnotas mn
                            INNER JOIN tipopago tp ON tp.id = mn.idTipoPago
                            GROUP BY mn.idNotas
                        ) pm_direct ON pm_direct.idNotas = n.Id_Notas_1
                        LEFT JOIN (
                            SELECT nc.referencia AS folio_padre,
                                   GROUP_CONCAT(DISTINCT tp.descripcion ORDER BY tp.id SEPARATOR '<br>') AS tipos_pago
                            FROM notas_1 nc
                            INNER JOIN montosnotas mn ON mn.idNotas = nc.Id_Notas_1
                            INNER JOIN tipopago tp ON tp.id = mn.idTipoPago
                            WHERE COALESCE(nc.referencia, 0) > 0
                              AND nc.status != 3
                            GROUP BY nc.referencia
                        ) pm_child ON pm_child.folio_padre = n.folio";

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
                        COALESCE(pm_direct.tipos_pago, pm_child.tipos_pago, NULLIF(TRIM(n.tipoPago), ''), 'A Crédito') AS tipopago,
                        n.total, n.status AS idstatus,
                        COALESCE(s.nombre, '')    AS status_nombre,
                        n.verificado,
                        COALESCE(n.referencia, 0) AS referencia,
                        n.factura, COALESCE(n.uuid_fiscal, '') AS uuid_fiscal,
                        COALESCE(n.status_facturacion, 0) AS status_facturacion,
                        COALESCE(n.rfc_receptor, '')          AS rfc_receptor,
                        COALESCE(n.razon_social_receptor, '') AS razon_social_receptor,
                        COALESCE(n.cp_receptor, '')           AS cp_receptor,
                        COALESCE(n.uso_cfdi, 'S01')           AS uso_cfdi,
                        COALESCE(n.regimen_fiscal_receptor, '616') AS regimen_fiscal_receptor,
                        COALESCE(n.forma_pago_cfdi, '01')     AS forma_pago_cfdi,
                        (SELECT mn2.idNotas FROM montosnotas mn2
                          INNER JOIN tipopago tp2 ON tp2.id = mn2.idTipoPago
                          WHERE mn2.idNotas = n.Id_Notas_1
                            AND TRIM(LOWER(tp2.descripcion)) IN ('transferencia','deposito','depósito','cargo con tarjeta')
                          LIMIT 1) AS mn_id_ref,
                        (SELECT mn2.idTipoPago FROM montosnotas mn2
                          INNER JOIN tipopago tp2 ON tp2.id = mn2.idTipoPago
                          WHERE mn2.idNotas = n.Id_Notas_1
                            AND TRIM(LOWER(tp2.descripcion)) IN ('transferencia','deposito','depósito','cargo con tarjeta')
                          LIMIT 1) AS mn_tipo_ref,
                        (SELECT COALESCE(mn2.referencia,'') FROM montosnotas mn2
                          INNER JOIN tipopago tp2 ON tp2.id = mn2.idTipoPago
                          WHERE mn2.idNotas = n.Id_Notas_1
                            AND TRIM(LOWER(tp2.descripcion)) IN ('transferencia','deposito','depósito','cargo con tarjeta')
                          LIMIT 1) AS mn_referencia_banco,
                        (SELECT tp2.descripcion FROM montosnotas mn2
                          INNER JOIN tipopago tp2 ON tp2.id = mn2.idTipoPago
                          WHERE mn2.idNotas = n.Id_Notas_1
                            AND TRIM(LOWER(tp2.descripcion)) IN ('transferencia','deposito','depósito','cargo con tarjeta')
                          LIMIT 1) AS tipopago_ref_nombre
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
    // GET /admin/auditoria  —  Vista de auditoría del sistema
    // ──────────────────────────────────────────────────────────────
    public function auditoria(): string
    {
        $db       = \Config\Database::connect();
        $usuarios = $db->query("SELECT Id, nombre FROM usuarios ORDER BY nombre ASC")->getResultArray();
        $acciones = $db->query("SELECT DISTINCT accion FROM audit_log ORDER BY accion ASC")->getResultArray();

        return view('admin/auditoria', [
            'usuario'  => $this->getUsuarioSesion(),
            'usuarios' => $usuarios,
            'acciones' => array_column($acciones, 'accion'),
        ]);
    }

    // GET /admin/auditoria/datatable  —  AJAX server-side para DataTable
    public function auditoriaDatatable(): \CodeIgniter\HTTP\Response
    {
        $db     = \Config\Database::connect();
        $draw   = (int) $this->request->getGet('draw');
        $start  = (int) $this->request->getGet('start');
        $length = (int) $this->request->getGet('length');
        $search = trim($this->request->getGet('search')['value'] ?? '');

        $filtroAccion  = trim($this->request->getGet('accion')     ?? '');
        $filtroUsuario = (int) $this->request->getGet('usuario_id');
        $filtroDesde   = trim($this->request->getGet('desde')      ?? '');
        $filtroHasta   = trim($this->request->getGet('hasta')      ?? '');

        $where  = "WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $where .= " AND (descripcion LIKE ? OR usuario_nombre LIKE ? OR registro_id LIKE ? OR tabla LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($filtroAccion !== '') {
            $where .= " AND accion = ?";
            $params[] = $filtroAccion;
        }
        if ($filtroUsuario > 0) {
            $where .= " AND usuario_id = ?";
            $params[] = $filtroUsuario;
        }
        if ($filtroDesde !== '') {
            $where .= " AND fecha >= ?";
            $params[] = $filtroDesde . ' 00:00:00';
        }
        if ($filtroHasta !== '') {
            $where .= " AND fecha <= ?";
            $params[] = $filtroHasta . ' 23:59:59';
        }

        $total    = (int) $db->query("SELECT COUNT(*) AS c FROM audit_log")->getRow()->c;
        $filtered = (int) $db->query("SELECT COUNT(*) AS c FROM audit_log {$where}", $params)->getRow()->c;

        $rows = $db->query(
            "SELECT id, fecha, usuario_nombre, rol, accion, tabla, registro_id,
                    descripcion, datos_antes, datos_despues
             FROM audit_log {$where}
             ORDER BY id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$length, $start])
        )->getResultArray();

        $rolMap = [0=>'Sistema',1=>'Admin',2=>'Caja',3=>'Mostrador',4=>'G.Ventas'];
        $accionColor = [
            'LOGIN_OK'=>'success','LOGIN_FAIL'=>'danger','LOGOUT'=>'secondary',
            'VENTA_CREADA'=>'primary','VENTA_CERRADA'=>'success','VENTA_CANCELADA'=>'danger',
            'VENTA_REACTIVADA'=>'warning','VENTA_DUPLICADA'=>'info','VENTA_ELIMINADA'=>'dark',
            'VENTA_LIQUIDADA'=>'success','VENTA_VERIFICADA'=>'success',
            'PAGO_REGISTRADO'=>'primary','PAGO_CANCELADO'=>'danger','ANTICIPO_CREADO'=>'info',
            'PRODUCTO_AGREGADO'=>'primary','PRODUCTO_QUITADO'=>'warning',
            'CLIENTE_CREADO'=>'primary','CLIENTE_EDITADO'=>'info',
            'CLIENTE_ELIMINADO'=>'danger','CLIENTE_RESTAURADO'=>'success',
            'USUARIO_CREADO'=>'primary','USUARIO_EDITADO'=>'info',
            'USUARIO_ELIMINADO'=>'danger','USUARIO_RESTAURADO'=>'success',
            'USUARIO_LIBERADO'=>'warning','PASSWORD_CAMBIADO'=>'warning',
            'MENSAJE_GUARDADO'=>'secondary','MENSAJE_ELIMINADO'=>'danger',
            'PRODUCTO_CREADO'=>'primary','PRODUCTO_ACTUALIZADO'=>'info',
            'PRODUCTO_ELIMINADO'=>'danger','STOCK_IMPORTADO'=>'success',
            'PRECIOS_IMPORTADOS'=>'success','PRODUCTOS_IMPORTADOS'=>'success',
        ];

        $data = [];
        foreach ($rows as $r) {
            $color = $accionColor[$r['accion']] ?? 'secondary';
            $rol   = $rolMap[(int)$r['rol']] ?? 'N/A';

            // Tabla legible para el admin
            $tablaMap = [
                'notas_1'        => 'Folio',
                'notas_2'        => 'Productos · Folio',
                'montosnotas'    => 'Pago · Nota',
                'clientes'       => 'Cliente',
                'usuarios'       => 'Usuario',
                'productosyazbek'=> 'Producto',
                'mensajes'       => 'Mensaje',
                'mensajesadmin'  => 'Mensaje',
            ];
            $tablaLegible = $tablaMap[$r['tabla']] ?? ucfirst($r['tabla']);
            $refDisplay   = $tablaLegible . ($r['registro_id'] ? ' <strong>#' . htmlspecialchars($r['registro_id']) . '</strong>' : '');

            $data[] = [
                $r['id'],
                $r['fecha'],
                htmlspecialchars($r['usuario_nombre']) . ' <small class="text-muted">(' . $rol . ')</small>',
                '<span class="badge badge-' . $color . '">' . $r['accion'] . '</span>',
                $refDisplay,
                htmlspecialchars($r['descripcion']),
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // MÓDULO DE FACTURACIÓN — /admin/facturacion
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/facturacion
     * Página principal del módulo de facturación.
     */
    public function facturacion(): string
    {
        // Detectar si viene desde /caja o /admin para que la vista use la base correcta
        $seg1   = service('uri')->setSilent()->getSegment(1);
        $prefix = ($seg1 === 'caja') ? 'caja' : 'admin';

        return view('admin/facturacion', [
            'usuario'       => $this->getUsuarioSesion(),
            'factUrlPrefix' => $prefix,
        ]);
    }

    /**
     * GET /admin/facturacion/datatable
     * Server-side DataTable con filtros: cliente, folio, desde, hasta, estado.
     * Solo notas cerradas (idstatus 5 = Pagada, 6 = Liquidado), nota padre (referencia=0).
     */
    public function facturacionDatatable(): \CodeIgniter\HTTP\Response
    {
        $draw        = (int) $this->request->getGet('draw');
        $start       = (int) $this->request->getGet('start');
        $length      = (int) $this->request->getGet('length');
        $search      = $this->request->getGet('search')['value'] ?? '';
        $orderColIdx = (int) ($this->request->getGet('order')[0]['column'] ?? 0);
        $orderDir    = $this->request->getGet('order')[0]['dir'] ?? 'desc';

        // Filtros del módulo de facturación
        $filtClienteId      = (int) ($this->request->getGet('clienteId') ?? 0);  // ID exacto vía Select2
        $filtFolio          = trim($this->request->getGet('folio')   ?? '');
        $filtDesde          = trim($this->request->getGet('desde')   ?? '');
        $filtHasta          = trim($this->request->getGet('hasta')   ?? '');
        $filtEstado         = $this->request->getGet('estado') ?? '';  // '', '0', '1', '2'
        $ocultarFacturados  = $this->request->getGet('ocultarFacturados') == '1';

        $cols     = ['n.folio', 'n.fecha_inicial', 'c.nombre', 'u.usuario', 'n.total',
                     'n.uuid_fiscal', 'n.folio'];
        $orderCol = $cols[$orderColIdx] ?? 'n.folio';
        $orderDir = $orderDir === 'asc' ? 'ASC' : 'DESC';

        try {
            $db = \Config\Database::connect();

            // ── Base SQL igual que consultaDatatable ──────────────────
            $baseSql = "FROM notas_1 n
                        LEFT JOIN clientes c ON c.id  = n.idCliente
                        LEFT JOIN usuarios u ON u.Id  = n.idVendedor";

            // ── Condiciones base: solo notas pagadas/liquidadas padre ─
            $whereClauses = [];
            $params       = [];

            $whereClauses[] = "n.status IN (5, 6)";
            $whereClauses[] = "COALESCE(n.referencia, 0) = 0";

            // Total base (solo condiciones de negocio, sin filtros de usuario)
            $baseWhere = "WHERE n.status IN (5, 6) AND COALESCE(n.referencia, 0) = 0";
            $total     = (int) $db->query("SELECT COUNT(*) AS total $baseSql $baseWhere")->getRow()->total;

            // Filtro cliente (ID exacto seleccionado por Select2)
            if ($filtClienteId > 0) {
                $whereClauses[] = "n.idCliente = ?";
                $params[]        = $filtClienteId;
            }

            // Filtro folio (opcional — sin folio muestra todos del cliente)
            if ($filtFolio !== '') {
                $whereClauses[] = "n.folio = ?";
                $params[]        = (int) $filtFolio;
            }

            // Filtro fechas
            if ($filtDesde !== '') {
                $whereClauses[] = "DATE(n.fecha_inicial) >= ?";
                $params[]        = $filtDesde;
            }
            if ($filtHasta !== '') {
                $whereClauses[] = "DATE(n.fecha_inicial) <= ?";
                $params[]        = $filtHasta;
            }

            // Filtro estado facturación
            if ($filtEstado !== '') {
                if ($filtEstado == '1') {
                    // Facturado: tiene UUID
                    $whereClauses[] = "n.uuid_fiscal IS NOT NULL AND n.uuid_fiscal != ''";
                } elseif ($filtEstado == '0') {
                    // No facturado: sin UUID y no pendiente SAT
                    $whereClauses[] = "(n.uuid_fiscal IS NULL OR n.uuid_fiscal = '')";
                    $whereClauses[] = "COALESCE(n.status_facturacion, 0) != 1";
                } elseif ($filtEstado == '2') {
                    // Pendiente SAT: sin UUID pero marcado como pendiente
                    $whereClauses[] = "(n.uuid_fiscal IS NULL OR n.uuid_fiscal = '')";
                    $whereClauses[] = "COALESCE(n.status_facturacion, 0) = 1";
                }
            }

            // Ocultar ya facturados (uuid_fiscal presente o status_facturacion=2)
            if ($ocultarFacturados) {
                $whereClauses[] = "(n.uuid_fiscal IS NULL OR n.uuid_fiscal = '')";
                $whereClauses[] = "COALESCE(n.status_facturacion, 0) != 2";
            }

            // Búsqueda global del DataTable
            if ($search !== '') {
                $s               = '%' . $search . '%';
                $whereClauses[]  = "(c.nombre LIKE ? OR n.folio LIKE ? OR u.usuario LIKE ?)";
                $params[]        = $s;
                $params[]        = $s;
                $params[]        = $s;
            }

            $where    = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";
            $filtered = (int) $db->query(
                "SELECT COUNT(*) AS cnt $baseSql $where", $params
            )->getRow()->cnt;

            $data = $db->query(
                "SELECT n.folio,
                        n.fecha_inicial,
                        COALESCE(c.nombre, '—')  AS cliente,
                        COALESCE(n.idCliente, 0) AS idCliente,
                        COALESCE(u.usuario, '—') AS vendedor,
                        n.total,
                        n.status                              AS idstatus,
                        COALESCE(n.uuid_fiscal, '')           AS uuid_fiscal,
                        COALESCE(n.status_facturacion, 0)     AS status_facturacion,
                        n.factura
                 $baseSql $where
                 ORDER BY $orderCol $orderDir
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
            log_message('error', 'AdminController::facturacionDatatable — ' . $e->getMessage());
            return $this->response->setJSON([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Error al cargar datos: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /admin/facturacion/resumen
     * Devuelve totales (monto + conteo) de notas facturadas y no facturadas.
     */
    public function facturacionResumen(): \CodeIgniter\HTTP\Response
    {
        $db  = \Config\Database::connect();
        $req = $this->request;

        $clienteId = (int) ($req->getGet('clienteId') ?? 0);
        $folio     = trim($req->getGet('folio')   ?? '');
        $desde     = trim($req->getGet('desde')   ?? '');
        $hasta     = trim($req->getGet('hasta')   ?? '');

        $where  = ["n.status IN (5,6)", "COALESCE(n.referencia, 0) = 0"];
        $params = [];

        if ($clienteId > 0)  { $where[] = "n.idCliente = ?"; $params[] = $clienteId; }
        if ($folio     !== '') { $where[] = "n.folio = ?";   $params[] = (int)$folio; }
        if ($desde   !== '') { $where[] = "DATE(n.fecha_inicial) >= ?"; $params[] = $desde; }
        if ($hasta   !== '') { $where[] = "DATE(n.fecha_inicial) <= ?"; $params[] = $hasta; }

        $wc = 'WHERE ' . implode(' AND ', $where);

        $resFacturado = $db->query("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(n.sumaImportes),0) AS monto
            FROM notas_1 n LEFT JOIN clientes c ON c.id = n.idCliente
            $wc AND n.uuid_fiscal IS NOT NULL AND n.uuid_fiscal != ''
        ", $params)->getRowArray();

        $resNoFact = $db->query("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(n.sumaImportes),0) AS monto
            FROM notas_1 n LEFT JOIN clientes c ON c.id = n.idCliente
            $wc AND (n.uuid_fiscal IS NULL OR n.uuid_fiscal = '')
        ", $params)->getRowArray();

        return $this->response->setJSON([
            'facturado_monto'    => round((float)($resFacturado['monto']  ?? 0), 2),
            'facturado_count'    => (int)($resFacturado['cnt']   ?? 0),
            'no_facturado_monto' => round((float)($resNoFact['monto'] ?? 0), 2),
            'no_facturado_count' => (int)($resNoFact['cnt']  ?? 0),
        ]);
    }

    /**
     * GET /admin/facturacion/folios-pendientes
     * Devuelve IDs de folios no facturados (para Factura Global sin selección manual).
     */
    public function facturacionFoliosPendientes(): \CodeIgniter\HTTP\Response
    {
        $db  = \Config\Database::connect();
        $req = $this->request;

        $clienteId = (int) ($req->getGet('clienteId') ?? 0);
        $desde     = trim($req->getGet('desde')   ?? '');
        $hasta     = trim($req->getGet('hasta')   ?? '');

        $where  = [
            "n.status IN (5,6)",
            "COALESCE(n.referencia, 0) = 0",
            "(n.uuid_fiscal IS NULL OR n.uuid_fiscal = '')",
            "COALESCE(n.status_facturacion, 0) != 2",
        ];
        $params = [];

        if ($clienteId > 0) { $where[] = "n.idCliente = ?"; $params[] = $clienteId; }
        if ($desde   !== '') { $where[] = "DATE(n.fecha_inicial) >= ?"; $params[] = $desde; }
        if ($hasta   !== '') { $where[] = "DATE(n.fecha_inicial) <= ?"; $params[] = $hasta; }

        $wc = 'WHERE ' . implode(' AND ', $where);

        $rows = $db->query("
            SELECT n.folio
            FROM notas_1 n LEFT JOIN clientes c ON c.id = n.idCliente
            $wc
            ORDER BY n.folio ASC
            LIMIT 500
        ", $params)->getResultArray();

        return $this->response->setJSON([
            'folios' => array_column($rows, 'folio'),
        ]);
    }

    /**
     * POST /admin/facturacion/consolidada
     * Genera UN solo CFDI con TODOS los productos de los folios enviados.
     * Todos los folios deben pertenecer al mismo cliente y no estar timbrados.
     */
    public function facturacionConsolidada(): \CodeIgniter\HTTP\Response
    {
        $db = \Config\Database::connect();

        // ── Parsear folios ────────────────────────────────────────────
        $foliosRaw = $this->request->getPost('folios');
        $folios    = is_string($foliosRaw)
            ? (json_decode($foliosRaw, true) ?: [])
            : (array)($foliosRaw ?? []);
        $folios = array_values(array_unique(array_filter(array_map('intval', $folios), fn($f) => $f > 0)));

        if (empty($folios)) {
            return $this->response->setJSON(['success' => false, 'message' => 'No se enviaron folios.']);
        }

        // ── Datos SAT del modal ──────────────────────────────────────
        $datosSAT = [
            'rfcReceptor'           => strtoupper(trim($this->request->getPost('rfcReceptor')           ?? '')),
            'razonSocialReceptor'   => strtoupper(trim($this->request->getPost('razonSocialReceptor')   ?? '')),
            'cpReceptor'            => trim($this->request->getPost('cpReceptor')                       ?? ''),
            'usoCFDI'               => trim($this->request->getPost('usoCFDI')                          ?? 'S01'),
            'regimenFiscalReceptor' => trim($this->request->getPost('regimenFiscalReceptor')            ?? '616'),
            'formaPago'             => trim($this->request->getPost('formaPagoCFDI')                    ?? '01'),
            'metodoPago'            => trim($this->request->getPost('metodoPagoCFDI')                   ?? 'PUE'),
        ];

        if (!$datosSAT['rfcReceptor'] || !$datosSAT['razonSocialReceptor'] || !$datosSAT['cpReceptor']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Faltan datos fiscales (RFC, Razón Social o CP).']);
        }

        try {
            $ph = implode(',', array_fill(0, count($folios), '?'));

            // ── Cargar y validar notas ────────────────────────────────
            $notas = $db->query(
                "SELECT folio, idCliente, sumaImportes, total, precioMayoreo, uuid_fiscal,
                        COALESCE(descuento, 0) AS descuento
                 FROM notas_1
                 WHERE folio IN ($ph)
                   AND status IN (5,6)
                   AND COALESCE(referencia,0) = 0",
                $folios
            )->getResultArray();

            if (count($notas) !== count($folios)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Uno o más folios no son válidos o no están pagados.']);
            }

            $clienteIds = array_unique(array_column($notas, 'idCliente'));
            if (count($clienteIds) > 1) {
                return $this->response->setJSON(['success' => false, 'message' => 'Los folios pertenecen a clientes distintos.']);
            }

            $yaFact = array_filter($notas, fn($n) => !empty($n['uuid_fiscal']));
            if (!empty($yaFact)) {
                return $this->response->setJSON(['success' => false,
                    'message' => 'Folios ya facturados: ' . implode(', ', array_column($yaFact, 'folio'))]);
            }

            $idCliente = (int)$clienteIds[0];
            $cliente   = $db->query("SELECT * FROM clientes WHERE id = ? LIMIT 1", [$idCliente])->getRowArray() ?? [];

            // ── Recolectar todos los productos ────────────────────────
            $detalleConsolidado = [];
            foreach ($notas as $nota) {
                $f       = (int)$nota['folio'];
                $detalle = $db->query(
                    "SELECT n2.cantidad,
                            n2.estilo AS sku,
                            CONCAT(COALESCE(p.estilo,''),'-',COALESCE(p.Descripcion_Larga,''),
                                   '-',COALESCE(p.Talla,''),'-',COALESCE(p.Color,'')) AS descripcion,
                            n2.pUnitario, n2.pUnitarioM,
                            (n2.cantidad * n2.pUnitario)  AS importeMenudeo,
                            (n2.cantidad * n2.pUnitarioM) AS importeMayoreo
                     FROM notas_2 n2
                     LEFT JOIN productosyazbek p ON p.sku = n2.estilo
                     WHERE n2.folio = ?
                     ORDER BY n2.Id_Notas_2 ASC",
                    [$f]
                )->getResultArray();

                $storedSuma = (float)($nota['sumaImportes'] ?? 0);
                if ($storedSuma > 0) {
                    $sumMenu = array_sum(array_map(fn($d) => $d['cantidad'] * (float)$d['pUnitario'], $detalle));
                    $sumMay  = array_sum(array_map(fn($d) => $d['cantidad'] * (float)($d['pUnitarioM'] ?: $d['pUnitario']), $detalle));
                    // Usar la suma pre-descuento como referencia para detectar menudeo/mayoreo
                    // correctamente aunque sumaImportes esté guardado como valor post-descuento.
                    $preDescRef = $storedSuma + (float)($nota['descuento'] ?? 0);
                    $esMayoreo = abs($sumMay - $preDescRef) < abs($sumMenu - $preDescRef);
                } else {
                    $esMayoreo = (int)($nota['precioMayoreo'] ?? 0) === 1;
                }

                foreach ($detalle as $linea) {
                    $usarMay = $esMayoreo && !empty($linea['pUnitarioM']) && (float)$linea['pUnitarioM'] > 0;
                    $linea['precio']  = $usarMay ? (float)$linea['pUnitarioM']     : (float)$linea['pUnitario'];
                    $linea['importe'] = $usarMay ? (float)$linea['importeMayoreo'] : (float)$linea['importeMenudeo'];
                    $detalleConsolidado[] = $linea;
                }
            }

            if (empty($detalleConsolidado)) {
                return $this->response->setJSON(['success' => false, 'message' => 'No se encontraron productos en los folios.']);
            }

            // ── Nota virtual con total consolidado ────────────────────
            sort($folios);
            $folioBase       = $folios[0];
            $totalBase       = array_sum(array_map(fn($l) => (float)$l['importe'], $detalleConsolidado));
            $descuentoTotal  = array_sum(array_map(fn($n) => (float)($n['descuento'] ?? 0), $notas));
            $subtotalConDesc = max(0, $totalBase - $descuentoTotal);
            $notaVirtual = [
                'sumaImportes'  => $totalBase,
                'descuento'     => $descuentoTotal,
                'subTotal'      => $subtotalConDesc,
                'total'         => round($subtotalConDesc * 1.16, 2),
                'precioMayoreo' => 0,
                'idCliente'     => $idCliente,
            ];

            // ── Marcar todos como pendiente SAT ──────────────────────
            foreach ($folios as $f) {
                $db->query(
                    "UPDATE notas_1
                     SET factura=1, rfc_receptor=?, razon_social_receptor=?, cp_receptor=?,
                         uso_cfdi=?, regimen_fiscal_receptor=?, forma_pago_cfdi=?,
                         status_facturacion=1
                     WHERE folio=?",
                    [$datosSAT['rfcReceptor'], $datosSAT['razonSocialReceptor'], $datosSAT['cpReceptor'],
                     $datosSAT['usoCFDI'], $datosSAT['regimenFiscalReceptor'], $datosSAT['formaPago'], $f]
                );
            }

            // ── Timbrar con DfactureService ───────────────────────────
            $dfacture  = new \App\Libraries\DfactureService();
            $resultado = $dfacture->timbrarNota($folioBase, $notaVirtual, $detalleConsolidado, $cliente, $datosSAT);

            if (!$resultado['success']) {
                // Revertir estado
                $db->query("UPDATE notas_1 SET status_facturacion=0 WHERE folio IN ($ph)", $folios);
                return $this->response->setJSON(['success' => false, 'message' => $resultado['message'] ?? 'Error al timbrar.']);
            }

            $uuid        = $resultado['uuid'];
            $xmlTimbrado = $resultado['xml'];

            // ── Guardar UUID en TODOS los folios ──────────────────────
            $db->query(
                "UPDATE notas_1
                 SET uuid_fiscal=?, cfdi_xml=?, cfdi_fecha_timbrado=NOW(),
                     cfdi_error=NULL, status_facturacion=2
                 WHERE folio IN ($ph)",
                array_merge([$uuid, $xmlTimbrado], $folios)
            );

            \App\Libraries\AuditService::log(\App\Libraries\AuditService::VENTA_CERRADA, 'notas_1', $folioBase,
                'Factura global consolidada UUID=' . $uuid . ' folios=' . implode(',', $folios));

            // ── Enviar correo ─────────────────────────────────────────
            try {
                $cfgRows    = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
                $cfgMap     = array_column($cfgRows, 'valor', 'clave');
                $xmlDecoded = base64_decode($xmlTimbrado);
                $pdfService = new \App\Libraries\CfdiPdfService();
                $pdfBytes   = $pdfService->generarPDF($xmlDecoded, $cfgMap);
                $emailSvc   = new \App\Libraries\CfdiEmailService();
                $emailSvc->enviar($xmlDecoded, $pdfBytes, $uuid,
                    'Consolidado (' . count($folios) . ' folios)',
                    $cliente['mail'] ?? '', $cliente['nombre'] ?? '',
                    $folios);
            } catch (\Throwable $eEmail) {
                log_message('error', '[facturacionConsolidada] correo: ' . $eEmail->getMessage());
            }

            return $this->response->setJSON([
                'success' => true,
                'uuid'    => $uuid,
                'folios'  => count($folios),
            ]);

        } catch (\Exception $e) {
            log_message('error', 'facturacionConsolidada: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────
    // GET  /admin/cfdi-config         — Ver configuración de facturación
    // POST /admin/cfdi-config/guardar — Guardar cambios
    // ──────────────────────────────────────────────────────────────
    public function cfdiConfig(): string
    {
        $db  = \Config\Database::connect();
        $rows = $db->query("SELECT clave, valor FROM ticket_config WHERE clave LIKE 'cfdi_%' OR clave LIKE 'smtp_%' OR clave = 'empresa_email_facturacion'")->getResultArray();
        $cfg  = array_column($rows, 'valor', 'clave');

        // ¿Existen los archivos CSD?
        $cerPath = APPPATH . 'ThirdParty/csd/csd.cer';
        $keyPath = APPPATH . 'ThirdParty/csd/csd.key';
        $cerInfo = file_exists($cerPath) ? round(filesize($cerPath) / 1024, 1) . ' KB' : null;
        $keyInfo = file_exists($keyPath) ? round(filesize($keyPath) / 1024, 1) . ' KB' : null;

        return view('admin/cfdi_config', [
            'usuario'   => $this->getUsuarioSesion(),
            'cfg'       => $cfg,
            'cerInfo'   => $cerInfo,
            'keyInfo'   => $keyInfo,
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function cfdiConfigGuardar()
    {
        $db      = \Config\Database::connect();
        $request = $this->request;
        $errores = [];

        // ── 1. Credenciales PAC ──────────────────────────────────────
        $claves = [
            'cfdi_pac_usuario'   => $request->getPost('cfdi_pac_usuario'),
            'cfdi_pac_password'  => $request->getPost('cfdi_pac_password'),
            'cfdi_pac_url'       => $request->getPost('cfdi_pac_url'),
            'cfdi_csd_password'  => $request->getPost('cfdi_csd_password'),
            'smtp_host'          => $request->getPost('smtp_host'),
            'smtp_user'          => $request->getPost('smtp_user'),
            'smtp_pass'          => $request->getPost('smtp_pass'),
            'smtp_port'          => $request->getPost('smtp_port'),
            'smtp_crypto'        => $request->getPost('smtp_crypto'),
            'smtp_from_email'    => $request->getPost('smtp_from_email'),
            'smtp_from_name'     => $request->getPost('smtp_from_name'),
            'empresa_email_facturacion' => $request->getPost('empresa_email_facturacion'),
        ];

        foreach ($claves as $clave => $valor) {
            if ($valor === null) continue;
            $db->query(
                "INSERT INTO ticket_config (clave, valor, etiqueta) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                [$clave, trim($valor), $clave]
            );
        }

        // ── 1b. Contraseña CSD → cifrada con AES-256-CBC antes de guardar en BD ──
        $csdPassword = trim((string) $request->getPost('csd_password'));
        if ($csdPassword !== '') {
            $encKey   = hex2bin(getenv('CSD_ENCRYPT_KEY'));
            $iv       = random_bytes(16);
            $cifrado  = openssl_encrypt($csdPassword, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
            $valor    = base64_encode($iv . $cifrado);
            $db->query(
                "INSERT INTO ticket_config (clave, valor, etiqueta) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                ['csd.password', $valor, 'Contraseña CSD']
            );
        }

        // ── 2. Archivo CSD .cer ──────────────────────────────────────
        $cerFile = $request->getFile('csd_cer');
        if ($cerFile && $cerFile->isValid() && !$cerFile->hasMoved()) {
            $ext = strtolower($cerFile->getClientExtension());
            if ($ext === 'cer') {
                $cerFile->move(APPPATH . 'ThirdParty/csd/', 'csd.cer', true);
            } else {
                $errores[] = 'El archivo CER debe tener extensión .cer';
            }
        }

        // ── 3. Archivo CSD .key ──────────────────────────────────────
        $keyFile = $request->getFile('csd_key');
        if ($keyFile && $keyFile->isValid() && !$keyFile->hasMoved()) {
            $ext = strtolower($keyFile->getClientExtension());
            if ($ext === 'key') {
                $keyFile->move(APPPATH . 'ThirdParty/csd/', 'csd.key', true);
            } else {
                $errores[] = 'El archivo KEY debe tener extensión .key';
            }
        }

        if ($errores) {
            session()->setFlashdata('error', implode('<br>', $errores));
        } else {
            session()->setFlashdata('success', 'Configuración de facturación guardada correctamente.');
        }

        return redirect()->to(base_url('admin/cfdi-config'));
    }

    // GET /admin/ticket-config  —  Configuración de textos del ticket
    // ──────────────────────────────────────────────────────────────
    public function ticketConfig(): string
    {
        $db     = \Config\Database::connect();
        $rows   = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
        $config = array_column($rows, 'valor', 'clave');

        return view('admin/ticket_config', [
            'usuario' => $this->getUsuarioSesion(),
            'config'  => $config,
        ]);
    }

    // POST /admin/ticket-config/guardar  —  Guarda los textos del ticket
    public function ticketConfigGuardar(): \CodeIgniter\HTTP\RedirectResponse
    {
        $db     = \Config\Database::connect();
        $claves = [
            'empresa_razon_social', 'empresa_sucursal', 'empresa_ciudad',
            'empresa_telefono',     'empresa_email_web', 'empresa_rfc',
            'empresa_regimen',
            'msg_fiscal', 'msg_cambios', 'msg_quejas', 'msg_privacidad',
        ];

        foreach ($claves as $clave) {
            $valor = trim($this->request->getPost($clave) ?? '');
            $db->query(
                "INSERT INTO ticket_config (clave, valor, etiqueta)
                 VALUES (?, ?, '')
                 ON DUPLICATE KEY UPDATE valor = ?",
                [$clave, $valor, $valor]
            );
        }

        AuditService::log(AuditService::MENSAJE_GUARDADO, 'ticket_config', null,
            "Configuración del ticket actualizada");

        return redirect()->to('/admin/ticket-config')
                         ->with('success', 'Configuración guardada correctamente.');
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: carga config del ticket desde BD (con fallbacks)
    // ──────────────────────────────────────────────────────────────
    private function getTicketConfig(): array
    {
        try {
            $db   = \Config\Database::connect();
            $rows = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
            return array_column($rows, 'valor', 'clave');
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ──────────────────────────────
    // Helper: datos del usuario logueado desde la sesión
    // ──────────────────────────────
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

    // ══════════════════════════════════════════════════════════════
    // RESPALDOS DE BASE DE DATOS
    // ══════════════════════════════════════════════════════════════

    /** GET /admin/backup — Lista los respaldos disponibles */
    public function backup(): string
    {
        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        $archivos = [];
        foreach (glob($backupDir . 'backup_*.sql') as $file) {
            $archivos[] = [
                'nombre' => basename($file),
                'tamano' => filesize($file),
                'fecha'  => date('d/m/Y H:i:s', filemtime($file)),
                'ts'     => filemtime($file),
            ];
        }
        usort($archivos, fn($a, $b) => $b['ts'] - $a['ts']);

        return view('admin/backup', [
            'archivos' => $archivos,
            'usuario'  => $this->getUsuarioSesion(),
        ]);
    }

    /** GET /admin/backup/descargar/{file} — Descarga un respaldo */
    public function backupDescargar(string $filename): void
    {
        $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $filename);
        $path = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($path) || !str_starts_with($filename, 'backup_')) {
            show_404();
        }

        // Nombre amigable: BD-Yazbek-2026-06-11_10-07-16.sql
        // El archivo original es: backup_nissipro_0525_2026-06-11_10-07-16.sql
        $downloadName = $filename;
        if (preg_match('/backup_.+_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql$/', $filename, $m)) {
            $downloadName = 'BD-Yazbek-' . $m[1] . '.sql';
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        readfile($path);
        exit;
    }

    /** POST /admin/backup/eliminar/{file} — Elimina un respaldo */
    public function backupEliminar(string $filename): \CodeIgniter\HTTP\RedirectResponse
    {
        $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $filename);
        $path = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path) && str_starts_with($filename, 'backup_')) {
            unlink($path);
            return redirect()->to('/admin/backup')->with('success', 'Respaldo eliminado.');
        }
        return redirect()->to('/admin/backup')->with('error', 'Archivo no encontrado.');
    }

    /** POST /admin/backup/eliminar-todo — Elimina todos los respaldos */
    public function backupEliminarTodo(): \CodeIgniter\HTTP\RedirectResponse
    {
        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
        $archivos  = glob($backupDir . 'backup_*.sql') ?: [];
        $eliminados = 0;

        foreach ($archivos as $file) {
            if (is_file($file)) {
                unlink($file);
                $eliminados++;
            }
        }

        return redirect()->to('/admin/backup')
            ->with('success', "Se eliminaron {$eliminados} respaldo(s).");
    }

    /** POST /admin/backup/generar — Genera un respaldo manual desde el panel */
    public function backupGenerar(): \CodeIgniter\HTTP\RedirectResponse
    {
        $dbCfg  = config('Database')->default;
        $host   = $dbCfg['hostname'] ?? 'localhost';
        $port   = $dbCfg['port']     ?? 3306;
        $user   = $dbCfg['username'] ?? 'root';
        $pass   = $dbCfg['password'] ?? '';
        $dbname = $dbCfg['database'];

        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        $timestamp  = date('Y-m-d_H-i-s');
        $filename   = "backup_{$dbname}_{$timestamp}.sql";
        $outputPath = $backupDir . $filename;

        // ── Intentar mysqldump primero (local/VPS) ───────────────────
        $mysqldump = $this->findMysqldump();
        $usedPhp   = false;

        if ($mysqldump && function_exists('proc_open')) {
            $args = [$mysqldump, "-h{$host}", "-P{$port}", "-u{$user}"];
            if ($pass !== '') $args[] = "-p{$pass}";
            $skipTables = $this->detectTablasCorrompidas($dbname);
            foreach ($skipTables as $t) {
                $args[] = "--ignore-table={$dbname}.{$t}";
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
                // mysqldump falló — caer en fallback PHP
                $mysqldump = null;
            }
        } else {
            $mysqldump = null; // forzar fallback
        }

        // ── Fallback PHP puro (hosting compartido / Hostinger) ───────
        if ($mysqldump === null) {
            [$ok, $errMsg] = $this->generateBackupPhp($outputPath, $host, (int)$port, $user, $pass, $dbname);
            if (!$ok) {
                if (file_exists($outputPath)) unlink($outputPath);
                return redirect()->to('/admin/backup')
                    ->with('error', 'Error al generar el respaldo: ' . $errMsg);
            }
            $usedPhp = true;
        }

        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            if (file_exists($outputPath)) unlink($outputPath);
            return redirect()->to('/admin/backup')
                ->with('error', 'El respaldo generado está vacío o es inválido.');
        }

        $size   = round(filesize($outputPath) / 1024, 1);
        return redirect()->to('/admin/backup')
            ->with('success', "Respaldo generado: {$filename} ({$size} KB)");
    }

    /**
     * Genera un dump SQL completo usando PDO — sin mysqldump ni exec().
     * Compatible con hosting compartido (Hostinger, cPanel, etc.).
     *
     * @return array{bool, string}  [éxito, mensaje_error]
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
                return [false, 'No se pudo crear el archivo de respaldo en ' . $outputPath];
            }

            // Cabecera
            fwrite($fp, "-- Yazbek Database Backup (PHP native)\n");
            fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "-- Database: {$dbname}\n\n");
            fwrite($fp, "SET NAMES utf8mb4;\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            // Listar tablas con ENGINE (omitir corruptas sin engine)
            $tables = $pdo->query(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = " . $pdo->quote($dbname) . "
                   AND ENGINE IS NOT NULL
                 ORDER BY TABLE_NAME"
            )->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // Estructura
                $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                fwrite($fp, "-- --------------------------------------------------------\n");
                fwrite($fp, "-- Table: `{$table}`\n");
                fwrite($fp, "-- --------------------------------------------------------\n\n");
                fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fp, $createRow[1] . ";\n\n");

                // Datos en lotes de 200 filas
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
                    $values = array_map(
                        fn($v) => $v === null ? 'NULL' : $pdo->quote($v),
                        $row
                    );
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

    /** Detecta tablas sin engine (corruptas/huérfanas) para omitirlas en el respaldo */
    private function detectTablasCorrompidas(string $dbname): array
    {
        try {
            $db   = \Config\Database::connect();
            $rows = $db->query(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND ENGINE IS NULL",
                [$dbname]
            )->getResultArray();
            return array_column($rows, 'TABLE_NAME');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Busca mysqldump en rutas habituales de XAMPP y MySQL */
    private function findMysqldump(): ?string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
        ];
        foreach ($candidates as $p) {
            if (file_exists($p)) return $p;
        }
        // Intentar localizar con PATH
        if (function_exists('exec')) {
            exec('where mysqldump 2>NUL', $out, $code);
            if ($code === 0 && !empty($out[0])) return trim($out[0]);
            exec('which mysqldump 2>/dev/null', $out2, $code2);
            if ($code2 === 0 && !empty($out2[0])) return trim($out2[0]);
        }
        return null;
    }
}
