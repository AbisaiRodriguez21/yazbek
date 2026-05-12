<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// =============================================================
// RUTAS PÚBLICAS - Autenticación
// =============================================================
$routes->get('/', 'AuthController::loginPage');
$routes->get('/login', 'AuthController::loginPage');
$routes->post('/login', 'AuthController::login');
$routes->get('/logout', 'AuthController::logout');

// =============================================================
// RUTAS DE ADMIN (acceso = 1)
// =============================================================
$routes->group('admin', ['filter' => 'role:1'], function ($routes) {
    // Dashboard
    $routes->get('/', 'AdminController::index');
    $routes->get('dashboard', 'AdminController::index');

    // Usuarios
    $routes->get('usuarios', 'AdminController::usuarios');
    $routes->post('usuarios/crear', 'AdminController::crearUsuario');
    $routes->post('usuarios/eliminar/(:num)', 'AdminController::eliminarUsuario/$1');
    $routes->post('usuarios/liberar/(:num)', 'AdminController::liberarUsuario/$1');

    // Inventario de productos
    $routes->get('inventario', 'AdminController::inventario');
    $routes->post('inventario/ajax', 'AdminController::ajaxInventario');

    // Mensajes / avisos de admin
    $routes->get('mensajes', 'AdminController::mensajes');
    $routes->post('mensajes/guardar', 'AdminController::guardarMensaje');
    $routes->post('mensajes/subir', 'AdminController::subirImagenMensaje');
    $routes->get('mensajes/eliminar/(:num)', 'AdminController::eliminarMensaje/$1');

    // Clientes (admin también puede gestionar clientes, igual que en el original)
    $routes->get('clientes', 'AdminController::clientes');
    $routes->get('clientes/datatable', 'AdminController::clientesDatatable');
    $routes->post('clientes/crear', 'AdminController::crearCliente');
    $routes->post('clientes/actualizar/(:num)', 'AdminController::actualizarCliente/$1');
    $routes->post('clientes/eliminar', 'AdminController::eliminarCliente');
    $routes->post('clientes/datos', 'AdminController::obtieneDatosCliente');

    // Reporte diario — GET muestra form, POST exporta XLS con rango de fechas
    $routes->get('reportediario', 'AdminController::reporteDiarioPage');
    $routes->post('reportediario', 'AdminController::reporteDiario');
    $routes->get('reportediario/dia', 'AdminController::reporteDiarioDia');

    // Videos
    $routes->get('videos', 'AdminController::videos');
    $routes->post('videos/subir', 'AdminController::subirVideo');

    // Caja admin (corte, vista corte2)
    $routes->get('caja', 'AdminController::caja');
    $routes->post('caja/ajax', 'AdminController::cajaAjax');
    $routes->post('caja/verificar', 'AdminController::cajaVerificar');
    $routes->post('caja/cancelar', 'AdminController::cajaCancelar');
    $routes->get('caja/corte', 'AdminController::cajaCorte');
    $routes->post('caja/corte', 'AdminController::cajaCorte');

    // Importar productos CSV
    $routes->get('importar', 'AdminController::importar');
    $routes->post('importar/procesar', 'AdminController::procesarImportacion');

    // Ajax usuarios (edición inline de contraseña)
    $routes->post('ajax/usuarios', 'AdminController::ajaxUsuarios');

    // Venta desde admin
    $routes->get('venta', 'AdminController::venta');

    // Exportar base de datos completa (BaseCompleta.php)
    $routes->get('exportar', 'AdminController::exportar');

    // Importar CSV desde vista de inventario
    $routes->post('importar/subir', 'AdminController::procesarImportacion');
});

