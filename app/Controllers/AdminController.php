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
        $hoy = date('Y-m-d');

        // Contadores de notas del día (equivalente a los queries del index.php original)
        $db = \Config\Database::connect();

        $totalHoy       = $db->query("SELECT COUNT(*) AS total FROM notas_1 WHERE fecha_inicial LIKE ?", ["{$hoy}%"])->getRow()->total;
        $totalAnticipo  = $db->query("SELECT COUNT(*) AS total FROM notas_1 WHERE status = 4 AND fecha_inicial LIKE ?", ["{$hoy}%"])->getRow()->total;
        $totalCancelado = $db->query("SELECT COUNT(*) AS total FROM notas_1 WHERE status = 3 AND fecha_inicial LIKE ?", ["{$hoy}%"])->getRow()->total;
        $totalPagado    = $db->query("SELECT COUNT(*) AS total FROM notas_1 WHERE verificado = 'Pagado' AND fecha_inicial LIKE ?", ["{$hoy}%"])->getRow()->total;

        // Órdenes recientes del día con datos del cliente
        $recientes = $db->query(
            "SELECT n.Id_Notas_1, n.folio, n.fecha_inicial, n.total,
                    n.status AS idstatus, n.idCliente,
                    COALESCE(c.nombre, '—') AS nombre
             FROM notas_1 n
             LEFT JOIN clientes c ON n.idCliente = c.id
             WHERE n.fecha_inicial LIKE ?
             ORDER BY n.Id_Notas_1 DESC
             LIMIT 20",
            ["{$hoy}%"]
        )->getResultArray();

        // Lo más vendido
        $masVendidos = $this->notaDetalleModel->getMasVendidos(30);

        return view('admin/index', [
            'usuario'        => $this->getUsuarioSesion(),
            'totalHoy'       => $totalHoy,
            'totalAnticipo'  => $totalAnticipo,
            'totalCancelado' => $totalCancelado,
            'totalPagado'    => $totalPagado,
            'recientes'      => $recientes,
            'masVendidos'    => $masVendidos,
            'error'          => session()->getFlashdata('error'),
            'success'        => session()->getFlashdata('success'),
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
    public function subirImagenMensaje(): void
    {
        $orden = $this->request->getPost('orden') ?? '0';

        $img = $this->request->getFile('archivo0');
        if ($img && $img->isValid() && ! $img->hasMoved()) {
            $fecha   = date('dmY_His');
            $nombre  = 'imgAdmin/' . $fecha . '_' . $img->getClientName();
            $destino = FCPATH . 'imgAdmin/';

            if (! is_dir($destino)) {
                mkdir($destino, 0777, true);
            }

            $img->move($destino, $fecha . '_' . $img->getClientName());
            echo $nombre . '*-' . $orden;
        } else {
            echo 'no *-' . $orden;
        }
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
            $clienteModel->delete($id);
        }
        return redirect()->to('/admin/clientes')->with('success', 'Cliente eliminado.');
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
            "SELECT n1.fecha_inicial, n1.folio, n2.sku AS estilo, p.Descripcion_corta,
                    n2.cantidad AS totalPiezas, n2.cantidad
             FROM notas_1 n1
             INNER JOIN notas_2 n2 ON n1.folio = n2.folio
             LEFT JOIN productosyazbek p ON p.sku = n2.sku
             WHERE n1.fecha_inicial >= ? AND n1.fecha_inicial <= ? AND n1.status != 3
             ORDER BY n1.folio ASC",
            [$desde, $hasta]
        )->getResultArray();

        $html  = '<html><head><meta charset="utf-8"></head><body>';
        $html .= '<table border="1">';
        $html .= '<tr><td colspan="6">Resultado de la fecha: ' . $desde . ' hasta: ' . $hasta . '</td></tr>';
        $html .= '<tr><td>Fecha</td><td>Folio</td><td>SKU</td><td>Descripcion Corta</td><td>Total Piezas</td><td>Cantidad</td></tr>';
        foreach ($filas as $f) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($f['fecha_inicial']) . '</td>';
            $html .= '<td>' . htmlspecialchars($f['folio']) . '</td>';
            $html .= '<td>' . htmlspecialchars($f['estilo']) . '</td>';
            $html .= '<td>' . htmlspecialchars($f['Descripcion_corta'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($f['totalPiezas']) . '</td>';
            $html .= '<td>' . htmlspecialchars($f['cantidad']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';

        return $this->response
            ->setHeader('Last-Modified', gmdate('D,d M Y H:i:s') . ' GMT')
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Content-Type', 'application/x-msexcel')
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
    public function cajaAjax(): string
    {
        $folio = (int) $this->request->getPost('folio');
        $db    = \Config\Database::connect();

        try {
            // Columnas correctas del original:
            //   usuarios  → u.usuario  (no u.nombre)
            //   notas_1   → n.referencia, n.verificado, n.subTotal, n.total, etc.
            $nota = $db->query(
                "SELECT n.folio, n.fecha_inicial, n.subTotal, n.descuento, n.iva, n.total,
                        n.referencia, n.verificado, s.nombre AS status,
                        c.nombre AS cliente, c.telefono,
                        u.usuario AS vendedor
                 FROM notas_1 n
                 LEFT JOIN clientes c ON n.idCliente  = c.id
                 LEFT JOIN usuarios u ON n.idVendedor = u.Id
                 LEFT JOIN status   s ON n.status     = s.id
                 WHERE n.folio = ?",
                [$folio]
            )->getRowArray();

            if (! $nota) {
                return '<p class="text-danger">No se encontró el folio <strong>' . $folio . '</strong>.</p>';
            }

            // Pagos — usando Id_Notas_1 del registro encontrado
            $pagos = $db->query(
                "SELECT tp.descripcion AS tipopago, mn.monto, mn.cargos, mn.anticipo
                 FROM montosnotas mn
                 LEFT JOIN tipopago tp ON mn.idTipoPago = tp.id
                 WHERE mn.idNotas = (SELECT Id_Notas_1 FROM notas_1 WHERE folio = ?)",
                [$folio]
            )->getResultArray();

            // Productos — columnas reales de notas_2: estilo (SKU), pUnitario (precio)
            $detalles = $db->query(
                "SELECT nd.estilo AS sku,
                        CONCAT(p.estilo, '-', p.Descripcion_Larga, '-', p.Talla, '-', p.Color) AS descripcion,
                        nd.cantidad, nd.pUnitario AS precio, nd.importe
                 FROM notas_2 nd
                 LEFT JOIN productosyazbek p ON p.sku = nd.estilo
                 WHERE nd.folio = ?",
                [$folio]
            )->getResultArray();

        } catch (\Exception $e) {
            log_message('error', 'cajaAjax error: ' . $e->getMessage());
            return '<p class="text-danger">Error al consultar el folio. Detalle: ' . esc($e->getMessage()) . '</p>';
        }

        // ── HTML — igual al layout del original ──
        $html  = '<table width="100%">';
        $html .= '<tr>';
        $html .= '<td><strong>Fecha:</strong></td><td>' . date('Y-m-d h:i A', strtotime($nota['fecha_inicial'])) . '</td>';
        $html .= '<td><strong>Nombre Cliente:</strong></td><td>' . esc($nota['cliente']) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td><strong>Estatus:</strong></td><td>' . esc($nota['status']) . '</td>';
        $html .= '<td><strong>Folio:</strong></td><td>' . $nota['folio'] . '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        // Calculadora (igual al original)
        if ($pagos) {
            foreach ($pagos as $pago) {
                $html .= '<div class="col-sm-4 col-lg-4 mt-3">';
                $html .= '<div class="font-w600 push-5">Calculadora</div>';
                $html .= '<table><tr>';
                $html .= '<td>Ingresa la cantidad</td><td>Total</td><td>Cambio</td>';
                $html .= '</tr><tr>';
                $html .= '<td><input class="font-w600" type="text" id="importe" placeholder="Ingresa la cantidad" onblur="blurFunction()" /></td>';
                $html .= '<td><input class="font-w600" type="text" id="pagar" disabled value="' . $nota['total'] . '" /></td>';
                $html .= '<td><input type="text" id="resultado" disabled /></td>';
                $html .= '<input type="hidden" id="pagar2" value="' . $nota['total'] . '" />';
                $html .= '</tr></table>';
                $html .= '</div>';
                break; // Solo mostrar calculadora una vez
            }
        }

        // Tabla de productos
        $html .= '<div class="line mt-3"></div>';
        $html .= '<table class="table" id="tabla">';
        $html .= '<thead><tr>';
        $html .= '<th>CANTIDAD</th><th>DESCRIPCION</th><th>PRECIO UNITARIO</th><th>IMPORTE</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($detalles as $d) {
            $html .= '<tr>';
            $html .= '<td>' . $d['cantidad'] . '</td>';
            $html .= '<td>' . esc($d['descripcion'] ?? $d['sku']) . '</td>';
            $html .= '<td>$ ' . number_format($d['precio'], 2) . '</td>';
            $html .= '<td>$ ' . number_format($d['importe'], 2) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        // Totales — igual al original
        $html .= '<tr><td colspan="3" class="text-right"><strong>Descuento</strong></td><td>$ ' . number_format($nota['descuento'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right no-border"><strong>SubTotal</strong></td><td>$ ' . number_format($nota['subTotal'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>IVA</strong></td><td>$ ' . number_format($nota['iva'] ?? 0, 2) . '</td></tr>';
        $html .= '<tr><td colspan="3" class="text-right"><strong>TOTAL</strong></td><td>$ ' . number_format($nota['total'] ?? 0, 2) . '</td></tr>';
        $html .= '</table>';

        // Script calculadora
        $html .= '<script>function blurFunction(){';
        $html .= 'var pagar=parseFloat(document.getElementById("pagar2").value);';
        $html .= 'var importe=parseFloat(document.getElementById("importe").value);';
        $html .= 'document.getElementById("resultado").value=(importe-pagar).toFixed(2);}';
        $html .= '</script>';

        return $html;
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
