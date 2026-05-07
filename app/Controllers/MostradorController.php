<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\MensajeAdminModel;
use App\Models\NotaDetalleModel;
use App\Models\NotaModel;
use App\Models\ProductoModel;
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
        return view('mostrador/index', [
            'usuario' => $this->getUsuarioSesion(),
            'banner'  => $this->mensajeModel->getBanner(),
            'mensaje' => $this->mensajeModel->getMensaje(),
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
            'usuario'  => $this->getUsuarioSesion(),
            'clientes' => $this->clienteModel->getTodos(),
            'error'    => session()->getFlashdata('error'),
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
            'NombreCliente'=> $cliente['nombre'] ?? '',
            'vendedor'     => $vendedor,
            'telefono'     => $cliente['telefono'] ?? '',
            'direccion'    => $cliente['direccion'] ?? '',
            'email'        => $cliente['mail']      ?? '',
        ]);

        // Marcar al vendedor como con ticket abierto
        $this->usuarioModel->activarBandera($idVendedor);

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
    public function ventaStp3(int $folio): string
    {
        $nota             = $this->notaModel->getPorFolio($folio);
        $esMayoreoForzado = session()->get("nota_{$folio}_tipo") === 'mayoreo';

        $db      = \Config\Database::connect();
        $carrito = $this->getCarritoData($folio, $db, $esMayoreoForzado);

        $tipoPagos = $db->query("SELECT * FROM tipopago ORDER BY id ASC")->getResultArray();

        return view('mostrador/venta_stp_3', [
            'usuario'      => $this->getUsuarioSesion(),
            'nota'         => $nota,
            'folio'        => $folio,
            'detalle'      => $carrito['detalle'],
            'totalPiezas'  => $carrito['totalPiezas'],
            'sumaImportes' => $carrito['sumaImportes'],
            'esMayoreo'    => $carrito['esMayoreo'],
            'tipoPagos'    => $tipoPagos,
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
        $db = \Config\Database::connect();

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
            $sumaImportes = $subTotal;
            $cargoTarjeta = 0;
            $subTotal2    = $subTotal + $iva;
            $impresion    = 1;
            $cargoImpresion = 0;

            $listaPagosRaw = json_decode($this->request->getPost('pagos') ?? '[]', true) ?: [];
            $listaPagos = [];
            foreach ($listaPagosRaw as $p) {
                $listaPagos[] = [
                    'tipo'    => $p['tipo'],
                    'monto'   => $p['monto'],
                    'cargo'   => $p['cargo'] ?? 0,
                    'anticipo'=> $p['anticipo'] ?? 0,
                ];
            }
        }

        $fechaHoraActual = date('Y-m-d H:i:s');

        // ── Registrar pagos en montosnotas ──
        if ($subTotal <= 0) {
            $db->query(
                "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, montoEfectivoIva, fecha)
                 VALUES (?, 1, 0, 0, 0, 0, ?)",
                [$idNotas1, $fechaHoraActual]
            );
        } else {
            foreach ($listaPagos as $pago) {
                $db->query(
                    "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, montoEfectivoIva, fecha)
                     VALUES (?, ?, ?, ?, ?, 0, ?)",
                    [$idNotas1, $pago['tipo'], $pago['monto'], $pago['cargo'], $pago['anticipo'], $fechaHoraActual]
                );
            }
        }

        // ── Actualizar notas_1 ──
        $db->query(
            "UPDATE notas_1
             SET sumaImportes=?, subTotal=?, cargoTarjeta=?, subTotal2=?,
                 iva=?, total=?, descuento=?, status=?,
                 factura=?, tipoImpresion=?, cargoPorImpresion=?,
                 totalPiezas=?, fecha_final=?
             WHERE folio=?",
            [
                $sumaImportes, $subTotal, $cargoTarjeta, $subTotal2,
                $iva, $total, $descuento, $idEstatus,
                $factura, $impresion, $cargoImpresion,
                $totalPiezas, $fechaHoraActual, $folioN1,
            ]
        );

        // ── Liberar bandera del vendedor ──
        $this->usuarioModel->liberarBandera((int) session()->get('user_id'));

        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode(['success' => true]));
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
        $cantidad = (int)   ($this->request->getPost('cantidad') ?: 1);

        // Formato nuevo (venta_stp_2): solo sku + cantidad + folio
        // El controlador resuelve el resto desde productosyazbek
        $sku = (string) $this->request->getPost('sku');

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

        if ((int)$producto['piezas'] < $cantidad) {
            return $this->response->setContentType('application/json')
                        ->setBody(json_encode(['success' => false, 'message' => 'Stock insuficiente.']));
        }

        $idN1      = (int)$nota['Id_Notas_1'];
        $estilo    = $sku;
        $precio_m  = (float)$producto['precio'];
        $precioM   = (float)($producto['precioMayoreo'] ?? $producto['precio']);
        $descripcion = $producto['Descripcion_corta'] ?? $sku;

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
                "INSERT INTO notas_2 (Id_Notas_1, cantidad, estilo, descripcion, pUnitario, folio, sku, pUnitarioM)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$idN1, $cantidad, $estilo, $descripcion, $precio_m, $folio, $sku, $precioM]
            );
        }

        // Descontar stock
        $db->query(
            "UPDATE productosyazbek SET piezas = piezas - ? WHERE sku = ?",
            [$cantidad, $sku]
        );

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

        if ($linea) {
            // Restaurar stock — usar sku si estilo no vino en POST
            $skuDevolver = $estilo ?: ($linea['sku'] ?: $linea['estilo']);
            $db->query(
                "UPDATE productosyazbek SET piezas = piezas + ? WHERE sku = ?",
                [$linea['cantidad'], $skuDevolver]
            );
        }

        // Eliminar línea
        $db->query("DELETE FROM notas_2 WHERE Id_Notas_2 = ?", [$idN2]);

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

        return $this->response->setBody('OK');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/cancelarPago  —  Cancela un pago registrado
    // ──────────────────────────────────────────────────────────────
    public function cancelarPago(): \CodeIgniter\HTTP\Response
    {
        $idMonto = (int) $this->request->getPost('idMonto');
        \Config\Database::connect()->query("DELETE FROM montosnotas WHERE id = ?", [$idMonto]);
        return $this->response->setBody('OK');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /mostrador/nota/verificarPago  —  Marca nota como pagada
    // ──────────────────────────────────────────────────────────────
    public function verificarPago(): \CodeIgniter\HTTP\Response
    {
        $folio = (int) $this->request->getPost('folio');
        $nota  = $this->notaModel->getPorFolio($folio);

        if ($nota) {
            $this->notaModel->marcarPagada($nota['Id_Notas_1']);
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

        // Crear nueva cabecera
        $this->notaModel->insert([
            'idCliente'    => $notaOrigen['idCliente'],
            'idVendedor'   => $idVendedor,
            'folio'        => $nuevoFolio,
            'status'       => 3,
            'NombreCliente'=> $notaOrigen['NombreCliente'],
            'vendedor'     => session()->get('user_nombre'),
            'telefono'     => $notaOrigen['telefono'],
            'direccion'    => $notaOrigen['direccion'],
            'email'        => $notaOrigen['email'],
        ]);

        $nuevaNota = $this->notaModel->getPorFolio($nuevoFolio);

        // Copiar detalle
        foreach ($detalleOrig as $linea) {
            unset($linea['Id_Notas_2']);
            $linea['folio']      = $nuevoFolio;
            $linea['Id_Notas_1'] = $nuevaNota['Id_Notas_1'];
            $this->notaDetalleModel->insert($linea);
        }

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
            $db->query(
                "UPDATE productosyazbek SET piezas = piezas + ? WHERE sku = ?",
                [$linea['cantidad'], $linea['estilo']]
            );
        }

        $this->notaModel->cambiarStatus($nota['Id_Notas_1'], 3);
        $this->usuarioModel->liberarBandera((int) session()->get('user_id'));

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
        $q        = $this->request->getPost('q') ?? '';
        $db       = \Config\Database::connect();

        $rows = $db->query(
            "SELECT sku, Descripcion_corta AS descripcion, piezas, precio, precioMayoreo
             FROM productosyazbek
             WHERE (sku LIKE ? OR Descripcion_corta LIKE ?) AND piezas > 0
             ORDER BY Descripcion_corta ASC
             LIMIT 30",
            ["%{$q}%", "%{$q}%"]
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

        return redirect()->to('/mostrador/clientes')->with('success', 'Cliente actualizado.');
    }

    // POST /mostrador/clientes/eliminar
    public function eliminarCliente(): \CodeIgniter\HTTP\RedirectResponse
    {
        $id = (int) $this->request->getPost('clienteDelete');
        if ($id) {
            $this->clienteModel->delete($id);
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
        return view('mostrador/anticipos', [
            'usuario'   => $this->getUsuarioSesion(),
            'anticipos' => $this->notaModel->getAnticipos(),
        ]);
    }

    // GET /mostrador/anticipos/folio/:folio  —  AJAX: datos del anticipo
    public function muestraFolioAnticipo(int $folio): \CodeIgniter\HTTP\Response
    {
        $nota = $this->notaModel->getPorFolio($folio);
        return $this->response
                    ->setContentType('application/json')
                    ->setBody(json_encode($nota));
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
            'NombreCliente'=> $cliente['nombre'] ?? '',
            'vendedor'     => $vendedor,
            'telefono'     => $cliente['telefono'] ?? '',
            'direccion'    => $cliente['direccion'] ?? '',
            'email'        => $cliente['mail']      ?? '',
        ]);

        $this->usuarioModel->activarBandera($idVendedor);

        // Marcar esta nota como MAYOREO en sesión (precio mayoreo siempre)
        session()->set("nota_{$folio}_tipo", 'mayoreo');

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

        $db = \Config\Database::connect();

        // Total sin filtro
        $total = $db->query("SELECT COUNT(*) AS total FROM notas_1")->getRow()->total;

        // Base query
        $baseSql = "FROM notas_1 n
                    LEFT JOIN clientes c ON c.id = n.idCliente
                    LEFT JOIN usuarios u ON u.Id = n.idVendedor
                    LEFT JOIN status s ON s.id = n.status";

        $whereClauses = [];
        $params = [];

        if ($search !== '') {
            $s = '%' . $search . '%';
            $whereClauses[] = "(n.folio LIKE ? OR c.nombre LIKE ? OR u.usuario LIKE ?)";
            $params = array_merge($params, [$s, $s, $s]);
        }

        $where = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

        $countResult = $db->query("SELECT COUNT(*) AS cnt {$baseSql} {$where}", $params)->getRow();
        $filtered = $countResult->cnt ?? 0;

        $data = $db->query(
            "SELECT n.folio, n.fecha_inicial,
                    COALESCE(c.nombre, n.NombreCliente, '—') AS cliente,
                    COALESCE(u.usuario, n.vendedor, '—') AS vendedor,
                    COALESCE(n.tipoPago, '—') AS tipopago,
                    n.total, n.status AS idstatus,
                    COALESCE(s.nombre, '') AS status_nombre,
                    n.verificado
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

        $notasDelMes = $db->query(
            "SELECT n.folio, n.fecha_inicial, c.nombre AS cliente, n.total, n.status
             FROM notas_1 n
             LEFT JOIN clientes c ON c.id = n.idCliente
             WHERE n.idVendedor = ? AND n.fecha_inicial LIKE ? AND n.status = 5
             ORDER BY n.folio DESC",
            [$usuario['Id'], "{$mes}%"]
        )->getResultArray();

        $piezasRow = $db->query(
            "SELECT SUM(n2.cantidad) AS total
             FROM notas_2 n2
             INNER JOIN notas_1 n ON n2.folio = n.folio
             WHERE n.idVendedor = ? AND n.fecha_inicial LIKE ? AND n.status = 5",
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
        $detalle     = $db->query(
            "SELECT * FROM notas_2 WHERE folio = ? ORDER BY Id_Notas_2 ASC",
            [$folio]
        )->getResultArray();

        $totalPiezas  = array_sum(array_column($detalle, 'cantidad'));
        $esMayoreo    = $forzarMayoreo || $totalPiezas >= 12;
        $sumaImportes = 0;

        foreach ($detalle as &$linea) {
            $linea['precio']  = $esMayoreo
                ? (float)($linea['pUnitarioM'] ?? $linea['pUnitario'] ?? 0)
                : (float)($linea['pUnitario']  ?? 0);
            $linea['importe'] = $linea['cantidad'] * $linea['precio'];
            $sumaImportes    += $linea['importe'];
        }
        unset($linea);

        return [
            'detalle'      => $detalle,
            'totalPiezas'  => $totalPiezas,
            'sumaImportes' => $sumaImportes,
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
