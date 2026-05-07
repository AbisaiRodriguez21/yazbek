<?php

namespace App\Controllers;

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
    public function caja(): string
    {
        $db   = \Config\Database::connect();
        $hoy  = date('Y-m-d');

        // Notas del día con joins (migrado del query original de caja/caja.php)
        $notas = $db->query(
            "SELECT n.folio,
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
             WHERE mn.fecha LIKE ?
             ORDER BY n.Id_Notas_1 DESC",
            ["{$hoy}%"]
        )->getResultArray();

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
    public function pagoVerificado(int $folio): \CodeIgniter\HTTP\Response
    {
        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota) {
            return $this->response->setStatusCode(404)->setBody('Nota no encontrada');
        }

        // Actualizar status a 5 (completada/pagada) — lógica de pagoVerificado.php
        \Config\Database::connect()->query(
            "UPDATE notas_1 SET status = 5 WHERE folio = ?",
            [$folio]
        );

        return $this->response->setBody('Pago Verificado Correctamente');
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

        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota) {
            return redirect()->to('/caja')->with('error', 'Nota no encontrada.');
        }

        $db = \Config\Database::connect();

        // Registrar el pago en montosnotas
        $db->query(
            "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, fecha) VALUES (?, ?, ?, ?, ?)",
            [$nota['Id_Notas_1'], $tipoPago, $monto, $cargos, date('Y-m-d H:i:s')]
        );

        // Marcar la nota como pagada
        $this->notaModel->marcarPagada($nota['Id_Notas_1']);

        return redirect()->to("/caja/folio/{$folio}")->with('success', 'Pago registrado correctamente.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/corte  —  Corte de caja
    // Migrado desde: caja/corte.php
    //
    // Muestra todas las notas pagadas con sus montos desglosados por tipo de pago.
    // ──────────────────────────────────────────────────────────────
    public function corte(): string
    {
        $db   = \Config\Database::connect();
        $hoy  = $this->request->getGet('fecha') ?? date('Y-m-d');

        // Query migrado de caja/corte.php — pagos del día por tipo
        $desglose = $db->query(
            "SELECT tp.descripcion AS tipopago,
                    SUM(mn.monto)  AS monto,
                    SUM(mn.cargos) AS cargos,
                    COUNT(mn.id)   AS cantidad
             FROM montosnotas mn
             INNER JOIN tipopago tp ON mn.idTipoPago = tp.id
             INNER JOIN notas_1  n  ON mn.idNotas = n.Id_Notas_1
             WHERE mn.fecha LIKE ? AND n.status = 5
             GROUP BY tp.id
             ORDER BY tp.id ASC",
            ["{$hoy}%"]
        )->getResultArray();

        // Notas completas del corte
        $notas = $db->query(
            "SELECT n.folio, c.nombre AS cliente, u.usuario AS vendedor,
                    n.total, n.verificado, n.status, tp.descripcion AS tipopago
             FROM notas_1 n
             LEFT JOIN clientes c  ON c.id = n.idCliente
             LEFT JOIN usuarios u  ON u.Id = n.idVendedor
             LEFT JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             LEFT JOIN tipopago tp ON mn.idTipoPago = tp.id
             WHERE n.fecha_inicial LIKE ? AND n.status = 5
             ORDER BY n.Id_Notas_1 DESC",
            ["{$hoy}%"]
        )->getResultArray();

        $totalDia = array_sum(array_column($notas, 'total'));

        return view('caja/corte', [
            'usuario'  => $this->getUsuarioSesion(),
            'desglose' => $desglose,
            'notas'    => $notas,
            'totalDia' => $totalDia,
            'fecha'    => $hoy,
        ]);
    }

    // GET /caja/corte/detalle
    public function corteDetalle(): string
    {
        return $this->corte();
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/cancelar/:folio  —  Cancela una nota desde caja
    // Migrado desde: caja/cancelar_nota.php
    // ──────────────────────────────────────────────────────────────
    public function cancelarNota(int $folio): \CodeIgniter\HTTP\RedirectResponse
    {
        $nota = $this->notaModel->getPorFolio($folio);

        if (! $nota) {
            return redirect()->to('/caja')->with('error', 'Nota no encontrada.');
        }

        // Solo se puede cancelar si no está ya completada (status=5)
        if ($nota['status'] === 5) {
            return redirect()->to("/caja/folio/{$folio}")
                             ->with('error', 'No se puede cancelar una nota ya pagada.');
        }

        $this->notaModel->cambiarStatus($nota['Id_Notas_1'], 3);

        return redirect()->to('/caja')->with('success', "Nota #{$folio} cancelada.");
    }

    // ──────────────────────────────────────────────────────────────
    // GET /caja/venta/:folio  —  Vista de nota para cobro en caja
    // Migrado desde: caja/venta_stp_2.php
    // ──────────────────────────────────────────────────────────────
    public function ventaStp2(int $folio): string
    {
        $nota        = $this->notaModel->getPorFolio($folio);
        $detalle     = $this->notaDetalleModel->getPorFolio($folio);
        $totalPiezas = $this->notaDetalleModel->totalPiezas($folio);

        $db        = \Config\Database::connect();
        $tipoPagos = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();
        $pagos     = $db->query(
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
             FROM Notas_2 nd
             LEFT JOIN productosYazbek p ON nd.estilo = p.sku
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
            'direccion'     => strtoupper(trim($this->request->getPost('direccion') ?? '')),
            'CP'            => trim($this->request->getPost('CP') ?? ''),
            'estado'        => strtoupper(trim($this->request->getPost('estado') ?? '')),
            'ciudad'        => strtoupper(trim($this->request->getPost('ciudad') ?? '')),
            'NombreEmpresa' => strtoupper(trim($this->request->getPost('NombreEmpresa') ?? '')),
            'razonSocial'   => strtoupper(trim($this->request->getPost('razonSocial') ?? '')),
            'comoNosConoce' => $this->request->getPost('comoNosConoce'),
            'fechaIngreso'  => date('Y-m-d'),
        ]);
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
            'direccion'     => strtoupper(trim($this->request->getPost('direccion') ?? '')),
            'CP'            => trim($this->request->getPost('CP') ?? ''),
            'estado'        => strtoupper(trim($this->request->getPost('estado') ?? '')),
            'ciudad'        => strtoupper(trim($this->request->getPost('ciudad') ?? '')),
            'NombreEmpresa' => strtoupper(trim($this->request->getPost('NombreEmpresa') ?? '')),
            'razonSocial'   => strtoupper(trim($this->request->getPost('razonSocial') ?? '')),
            'comoNosConoce' => $this->request->getPost('comoNosConoce'),
        ]);
        return redirect()->to('/caja/clientes')->with('success', 'Cliente actualizado.');
    }

    // POST /caja/clientes/eliminar
    public function eliminarCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $id = (int) $this->request->getPost('clienteDelete');
        if ($id) {
            $clienteModel = new \App\Models\ClienteModel();
            $clienteModel->delete($id);
        }
        return redirect()->to('/caja/clientes')->with('success', 'Cliente eliminado.');
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