// =============================================================
// RUTAS DE MOSTRADOR (acceso = 3 ó 4)
// =============================================================
$routes->group('mostrador', ['filter' => 'role:3,4'], function ($routes) {
    // Dashboard mostrador
    $routes->get('/', 'MostradorController::index');

    // ── Flujo de venta de 3 pasos ──
    // Paso 1: Seleccionar cliente e iniciar nota
    $routes->get('venta', 'MostradorController::ventaStp1');
    $routes->post('venta', 'MostradorController::ventaStp1Post');

    // Paso 2: Agregar productos al carrito
    $routes->get('venta/(:num)/productos', 'MostradorController::ventaStp2/$1');
    $routes->post('venta/(:num)/productos', 'MostradorController::ventaStp2Post/$1');

    // Paso 3: Confirmar y cerrar nota
    $routes->get('venta/(:num)/confirmar', 'MostradorController::ventaStp3/$1');
    $routes->post('venta/(:num)/confirmar', 'MostradorController::ventaStp3Post/$1');

    // ── Operaciones sobre notas ──
    $routes->get('venta/(:num)/duplicar', 'MostradorController::duplicar/$1');
    $routes->get('venta/(:num)/cancelar', 'MostradorController::cancelar/$1');

    // ── Clientes ──
    $routes->get('clientes', 'MostradorController::clientes');
    $routes->get('clientes/datatable', 'MostradorController::clientesDatatable');
    $routes->post('clientes/buscar', 'MostradorController::buscarClientes');
    $routes->post('clientes/datos', 'MostradorController::obtieneDatosCliente');
    $routes->post('clientes/crear', 'MostradorController::crearCliente');
    $routes->post('clientes/actualizar/(:num)', 'MostradorController::actualizarCliente/$1');
    $routes->post('clientes/eliminar', 'MostradorController::eliminarCliente');
    $routes->get('clientes/ciudades', 'MostradorController::cargaCiudades');

    // ── Anticipos ──
    $routes->get('anticipos', 'MostradorController::anticipos');
    $routes->get('anticipos/folio/(:num)', 'MostradorController::muestraFolioAnticipo/$1');

    // ── Búsqueda de productos (select2 autocomplete) ──
    $routes->post('productos/buscar', 'MostradorController::buscarProductos');

    // ── Productos en nota (AJAX) ──
    $routes->post('nota/agregarProducto', 'MostradorController::agregarProducto');
    $routes->post('nota/agregarProductoPM', 'MostradorController::agregarProductoPM');
    $routes->post('nota/agregarProductoR', 'MostradorController::agregarProductoR');
    $routes->post('nota/eliminarProducto', 'MostradorController::eliminarProducto');
    $routes->post('nota/addRows', 'MostradorController::addRows');
    $routes->post('nota/addRowsAnt', 'MostradorController::addRowsAnt');

    // ── Pagos y montos ──
    $routes->post('nota/addPagosMontos', 'MostradorController::addPagosMontos');
    $routes->post('nota/cancelarPago', 'MostradorController::cancelarPago');
    $routes->post('nota/verificarPago', 'MostradorController::verificarPago');

    // ── AJAX general (búsqueda de notas/folios) ──
    $routes->post('ajax', 'MostradorController::ajax');

    // ── Inventario (solo lectura) ──
    $routes->get('inventario', 'MostradorController::inventario');

    // ── Tipos de precio ──
    $routes->get('mayoreo',  'MostradorController::mayoreo');
    $routes->post('mayoreo', 'MostradorController::mayoreoPost');
    $routes->get('menudeo',  'MostradorController::menudeo');

    // ── Consulta de notas / metas ──
    $routes->get('consulta/datatable', 'MostradorController::notasDatatable');
    $routes->get('consulta', 'MostradorController::consultaStp1');
    $routes->get('metas', 'MostradorController::metas');
});

// =============================================================
// RUTAS DE CAJA (acceso = 2)
// =============================================================
$routes->group('caja', ['filter' => 'role:2'], function ($routes) {
    // Dashboard caja
    $routes->get('/', 'CajaController::index');

    // Clientes desde caja
    $routes->get('clientes', 'CajaController::clientes');
    $routes->get('clientes/datatable', 'CajaController::clientesDatatable');
    $routes->post('clientes/crear', 'CajaController::crearCliente');
    $routes->post('clientes/actualizar/(:num)', 'CajaController::actualizarCliente/$1');
    $routes->post('clientes/eliminar', 'CajaController::eliminarCliente');

    // Módulo caja (lista de notas a cobrar / verificar)
    $routes->get('cobrar', 'CajaController::caja');
    $routes->get('cobrar/ajax/(:num)', 'CajaController::cobrarFolioAjax/$1');

    // Consulta por folio
    $routes->get('folio/(:num)', 'CajaController::porFolio/$1');

    // Pago verificado / confirmar pago
    $routes->get('pago/verificado/(:num)', 'CajaController::pagoVerificado/$1');
    $routes->post('pago/procesar', 'CajaController::procesarPago');

    // Corte de caja
    $routes->get('corte', 'CajaController::corte');
    $routes->post('corte', 'CajaController::corte');
    $routes->get('corte/exportar', 'CajaController::exportarCorte');
    $routes->get('corte/detalle', 'CajaController::corteDetalle');

    // Cancelar nota desde caja
    $routes->get('cancelar/(:num)', 'CajaController::cancelarNota/$1');

    // Venta stp 2 desde caja (cobro)
    $routes->get('venta/(:num)', 'CajaController::ventaStp2/$1');
    $routes->post('venta/(:num)', 'CajaController::ventaStp2Post/$1');

    // Consulta de folios (accesible también desde caja — igual que original: roles 1,2,3,4)
    $routes->get('consulta', 'CajaController::consulta');
    $routes->get('consulta/datatable', 'CajaController::consultaDatatable');

    // Exportar corte de caja (accesible desde módulo caja)
    $routes->get('cortecaja', 'ReportesController::corteCaja');
    $routes->get('cortecaja2', 'ReportesController::corteCaja2');
});

// =============================================================
// RUTAS DE REPORTES (solo admin = 1)
// =============================================================
$routes->group('reportes', ['filter' => 'role:1'], function ($routes) {
    $routes->get('/', 'ReportesController::index');
    $routes->get('cortecaja', 'ReportesController::corteCaja');
    $routes->get('cortecaja2', 'ReportesController::corteCaja2');
    $routes->get('excel', 'ReportesController::generarExcel');
});
