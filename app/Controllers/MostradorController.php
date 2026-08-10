<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\MensajeAdminModel;
use App\Models\NotaDetalleModel;
use App\Models\NotaModel;
use App\Models\ProductoModel;
use App\Libraries\AuditService;
use App\Models\UsuarioModel;

/**
 * MostradorController
 *
 * Módulo de Mostrador (acceso = 3 ó 4).
 *
 * Migrado desde:
 *   mostrador/index.php
 *   mostrador/venta_stp_1.php   → ventaStp1 / ventaStp1Post
 *   mostrador/agregarProducto.php → agregarProducto (AJAX)
 *   mostrador/eliminarProducto.php → eliminarProducto (AJAX)
 *   mostrador/addRows.php         → addRows (AJAX — cierra la nota)
 *   mostrador/llamadasAjax.php    → ajax (AJAX — búsqueda de notas)
 *   mostrador/venta_stp_3.php     → ventaStp3
 *   mostrador/clientes_add.php    → crearCliente
 *   mostrador/BuscarClientes.php  → buscarClientes (AJAX)
 *   mostrador/ObtieneDatosCliente.php → obtieneDatosCliente (AJAX)
 *   mostrador/duplicar.php        → duplicar
 *   mostrador/anticipos.php       → anticipos
 *   mostrador/inventario.php      → inventario
 */
