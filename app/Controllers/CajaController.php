<?php

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Models\MensajeAdminModel;
use App\Models\NotaDetalleModel;
use App\Models\NotaModel;
use App\Models\UsuarioModel;

/**
 * CajaController
 *
 * Módulo de Caja (acceso = 2).
 *
 * Migrado desde:
 *   caja/index.php       → index
 *   caja/caja.php        → caja
 *   caja/corte.php       → corte / corteDetalle
 *   caja/corte1.php      → corteDetalle
 *   caja/p_folio.php     → porFolio
 *   caja/pagoVerificado.php → pagoVerificado (AJAX)
 *   caja/venta_stp_2.php → ventaStp2 / ventaStp2Post
 *   caja/cancelar_nota.php → cancelarNota
 */
class CajaController extends BaseController
{
    protected UsuarioModel      $usuarioModel;
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
        $this->notaModel        = new NotaModel();
        $this->notaDetalleModel = new NotaDetalleModel();
        $this->mensajeModel     = new MensajeAdminModel();
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja  —  Dashboard del módulo caja
    // Migrado desde: caja/index.php
    // ──────────────────────────────────────────────────────────────
    public function index(): string
    {
        return view('caja/index', [
            'usuario' => $this->getUsuarioSesion(),
            'banner'  => $this->mensajeModel->getBanner(),
            'mensaje' => $this->mensajeModel->getMensaje(),
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/cobrar  —  Lista de notas pendientes de cobro
    // Migrado desde: caja/caja.php
    //
    // Muestra las notas unidas con pagos, clientes, vendedores y tipo de pago.
    // La consulta original tenía una query muy compleja con múltiples JOINs.
    // ──────────────────────────────────────────────────────────────
// ──────────────────────────────────────────────────────────────
    // GET /caja/cobrar  —  Lista de notas pendientes de cobro
    // ──────────────────────────────────────────────────────────────
    public function caja(): string
    {
        $db   = \Config\Database::connect();
        $hoy  = date('Y-m-d');

        $queryStr = "SELECT n.folio,
                            DATE_FORMAT(mn.fecha, '%d/%m/%Y') AS fecha,
                            c.nombre AS cliente,
                            u.usuario AS vendedor,
                            tp.descripcion AS tipopago,
                            (mn.monto + mn.cargos) AS total,
                            s.nombre AS status,
                            n.status AS idstatus,
                            n.Id_Notas_1,
                            n.verificado
                     FROM montosnotas mn
                     INNER JOIN notas_1 n  ON mn.idNotas = n.Id_Notas_1
                     INNER JOIN clientes c ON c.id = n.idCliente
                     INNER JOIN usuarios u ON u.Id = n.idVendedor
                     INNER JOIN tipopago tp ON mn.idTipoPago = tp.id
                     LEFT JOIN  status s   ON n.status = s.id
                     WHERE mn.fecha LIKE ?";
        
        $params = ["{$hoy}%"];

        // CANDADO DE SEGURIDAD: Solo nivel 1 (Admin) ve todo. Los demás ven solo lo suyo.
        if ((int) session()->get('user_acceso') !== 1) {
            $queryStr .= " AND n.idVendedor = ?";
            $params[] = (int) session()->get('user_id');
        }

        $queryStr .= " ORDER BY n.Id_Notas_1 DESC";

        $notas = $db->query($queryStr, $params)->getResultArray();

        return view('caja/caja', [
            'usuario' => $this->getUsuarioSesion(),
            'notas'   => $notas,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/folio/:folio  —  Detalle de una nota por folio
    // Migrado desde: caja/p_folio.php
    // ──────────────────────────────────────────────────────────────
    public function porFolio(int $folio): string
    {
        $nota    = $this->notaModel->getPorFolio($folio);
        $detalle = $this->notaDetalleModel->getPorFolio($folio);

        $db      = \Config\Database::connect();
        $pagos   = $db->query(
            "SELECT mn.*, tp.descripcion
             FROM montosnotas mn
             INNER JOIN tipopago tp ON mn.idTipoPago = tp.id
             WHERE mn.idNotas = ?",
            [$nota['Id_Notas_1'] ?? 0]
        )->getResultArray();

        return view('caja/folio', [
            'usuario' => $this->getUsuarioSesion(),
            'nota'    => $nota,
            'detalle' => $detalle,
            'pagos'   => $pagos,
            'folio'   => $folio,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/pago/verificado/:folio  —  Confirma el pago de una nota
    // Migrado desde: caja/pagoVerificado.php
    // ──────────────────────────────────────────────────────────────
    public function pagoVerificado(int $folio): \CodeIgniter\HTTP\ResponseInterface
    {
        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Nota no encontrada.']);
        }

        $db = \Config\Database::connect();

        // Un folio hijo (referencia > 0) es un abono; el vendedor decide si es
        // un anticipo (parcial) o si liquida la nota al registrarlo (ver
        // MostradorController::abonoPost) — se respeta lo que dejó guardado en
        // notas_1.anticipo en vez de asumir siempre "anticipo".
        $referenciaPadre = (int) ($nota['referencia'] ?? 0);
        $esAnticipo      = $referenciaPadre > 0 ? (int) ($nota['anticipo'] ?? 1) : 0;

        // "Verificar Pago"/"Recibir Pago" cubre dos casos:
        //  1) Ya existe un pago registrado en montosnotas (p. ej. una transferencia
        //     pendiente de confirmar) — aquí solo se confirma, sin tocar el monto.
        //  2) La nota no tiene ningún pago aún (recién finalizada por el vendedor,
        //     status=2): se registra el pago ahora mismo usando el tipo de pago que
        //     el vendedor eligió al finalizar (notas_1.tipoPago) y el total de la
        //     nota — caja solo confirma que recibió el dinero.
        $tienePago = $db->query(
            "SELECT 1 FROM montosnotas WHERE idNotas = ? LIMIT 1",
            [$nota['Id_Notas_1']]
        )->getRowArray();

        if (! $tienePago) {
            // Nota recién finalizada por el vendedor con UN solo método: el
            // tipo de pago quedó en notas_1.tipoPago. Si el vendedor eligió
            // varias formas de pago, esas filas ya se insertaron en
            // montosnotas al finalizar (ver MostradorController::ventaStp3Post)
            // y $tienePago habría sido true — este bloque no se ejecuta.
            $idTipoPago = (int) ($nota['tipoPago'] ?? 0);
            if ($idTipoPago < 1) {
                return $this->response->setJSON([
                    'ok'      => false,
                    'mensaje' => "El folio #{$folio} no tiene tipo de pago asignado por el vendedor. Usa \"Registrar Pago\" para capturarlo manualmente.",
                ]);
            }

            $db->query(
                "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, fecha)
                 VALUES (?, ?, ?, 0, ?, ?)",
                [$nota['Id_Notas_1'], $idTipoPago, $nota['total'] ?? 0, $esAnticipo, date('Y-m-d H:i:s')]
            );
            AuditService::log(AuditService::PAGO_REGISTRADO, 'montosnotas', $folio,
                "Pago recibido en caja: folio #{$folio}, tipoPago {$idTipoPago}, monto $" . number_format($nota['total'] ?? 0, 2) . ", anticipo {$esAnticipo}");
        }

        // Actualizar status a 5 (completada/pagada)
        $db->query(
            "UPDATE notas_1 SET status = 5, verificado = 1 WHERE folio = ?",
            [$folio]
        );
        AuditService::log(AuditService::VENTA_VERIFICADA, 'notas_1', $folio,
            "Pago verificado: folio #{$folio}");

        // Si este folio es un abono (hijo) de una nota "Sin Pagar", revisar si con
        // este pago ya se cubrió el total del padre — de ser así, liquidar padre e
        // hijos (status 6) para que la venta aparezca como pagada/facturable.
        $mensaje = "Pago del folio #{$folio} verificado correctamente.";
        if ($referenciaPadre > 0) {
            $padre = $this->notaModel->getPorFolio($referenciaPadre);
            if ($padre && (int)($padre['status'] ?? 0) !== 3) {
                $totalPagado = $this->notaModel->getTotalPagadoAnticipo($referenciaPadre);
                $totalNota   = (float) ($padre['total'] ?? 0);
                if ($totalPagado >= $totalNota - 0.99) {
                    $this->notaModel->liquidarAnticipo($referenciaPadre);
                    AuditService::log(AuditService::VENTA_LIQUIDADA, 'notas_1', $referenciaPadre,
                        "Folio #{$referenciaPadre} liquidado automáticamente al completar sus abonos.");
                    $mensaje .= " La cuenta del folio padre #{$referenciaPadre} ya quedó liquidada.";
                }
            }
        }

        return $this->response->setJSON(['ok' => true, 'mensaje' => $mensaje]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /caja/pago/procesar  —  Procesa un pago y actualiza la nota
    // ──────────────────────────────────────────────────────────────
    public function procesarPago(): \CodeIgniter\HTTP\RedirectResponse
    {
        $folio    = (int)   $this->request->getPost('folio');
        $tipoPago = (int)   $this->request->getPost('tipoPago');
        $monto    = (float) $this->request->getPost('monto');
        $cargos   = (float) $this->request->getPost('cargos');
        $anticipo = $this->request->getPost('anticipo') ? 1 : 0;

        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota) {
            return redirect()->to('/caja')->with('error', 'Nota no encontrada.');
        }

        $db = \Config\Database::connect();

        // Registrar el pago en montosnotas
        $db->query(
            "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, fecha) VALUES (?, ?, ?, ?, ?, ?)",
            [$nota['Id_Notas_1'], $tipoPago, $monto, $cargos, $anticipo, date('Y-m-d H:i:s')]
        );

        // Verificar si la suma de pagos cubre el total de la nota
        $totalPagado = (float) $db->query(
            "SELECT COALESCE(SUM(monto), 0) AS pagado FROM montosnotas WHERE idNotas = ?",
            [$nota['Id_Notas_1']]
        )->getRow()->pagado;

        $totalNota = (float) ($nota['total'] ?? 0);

        if ($totalPagado >= $totalNota) {
            // Totalmente liquidada → Pagada (status 5)
            $this->notaModel->marcarPagada($nota['Id_Notas_1']);
            AuditService::log(AuditService::VENTA_VERIFICADA, 'notas_1', $folio,
                "Nota pagada completamente: folio #{$folio}, total $" . number_format($totalNota, 2));
        }
        // Si no alcanza → dejar el status actual (Abierta/Parcial), no tocar
        AuditService::log(AuditService::PAGO_REGISTRADO, 'montosnotas', $folio,
            "Pago registrado: folio #{$folio}, monto $" . number_format($monto, 2) . ", anticipo {$anticipo}");

        // Si este folio es un abono (hijo) de una nota "Sin Pagar", revisar si con
        // este pago ya se cubrió el total del padre — de ser así, liquidar padre e
        // hijos (status 6) para que la venta aparezca como pagada/facturable.
        $referenciaPadre = (int) ($nota['referencia'] ?? 0);
        if ($referenciaPadre > 0) {
            $padre = $this->notaModel->getPorFolio($referenciaPadre);
            if ($padre && (int)($padre['status'] ?? 0) !== 3) {
                $totalPagadoPadre = $this->notaModel->getTotalPagadoAnticipo($referenciaPadre);
                $totalNotaPadre   = (float) ($padre['total'] ?? 0);
                if ($totalPagadoPadre >= $totalNotaPadre - 0.99) {
                    $this->notaModel->liquidarAnticipo($referenciaPadre);
                    AuditService::log(AuditService::VENTA_LIQUIDADA, 'notas_1', $referenciaPadre,
                        "Folio #{$referenciaPadre} liquidado automáticamente al completar sus abonos.");
                }
            }
        }

        return redirect()->to('/caja/consulta')->with('success', "Pago del folio #{$folio} registrado correctamente.");
    }

    // ──────────────────────────────────────────────────────────────
    // GET|POST /caja/corte  —  Corte de caja
    // Migrado desde: admin/corte2.php  (idéntico a AdminController::cajaCorte)
    //
    // Fecha en formato dd/mm/yyyy — misma lógica que el original PHP.
    // Agrupa múltiples pagos del mismo folio en una sola fila.
    // ──────────────────────────────────────────────────────────────
// ──────────────────────────────────────────────────────────────
    // GET|POST /caja/corte  —  Corte de caja
    // ──────────────────────────────────────────────────────────────
    public function corte(): string
    {
        $db = \Config\Database::connect();

        // Filtros: POST tiene precedencia, GET como fallback (para links directos)
        $fecha      = $this->request->getPost('fecha')
                   ?? $this->request->getGet('fecha')
                   ?? date('d/m/Y');
        $fechaHasta = $this->request->getPost('fecha_hasta')
                   ?? $this->request->getGet('fecha_hasta')
                   ?? '';
        $estatus    = (int) ($this->request->getPost('estatus')  ?? $this->request->getGet('estatus')  ?? 0);
        $tipopago   = (int) ($this->request->getPost('tipopago') ?? $this->request->getGet('tipopago') ?? 0);

        if (empty($fecha)) {
            $fecha = date('d/m/Y');
        }

        // ── Catálogos para los selects ─────────────────────────────
        $statusList   = $db->query("SELECT * FROM status ORDER BY Id ASC")->getResultArray();
        $tipoPagoList = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();

        // ── Query principal ──
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
            $where .= " AND s.id = " . (int) $estatus;
        }
        if ($tipopago > 0) {
            $where .= " AND tp.id = " . (int) $tipopago;
        }

        // CANDADO DE SEGURIDAD
        if (!in_array((int) session()->get('user_acceso'), [1, 2])) {
            $where .= " AND n.idVendedor = " . (int) session()->get('user_id');
        }

        $filas = $db->query(
            "SELECT n.folio, n.referencia,
                    DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') AS fecha,
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
             LEFT  JOIN montosnotas mn ON mn.idNotas    = n.Id_Notas_1
             LEFT  JOIN clientes    c  ON n.idCliente   = c.id
             INNER JOIN usuarios    u  ON u.Id          = n.idVendedor
             LEFT  JOIN tipopago    tp ON mn.idTipoPago = tp.id
             LEFT  JOIN status      s  ON n.status      = s.id
             {$where}
             ORDER BY DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y'), n.folio"
        )->getResultArray();

        // Métodos que caja puede capturar referencia después
        $metodosSinRef = ['transferencia', 'deposito', 'cheque', 'cargo con tarjeta', 'tarjeta debito', 'tarjeta credito'];

        // ── Agrupar pagos por folio ──
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
                $monto       = $row['total'] != '' ? '$ ' . number_format((float) $row['total'], 2) : '';
                $descLower   = strtolower($row['tipopago']);
                $editableRef = in_array($descLower, $metodosSinRef);

                $agrupadas[$f]['pagos'][] = [
                    'texto'      => $row['tipopago'] . ' / ' . $monto,
                    'tipopago'   => $row['tipopago'],
                    'mn_id'      => $row['mn_id'],
                    'referencia' => $row['mn_referencia'] ?? '',
                    'editable'   => $editableRef,
                ];
                if (in_array($descLower, ['tarjeta credito', 'tarjeta crédito', 'tarjeta debito', 'tarjeta débito', 't.credito', 't.debito']) && $row['cargos']) {
                    $agrupadas[$f]['pagos'][] = [
                        'texto'    => 'Cargo / $' . number_format((float) $row['cargos'], 2),
                        'tipopago' => '',
                        'mn_id'    => null,
                        'referencia' => '',
                        'editable' => false,
                    ];
                }
            }
        }

        $fechaYmd = '';
        $dt = \DateTime::createFromFormat('d/m/Y', $fecha);
        if ($dt) {
            $fechaYmd = $dt->format('Y-m-d');
        }

        return view('caja/corte', [
            'usuario'      => $this->getUsuarioSesion(),
            'notas'        => array_values($agrupadas),
            'statusList'   => $statusList,
            'tipoPagoList' => $tipoPagoList,
            'fecha'        => $fecha,
            'fechaHasta'   => $fechaHasta,
            'fechaYmd'     => $fechaYmd,
            'estatus'      => $estatus,
            'tipopago'     => $tipopago,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/corte/exportar  —  Descarga XLSX del corte (nuevo formato)
    // ──────────────────────────────────────────────────────────────
    public function exportarCorte(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();

        $fecha      = $this->request->getGet('fecha')       ?? date('d/m/Y');
        $fechaHasta = $this->request->getGet('fecha_hasta') ?? '';
        $estatus    = (int)($this->request->getGet('estatus')  ?? 0);
        $tipopago   = (int)($this->request->getGet('tipopago') ?? 0);

        if (empty($fecha)) { $fecha = date('d/m/Y'); }

        $where  = "WHERE 1=1";
        $params = [];

        if ($fecha !== '') {
            $dtDesde = \DateTime::createFromFormat('d/m/Y', $fecha);
            $dtHasta = $fechaHasta !== '' ? \DateTime::createFromFormat('d/m/Y', $fechaHasta) : null;

            if ($dtDesde && $dtHasta) {
                $where   .= " AND DATE(n.fecha_inicial) BETWEEN ? AND ?";
                $params[] = $dtDesde->format('Y-m-d');
                $params[] = $dtHasta->format('Y-m-d');
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

        // Candado de seguridad
        if (!in_array((int)session()->get('user_acceso'), [1, 2])) {
            $where .= " AND n.idVendedor = ?";
            $params[] = (int)session()->get('user_id');
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
             LEFT  JOIN clientes    c  ON c.id         = n.idCliente
             INNER JOIN usuarios    u  ON u.Id          = n.idVendedor
             LEFT  JOIN montosnotas mn ON mn.idNotas    = n.Id_Notas_1
             LEFT  JOIN tipopago    tp ON tp.id         = mn.idTipoPago
             LEFT  JOIN status      s  ON s.id          = n.status
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

    // GET /caja/corte/detalle
    public function corteDetalle(): string
    {
        return $this->corte();
    }

    // ──────────────────────────────────────────────────────────────
    // POST /caja/monto/referencia  —  Guarda referencia de pago (AJAX)
    // ──────────────────────────────────────────────────────────────
    public function guardarReferenciaMontosnotas(): \CodeIgniter\HTTP\ResponseInterface
    {
        $idNotas    = (int)$this->request->getPost('mn_id');
        $idTipoPago = (int)$this->request->getPost('mn_tipo_id');
        $folio      = (int)$this->request->getPost('folio');
        $referencia = trim($this->request->getPost('referencia') ?? '');

        $db = \Config\Database::connect();

        // Caso 1: tenemos idNotas + idTipoPago → UPDATE si ya existe la fila en
        // montosnotas; si no existe aún (nota de un solo método, todavía sin
        // verificar por caja, sin fila propia), se crea usando el total de la
        // nota — mismo monto que registraría "Verificar Pago" más adelante.
        if ($idNotas > 0 && $idTipoPago > 0) {
            $db->query(
                "UPDATE montosnotas SET referencia = ? WHERE idNotas = ? AND idTipoPago = ?",
                [$referencia, $idNotas, $idTipoPago]
            );
            if ($db->affectedRows() === 0) {
                $nota = $db->query(
                    "SELECT total, referencia AS ref_padre, anticipo FROM notas_1 WHERE Id_Notas_1 = ? LIMIT 1",
                    [$idNotas]
                )->getRowArray();
                if ($nota) {
                    $esAnticipo = (int) ($nota['ref_padre'] ?? 0) > 0 ? (int) ($nota['anticipo'] ?? 1) : 0;
                    $db->query(
                        "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, referencia, fecha)
                         VALUES (?, ?, ?, 0, ?, ?, ?)",
                        [$idNotas, $idTipoPago, $nota['total'] ?? 0, $esAnticipo, $referencia, date('Y-m-d H:i:s')]
                    );
                }
            }
            return $this->response->setJSON(['ok' => true]);
        }

        // Caso 2: fallback por folio (nota sin montosnotas con id)
        if ($folio > 0) {
            $row = $db->query(
                "SELECT mn.idNotas, mn.idTipoPago FROM montosnotas mn
                 INNER JOIN notas_1 n   ON n.Id_Notas_1 = mn.idNotas
                 INNER JOIN tipopago tp ON tp.id = mn.idTipoPago
                 WHERE n.folio = ?
                   AND TRIM(LOWER(tp.descripcion)) IN ('transferencia','deposito','depósito','cheque','cargo con tarjeta','tarjeta debito','tarjeta débito','tarjeta credito','tarjeta crédito')
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

            // Caso 3: no existe montosnotas, crear el registro usando el
            // tipoPago propio de la nota (comparación por ID, no por texto)
            $nota = $db->query(
                "SELECT n.Id_Notas_1, n.total, COALESCE(n.referencia,0) AS ref_padre, n.anticipo,
                        (SELECT tp2.id FROM tipopago tp2
                         WHERE tp2.id = n.tipoPago
                           AND TRIM(LOWER(tp2.descripcion)) IN ('transferencia','deposito','depósito','cheque','cargo con tarjeta','tarjeta debito','tarjeta débito','tarjeta credito','tarjeta crédito')
                         LIMIT 1) AS id_tipo_pago
                 FROM notas_1 n WHERE n.folio = ? LIMIT 1",
                [$folio]
            )->getRowArray();

            if ($nota && $nota['id_tipo_pago']) {
                $esAnticipo = (int) ($nota['ref_padre'] ?? 0) > 0 ? (int) ($nota['anticipo'] ?? 1) : 0;
                $db->query(
                    "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, referencia, fecha)
                     VALUES (?, ?, ?, 0, ?, ?, ?)",
                    [$nota['Id_Notas_1'], $nota['id_tipo_pago'], $nota['total'] ?? 0, $esAnticipo, $referencia, date('Y-m-d H:i:s')]
                );
                return $this->response->setJSON(['ok' => true]);
            }
        }

        return $this->response->setJSON(['ok' => false, 'msg' => 'No se encontró el registro de pago']);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /caja/monto/referencias-pendientes — Lista TODAS las formas de
    // pago de un folio (no solo las que técnicamente requieren referencia),
    // con su monto y su referencia actual, para que caja vea el desglose
    // completo y pueda capturar/editar la referencia de cualquiera.
    // ──────────────────────────────────────────────────────────────
    public function referenciasPendientes(): \CodeIgniter\HTTP\ResponseInterface
    {
        $folio = (int) $this->request->getPost('folio');
        $db    = \Config\Database::connect();

        $items = $db->query(
            "SELECT mn.idNotas, mn.idTipoPago, tp.descripcion AS tipo,
                    COALESCE(MAX(mn.referencia), '') AS referencia,
                    SUM(mn.monto) AS monto
             FROM montosnotas mn
             INNER JOIN notas_1 n   ON n.Id_Notas_1 = mn.idNotas
             INNER JOIN tipopago tp ON tp.id = mn.idTipoPago
             WHERE n.folio = ?
               AND TRIM(LOWER(tp.descripcion)) IN ('transferencia','deposito','depósito','cheque','cargo con tarjeta','tarjeta debito','tarjeta débito','tarjeta credito','tarjeta crédito')
             GROUP BY mn.idTipoPago, tp.descripcion
             ORDER BY tp.descripcion ASC",
            [$folio]
        )->getResultArray();

        // Si la nota tiene un solo método (notas_1.tipoPago) y AÚN no la
        // verifica caja, todavía no existe su fila en montosnotas — se
        // incluye igual como "pendiente" para poder capturar la referencia
        // desde ahora (se crea la fila hasta que se guarda la referencia).
        if (empty($items)) {
            $nota = $db->query(
                "SELECT n.Id_Notas_1, n.total, n.tipoPago, tp.descripcion AS tipo
                 FROM notas_1 n
                 LEFT JOIN tipopago tp ON tp.id = n.tipoPago
                 WHERE n.folio = ? AND n.status IN (1,2)
                 LIMIT 1",
                [$folio]
            )->getRowArray();

            if ($nota && $nota['tipoPago'] && $nota['tipo']
                && in_array(trim(mb_strtolower($nota['tipo'])), ['transferencia','deposito','depósito','cheque','cargo con tarjeta','tarjeta debito','tarjeta débito','tarjeta credito','tarjeta crédito'], true)) {
                $items[] = [
                    'idNotas'    => $nota['Id_Notas_1'],
                    'idTipoPago' => $nota['tipoPago'],
                    'tipo'       => $nota['tipo'],
                    'referencia' => '',
                    'monto'      => $nota['total'],
                ];
            }
        }

        return $this->response->setJSON(['ok' => true, 'items' => $items]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET  /caja/reportediario         — Vista Reporte Diario Caja
    // POST /caja/reportediario         — Exporta xlsx (rango fechas)
    // GET  /caja/reportediario/dia     — Exporta xlsx (hoy)
    // ──────────────────────────────────────────────────────────────
    public function reporteDiarioPage(): string
    {
        return view('caja/reportediario', [
            'usuario' => $this->getUsuarioSesion(),
        ]);
    }

    public function reporteDiario(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db     = \Config\Database::connect();
        $fecha1 = $this->request->getPost('fecha1') ?? date('Y-m-d');
        $h1     = str_pad($this->request->getPost('horas')    ?? '0',  2, '0', STR_PAD_LEFT);
        $m1     = str_pad($this->request->getPost('minutos')  ?? '0',  2, '0', STR_PAD_LEFT);
        $s1     = str_pad($this->request->getPost('segundos') ?? '0',  2, '0', STR_PAD_LEFT);
        $fecha2 = $this->request->getPost('fecha2') ?? date('Y-m-d');
        $h2     = str_pad($this->request->getPost('horas2')   ?? '23', 2, '0', STR_PAD_LEFT);
        $m2     = str_pad($this->request->getPost('minutos2') ?? '59', 2, '0', STR_PAD_LEFT);
        $s2     = str_pad($this->request->getPost('segundos2')  ?? '59', 2, '0', STR_PAD_LEFT);

        return $this->exportarReporteDiarioCajaXls($db, $fecha1, $h1, $m1, $s1, $fecha2, $h2, $m2, $s2);
    }

    public function reporteDiarioDia(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db  = \Config\Database::connect();
        $hoy = date('Y-m-d');
        return $this->exportarReporteDiarioCajaXls($db, $hoy, '00', '00', '00', $hoy, '23', '59', '59');
    }

    private function exportarReporteDiarioCajaXls($db, string $fecha1, string $h1, string $m1, string $s1,
                                                   string $fecha2, string $h2, string $m2, string $s2): \CodeIgniter\HTTP\ResponseInterface
    {
        $desde = "{$fecha1} {$h1}:{$m1}:{$s1}";
        $hasta = "{$fecha2} {$h2}:{$m2}:{$s2}";

        $where  = "WHERE n.fecha_inicial >= ? AND n.fecha_inicial <= ? AND n.status != 3";
        $params = [$desde, $hasta];

        // Candado de seguridad por vendedor
        if (!in_array((int)session()->get('user_acceso'), [1, 2])) {
            $where .= " AND n.idVendedor = ?";
            $params[] = (int)session()->get('user_id');
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

        $fechaLabel = date('d/m/Y', strtotime($fecha1)) . ' — ' . date('d/m/Y', strtotime($fecha2));
        $content    = \App\Libraries\CorteCajaExporter::build($rows, $fechaLabel);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="reporte_diario_' . date('dmY') . '.xlsx"')
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/cancelar/:folio  —  Cancela una nota desde caja
    // Migrado desde: caja/cancelar_nota.php
    // ──────────────────────────────────────────────────────────────
    public function cancelarNota(int $folio): \CodeIgniter\HTTP\RedirectResponse
    {
        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota) {
            return redirect()->to('/caja/consulta')->with('error', 'Nota no encontrada.');
        }

        // Solo se puede cancelar si no está ya pagada (5) o liquidada (6)
        if (in_array((int)$nota['status'], [5, 6])) {
            return redirect()->to('/caja/consulta')
                             ->with('error', 'No se puede cancelar una nota ya pagada o liquidada.');
        }

        $this->notaModel->cambiarStatus($nota['Id_Notas_1'], 3);
        AuditService::log(AuditService::VENTA_CANCELADA, 'notas_1', $folio,
            "Nota cancelada: folio #{$folio}");

        return redirect()->to('/caja/consulta')->with('success', "Nota #{$folio} cancelada.");
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/venta/:folio  —  Vista de nota para cobro en caja
    // Migrado desde: caja/venta_stp_2.php
    // ──────────────────────────────────────────────────────────────
    public function ventaStp2(int $folio): string
    {
        $db = \Config\Database::connect();

        // JOIN con clientes y usuarios para traer nombre y vendedor
        $nota = $db->query(
            "SELECT n.*,
                    COALESCE(c.nombre, '—') AS cliente,
                    COALESCE(u.usuario, '—') AS vendedor
             FROM notas_1 n
             LEFT JOIN clientes c ON c.id = n.idCliente
             LEFT JOIN usuarios u ON u.Id = n.idVendedor
             WHERE n.folio = ?
             LIMIT 1",
            [$folio]
        )->getRowArray();

        if (! $nota) {
            return redirect()->to('/caja/consulta')->with('error', 'Folio no encontrado.');
        }

        $detalle     = $this->notaDetalleModel->getPorFolio($folio);
        $totalPiezas = $this->notaDetalleModel->totalPiezas($folio);
        $tipoPagos   = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();
        $pagos       = $db->query(
            "SELECT mn.*, tp.descripcion FROM montosnotas mn
             INNER JOIN tipopago tp ON mn.idTipoPago = tp.id
             WHERE mn.idNotas = ?",
            [$nota['Id_Notas_1'] ?? 0]
        )->getResultArray();

        return view('caja/venta_stp_2', [
            'usuario'     => $this->getUsuarioSesion(),
            'nota'        => $nota,
            'folio'       => $folio,
            'detalle'     => $detalle,
            'totalPiezas' => $totalPiezas,
            'tipoPagos'   => $tipoPagos,
            'pagos'       => $pagos,
        ]);
    }

    // POST /caja/venta/:folio
    public function ventaStp2Post(int $folio): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to("/caja/folio/{$folio}");
    }

    // ──────────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────

    // ──────────────────────────────────────────────────────────────
    // POST /caja/folio/detalle  —  AJAX: HTML completo del folio (modal)
    // Idéntico a AdminController::cajaAjax() pero accesible para rol caja
    // ──────────────────────────────────────────────────────────────
    public function folioDetalleAjax(): string
    {
        $folio = (int) $this->request->getPost('folio');

        try {
            $db = \Config\Database::connect();

            $nota = $db->query(
                "SELECT n.Id_Notas_1, n.fecha_inicial,
                        c.nombre  AS NombreCliente,
                        u.usuario AS vendedor,
                        n.folio, n.referencia, n.anticipo, n.verificado, n.factura, n.descuento,
                        n.status AS statusId,
                        n.sumaImportes, n.subTotal, n.tipoPago,
                        tpVendedor.descripcion AS tipoPagoNombre,
                        n.cargoTarjeta, n.subTotal2, n.iva, n.total,
                        n.tipoImpresion, n.cargoPorImpresion,
                        n.montoTCTD, n.totalPiezas, n.precioMayoreo,
                        n.fecha_edicion
                 FROM notas_1 n
                 LEFT JOIN clientes c ON n.idCliente = c.id
                 LEFT JOIN usuarios u ON u.Id        = n.idVendedor
                 LEFT JOIN tipopago tpVendedor ON tpVendedor.id = n.tipoPago
                 WHERE n.folio = ?
                 ORDER BY n.Id_Notas_1 DESC
                 LIMIT 1",
                [$folio]
            )->getRowArray();

            if (! $nota) {
                return '<p class="text-danger">FOLIO NO ENCONTRADO</p>';
            }

            $pagos = $db->query(
                "SELECT m.idTipoPago, t.descripcion AS tipopago,
                        m.monto, m.cargos AS cargo, m.anticipo,
                        n2.folio AS folio_pago,
                        n2.status AS folio_status,
                        COALESCE(n2.referencia, 0) AS folio_pago_referencia
                 FROM notas_1 n2
                 INNER JOIN montosnotas m ON m.idNotas = n2.Id_Notas_1
                 INNER JOIN tipopago t ON t.id = m.idTipoPago
                 WHERE n2.folio = ? OR n2.referencia = ?
                 ORDER BY n2.folio ASC",
                [$folio, $folio]
            )->getResultArray();

            $detalles = $db->query(
                "SELECT n.cantidad,
                        n.estilo AS sku,
                        CONCAT(p.estilo,'-',p.Descripcion_Larga,'-',p.Talla,'-',p.Color) AS descripcion,
                        COALESCE(NULLIF(n.pUnitario,  0), p.pMenudeo, 0) AS pMenudeo,
                        COALESCE(NULLIF(n.pUnitarioM, 0), p.pMayoreo, n.pUnitario, 0) AS pMayoreo
                 FROM notas_2 n
                 LEFT JOIN productosyazbek p ON p.sku = n.estilo
                 WHERE n.folio = ?",
                [$folio]
            )->getResultArray();

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
            log_message('error', 'folioDetalleAjax folio=' . $folio . ': ' . $e->getMessage());
            return '<p class="text-danger">Error al consultar el folio.</p>';
        }

        $letras = '';
        try {
            $letras = mb_strtoupper(\App\Libraries\AifLibNumber::toCurrency($nota['total']));
        } catch (\Throwable $e) {}

        $total = $nota['total'] ?? 0;
        $statusMap = [1=>'Abierta',2=>'En proceso',3=>'Cancelada',4=>'Anticipo',5=>'Pagada',6=>'Pagado'];
        $verificado   = $nota['verificado'] ?? '';
        $statusId     = (int)($nota['statusId'] ?? 0);
        $statusNombre = $statusMap[$statusId] ?? 'Desconocido';
        $esLiquidado  = ($verificado === 'Pagado' || $statusId === 6);
        $headerStatus = $esLiquidado ? 'Pagado' : $statusNombre;

        // Encabezado
        $html  = '<div style="overflow-x:auto"><table style="width:100%"><tr>';
        $html .= '<td><strong>Fecha:&nbsp;</strong></td>';
        $html .= '<td>' . date('Y-m-d h:i A', strtotime($nota['fecha_inicial'])) . '</td>';
        $html .= '<td>&nbsp;</td><td><strong>Nombre Cliente:&nbsp;</strong></td>';
        $html .= '<td>' . esc($nota['NombreCliente']) . '</td>';
        $html .= '</tr><tr>';
        $html .= '<td><strong>Estatus:&nbsp;</strong></td><td>' . esc($headerStatus) . '</td>';
        $html .= '<td>&nbsp;</td><td><strong>Folio:&nbsp;</strong></td>';
        $html .= '<td>' . $nota['folio'];
        $referenciaPadre = (int)($nota['referencia'] ?? 0);
        if ($referenciaPadre > 0) {
            $html .= ' &nbsp; <strong>Referencia:&nbsp;</strong>' . $referenciaPadre
                   . ' &nbsp; <span style="background:#fd7e14;color:#fff;font-size:.75em;padding:2px 8px;border-radius:4px;">ANTICIPO</span>';
        }
        $html .= '</td>';
        $html .= '</tr></table></div>';
        if ($referenciaPadre > 0) {
            $notaPadre   = $db->query("SELECT total FROM notas_1 WHERE folio = ? LIMIT 1", [$referenciaPadre])->getRowArray();
            $totalPadre  = (float) ($notaPadre['total'] ?? 0);
            // OJO: $pagos está filtrado por el folio que se está viendo (este
            // hijo), no por el padre — para "lo ya pagado" hay que sumar TODOS
            // los pagos del padre y de TODOS sus hermanos, no solo los de este.
            $pagosPadre = $db->query(
                "SELECT m.monto, n2.status AS folio_status
                 FROM notas_1 n2
                 INNER JOIN montosnotas m ON m.idNotas = n2.Id_Notas_1
                 WHERE n2.folio = ? OR n2.referencia = ?",
                [$referenciaPadre, $referenciaPadre]
            )->getResultArray();
            $pagadoPadre = array_sum(array_map(
                fn($p) => (float) ($p['monto'] ?? 0),
                array_filter($pagosPadre, fn($p) => (int) ($p['folio_status'] ?? 0) !== 3)
            ));
            $restantePadre = max(0, $totalPadre - $pagadoPadre);
            $esAnticipoTxt = ((int) ($nota['anticipo'] ?? 1) === 1) ? 'ANTICIPO (abono)' : 'LIQUIDACIÓN';

            $html .= '<div class="alert alert-warning py-2 mb-3">'
                   . '<strong>Este folio es un ' . $esAnticipoTxt . '</strong> del folio padre #' . $referenciaPadre . '.'
                   . '<br><span style="font-size:1.15em;"><strong>Total de la nota padre:</strong> $' . number_format($totalPadre, 2)
                   . ' &nbsp; <strong>Pagado:</strong> $' . number_format($pagadoPadre, 2)
                   . ' &nbsp; <strong>Restante:</strong> $' . number_format($restantePadre, 2) . '</span>'
                   . '</div>';
        }
        if (! empty($nota['fecha_edicion'])) {
            $html .= '<div class="alert py-2 mb-3" style="background:#f1ecfa;border-color:#6f42c1;color:#4b2e83;">'
                   . '<strong>⚠ Este folio fue editado</strong> (productos y/o forma de pago) el '
                   . date('d/m/Y h:i A', strtotime($nota['fecha_edicion'])) . '.'
                   . '</div>';
        }

        // Tipo de pago elegido por el vendedor al finalizar (solo lectura) —
        // se muestra mientras la nota siga sin ningún pago registrado.
        if ($statusId === 2 && empty($pagos)) {
            $tipoPagoTexto = $nota['tipoPagoNombre'] ?? null;
            $html .= '<div class="alert alert-info py-2 mb-3"><strong>Tipo de pago (elegido por el vendedor):</strong> '
                    . ($tipoPagoTexto ? esc($tipoPagoTexto) : '<span class="text-danger">No asignado</span>')
                    . '</div>';
        }

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
        $html .= '<tr><td colspan="3" class="text-right"><strong>Tipo bordado/impresión</strong></td><td>' . $otros . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo extra</strong></td><td>$&nbsp;' . number_format($nota['cargoPorImpresion'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Descuento</strong></td><td>$&nbsp;' . number_format($nota['descuento'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>SubTotal</strong></td><td>$&nbsp;' . number_format($nota['subTotal'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo TC/TD</strong></td><td>$&nbsp;' . number_format($nota['montoTCTD'] ?? $nota['cargoTarjeta'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>IVA</strong></td><td>$&nbsp;' . number_format($nota['iva'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>SubTotal2</strong></td><td>$&nbsp;' . number_format($nota['subTotal2'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Total</strong></td><td>$&nbsp;' . number_format($total, 2) . '</td></tr>';

        if ($letras) {
            $html .= '<tr><td colspan="4" align="left"><strong>Cantidad con letra( ' . esc($letras) . ')</strong></td></tr>';
        }

        // Pagos con folios hijos
        $hayFoliosHijos = !empty(array_filter($pagos, fn($p) => (int)($p['folio_pago'] ?? 0) !== $folio));
        foreach ($pagos as $p) {
            $esCancelado = (int)($p['folio_status'] ?? 0) === 3;
            $rowStyle    = $esCancelado ? 'opacity:.45;text-decoration:line-through;' : '';
            $html .= '<tr style="' . $rowStyle . '"><td colspan="4" class="text-right">';
            if ($hayFoliosHijos && (int)($p['folio_pago'] ?? 0) !== $folio) {
                $html .= '<strong>Folio #' . $p['folio_pago'] . '</strong>';
                if ($esCancelado) {
                    $html .= ' <span style="background:#dc3545;color:#fff;font-size:.7em;padding:1px 6px;border-radius:4px;text-decoration:none;vertical-align:middle;">CANCELADO</span>';
                }
                $html .= ' — ';
            }
            $html .= '<strong>Forma de Pago:</strong> ' . esc($p['tipopago']);
            $html .= ' <strong>Monto:</strong> $&nbsp;' . number_format($p['monto'] ?? 0, 2);
            if (in_array($p['idTipoPago'] ?? 0, [2, 3])) {
                $html .= ' <strong>Cargo:</strong> $&nbsp;' . number_format($p['cargo'] ?? 0, 2);
            }
            if (($p['anticipo'] ?? 0) == 1) {
                $html .= ' <strong style="color:#e67e22;">Es anticipo:</strong> Si';
            } elseif ((int) ($p['folio_pago_referencia'] ?? 0) > 0) {
                $html .= ' <strong style="color:#28a745;">Es liquidación:</strong> Si';
            }
            $html .= '</td></tr>';
        }

        // Estatus final
        $pagosActivos = array_filter($pagos, fn($p) => (int)($p['folio_status'] ?? 0) !== 3);
        $sumPagado    = array_sum(array_column($pagosActivos, 'monto'));
        $hayAnticipo  = !empty(array_filter($pagosActivos, fn($p) => ($p['anticipo'] ?? 0) == 1));
        if ($esLiquidado) {
            $displayStatus = 'Pagado';
        } elseif ($hayAnticipo && $sumPagado < ($nota['total'] ?? 0)) {
            $displayStatus = 'Abierta';
        } else {
            $displayStatus = $statusNombre;
        }

        $html .= '<tr><td colspan="2" align="left"><strong>Estatus:</strong></td>';
        $html .= '<td colspan="2" id="estatusPago">' . esc($displayStatus) . '</td></tr>';
        // Consultar status_facturacion
        $sfRow2     = $db->query(
            "SELECT COALESCE(status_facturacion,0) AS sf, COALESCE(uuid_fiscal,'') AS uuid FROM notas_1 WHERE folio = ? LIMIT 1",
            [$folio]
        )->getRowArray();
        $statusFact2 = (int)($sfRow2['sf']   ?? 0);
        $uuidFact2   =       $sfRow2['uuid'] ?? '';

        $factLabel = !empty($uuidFact2)
            ? 'Facturado ✓'
            : (($nota['factura'] ?? 0) == 1 ? 'Pendiente por facturar' : 'No requerida');

        $html .= '<tr><td colspan="2" align="left"><strong>Factura:</strong></td>';
        $html .= '<td colspan="2">' . $factLabel . '</td></tr>';

        // Botones caja: Verificar Pago y Cancelar
        $html .= '<tr><td colspan="4" class="text-right pt-3">';
        $html .= '<input type="hidden" id="folio_input_caja_modal" value="' . $nota['folio'] . '" />';
        if ($statusId !== 3 && $statusId !== 5 && !$esLiquidado) {
            $html .= '<button type="button" class="btn btn-danger mr-2" onclick="cajaModalCancelar()">Cancelar Nota</button>';
            if ($statusId === 2) {
                $btnLabelPago = 'Verificar Pago';
                $html .= '<button type="button" class="btn btn-success" onclick="cajaModalVerificar()">' . $btnLabelPago . '</button>';
            }
        }
        $html .= '</td></tr>';

        // Ver Ticket: disponible siempre, incluyendo notas canceladas
        $html .= '<tr><td colspan="4" class="text-right pt-2">'
               . '<button type="button" class="btn btn-outline-dark" onclick="cajaVerTicket(' . $folio . ')">'
               . '<i class="simple-icon-printer mr-1"></i> Ver Ticket</button>'
               . '</td></tr>';

        // Botón Facturar en modal (solo folio PADRE, pagado/liquidado, no cancelado, no ya facturado)
        // Los folios hijos (abonos) no tienen productos propios — la factura siempre
        // se solicita sobre el padre, una sola vez.
        if ($referenciaPadre === 0 && $statusId !== 3 && ($statusId === 5 || $esLiquidado) && empty($uuidFact2)) {
            $btnLabel2 = $statusFact2 === 1 ? 'Facturar' : 'Solicitar Factura';
            $html .= '<tr><td colspan="4" class="text-right pt-2">'
                   . '<button type="button" class="btn btn-primary" '
                   . 'onclick="$(\'#modalVerFolioCaja\').modal(\'hide\'); cajaAbrirModalFactura(' . $folio . ');">'
                   . '<i class=\'simple-icon-doc mr-1\'></i> ' . $btnLabel2 . '</button>'
                   . '</td></tr>';
        }

        $html .= '</tbody></table></div>';
        $html .= '<script>function blurFnCj(){var p=parseFloat(document.getElementById("pagar2_cj").value)||0;var i=parseFloat(document.getElementById("importe_cj").value)||0;var r=document.getElementById("resultado_cj");if(r)r.value=(i-p).toFixed(2);}</script>';

        return $html;
    }

    // GET /caja/cobrar/ajax/:folio  —  AJAX: datos de un folio para Verificar Caja
    // ──────────────────────────────────────────────────────────────
    public function cobrarFolioAjax(int $folio): \CodeIgniter\HTTP\ResponseInterface
    {
        require_once APPPATH . 'Helpers/CantidadLetraHelper.php';
        $db = \Config\Database::connect();

        $nota = $db->query(
            "SELECT n.Id_Notas_1, n.folio, n.fecha_inicial, n.total,
                    n.status, n.verificado, n.descuento, n.subTotal, n.iva,
                    n.cargoTarjeta, n.subTotal2, n.tipoImpresion, n.cargoPorImpresion,
                    n.factura, n.totalPiezas, n.idCliente, n.idVendedor,
                    c.nombre AS cliente_nombre, c.direccion, c.telefono, c.mail,
                    u.usuario AS vendedor_nombre,
                    s.nombre AS status_nombre
             FROM notas_1 n
             LEFT JOIN clientes  c ON c.id = n.idCliente
             LEFT JOIN usuarios  u ON u.Id = n.idVendedor
             LEFT JOIN status    s ON s.id = n.status
             WHERE n.folio = ?
             LIMIT 1",
            [$folio]
        )->getRowArray();

        if (! $nota) {
            return $this->response->setJSON(['error' => 'Folio no encontrado']);
        }

        // Formatear fecha igual que el original: Y-m-d h:i A (12h con AM/PM)
        $nota['fecha_inicial'] = $nota['fecha_inicial']
            ? date('Y-m-d h:i A', strtotime($nota['fecha_inicial']))
            : '';

        // Cantidad en letra (igual que AifLibNumber::toCurrency en el original)
        $total = (float) ($nota['total'] ?? 0);
        $letra = \AifLibNumber::toCurrency((string) $total);
        $nota['cantidadLetra'] = $letra ? mb_strtoupper($letra) : '';

        // Castear a int para evitar bug de string "0"/"1" en JS
        $nota['factura'] = (int) $nota['factura'];
        $nota['status']  = (int) $nota['status'];

        $detalle = $db->query(
            "SELECT nd.cantidad, nd.pUnitario, nd.importe,
                    CONCAT(COALESCE(p.estilo,''), ' - ', COALESCE(p.Descripcion_Larga,''), ' - ', COALESCE(p.Talla,''), ' - ', COALESCE(p.Color,'')) AS descripcion
             FROM notas_2 nd
             LEFT JOIN productosyazbek p ON nd.estilo = p.sku
             WHERE nd.folio = ?",
            [$folio]
        )->getResultArray();

        $pagos = $db->query(
            "SELECT mn.monto, mn.cargos, mn.anticipo, mn.idTipoPago, tp.descripcion AS tipo
             FROM montosnotas mn
             INNER JOIN tipopago tp ON mn.idTipoPago = tp.id
             WHERE mn.idNotas = ?",
            [$nota['Id_Notas_1']]
        )->getResultArray();

        // Castear anticipo a int para evitar bug de string "0"/"1" en JS
        foreach ($pagos as &$pago) {
            $pago['anticipo']    = (int) $pago['anticipo'];
            $pago['idTipoPago']  = (int) $pago['idTipoPago'];
        }
        unset($pago);

        return $this->response->setJSON([
            'nota'    => $nota,
            'detalle' => $detalle,
            'pagos'   => $pagos,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/folio/:folio/datos-fiscales
    // Devuelve datos SAT para pre-llenar el modal de facturación.
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

        $cliente = [];
        if (! empty($nota['idCliente'])) {
            $cliente = $db->query(
                "SELECT RFC, razonSocial, CP, mail FROM clientes WHERE id = ? LIMIT 1",
                [(int)$nota['idCliente']]
            )->getRowArray() ?? [];
        }

        $rfc   = $nota['rfc_receptor']           ?: ($cliente['RFC']         ?? '');
        $rs    = $nota['razon_social_receptor']  ?: ($cliente['razonSocial'] ?? '');
        $cp    = $nota['cp_receptor']            ?: ($cliente['CP']          ?? '');
        $uso   = $nota['uso_cfdi']               ?: 'S01';
        $reg   = $nota['regimen_fiscal_receptor'] ?: '616';

        // Forma de pago: la que domine por monto entre lo realmente cobrado
        // (montosnotas). Si aún no hay pagos registrados, se usa lo guardado
        // en la nota (o Efectivo por default).
        $forma = $this->calcularFormaPagoDominante($folio)
              ?: ($nota['forma_pago_cfdi'] ?: '01');

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

    // ──────────────────────────────────────────────────────────────
    // POST /caja/folio/:folio/previsualizar-factura
    // Calcula conceptos/subtotal/IVA/total SIN timbrar ni guardar nada.
    // Se usa para mostrar la vista previa antes de confirmar el timbrado.
    // ──────────────────────────────────────────────────────────────
    public function previsualizarFactura(int $folio): \CodeIgniter\HTTP\Response
    {
        try {
            $datosModal = $this->leerDatosModalFactura();
            $svc        = new \App\Libraries\FacturacionService();
            $result     = $svc->previsualizar($folio, $datosModal);

            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            log_message('error', "CajaController::previsualizarFactura folio={$folio}: " . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /caja/folio/:folio/facturar
    // Genera la factura CFDI para un folio ya cerrado.
    // Solo Caja (acceso=2) y Admin (acceso=1) pueden llamar este endpoint.
    // ──────────────────────────────────────────────────────────────
    public function facturarFolio(int $folio): \CodeIgniter\HTTP\Response
    {
        try {
            $datosModal = $this->leerDatosModalFactura();

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
            log_message('error', "CajaController::facturarFolio folio={$folio}: " . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'mensaje' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Lee del POST los datos fiscales capturados en el modal de
    // facturación (RFC, Razón Social, CP, etc). Devuelve null si el
    // modal no envió nada (Escenario 1: datos ya guardados en notas_1).
    // Compartido por previsualizarFactura() y facturarFolio().
    // ──────────────────────────────────────────────────────────────
    private function leerDatosModalFactura(): ?array
    {
        $req      = $this->request;
        $rfcModal = trim($req->getPost('rfcReceptor') ?? '');
        if ($rfcModal === '') {
            return null;
        }

        return [
            'rfcReceptor'           => $rfcModal,
            'razonSocialReceptor'   => trim($req->getPost('razonSocialReceptor') ?? ''),
            'cpReceptor'            => trim($req->getPost('cpReceptor')          ?? ''),
            'usoCFDI'               => trim($req->getPost('usoCFDI')             ?? 'S01'),
            'regimenFiscalReceptor' => trim($req->getPost('regimenFiscalReceptor') ?? '616'),
            'formaPago'             => trim($req->getPost('formaPagoCFDI')       ?? '01'),
            'metodoPago'            => trim($req->getPost('metodoPagoCFDI')      ?? 'PUE'),
            'observaciones'         => trim($req->getPost('observacionesFactura') ?? ''),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/clientes  —  Lista de clientes (server-side DataTables)
    // ──────────────────────────────────────────────────────────────
    public function clientes(): string
    {
        return view('mostrador/clientes', [
            'usuario'  => $this->getUsuarioSesion(),
            'rutaBase' => 'caja',
        ]);
    }

    // GET /caja/clientes/datatable  —  AJAX server-side
    public function clientesDatatable(): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteModel = new \App\Models\ClienteModel();
        $draw     = (int) $this->request->getGet('draw');
        $start    = (int) $this->request->getGet('start');
        $length   = (int) $this->request->getGet('length');
        $search   = $this->request->getGet('search')['value'] ?? '';
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

    // POST /caja/clientes/crear
    public function crearCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $clienteModel = new \App\Models\ClienteModel();
        $clienteModel->insert([
            'nombre'        => strtoupper(trim($this->request->getPost('nombre') ?? '')),
            'telefono'      => trim($this->request->getPost('telefono') ?? ''),
            'celular'       => trim($this->request->getPost('celular') ?? ''),
            'mail'          => trim($this->request->getPost('mail') ?? ''),
            'RFC'           => strtoupper(trim($this->request->getPost('RFC') ?? '')),
            'regimenFiscal' => trim($this->request->getPost('regimenFiscal') ?? '') ?: null,
            'direccion'     => strtoupper(trim($this->request->getPost('direccion') ?? '')),
            'numero'        => strtoupper(trim($this->request->getPost('numero') ?? '')),
            'CP'            => trim($this->request->getPost('CP') ?? ''),
            'estado'        => strtoupper(trim($this->request->getPost('estado') ?? '')),
            'ciudad'        => strtoupper(trim($this->request->getPost('ciudad') ?? '')),
            'razonSocial'   => strtoupper(trim($this->request->getPost('razonSocial') ?? '')),
            'comoNosConoce' => $this->request->getPost('comoNosConoce'),
            'fechaIngreso'  => date('Y-m-d'),
        ]);
        $nombreCliente = strtoupper(trim($this->request->getPost('nombre') ?? ''));
        AuditService::log(AuditService::CLIENTE_CREADO, 'clientes', null,
            "Cliente creado: {$nombreCliente}");
        return redirect()->to('/caja/clientes')->with('success', 'Cliente registrado correctamente.');
    }

    // POST /caja/clientes/actualizar/:id
    public function actualizarCliente(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $clienteModel = new \App\Models\ClienteModel();
        $clienteModel->update($id, [
            'nombre'        => strtoupper(trim($this->request->getPost('nombre') ?? '')),
            'telefono'      => trim($this->request->getPost('telefono') ?? ''),
            'celular'       => trim($this->request->getPost('celular') ?? ''),
            'mail'          => trim($this->request->getPost('mail') ?? ''),
            'RFC'           => strtoupper(trim($this->request->getPost('RFC') ?? '')),
            'regimenFiscal' => trim($this->request->getPost('regimenFiscal') ?? '') ?: null,
            'direccion'     => strtoupper(trim($this->request->getPost('direccion') ?? '')),
            'numero'        => strtoupper(trim($this->request->getPost('numero') ?? '')),
            'CP'            => trim($this->request->getPost('CP') ?? ''),
            'estado'        => strtoupper(trim($this->request->getPost('estado') ?? '')),
            'ciudad'        => strtoupper(trim($this->request->getPost('ciudad') ?? '')),
            'razonSocial'   => strtoupper(trim($this->request->getPost('razonSocial') ?? '')),
            'comoNosConoce' => $this->request->getPost('comoNosConoce'),
        ]);
        AuditService::log(AuditService::CLIENTE_EDITADO, 'clientes', $id,
            "Cliente actualizado: ID {$id}");
        return redirect()->to('/caja/clientes')->with('success', 'Cliente actualizado.');
    }

    // POST /caja/clientes/eliminar
    public function eliminarCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $id = (int) $this->request->getPost('clienteDelete');
        if ($id) {
            $clienteModel = new \App\Models\ClienteModel();
            $clienteModel->softDelete($id);
            AuditService::log(AuditService::CLIENTE_ELIMINADO, 'clientes', $id,
                "Cliente eliminado: ID {$id}");
        }
        return redirect()->to('/caja/clientes')->with('success', 'Cliente eliminado.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/consulta  —  Consulta de folios (caja, rol 2)
    // ──────────────────────────────────────────────────────────────
    public function consulta(): string
    {
        return view('caja/consulta', [
            'usuario' => $this->getUsuarioSesion(),
        ]);
    }

    // GET /caja/consulta/datatable  —  AJAX server-side DataTables
    public function consultaDatatable(): \CodeIgniter\HTTP\ResponseInterface
    {
        $draw        = (int) $this->request->getGet('draw');
        $start       = (int) $this->request->getGet('start');
        $length      = (int) $this->request->getGet('length');
        $search      = $this->request->getGet('search')['value'] ?? '';
        $orderColIdx = (int) ($this->request->getGet('order')[0]['column'] ?? 0);
        $orderDir    = $this->request->getGet('order')[0]['dir'] ?? 'desc';

        $cols     = ['n.folio', 'n.fecha_inicial', 'c.nombre', 'u.usuario', 'n.tipoPago', 'n.total', 'n.status'];
        $orderCol = $cols[$orderColIdx] ?? 'n.folio';
        $orderDir = $orderDir === 'asc' ? 'ASC' : 'DESC';

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
                    ) pm_child ON pm_child.folio_padre = n.folio
                    LEFT JOIN tipopago tpVendedor ON tpVendedor.id = n.tipoPago";

        $whereClauses = [];
        $params       = [];

        $session = session();
        if (!in_array((int) $session->get('user_acceso'), [1, 2])) {
            $whereClauses[] = "n.idVendedor = ?";
            $params[]       = (int) $session->get('user_id');
        }

        if ($search !== '') {
            $s = '%' . $search . '%';
            $whereClauses[] = "(n.folio LIKE ? OR c.nombre LIKE ? OR u.usuario LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }
        $where = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

        $filtered = (int) $db->query("SELECT COUNT(*) AS cnt {$baseSql} {$where}", $params)->getRow()->cnt;

        $data = $db->query(
            "SELECT n.folio, n.fecha_inicial,
                    COALESCE(c.nombre, '—') AS cliente,
                    COALESCE(u.usuario, '—') AS vendedor,
                    CASE
                        WHEN TRIM(LOWER(tpVendedor.descripcion)) IN ('sin pagar','a credito','a crédito','credito','crédito')
                            THEN 'A Crédito'
                        ELSE COALESCE(pm_direct.tipos_pago, pm_child.tipos_pago, tpVendedor.descripcion,
                                      IF(n.status = 2, 'Pendiente de cobro', 'A Crédito'))
                    END AS tipopago,
                    n.total, n.status AS idstatus,
                    COALESCE(s.nombre, '')                   AS status_nombre,
                    n.verificado, n.referencia,
                    COALESCE(n.anticipo, 1) AS anticipo,
                    (SELECT COALESCE(SUM(mn.monto), 0)
                     FROM notas_1 nn
                     INNER JOIN montosnotas mn ON mn.idNotas = nn.Id_Notas_1
                     WHERE (nn.folio = n.folio OR nn.referencia = n.folio) AND nn.status != 3
                    ) AS pagado_total,
                    n.factura, COALESCE(n.uuid_fiscal, '') AS uuid_fiscal,
                    COALESCE(n.status_facturacion, 0) AS status_facturacion,
                    COALESCE(n.rfc_receptor, '')          AS rfc_receptor,
                    COALESCE(n.razon_social_receptor, '') AS razon_social_receptor,
                    COALESCE(n.cp_receptor, '')           AS cp_receptor,
                    COALESCE(n.uso_cfdi, 'S01')           AS uso_cfdi,
                    COALESCE(n.regimen_fiscal_receptor, '616') AS regimen_fiscal_receptor,
                    COALESCE(n.forma_pago_cfdi, '01')     AS forma_pago_cfdi,
                    n.fecha_edicion
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
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: datos del usuario activo desde la sesión
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