class MostradorController extends BaseController
{
    protected UsuarioModel      $usuarioModel;
    protected ClienteModel      $clienteModel;
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
        $this->clienteModel     = new ClienteModel();
        $this->productoModel    = new ProductoModel();
        $this->notaModel        = new NotaModel();
        $this->notaDetalleModel = new NotaDetalleModel();
        $this->mensajeModel     = new MensajeAdminModel();
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador  —  Dashboard del módulo mostrador
    // Migrado desde: mostrador/index.php
    // ──────────────────────────────────────────────────────────────
    public function index(): string
    {
        $userId  = (int) session()->get('user_id');
        $usuarioDB = \Config\Database::connect()
            ->query("SELECT bandera FROM usuarios WHERE Id = ? LIMIT 1", [$userId])
            ->getRowArray();
        $bandera = (int) ($usuarioDB['bandera'] ?? 0);

        return view('mostrador/index', [
            'usuario' => $this->getUsuarioSesion(),
            'banner'  => $this->mensajeModel->getBanner(),
            'mensaje' => $this->mensajeModel->getMensaje(),
            'bandera' => $bandera,
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/venta  —  Paso 1: Selección de cliente
    // Migrado desde: mostrador/venta_stp_1.php (parte GET)
    // ──────────────────────────────────────────────────────────────
    public function ventaStp1(): string
    {
        return view('mostrador/venta_stp_1', [
            'usuario' => $this->getUsuarioSesion(),
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/venta  —  Paso 1: Crea la nota y redirige al paso 2
    //
    // Lógica migrada desde: mostrador/venta_stp_1.php (bloque MM_insert)
    //   1. Obtiene el siguiente folio disponible
    //   2. Inserta la nota con status = 3 (cancelada por defecto hasta confirmarla)
    //   3. Activa la bandera del usuario (ticket abierto)
    //   4. Redirige a /mostrador/venta/{folio}/productos
    // ──────────────────────────────────────────────────────────────
    public function ventaStp1Post(): \CodeIgniter\HTTP\RedirectResponse
    {
        $idCliente  = (int) $this->request->getPost('idCliente');
        $idVendedor = (int) session()->get('user_id');
        $vendedor   = session()->get('user_nombre');

        if (! $idCliente) {
            return redirect()->to('/mostrador/venta')->with('error', 'Debes seleccionar un cliente.');
        }

        // Obtener datos del cliente para desnormalizar en la nota
        $cliente = $this->clienteModel->find($idCliente);

        // Generar siguiente folio
        $folio = $this->notaModel->siguienteFolio();

        // Insertar nota con status 3 (cancelada) — se activa al confirmar
        $this->notaModel->insert([
            'idCliente'    => $idCliente,
            'idVendedor'   => $idVendedor,
            'folio'        => $folio,
            'status'       => 3,
            'precioMayoreo'=> 0,
        ]);

        // Marcar al vendedor como con ticket abierto
        $this->usuarioModel->activarBandera($idVendedor);

        AuditService::log(AuditService::VENTA_CREADA, 'notas_1', $folio,
            "Nota creada: folio #{$folio}, cliente ID {$idCliente}, vendedor: {$vendedor}");

        return redirect()->to("/mostrador/venta/{$folio}/productos");
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/venta/:folio/productos  —  Paso 2: Carrito
    // Migrado desde: mostrador/agregarProducto.php (parte de vista)
    // ──────────────────────────────────────────────────────────────
    public function ventaStp2(int $folio): string
    {
        $nota        = $this->notaModel->getPorFolio($folio);

        // Detectar modo mayoreo: por sesión (creada desde /mostrador/mayoreo)
        // o por regla de negocio (>= 12 piezas)
        $esMayoreoForzado = session()->get("nota_{$folio}_tipo") === 'mayoreo';

        $db      = \Config\Database::connect();
        $carrito = $this->getCarritoData($folio, $db, $esMayoreoForzado);

        return view('mostrador/venta_stp_2', [
            'usuario'      => $this->getUsuarioSesion(),
            'nota'         => $nota,
            'folio'        => $folio,
            'detalle'      => $carrito['detalle'],
            'totalPiezas'  => $carrito['totalPiezas'],
            'sumaImportes' => $carrito['sumaImportes'],
            'esMayoreo'    => $carrito['esMayoreo'],
        ]);
    }

    // POST /mostrador/venta/:folio/productos — sólo redirige al confirmar
    public function ventaStp2Post(int $folio): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to("/mostrador/venta/{$folio}/confirmar");
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/venta/:folio/confirmar  —  Paso 3: Resumen y pago
    // Migrado desde: mostrador/venta_stp_3.php
    // ──────────────────────────────────────────────────────────────
    public function ventaStp3(int $folio): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $nota             = $this->notaModel->getPorFolio($folio);
        $esMayoreoForzado = session()->get("nota_{$folio}_tipo") === 'mayoreo';

        $db      = \Config\Database::connect();
        $carrito = $this->getCarritoData($folio, $db, $esMayoreoForzado);

        // Si el carrito está vacío y la nota es un folio hijo (anticipo),
        // redirigir al padre donde están los productos y los pagos
        if (empty($carrito['detalle'])) {
            $refPadre = (int)($nota['referencia'] ?? 0);
            if ($refPadre > 0) {
                return redirect()->to("/mostrador/venta/{$refPadre}/confirmar");
            }
            return redirect()->to("/mostrador/venta/{$folio}/productos")
                             ->with('error', 'Agrega al menos un producto antes de confirmar.');
        }

        $tipoPagos      = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();
        $tiposImpresion = $db->query("SELECT id, descripcion FROM impresion ORDER BY id ASC")->getResultArray();

        // Pagos ya registrados: del folio padre + de todos sus folios hijos (anticipos previos)
        // Se incluye n2.status para identificar folios hijos cancelados (status=3)
        $pagosExistentes = $db->query(
            "SELECT mn.id, mn.idTipoPago, mn.monto, mn.cargos, mn.anticipo,
                    COALESCE(tp.descripcion, 'Pago') AS descripcion,
                    n2.folio AS folio_origen,
                    n2.status AS nota_status
             FROM notas_1 n2
             INNER JOIN montosnotas mn ON mn.idNotas = n2.Id_Notas_1
             LEFT JOIN tipopago tp ON tp.id = mn.idTipoPago
             WHERE n2.folio = ? OR n2.referencia = ?
             ORDER BY mn.id ASC",
            [$folio, $folio]
        )->getResultArray();

        // Datos fiscales del cliente (para auto-rellenar el panel de factura)
        $idCliente = (int)($nota['idCliente'] ?? 0);
        $clienteRFC         = '';
        $clienteRazonSocial = '';
        $clienteCP          = '';
        $clienteEmail = '';
        if ($idCliente > 0) {
            $clienteDB = $db->query(
                "SELECT RFC, razonSocial, CP, mail FROM clientes WHERE id = ? LIMIT 1",
                [$idCliente]
            )->getRowArray();
            if ($clienteDB) {
                $clienteRFC         = $clienteDB['RFC']         ?? '';
                $clienteRazonSocial = $clienteDB['razonSocial'] ?? '';
                $clienteCP          = $clienteDB['CP']          ?? '';
                $clienteEmail       = $clienteDB['mail']        ?? '';
            }
        }

        // Detectar si se accede desde admin o mostrador para las URLs de navegación
        $base = $this->request->getUri()->getSegment(1) === 'admin' ? 'admin' : 'mostrador';

        // Si ya hay pagos activos (no cancelados), el descuento queda fijo —
        // fue acordado en el primer pago y no debe modificarse en pagos subsecuentes.
        $hayPagosActivos = !empty(array_filter(
            $pagosExistentes,
            fn($p) => (int)($p['nota_status'] ?? 0) !== 3
        ));

        return view('mostrador/venta_stp_3', [
            'usuario'             => $this->getUsuarioSesion(),
            'nota'                => $nota,
            'folio'               => $folio,
            'detalle'             => $carrito['detalle'],
            'totalPiezas'         => $carrito['totalPiezas'],
            'sumaImportes'        => $carrito['sumaImportes'],
            'esMayoreo'           => $carrito['esMayoreo'],
            'tipoPagos'           => $tipoPagos,
            'tiposImpresion'      => $tiposImpresion,
            'pagosExistentes'     => $pagosExistentes,
            'descuentoFijo'       => $hayPagosActivos,
            'base'                => $base,
            'clienteRFC'          => $clienteRFC,
            'clienteRazonSocial'  => $clienteRazonSocial,
            'clienteCP'           => $clienteCP,
            'clienteEmail'        => $clienteEmail,
            'idCliente'           => $idCliente,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/venta/:folio/confirmar  —  Cierra la nota
    // Migrado desde: mostrador/addRows.php
    //
    // Recibe JSON con:
    //   Id_Notas_1, FolioN1, SumaImporte, SubTotal, CargosTarjetas,
    //   SubTotal2, IdEstatus, IVA, Total, Factura, Impresion,
    //   CargoImpresion, Descuento, TotalPiezas, Productos[], Pagos[]
    // ──────────────────────────────────────────────────────────────
    public function ventaStp3Post(int $folio): \CodeIgniter\HTTP\Response
    {
        // Captura cualquier excepción y la devuelve como JSON
        // para que el frontend siempre reciba JSON (nunca HTML de error)
        try {
        $db = \Config\Database::connect();

        // Este endpoint es compartido entre /admin/venta/:folio/confirmar y
        // /mostrador/venta/:folio/confirmar. Ni Admin ni Vendedor cobran/cierran
        // la nota aquí: solo finalizan con un tipo de pago. La nota queda "En
        // proceso" (2) para que Caja (o Admin desde su propia Verificar Caja)
        // la busque por folio, cobre y cierre.

        // ── Detectar formato: JSON legacy (campo 'data') vs nuevo (campos individuales) ──
        $rawData = $this->request->getPost('data');
        if ($rawData) {
            // Formato legacy (addRows.php original)
            $data = json_decode($rawData, true);
            if (! $data) {
                return $this->response->setContentType('application/json')
                            ->setBody(json_encode(['success' => false, 'message' => 'Datos inválidos']));
            }
            $idNotas1       = (int)   $data['Id_Notas_1'];
            $folioN1        = (int)   $data['FolioN1'];
            $sumaImportes   = (float) $data['SumaImporte'];
            $subTotal       = (float) $data['SubTotal'];
            $cargoTarjeta   = (float) ($data['CargosTarjetas'] ?? 0);
            $subTotal2      = (float) ($data['SubTotal2'] ?? $subTotal);
            $idEstatus      = (int)   $data['IdEstatus'];
            $iva            = (float) $data['IVA'];
            $total          = (float) $data['Total'];
            $factura        = (int)   ($data['Factura'] ?? 0);
            $impresion      = (int)   ($data['Impresion'] ?? 1);
            $cargoImpresion = (float) ($data['CargoImpresion'] ?? 0);
            $descuento      = (float) ($data['Descuento'] ?? 0);
            $totalPiezas    = (int)   ($data['TotalPiezas'] ?? 0);
            $listaPagosRaw  = $data['Pagos'] ?? [];
            // Normalizar al formato interno
            $listaPagos = [];
            foreach ($listaPagosRaw as $p) {
                $listaPagos[] = [
                    'tipo'    => $p['TipoPago'],
                    'monto'   => $p['Monto'],
                    'cargo'   => $p['Cargo'] ?? 0,
                    'anticipo'=> $p['Anticipo'] ?? 0,
                ];
            }
        } else {
            // Formato nuevo (venta_stp_3.php CI4)
            $notaRow      = $db->query("SELECT * FROM notas_1 WHERE folio = ? LIMIT 1", [$folio])->getRowArray();
            $idNotas1     = (int)   ($notaRow['Id_Notas_1'] ?? $this->request->getPost('Id_Notas_1'));
            $folioN1      = $folio;
            $subTotal     = (float) $this->request->getPost('subtotal');
            $iva          = (float) $this->request->getPost('iva');
            $total        = (float) $this->request->getPost('total');
            $idEstatus    = (int)   $this->request->getPost('estatus');
            $factura      = (int)   $this->request->getPost('factura');
            $descuento    = (float) $this->request->getPost('descuento');
            $totalPiezas  = (int)   $this->request->getPost('totalPiezas');
            $sumaImportes = (float)($this->request->getPost('sumaImportes') ?: $subTotal);
            $cargoTarjeta = 0;
            $subTotal2    = $subTotal + $iva;
            $impresion      = (int)  ($this->request->getPost('impresion') ?: 1);
            $cargoImpresion = (float)($this->request->getPost('cargoImpresion') ?: 0);

            $listaPagosRaw = json_decode($this->request->getPost('pagos') ?? '[]', true) ?: [];
            $listaPagos = [];
            foreach ($listaPagosRaw as $p) {
                $listaPagos[] = [
                    'tipo'       => $p['tipo'],
                    'monto'      => $p['monto'],
                    'cargo'      => $p['cargo'] ?? 0,
                    'anticipo'   => $p['anticipo'] ?? 0,
                    'referencia' => $p['referencia'] ?? '',
                ];
            }
        }

        // Nadie cobra aquí (ni Admin ni Vendedor): el pago se registra después,
        // desde Caja (o desde la propia Verificar Caja de Admin), usando el tipo
        // de pago que se guarda más abajo en notas_1.tipoPago.

        // ── Capturar datos fiscales del receptor (solo si factura=1) ──
        $rfcReceptor           = strtoupper(trim($this->request->getPost('rfcReceptor')           ?? ''));
        $razonSocialReceptor   = strtoupper(trim($this->request->getPost('razonSocialReceptor')   ?? ''));
        $cpReceptor            = trim($this->request->getPost('cpReceptor')                       ?? '');
        $usoCFDI               = trim($this->request->getPost('usoCFDI')                          ?? 'S01');
        $regimenFiscalReceptor = trim($this->request->getPost('regimenFiscalReceptor')            ?? '616');
        $formaPagoCFDI         = trim($this->request->getPost('formaPagoCFDI')                    ?? '01');
        $metodoPagoCFDI        = trim($this->request->getPost('metodoPagoCFDI')                   ?? 'PUE');

        // Vendedor no decide el estatus final: la nota siempre queda "En proceso" (2),
        // pendiente de que caja la busque por folio y la cobre/cierre.
        $idEstatus = 2;

        // ── Actualizar notas_1 ──
        $db->query(
            "UPDATE notas_1
             SET sumaImportes=?, subTotal=?, cargoTarjeta=?, subTotal2=?,
                 iva=?, total=?, descuento=?, status=?,
                 factura=?, tipoImpresion=?, cargoPorImpresion=?, totalPiezas=?,
                 rfc_receptor=?, razon_social_receptor=?, cp_receptor=?,
                 uso_cfdi=?, regimen_fiscal_receptor=?, forma_pago_cfdi=?
             WHERE folio=?",
            [
                $sumaImportes, $subTotal, $cargoTarjeta, $subTotal2,
                $iva, $total, $descuento, $idEstatus,
                $factura, $impresion, $cargoImpresion, $totalPiezas,
                $rfcReceptor ?: null, $razonSocialReceptor ?: null, $cpReceptor ?: null,
                $usoCFDI, $regimenFiscalReceptor, $formaPagoCFDI,
                $folioN1,
            ]
        );

        // Se indica el tipo de pago con el que pagará el cliente (sin registrar
        // dinero) para que caja (o Admin desde su Verificar Caja) lo vea de
        // inmediato al recibir el folio.
        $tipoPagoVendedor = (int) $this->request->getPost('tipoPago');
        if ($tipoPagoVendedor > 0) {
            $db->query("UPDATE notas_1 SET tipoPago = ? WHERE folio = ?", [$tipoPagoVendedor, $folioN1]);
        }

        // ── Actualizar datos fiscales del cliente si los llenó en la factura ──
        if ($factura === 1 && ($rfcReceptor || $razonSocialReceptor || $cpReceptor)) {
            $notaParaCliente = $db->query(
                "SELECT idCliente FROM notas_1 WHERE folio = ? LIMIT 1", [$folioN1]
            )->getRowArray();
            $idClienteActualizar = (int)($notaParaCliente['idCliente'] ?? 0);
            if ($idClienteActualizar > 0) {
                $db->query(
                    "UPDATE clientes SET
                        RFC        = IF(RFC        IS NULL OR RFC        = '' OR RFC        = '-', ?, RFC),
                        razonSocial= IF(razonSocial IS NULL OR razonSocial= '' OR razonSocial= '-', ?, razonSocial),
                        CP         = IF(CP          IS NULL OR CP         = '' OR CP         = '-', ?, CP)
                     WHERE id = ?",
                    [$rfcReceptor, $razonSocialReceptor, $cpReceptor, $idClienteActualizar]
                );
            }
        }

        // ── Liberar bandera del vendedor ──
        $this->usuarioModel->liberarBandera((int) session()->get('user_id'));

        AuditService::log(AuditService::VENTA_CREADA, 'notas_1', $folioN1,
            "Nota finalizada, enviada a caja: folio #{$folioN1}, total $" . number_format($total, 2));

        // ── Actualizar status_facturacion (NO se timbra aquí; se hace desde Consulta Folios) ──
        // 0 = No Requerida, 1 = Pendiente por Facturar
        $statusFacturacion = ($factura === 1) ? 1 : 0;
        $db->query(
            "UPDATE notas_1 SET status_facturacion = ? WHERE folio = ?",
            [$statusFacturacion, $folioN1]
        );

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode([
                        'success'      => true,
                        'cfdi_uuid'    => '',
                        'cfdi_error'   => '',
                        'ticket_url'   => base_url("caja/folio/{$folioN1}/ticket"),
                    ]));

        } catch (\Throwable $e) {
            log_message('error', 'ventaStp3Post folio=' . $folio . ' → ' . $e->getMessage());
            return $this->response
                        ->setContentType('application/json')
                        ->setBody(json_encode([
                            'success' => false,
                            'message' => $e->getMessage(),
                        ]));
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/venta/:folio/abono  —  Formulario de abono sobre una
    // nota "Sin Pagar" (crédito) con saldo pendiente.
    //
    // El vendedor solo indica cantidad + tipo de pago; esto crea un folio
    // hijo (igual que antes, vía siguienteFolio) pero SIN registrar el pago
    // en montosnotas — queda pendiente de que Caja lo reciba y confirme
    // (mismo botón "Recibir Pago y Cerrar Nota" que ya usa Caja para
    // cualquier folio en status=2). Solo entonces se suma al total pagado.
    // ──────────────────────────────────────────────────────────────
    public function abono(int $folio): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db   = \Config\Database::connect();
        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota || (int)($nota['referencia'] ?? 0) > 0) {
            return redirect()->to($this->consultaUrl())->with('error', 'Folio no encontrado.');
        }
        if ((int)$nota['status'] === 3) {
            return redirect()->to($this->consultaUrl())->with('error', 'La nota está cancelada.');
        }
        if (! $this->esNotaSinPagar($nota)) {
            return redirect()->to($this->consultaUrl())
                              ->with('error', 'Los abonos solo aplican a notas "Sin Pagar" (crédito). Esta nota se cobra completa en Caja.');
        }

        $yaPagado = (float) $db->query(
            "SELECT COALESCE(SUM(mn.monto), 0) AS total
             FROM notas_1 n2
             INNER JOIN montosnotas mn ON mn.idNotas = n2.Id_Notas_1
             WHERE (n2.folio = ? OR n2.referencia = ?) AND n2.status != 3",
            [$folio, $folio]
        )->getRow()->total;

        $tipoPagos = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();

        return view('mostrador/abono', [
            'usuario'   => $this->getUsuarioSesion(),
            'nota'      => $nota,
            'folio'     => $folio,
            'yaPagado'  => $yaPagado,
            'restante'  => max(0, (float)($nota['total'] ?? 0) - $yaPagado),
            'tipoPagos' => $tipoPagos,
            'base'      => (int) session()->get('user_acceso') === 1 ? 'admin' : 'mostrador',
        ]);
    }

    // POST /mostrador/venta/:folio/abono
    public function abonoPost(int $folio): \CodeIgniter\HTTP\RedirectResponse
    {
        $db   = \Config\Database::connect();
        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota || (int)($nota['referencia'] ?? 0) > 0 || (int)$nota['status'] === 3) {
            return redirect()->to($this->consultaUrl())->with('error', 'Folio no válido.');
        }
        if (! $this->esNotaSinPagar($nota)) {
            return redirect()->to($this->consultaUrl())
                              ->with('error', 'Los abonos solo aplican a notas "Sin Pagar" (crédito). Esta nota se cobra completa en Caja.');
        }

        $monto      = (float) $this->request->getPost('monto');
        $idTipoPago = (int)   $this->request->getPost('tipoPago');

        if ($monto <= 0 || $idTipoPago < 1) {
            $base = (int) session()->get('user_acceso') === 1 ? 'admin' : 'mostrador';
            return redirect()->to("/{$base}/venta/{$folio}/abono")
                              ->with('error', 'Ingresa una cantidad y un tipo de pago válidos.');
        }

        $nuevoFolio = $this->notaModel->siguienteFolio();
        $fecha      = date('Y-m-d H:i:s');

        $db->query(
            "INSERT INTO notas_1
             (folio, fecha_inicial, fecha_final, idCliente, idVendedor, referencia,
              sumaImportes, subTotal, subTotal2, iva, total, totalPiezas,
              status, descuento, cargoTarjeta, factura, tipoImpresion, tipoPago)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, 0, ?, 0, 2, 0, 0, 0, 1, ?)",
            [
                $nuevoFolio, $fecha, $fecha,
                $nota['idCliente'], $nota['idVendedor'], $folio,
                $monto, $monto, $idTipoPago,
            ]
        );

        AuditService::log(AuditService::VENTA_CREADA, 'notas_1', $nuevoFolio,
            "Abono registrado por vendedor sobre folio #{$folio}: nuevo folio #{$nuevoFolio}, " .
            "monto $" . number_format($monto, 2) . ", pendiente de que caja lo reciba y confirme.");

        return redirect()->to($this->consultaUrl())
                          ->with('success', "Abono registrado como folio #{$nuevoFolio}. Caja lo recibirá y confirmará el pago.");
    }

    // URL de "Consulta de Folios" según el rol de quien hizo la petición — Admin
    // no puede ver /mostrador/* (bloqueado por RoleFilter), así que este método
    // se reutiliza también para las rutas /admin/venta/:folio/abono.
    private function consultaUrl(): string
    {
        return (int) session()->get('user_acceso') === 1 ? '/admin/consulta' : '/mostrador/consulta';
    }

    // Los abonos (folios hijo) solo tienen sentido sobre una nota "Sin Pagar"
    // (crédito), que por definición se cobrará en varios abonos a lo largo del
    // tiempo. Otros tipos de pago los cobra Caja completos, de una sola vez.
    private function esNotaSinPagar(array $nota): bool
    {
        $idTipoPago = (int) ($nota['tipoPago'] ?? 0);
        if ($idTipoPago < 1) {
            return false;
        }
        $descripcion = \Config\Database::connect()
            ->query("SELECT descripcion FROM tipopago WHERE id = ? LIMIT 1", [$idTipoPago])
            ->getRow()->descripcion ?? '';

        return mb_stripos($descripcion, 'sin pagar') !== false
            || mb_stripos($descripcion, 'credito') !== false
            || mb_stripos($descripcion, 'crédito') !== false;
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/agregarProducto  —  AJAX: Agrega un producto a la nota
    //
    // Lógica migrada desde: mostrador/agregarProducto.php
    //   - Si ya existe el SKU en la nota → actualiza cantidad
    //   - Si no existe → inserta línea nueva
    //   - Descuenta piezas del inventario
    //   - Retorna HTML actualizado del carrito
    // ──────────────────────────────────────────────────────────────
    public function agregarProducto(): \CodeIgniter\HTTP\Response
    {
        $db       = \Config\Database::connect();
        $folio    = (int)    $this->request->getPost('folio');
        $cantidad = (int) abs((int) ($this->request->getPost('cantidad') ?: 1));

        // Formato nuevo (venta_stp_2): solo sku + cantidad + folio
        // El controlador resuelve el resto desde productosyazbek
        $sku = trim((string) $this->request->getPost('sku'));

        // ── Validaciones de entrada ─────────────────────────────────
        if ($cantidad < 1) {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'La cantidad debe ser al menos 1.']));
        }
        if ($cantidad > 9999) {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'Cantidad demasiado grande.']));
        }
        if ($sku === '') {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'Selecciona un producto.']));
        }
        if ($folio < 1) {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'Folio inválido.']));
        }

        // Obtener nota y producto
        $nota = $db->query(
            "SELECT Id_Notas_1 FROM notas_1 WHERE folio = ? LIMIT 1", [$folio]
        )->getRowArray();

        if (!$nota) {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'Nota no encontrada.']));
        }

        $producto = $db->query(
            "SELECT * FROM productosyazbek WHERE sku = ? LIMIT 1", [$sku]
        )->getRowArray();

        if (!$producto) {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'Producto no encontrado.']));
        }

        $idN1        = (int)$nota['Id_Notas_1'];
        $estilo      = $sku;
        $precio_m    = (float)($producto['pMenudeo']  ?? 0);
        $precioM     = (float)($producto['pMayoreo']  ?? $precio_m);
        $descripcion = $producto['Descripcion_Larga'] ?? ($producto['Descripcion_corta'] ?? $sku);
        $color       = $producto['Color'] ?? '';

        // ── Descuento atómico de stock ──────────────────────────────
        // Un solo UPDATE que verifica Y descuenta en la misma operación.
        // Evita la condición de carrera entre dos cajas sobre el mismo SKU.
        $db->query(
            "UPDATE productosyazbek SET piezas = piezas - ? WHERE sku = ? AND piezas >= ?",
            [$cantidad, $sku, $cantidad]
        );
        if ($db->affectedRows() === 0) {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'Sin stock suficiente para esa cantidad.']));
        }

        // ¿Ya existe la línea en la nota?
        $existente = $db->query(
            "SELECT * FROM notas_2 WHERE folio = ? AND estilo = ? ORDER BY Id_Notas_2 DESC LIMIT 1",
            [$folio, $estilo]
        )->getRowArray();

        if ($existente) {
            $nuevaCantidad = $existente['cantidad'] + $cantidad;
            $db->query(
                "UPDATE notas_2 SET cantidad = ? WHERE folio = ? AND estilo = ?",
                [$nuevaCantidad, $folio, $estilo]
            );
        } else {
            $db->query(
                "INSERT INTO notas_2 (Id_Notas_1, cantidad, estilo, descripcion, pUnitario, folio, sku, pUnitarioM, color)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$idN1, $cantidad, $estilo, $descripcion, $precio_m, $folio, $sku, $precioM, $color]
            );
        }

        // ── Broadcast SSE: notificar a todas las pantallas el nuevo stock ──
        try {
            $stockRow = $db->query(
                "SELECT piezas FROM productosyazbek WHERE sku = ? LIMIT 1", [$sku]
            )->getRowArray();
            $db->query(
                "INSERT INTO stock_eventos (sku, nuevo_stock) VALUES (?, ?)",
                [$sku, (int)($stockRow['piezas'] ?? 0)]
            );
        } catch (\Throwable $e) {
            // stock_eventos table may not exist — SSE broadcast is optional
            log_message('warning', 'stock_eventos INSERT skipped: ' . $e->getMessage());
        }

        AuditService::log(AuditService::PRODUCTO_AGREGADO, 'notas_2', $folio,
            "Producto agregado a folio #{$folio}: SKU {$sku}, cantidad {$cantidad}");

        $esMayoreoForzado = session()->get("nota_{$folio}_tipo") === 'mayoreo';
        $carrito = $this->getCarritoData($folio, $db, $esMayoreoForzado);

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode(array_merge($carrito, [
                        'success'      => true,
                        'folio'        => $folio,
                        'sumaImportes' => $carrito['sumaImportes'],
                        'csrf_hash'    => csrf_hash(),
                    ])));
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/eliminarProducto  —  AJAX: Elimina un producto de la nota
    //
    // Lógica migrada desde: mostrador/eliminarProducto.php
    //   - Restaura el stock del producto
    //   - Elimina la línea de notas_2
    //   - Retorna HTML del carrito actualizado
    // ──────────────────────────────────────────────────────────────
    public function eliminarProducto(): \CodeIgniter\HTTP\Response
    {
        // Acepta idN2 (legacy) o idLinea (nuevo formato desde venta_stp_2)
        $idN2   = (int) ($this->request->getPost('idN2') ?: $this->request->getPost('idLinea'));
        $folio  = (int)    $this->request->getPost('folio');
        $estilo = (string) $this->request->getPost('estilo');

        $db = \Config\Database::connect();

        // Obtener la línea para saber sku y cantidad a devolver
        $linea = $db->query(
            "SELECT cantidad, estilo, sku FROM notas_2 WHERE Id_Notas_2 = ?",
            [$idN2]
        )->getRowArray();

        $skuBroadcast = null;
        if ($linea) {
            // Restaurar stock — usar sku si estilo no vino en POST
            $skuDevolver = $estilo ?: ($linea['sku'] ?: $linea['estilo']);
            $db->query(
                "UPDATE productosyazbek SET piezas = piezas + ? WHERE sku = ?",
                [$linea['cantidad'], $skuDevolver]
            );
            $skuBroadcast = $skuDevolver;
        }

        // Eliminar línea
        $db->query("DELETE FROM notas_2 WHERE Id_Notas_2 = ?", [$idN2]);
        if ($linea) {
            AuditService::log(AuditService::PRODUCTO_QUITADO, 'notas_2', $folio,
                "Producto quitado de folio #{$folio}: SKU " . ($skuBroadcast ?? $estilo) . ", cantidad " . ($linea['cantidad'] ?? 0));
        }

        // ── Broadcast SSE: stock restaurado, avisar a todas las pantallas ──
        if ($skuBroadcast) {
            try {
                $stockRow = $db->query(
                    "SELECT piezas FROM productosyazbek WHERE sku = ? LIMIT 1", [$skuBroadcast]
                )->getRowArray();
                $db->query(
                    "INSERT INTO stock_eventos (sku, nuevo_stock) VALUES (?, ?)",
                    [$skuBroadcast, (int)($stockRow['piezas'] ?? 0)]
                );
            } catch (\Throwable $e) {
                log_message('warning', 'stock_eventos INSERT skipped: ' . $e->getMessage());
            }
        }

        $esMayoreoForzado = session()->get("nota_{$folio}_tipo") === 'mayoreo';
        $carrito = $this->getCarritoData($folio, $db, $esMayoreoForzado);

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode(array_merge($carrito, [
                        'success'      => true,
                        'folio'        => $folio,
                        'sumaImportes' => $carrito['sumaImportes'],
                        'csrf_hash'    => csrf_hash(),
                    ])));
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/addRows  —  AJAX: Cierra la nota (igual que ventaStp3Post)
    // Alias para compatibilidad con el JS heredado
    // ──────────────────────────────────────────────────────────────
    public function addRows(): \CodeIgniter\HTTP\Response
    {
        // Reutilizar lógica del paso 3
        $folio = (int) json_decode($this->request->getPost('data'), true)['FolioN1'];
        return $this->ventaStp3Post($folio);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/addRowsAnt  —  Cierra nota como anticipo
    // ──────────────────────────────────────────────────────────────
    public function addRowsAnt(): \CodeIgniter\HTTP\Response
    {
        return $this->addRows(); // misma lógica, el idEstatus viene en el payload
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/addPagosMontos  —  AJAX: Agrega montos de pago
    // ──────────────────────────────────────────────────────────────
    public function addPagosMontos(): \CodeIgniter\HTTP\Response
    {
        $idNotas = (int)   $this->request->getPost('idNotas');
        $tipoPago= (int)   $this->request->getPost('tipoPago');
        $monto   = (float) $this->request->getPost('monto');
        $cargos  = (float) $this->request->getPost('cargos');
        $anticipo= (int)   $this->request->getPost('anticipo');

        $db = \Config\Database::connect();
        $db->query(
            "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, fecha) VALUES (?, ?, ?, ?, ?, ?)",
            [$idNotas, $tipoPago, $monto, $cargos, $anticipo, date('Y-m-d H:i:s')]
        );
        AuditService::log(AuditService::PAGO_REGISTRADO, 'montosnotas', $idNotas,
            "Pago registrado en nota ID {$idNotas}: monto $" . number_format($monto, 2) . ", tipoPago {$tipoPago}, anticipo {$anticipo}");

        return $this->response->setBody('OK');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/cancelarPago  —  Cancela un pago registrado
    // ──────────────────────────────────────────────────────────────
    public function cancelarPago(): \CodeIgniter\HTTP\Response
    {
        $idMonto = (int) $this->request->getPost('idMonto');
        \Config\Database::connect()->query("DELETE FROM montosnotas WHERE id = ?", [$idMonto]);
        AuditService::log(AuditService::PAGO_CANCELADO, 'montosnotas', $idMonto,
            "Pago cancelado: montosnotas ID {$idMonto}");
        return $this->response->setBody('OK');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/verificarPago  —  Marca nota como pagada
    // ──────────────────────────────────────────────────────────────
    public function verificarPago(): \CodeIgniter\HTTP\Response
    {
        // Vendedor no puede marcar notas como pagadas — eso es exclusivo de
        // Caja/Admin, quienes reciben el dinero y lo registran.
        if (! in_array((int) session()->get('user_acceso'), [1, 2], true)) {
            return $this->response->setStatusCode(403)->setBody('No autorizado.');
        }

        $folio = (int) $this->request->getPost('folio');
        $nota  = $this->notaModel->getPorFolio($folio);

        if ($nota) {
            $this->notaModel->marcarPagada($nota['Id_Notas_1']);
            AuditService::log(AuditService::VENTA_VERIFICADA, 'notas_1', $folio,
                "Nota verificada/pagada: folio #{$folio}");
        }

        return $this->response->setBody('OK');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/venta/:folio/duplicar  —  Duplica una nota
    // Migrado desde: mostrador/duplicar.php
    // ──────────────────────────────────────────────────────────────
    public function duplicar(int $folio): \CodeIgniter\HTTP\RedirectResponse
    {
        $notaOrigen  = $this->notaModel->getPorFolio($folio);
        $detalleOrig = $this->notaDetalleModel->getPorFolio($folio);

        if (! $notaOrigen) {
            return redirect()->to('/mostrador')->with('error', 'Nota no encontrada.');
        }

        $nuevoFolio = $this->notaModel->siguienteFolio();
        $idVendedor = (int) session()->get('user_id');

        // Crear nueva cabecera — copia toda la info del original, nuevo folio, sin pagos
        $this->notaModel->insert([
            'idCliente'        => $notaOrigen['idCliente'],
            'idVendedor'       => $idVendedor,
            'folio'            => $nuevoFolio,
            'status'           => 1,  // Abierta — sin pagos registrados
            'precioMayoreo'    => $notaOrigen['precioMayoreo']    ?? 0,
            'factura'          => $notaOrigen['factura']          ?? 0,
            'descuento'        => $notaOrigen['descuento']        ?? 0,
            'tipoPago'         => $notaOrigen['tipoPago']         ?? '',
            'tipoImpresion'    => $notaOrigen['tipoImpresion']    ?? 0,
            'cargoPorImpresion'=> $notaOrigen['cargoPorImpresion'] ?? 0,
            'sumaImportes'     => $notaOrigen['sumaImportes']     ?? 0,
            'subTotal'         => $notaOrigen['subTotal']         ?? 0,
            'subTotal2'        => $notaOrigen['subTotal2']        ?? 0,
            'iva'              => $notaOrigen['iva']              ?? 0,
            'total'            => $notaOrigen['total']            ?? 0,
            'totalPiezas'      => $notaOrigen['totalPiezas']      ?? 0,
            // verificado y montosnotas NO se copian — empieza sin pagos
        ]);

        $nuevaNota = $this->notaModel->getPorFolio($nuevoFolio);

        // Copiar detalle
        foreach ($detalleOrig as $linea) {
            unset($linea['Id_Notas_2']);
            $linea['folio']      = $nuevoFolio;
            $linea['Id_Notas_1'] = $nuevaNota['Id_Notas_1'];
            $this->notaDetalleModel->insert($linea);
        }

        AuditService::log(AuditService::VENTA_DUPLICADA, 'notas_1', $nuevoFolio,
            "Nota duplicada: original #{$folio} -> nuevo folio #{$nuevoFolio}");

        return redirect()->to("/mostrador/venta/{$nuevoFolio}/productos")
                         ->with('success', "Nota duplicada. Nuevo folio: {$nuevoFolio}");
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/venta/:folio/cancelar  —  Cancela una nota
    // ──────────────────────────────────────────────────────────────
    public function cancelar(int $folio): \CodeIgniter\HTTP\RedirectResponse
    {
        $nota = $this->notaModel->getPorFolio($folio);
        if (! $nota) {
            return redirect()->to('/mostrador')->with('error', 'Nota no encontrada.');
        }

        // Restaurar stock de cada producto en la nota
        $detalle = $this->notaDetalleModel->getPorFolio($folio);
        $db      = \Config\Database::connect();
        foreach ($detalle as $linea) {
            $skuLinea = $linea['sku'] ?: $linea['estilo'];
            $db->query(
                "UPDATE productosyazbek SET piezas = piezas + ? WHERE sku = ?",
                [$linea['cantidad'], $skuLinea]
            );
            // ── Broadcast SSE: stock restaurado al cancelar nota ──
            try {
                $stockRow = $db->query(
                    "SELECT piezas FROM productosyazbek WHERE sku = ? LIMIT 1", [$skuLinea]
                )->getRowArray();
                $db->query(
                    "INSERT INTO stock_eventos (sku, nuevo_stock) VALUES (?, ?)",
                    [$skuLinea, (int)($stockRow['piezas'] ?? 0)]
                );
            } catch (\Throwable $e) {
                log_message('warning', 'stock_eventos INSERT skipped: ' . $e->getMessage());
            }
        }

        // Cancelar nota padre + todos sus hijos (anticipos) si los hubiera
        $this->notaModel->cambiarStatus($nota['Id_Notas_1'], 3);
        $db->query(
            "UPDATE notas_1 SET status = 3 WHERE referencia = ? AND status != 3",
            [$folio]
        );
        $this->usuarioModel->liberarBandera((int) session()->get('user_id'));

        AuditService::log(AuditService::VENTA_CANCELADA, 'notas_1', $folio,
            "Nota cancelada: folio #{$folio}");

        return redirect()->to('/mostrador')->with('success', "Nota #{$folio} cancelada.");
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/ajax  —  AJAX general: búsqueda de notas
    // Migrado desde: mostrador/llamadasAjax.php
    // ──────────────────────────────────────────────────────────────
    public function ajax(): \CodeIgniter\HTTP\Response
    {
        // Compatible con llamadas legacy (tipo/pago) y las nuevas vistas (folio/status)
        $folio  = $this->request->getPost('folio');
        $tipo   = (int) $this->request->getPost('tipo');
        $pago   = (int) $this->request->getPost('pago');
        $status = (int) $this->request->getPost('status');

        // Si vienen parámetros del nuevo formato (status), adaptarlos
        if ($status > 0 && $tipo === 0) {
            $notas = $this->notaModel->buscarConFiltros($folio ?: null, $status);
        } elseif ($tipo === 1) {
            $notas = $this->notaModel->buscarConFiltros($folio ?: null, null);
        } elseif ($tipo === 2) {
            $notas = $this->notaModel->buscarConFiltros(null, $pago ?: null);
        } else {
            $notas = $this->notaModel->buscarConFiltros($folio ?: null, null);
        }

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode([
                        'data'      => $notas,
                        'csrf_hash' => csrf_hash(),
                    ]));
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/productos/buscar  —  AJAX: busca productos para select2
    // ──────────────────────────────────────────────────────────────
    public function buscarProductos(): \CodeIgniter\HTTP\Response
    {
        $q   = strtolower(trim($this->request->getPost('q') ?? ''));
        $db  = \Config\Database::connect();

        // Búsqueda inteligente: cada palabra debe aparecer en algún campo
        $palabras = array_filter(explode(' ', $q), fn($p) => $p !== '');
        if (empty($palabras)) {
            return $this->response->setContentType('application/json')->setBody('[]');
        }

        $whereParts = [];
        $bindings   = [];
        foreach ($palabras as $palabra) {
            $s = "%{$palabra}%";
            $whereParts[] = "(LOWER(sku) LIKE ? OR LOWER(estilo) LIKE ?
                              OR LOWER(Descripcion_corta) LIKE ? OR LOWER(Descripcion_Larga) LIKE ?
                              OR LOWER(Color) LIKE ?)";
            array_push($bindings, $s, $s, $s, $s, $s);
        }

        $where = implode(' AND ', $whereParts);

        // Incluye productos sin stock (piezas = 0) para mostrarlos como "Sin existencias"
        // Los primeros son los que sí tienen stock, los sin stock al final
        $rows = $db->query(
            "SELECT sku, estilo, Color,
                    CONCAT(estilo, ' - ', Descripcion_Larga, ' - ', Color) AS descripcion,
                    piezas, pMenudeo AS precio, pMayoreo AS precioMayoreo
             FROM productosyazbek
             WHERE {$where}
             ORDER BY (piezas > 0) DESC, estilo, Color ASC
             LIMIT 40",
            $bindings
        )->getResultArray();

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode($rows));
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/clientes  —  Lista de clientes
    // ──────────────────────────────────────────────────────────────
    public function clientes(): string
    {
        return view('mostrador/clientes', [
            'usuario'  => $this->getUsuarioSesion(),
            'error'    => session()->getFlashdata('error'),
            'success'  => session()->getFlashdata('success'),
        ]);
    }

    // GET /mostrador/clientes/datatable  —  AJAX server-side
    public function clientesDatatable(): \CodeIgniter\HTTP\ResponseInterface
    {
        $draw     = (int) $this->request->getGet('draw');
        $start    = (int) $this->request->getGet('start');
        $length   = (int) $this->request->getGet('length');
        $search   = $this->request->getGet('search')['value'] ?? '';
        $orderCol = $this->request->getGet('order')[0]['column'] ?? 0;
        $orderDir = $this->request->getGet('order')[0]['dir'] ?? 'asc';

        $result = $this->clienteModel->getDatatable($start, $length, $search, $orderCol, $orderDir);

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $result['data'],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/clientes/buscar  —  AJAX: busca clientes
    // Migrado desde: mostrador/BuscarClientes.php
    // ──────────────────────────────────────────────────────────────
    public function buscarClientes(): \CodeIgniter\HTTP\Response
    {
        $termino  = $this->request->getPost('termino') ?? '';
        $clientes = $this->clienteModel->buscar($termino);

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode($clientes));
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/clientes/datos  —  AJAX: datos de un cliente
    // Migrado desde: mostrador/ObtieneDatosCliente.php
    // ──────────────────────────────────────────────────────────────
    public function obtieneDatosCliente(): \CodeIgniter\HTTP\Response
    {
        // Acepta 'id' (legacy) o 'idCliente' (nuevo formato)
        $id      = (int) ($this->request->getPost('idCliente') ?: $this->request->getPost('id'));
        $cliente = $this->clienteModel->find($id);

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
    // POST /mostrador/clientes/crear  —  Crea un cliente nuevo
    // Migrado desde: mostrador/clientes_add.php
    // ──────────────────────────────────────────────────────────────
    public function crearCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'nombre'   => 'required|min_length[2]',
            'telefono' => 'permit_empty',
            'mail'     => 'permit_empty|valid_email',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/mostrador/clientes')
                             ->with('error', implode(', ', $this->validator->getErrors()));
        }

        $this->clienteModel->insert([
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

        return redirect()->to('/mostrador/clientes')->with('success', 'Cliente registrado correctamente.');
    }

    // POST /mostrador/clientes/actualizar/:id
    public function actualizarCliente(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->clienteModel->update($id, [
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

        return redirect()->to('/mostrador/clientes')->with('success', 'Cliente actualizado.');
    }

    // POST /mostrador/clientes/eliminar
    public function eliminarCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $id = (int) $this->request->getPost('clienteDelete');
        if ($id) {
            $this->clienteModel->softDelete($id);
            AuditService::log(AuditService::CLIENTE_ELIMINADO, 'clientes', $id,
                "Cliente eliminado: ID {$id}");
        }
        return redirect()->to('/mostrador/clientes')->with('success', 'Cliente eliminado.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/clientes/ciudades  —  AJAX: carga ciudades por estado
    // Migrado desde: mostrador/cargaCiudades.php
    // ──────────────────────────────────────────────────────────────
    public function cargaCiudades(): \CodeIgniter\HTTP\Response
    {
        $estadoId = (int) $this->request->getGet('estado');
        $db       = \Config\Database::connect();
        $ciudades = $db->query(
            "SELECT * FROM municipios WHERE estado_id = ? ORDER BY nombre ASC",
            [$estadoId]
        )->getResultArray();

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode($ciudades));
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/anticipos  —  Lista de anticipos
    // Migrado desde: mostrador/anticipos.php
    // ──────────────────────────────────────────────────────────────
    public function anticipos(): string
    {
        $db = \Config\Database::connect();
        $tiposPago = $db->query("SELECT id, descripcion AS tipo FROM tipopago ORDER BY id ASC")->getResultArray();
        return view('mostrador/anticipos', [
            'usuario'    => $this->getUsuarioSesion(),
            'anticipos'  => $this->notaModel->getAnticipos(),
            'tiposPago'  => $tiposPago,
        ]);
    }

    // GET /mostrador/anticipos/folio/:folio  —  AJAX: datos del anticipo
    public function muestraFolioAnticipo(int $folio): \CodeIgniter\HTTP\Response
    {
        $nota     = $this->notaModel->getPorFolio($folio);
        $pagados  = $this->notaModel->getPagosHijos($folio);
        $total    = (float)($nota['total'] ?? 0);
        $pagado   = $this->notaModel->getTotalPagadoAnticipo($folio);

        return $this->response->setContentType('application/json')->setBody(json_encode([
            'ok'      => true,
            'nota'    => $nota,
            'pagos'   => $pagados,
            'total'   => $total,
            'pagado'  => $pagado,
            'restante'=> $total - $pagado,
        ]));
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/anticipo/nuevo-pago — Registra nuevo pago anticipo
    // Crea un folio hijo que referencia al padre
    // ──────────────────────────────────────────────────────────────
    public function nuevoPagoAnticipo(): \CodeIgniter\HTTP\Response
    {
        $foliopadre = (int)   $this->request->getPost('folio');
        $monto      = (float) $this->request->getPost('monto');
        $idTipoPago = (int)   $this->request->getPost('tipoPago');

        if ($foliopadre < 1 || $monto <= 0 || $idTipoPago < 1) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Datos inválidos.']);
        }

        $padre = $this->notaModel->getPorFolio($foliopadre);
        if (! $padre || (int)($padre['status'] ?? 0) === 5) {
            return $this->response->setJSON(['ok' => false, 'error' => 'La nota no existe o ya está liquidada.']);
        }

        $idCliente  = (int)$padre['idCliente'];
        $idVendedor = (int) session()->get('user_id');

        // Cargo de tarjeta si aplica
        $db       = \Config\Database::connect();
        $tipoPago = $db->query("SELECT cargo FROM tipopago WHERE id = ? LIMIT 1", [$idTipoPago])->getRowArray();
        $cargoPct = (float)($tipoPago['cargo'] ?? 0);
        $cargo    = $monto * $cargoPct / 100;

        $folioHijo = $this->notaModel->crearFolioPagoAnticipo($foliopadre, $idCliente, $idVendedor, $monto, $idTipoPago, $cargo);
        AuditService::log(AuditService::ANTICIPO_CREADO, 'notas_1', $folioHijo,
            "Anticipo registrado: folio hijo #{$folioHijo} -> padre #{$foliopadre}, monto $" . number_format($monto, 2));

        // Verificar si ya está liquidado (total pagado >= total nota)
        $totalPagado = $this->notaModel->getTotalPagadoAnticipo($foliopadre);
        $totalNota   = (float)($padre['total'] ?? 0);
        if ($totalPagado >= $totalNota - 0.99) {
            $this->notaModel->liquidarAnticipo($foliopadre);
            AuditService::log(AuditService::VENTA_LIQUIDADA, 'notas_1', $foliopadre,
                "Folio #{$foliopadre} liquidado automaticamente al completar pagos");
        }

        return $this->response->setJSON([
            'ok'         => true,
            'folioHijo'  => $folioHijo,
            'totalPagado'=> $totalPagado,
            'totalNota'  => $totalNota,
            'liquidado'  => $totalPagado >= $totalNota - 0.99,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/inventario  —  Inventario (solo lectura)
    // ──────────────────────────────────────────────────────────────
    public function inventario(): string
    {
        return view('mostrador/inventario', [
            'usuario'   => $this->getUsuarioSesion(),
            'productos' => $this->productoModel->getInventario(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /admin/venta/:folio/productos  —  Paso 2 desde admin
    // Wrapper público para que el admin pueda usar la misma vista de carrito
    // que mostrador, pasando $base='admin' para que las URLs apunten a /admin/...
    // ──────────────────────────────────────────────────────────────
    public function ventaProductosAdmin(int $folio): string
    {
        $nota             = $this->notaModel->getPorFolio($folio);
        $esMayoreoForzado = session()->get("nota_{$folio}_tipo") === 'mayoreo';
        $db               = \Config\Database::connect();
        $carrito          = $this->getCarritoData($folio, $db, $esMayoreoForzado);

        return view('mostrador/venta_stp_2', [
            'usuario'      => $this->getUsuarioSesion(),
            'nota'         => $nota,
            'folio'        => $folio,
            'detalle'      => $carrito['detalle'],
            'totalPiezas'  => $carrito['totalPiezas'],
            'sumaImportes' => $carrito['sumaImportes'],
            'esMayoreo'    => $carrito['esMayoreo'],
            'base'         => 'admin',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/mayoreo  —  Paso 1 Mayoreo: Selección de cliente
    // POST /mostrador/mayoreo — Crea la nota y redirige al paso 2 (modo mayoreo)
    // ──────────────────────────────────────────────────────────────
    public function mayoreo(): string
    {
        return view('mostrador/venta_stp_1', [
            'usuario'    => $this->getUsuarioSesion(),
            'clientes'   => $this->clienteModel->getTodos(),
            'tipoVenta'  => 'mayoreo',
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function mayoreoPost(): \CodeIgniter\HTTP\RedirectResponse
    {
        $idCliente  = (int) $this->request->getPost('idCliente');
        $idVendedor = (int) session()->get('user_id');
        $vendedor   = session()->get('user_nombre');

        if (! $idCliente) {
            return redirect()->to('/mostrador/mayoreo')->with('error', 'Debes seleccionar un cliente.');
        }

        $cliente = $this->clienteModel->find($idCliente);
        $folio   = $this->notaModel->siguienteFolio();

        $this->notaModel->insert([
            'idCliente'    => $idCliente,
            'idVendedor'   => $idVendedor,
            'folio'        => $folio,
            'status'       => 3,
            'precioMayoreo'=> 1,
        ]);

        $this->usuarioModel->activarBandera($idVendedor);

        // Marcar esta nota como MAYOREO en sesión (precio mayoreo siempre)
        session()->set("nota_{$folio}_tipo", 'mayoreo');

        AuditService::log(AuditService::VENTA_CREADA, 'notas_1', $folio,
            "Nota mayoreo creada: folio #{$folio}, cliente ID {$idCliente}, vendedor: {$vendedor}");

        return redirect()->to("/mostrador/venta/{$folio}/productos");
    }

    public function menudeo(): string
    {
        return view('mostrador/venta_stp_1', [
            'usuario'   => $this->getUsuarioSesion(),
            'clientes'  => $this->clienteModel->getTodos(),
            'tipoVenta' => 'menudeo',
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/consulta  —  Consulta de notas
    // ──────────────────────────────────────────────────────────────
    public function consultaStp1(): string
    {
        $db        = \Config\Database::connect();
        $tipoPagos = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();

        return view('mostrador/consulta_stp_1', [
            'usuario'   => $this->getUsuarioSesion(),
            'tipoPagos' => $tipoPagos,
        ]);
    }

    // GET /mostrador/consulta/datatable  —  AJAX server-side DataTables para consulta de folios
    public function notasDatatable(): \CodeIgniter\HTTP\ResponseInterface
    {
        $draw     = (int) $this->request->getGet('draw');
        $start    = (int) $this->request->getGet('start');
        $length   = (int) $this->request->getGet('length');
        $search   = $this->request->getGet('search')['value'] ?? '';
        $orderColIdx = (int) ($this->request->getGet('order')[0]['column'] ?? 0);
        $orderDir = $this->request->getGet('order')[0]['dir'] ?? 'desc';

        $cols = ['n.folio', 'n.fecha_inicial', 'c.nombre', 'u.usuario', 'n.tipoPago', 'n.total', 'n.status'];
        $orderCol = $cols[$orderColIdx] ?? 'n.folio';
        $orderDir = $orderDir === 'asc' ? 'ASC' : 'DESC';

        $db      = \Config\Database::connect();
        $userId  = (int) session()->get('user_id');

        // Total solo del vendedor logueado
        $total = $db->query("SELECT COUNT(*) AS total FROM notas_1 WHERE idVendedor = ?", [$userId])->getRow()->total;

        // Base query
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

        // Siempre filtrar por el vendedor logueado
        $whereClauses = ["n.idVendedor = ?"];
        $params       = [$userId];

        if ($search !== '') {
            $s = '%' . $search . '%';
            $whereClauses[] = "(n.folio LIKE ? OR c.nombre LIKE ? OR u.usuario LIKE ?)";
            $params = array_merge($params, [$s, $s, $s]);
        }

        $where = "WHERE " . implode(" AND ", $whereClauses);

        $countResult = $db->query("SELECT COUNT(*) AS cnt {$baseSql} {$where}", $params)->getRow();
        $filtered = $countResult->cnt ?? 0;

        $data = $db->query(
            "SELECT n.folio, n.fecha_inicial,
                    COALESCE(c.nombre, '—') AS cliente,
                    COALESCE(u.usuario, '—') AS vendedor,
                    COALESCE(pm_direct.tipos_pago, pm_child.tipos_pago, tpVendedor.descripcion,
                             IF(n.status = 2, 'Pendiente de cobro', 'A Crédito')) AS tipopago,
                    n.total, n.status AS idstatus,
                    COALESCE(s.nombre, '') AS status_nombre,
                    n.verificado,
                    COALESCE(n.referencia, 0) AS referencia,
                    n.factura, COALESCE(n.uuid_fiscal, '') AS uuid_fiscal
             {$baseSql} {$where}
             ORDER BY {$orderCol} {$orderDir}
             LIMIT ? OFFSET ?",
            array_merge($params, [$length, $start])
        )->getResultArray();

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => (int) $total,
            'recordsFiltered' => (int) $filtered,
            'data'            => $data,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /mostrador/metas
    // ──────────────────────────────────────────────────────────────
    public function metas(): string
    {
        $usuario = $this->getUsuarioSesion();
        $db      = \Config\Database::connect();
        $mes     = date('Y-m');

        // status 2 = finalizada por el vendedor, pendiente de cobro en caja;
        // status 5 = ya cobrada. Ambas cuentan como venta hecha por el vendedor —
        // el retraso en que caja la cobre no debe ocultarla de sus metas.
        $notasDelMes = $db->query(
            "SELECT n.folio, n.fecha_inicial, c.nombre AS cliente, n.total, n.status
             FROM notas_1 n
             LEFT JOIN clientes c ON c.id = n.idCliente
             WHERE n.idVendedor = ? AND n.fecha_inicial LIKE ? AND n.status IN (2, 5)
             ORDER BY n.folio DESC",
            [$usuario['Id'], "{$mes}%"]
        )->getResultArray();

        $piezasRow = $db->query(
            "SELECT SUM(n2.cantidad) AS total
             FROM notas_2 n2
             INNER JOIN notas_1 n ON n2.folio = n.folio
             WHERE n.idVendedor = ? AND n.fecha_inicial LIKE ? AND n.status IN (2, 5)",
            [$usuario['Id'], "{$mes}%"]
        )->getRowArray();

        return view('mostrador/metas', [
            'usuario'      => $usuario,
            'notasDelMes'  => $notasDelMes,
            'estadisticas' => [
                'totalNotas'  => count($notasDelMes),
                'totalVentas' => array_sum(array_column($notasDelMes, 'total')),
                'totalPiezas' => (int)($piezasRow['total'] ?? 0),
            ],
        ]);
    }

    // POST /mostrador/nota/agregarProductoPM — alias precio PM
    public function agregarProductoPM(): \CodeIgniter\HTTP\Response
    {
        return $this->agregarProducto();
    }

    // POST /mostrador/nota/agregarProductoR — alias precio reposición
    public function agregarProductoR(): \CodeIgniter\HTTP\Response
    {
        return $this->agregarProducto();
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/folio/ajax  —  Modal de detalle de folio
    // Misma lógica que AdminController::cajaAjax() pero sin botón
    // "Pago Verificado". Solo muestra "Cancelar Nota" para mostrador.
    // ──────────────────────────────────────────────────────────────
    public function folioAjax(): string
    {
        $folio = (int) $this->request->getPost('folio');

        try {
            $db = \Config\Database::connect();

            $nota = $db->query(
                "SELECT n.Id_Notas_1, n.fecha_inicial,
                        c.nombre  AS NombreCliente,
                        u.usuario AS vendedor,
                        n.folio, n.verificado, n.factura, n.descuento,
                        n.status AS statusId,
                        n.sumaImportes, n.subTotal, n.tipoPago,
                        n.cargoTarjeta, n.subTotal2, n.iva, n.total,
                        n.tipoImpresion, n.cargoPorImpresion,
                        n.montoTCTD, n.totalPiezas, n.precioMayoreo
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

        } catch (\Exception $e) {
            log_message('error', 'folioAjax folio=' . $folio . ': ' . $e->getMessage());
            return '<p class="text-danger">Error al consultar el folio.</p>';
        }

        $letras = '';
        try {
            $letras = mb_strtoupper(\App\Libraries\AifLibNumber::toCurrency($nota['total']));
        } catch (\Throwable $e) { }

        $total = $nota['total'] ?? 0;

        // Mapa de status legibles (independiente del valor en la tabla status de BD)
        $statusMap    = [1=>'Abierta', 2=>'En proceso', 3=>'Cancelada', 4=>'Anticipo', 5=>'Pagada', 6=>'Liquidado'];
        $verificado   = $nota['verificado'] ?? '';
        $statusId     = (int)($nota['statusId'] ?? 0);
        $statusNombre = $statusMap[$statusId] ?? 'Desconocido';
        $esLiquidado  = ($verificado === 'Pagado' || $statusId === 6);
        $headerStatus = $esLiquidado ? 'Liquidado' : $statusNombre;

        // Cabecera
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
        $html .= '<tr><td colspan="3" class="text-right"><strong>Tipo bordado/impresión</strong></td><td>' . $otros . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo extra</strong></td><td>$&nbsp;' . number_format($nota['cargoPorImpresion'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Descuento</strong></td><td>$&nbsp;' . number_format($nota['descuento'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right no-border"><strong>SubTotal</strong></td><td>$&nbsp;' . number_format($nota['subTotal'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Cargo TC/TD</strong></td><td>$&nbsp;' . number_format($nota['montoTCTD'] ?? $nota['cargoTarjeta'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>IVA</strong></td><td>$&nbsp;' . number_format($nota['iva'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>SubTotal2</strong></td><td>$&nbsp;' . number_format($nota['subTotal2'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>Total</strong></td><td>$&nbsp;' . number_format($total, 2) . '</td></tr>';

        if ($letras) {
            $html .= '<tr><td colspan="4" class="text-right no-border" align="left">';
            $html .= '<strong>Cantidad con letra( ' . esc($letras) . ')</strong></td></tr>';
        }

        // Mostrar folio en pagos solo si hay hijos (pagos de folios distintos al padre)
        $hayFoliosHijos = !empty(array_filter($pagos, fn($p) => (int)($p['folio_pago'] ?? 0) !== $folio));

        foreach ($pagos as $p) {
            $esCancelado = (int)($p['folio_status'] ?? 0) === 3;
            $rowStyle    = $esCancelado ? 'opacity:.45;text-decoration:line-through;' : '';
            $html .= '<tr style="' . $rowStyle . '"><td colspan="4" class="text-right no-border">';
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

        // Estatus en pie de modal — ignora pagos de folios hijos cancelados
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
        $html .= '<td colspan="2" align="left">' . esc($displayStatus) . '</td></tr>';
        $html .= '<tr><td colspan="2" class="text-right no-border" align="left"><strong>Factura:</strong></td>';
        $html .= '<td colspan="2" align="left">' . (($nota['factura'] ?? 0) == 1 ? 'Si requiere' : 'No requiere') . '</td></tr>';

        // Solo botón Cancelar Nota — sin Liquidar en mostrador; ocultar si ya liquidado o cancelado
        $html .= '<tr><input type="hidden" id="folio_input_m" value="' . $nota['folio'] . '" />';
        if ($statusId !== 3 && ! $esLiquidado) {
            $html .= '<td colspan="4" class="text-right no-border">';
            $html .= '<button type="button" class="btn btn-danger" onclick="fnCancelarFolioMostrador()">Cancelar Nota</button>';
            $html .= '</td>';
        }
        $html .= '</tr></tbody></table></div>';
        $html .= '<script>function blurFnCj(){var p=parseFloat(document.getElementById("pagar2_cj").value)||0;var i=parseFloat(document.getElementById("importe_cj").value)||0;document.getElementById("resultado_cj").value=(i-p).toFixed(2);}</script>';

        return $html;
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/folio/cancelar  —  Cancela nota desde el modal de consulta
    // Restaura inventario y emite eventos de stock.
    // ──────────────────────────────────────────────────────────────
    public function folioAjaxCancelar(): \CodeIgniter\HTTP\Response
    {
        $folio = (int) $this->request->getPost('folio');
        if (! $folio) {
            return $this->response->setContentType('application/json')->setJSON(['ok' => false, 'error' => 'Folio inválido']);
        }
        try {
            $db   = \Config\Database::connect();
            $nota = $db->query(
                "SELECT Id_Notas_1, status FROM notas_1 WHERE folio = ? LIMIT 1",
                [$folio]
            )->getRowArray();

            if (! $nota) {
                return $this->response->setContentType('application/json')->setJSON(['ok' => false, 'error' => 'Folio no encontrado']);
            }
            if ((int)$nota['status'] === 3) {
                return $this->response->setContentType('application/json')->setJSON(['ok' => false, 'error' => 'Folio ya cancelado']);
            }

            // Restaurar stock producto por producto
            $detalles = $db->query(
                "SELECT estilo AS sku, cantidad FROM notas_2 WHERE folio = ?",
                [$folio]
            )->getResultArray();

            foreach ($detalles as $det) {
                $sku = $det['sku'];
                $db->query(
                    "UPDATE productosyazbek SET piezas = piezas + ? WHERE sku = ?",
                    [(int)$det['cantidad'], $sku]
                );
                // Broadcast del nuevo stock
                try {
                    $stockRow = $db->query(
                        "SELECT piezas FROM productosyazbek WHERE sku = ? LIMIT 1",
                        [$sku]
                    )->getRowArray();
                    $db->query(
                        "INSERT INTO stock_eventos (sku, nuevo_stock) VALUES (?, ?)",
                        [$sku, (int)($stockRow['piezas'] ?? 0)]
                    );
                } catch (\Throwable $e) {
                    log_message('warning', 'stock_eventos INSERT skipped: ' . $e->getMessage());
                }
            }

            // Cancelar nota padre + todos sus hijos (anticipos) no cancelados
            $db->query(
                "UPDATE notas_1 SET status = 3 WHERE folio = ? OR (referencia = ? AND status != 3)",
                [$folio, $folio]
            );
            AuditService::log(AuditService::VENTA_CANCELADA, 'notas_1', $folio,
                "Nota cancelada: folio #{$folio}");

            return $this->response->setContentType('application/json')->setJSON(['ok' => true]);

        } catch (\Throwable $e) {
            log_message('error', 'folioAjaxCancelar folio=' . $folio . ': ' . $e->getMessage());
            return $this->response->setContentType('application/json')->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────────

    /**
     * Resumen del carrito en JSON.
     * Aplica regla de precio:
     *   - Si $forzarMayoreo = true (nota creada desde Venta Mayoreo): siempre precio mayoreo.
     *   - Si no: < 12 piezas = menudeo, >= 12 = mayoreo (regla original).
     */
    private function getCarritoData(int $folio, $db, bool $forzarMayoreo = false): array
    {
        $detalle = $db->query(
            "SELECT n2.*,
                    COALESCE(NULLIF(n2.color,''), p.Color, '')                            AS color,
                    COALESCE(NULLIF(n2.descripcion,''), p.Descripcion_Larga, n2.estilo)   AS descripcion,
                    COALESCE(NULLIF(n2.pUnitario, 0),  p.pMenudeo, 0)                     AS pUnitarioFinal,
                    COALESCE(NULLIF(n2.pUnitarioM, 0), p.pMayoreo, n2.pUnitario, 0)       AS pUnitarioMFinal
             FROM notas_2 n2
             LEFT JOIN productosyazbek p ON p.sku = n2.sku
             WHERE n2.folio = ?
             ORDER BY n2.Id_Notas_2 ASC",
            [$folio]
        )->getResultArray();

        $totalPiezas     = array_sum(array_column($detalle, 'cantidad'));
        $esMayoreo       = $forzarMayoreo || $totalPiezas >= 12;
        $sumaImportes    = 0;
        $sumaMenudeo     = 0;   // total a precio normal (menudeo), siempre
        $sumaMayoreo     = 0;   // total a precio mayoreo, siempre

        foreach ($detalle as &$linea) {
            $pMenudeo = (float)($linea['pUnitarioFinal']  ?? 0);
            $pMayoreo = (float)($linea['pUnitarioMFinal'] ?? 0);

            $linea['precio']  = $esMayoreo ? $pMayoreo : $pMenudeo;
            $linea['importe'] = $linea['cantidad'] * $linea['precio'];
            $sumaImportes    += $linea['importe'];
            $sumaMenudeo     += $linea['cantidad'] * $pMenudeo;
            $sumaMayoreo     += $linea['cantidad'] * $pMayoreo;
        }
        unset($linea);

        return [
            'detalle'      => $detalle,
            'totalPiezas'  => $totalPiezas,
            'sumaImportes' => $sumaImportes,
            'sumaMenudeo'  => $sumaMenudeo,
            'sumaMayoreo'  => $sumaMayoreo,
            'ahorro'       => $sumaMenudeo - $sumaMayoreo,
            'esMayoreo'    => $esMayoreo,
        ];
    }

    /**
     * Datos del usuario activo desde la sesión.
     */
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
