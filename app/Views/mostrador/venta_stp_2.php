<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2-bootstrap.min.css') ?>">
<style>
.carrito-table td, .carrito-table th { vertical-align: middle; white-space: normal; word-break: break-word; }
.carrito-table th.col-sku, .carrito-table td.col-sku { width: 12%; }
.carrito-table th.col-desc, .carrito-table td.col-desc { width: 43%; }
.carrito-table th.col-precio, .carrito-table td.col-precio { width: 13%; }
.carrito-table th.col-cant, .carrito-table td.col-cant { width: 9%; }
.carrito-table th.col-importe, .carrito-table td.col-importe { width: 13%; }
.carrito-table th.col-accion, .carrito-table td.col-accion { width: 10%; }
.btn-quitar { padding: 2px 8px; }
/* #panelResumen sin sticky — el layout scrollea completo */
.precio-tachado { text-decoration: line-through; color: #888; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $basePrefix = $base ?? 'mostrador'; ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Nota #<?= (int)$nota['folio'] ?> — Paso 2: Productos</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url($basePrefix) ?>"><?= $basePrefix === 'admin' ? 'Admin' : 'Mostrador' ?></a></li>
                    <li class="breadcrumb-item active">Agregar Productos</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <a href="<?= base_url($basePrefix . '/venta/' . (int)$nota['folio'] . '/cancelar') ?>"
               class="btn btn-outline-danger"
               onclick="return confirm('¿Cancelar esta nota?')">
                <i class="iconsminds-close"></i> Cancelar Nota
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Panel izquierdo: buscador -->
    <div class="col-md-3">
        <!-- Agregar producto -->
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Agregar Producto</div>
            <div class="card-body">
                <div class="form-group" id="selectProductoWrapper" style="position:relative; z-index:10;">
                    <label>Buscar por SKU / Descripción</label>
                    <select id="selectProducto" class="form-control select2" style="width:100%">
                        <option value="">Escribe para buscar...</option>
                    </select>
                </div>
                <div id="preciosProducto" style="display:none;" class="mb-3">
                    <div class="row no-gutters">
                        <div class="col-6 pr-1">
                            <div class="text-center py-1 px-1 rounded" style="background:#e8f4fd;border:1px solid #b8daff;">
                                <div style="font-size:0.6rem;color:#666;text-transform:uppercase;letter-spacing:.06em;">Menudeo</div>
                                <div class="font-weight-bold text-primary" id="precioMenudeo" style="font-size:0.85rem;">—</div>
                            </div>
                        </div>
                        <div class="col-6 pl-1">
                            <div class="text-center py-1 px-1 rounded" style="background:#e8f8ee;border:1px solid #b8dfc8;">
                                <div style="font-size:0.6rem;color:#666;text-transform:uppercase;letter-spacing:.06em;">Mayoreo</div>
                                <div class="font-weight-bold text-success" id="precioMayoreo" style="font-size:0.85rem;">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Cantidad <small id="stockDisponible" class="text-muted"></small></label>
                    <input type="number" id="inputCantidad" class="form-control" value="1" min="1" max="9999" step="1">
                </div>
                <button id="btnAgregar" class="btn btn-primary btn-block">
                    <i class="iconsminds-add"></i> Agregar
                </button>
                <div id="msgAgregar" class="mt-2"></div>
            </div>
        </div>
    </div>

    <!-- Panel derecho: resumen + carrito -->
    <div class="col-md-9">
        <div class="card mb-3" id="panelResumen">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Resumen</div>
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap">
                    <div>
                        <p class="mb-1"><strong>Folio:</strong> <?= (int)$nota['folio'] ?></p>
                        <p class="mb-1"><strong>Cliente:</strong> <?= esc($nota['cliente'] ?? '') ?></p>
                        <p class="mb-1"><strong>Vendedor:</strong> <?= esc($usuario['nombre']) ?></p>
                        <p class="mb-1"><strong>Fecha:</strong> <?= date('Y-m-d', strtotime($nota['fecha_inicial'])) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="mb-1">
                            <strong>Total piezas:</strong>
                            <span id="spTotalPiezas" class="badge badge-info"><?= (int)$totalPiezas ?></span>
                            <span id="badgeTipo" class="badge ml-1 <?= $esMayoreo ? 'badge-success' : 'badge-secondary' ?>">
                                <?= $esMayoreo ? 'Mayoreo' : 'Menudeo' ?>
                            </span>
                        </p>
                        <p class="mb-1">
                            <strong>Importe:</strong>
                            <span id="spImporte" class="text-success font-weight-bold">
                                $<?= number_format($sumaImportes, 2) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Productos en la Nota</div>
            <div class="card-body p-0 pt-2">
                <div style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-striped mb-0 carrito-table" id="tablaCarrito">
                        <thead>
                            <tr>
                                <th class="col-sku">SKU</th>
                                <th class="col-desc">Descripción</th>
                                <th class="text-right col-precio">Precio</th>
                                <th class="text-right col-cant">Cant.</th>
                                <th class="text-right col-importe">Importe</th>
                                <th class="col-accion"></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCarrito">
                            <?php foreach ($detalle as $d): ?>
                            <tr id="row-<?= (int)$d['Id_Notas_2'] ?>">
                                <td class="col-sku"><?= esc($d['sku']) ?></td>
                                <td class="col-desc"><?php
                                    $partesDesc = array_filter([
                                        $d['descripcion'] ?? $d['estilo'],
                                        $d['talla']  ?? '',
                                        $d['color']  ?? '',
                                    ], fn($p) => trim((string)$p) !== '');
                                    echo esc(implode(' — ', $partesDesc));
                                ?></td>
                                <td class="text-right col-precio">$<?= number_format($d['precio'], 2) ?></td>
                                <td class="text-right col-cant"><?= (int)$d['cantidad'] ?></td>
                                <td class="text-right col-importe">$<?= number_format($d['importe'], 2) ?></td>
                                <td class="text-center col-accion">
                                    <button class="btn btn-sm btn-outline-danger btn-quitar"
                                            data-id="<?= (int)$d['Id_Notas_2'] ?>"
                                            data-folio="<?= (int)$nota['folio'] ?>">
                                        &times;
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($detalle)): ?>
                            <tr id="rowVacio">
                                <td colspan="6" class="text-center text-muted py-4">Sin productos aún.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-right">
                <div id="alertSinProductos" class="text-danger small mb-2" style="display:none;">
                    <i class="iconsminds-danger"></i> Agrega al menos un producto antes de continuar.
                </div>
                <button id="btnSiguiente" type="button" class="btn btn-success"
                        data-url="<?= base_url($basePrefix . '/venta/' . (int)$nota['folio'] . '/confirmar') ?>">
                    Siguiente: Confirmar <i class="iconsminds-arrow-right ml-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden fields -->
<input type="hidden" id="hidFolio" value="<?= (int)$nota['folio'] ?>">
<input type="hidden" id="hidIdNota" value="<?= (int)$nota['Id_Notas_1'] ?>">
<input type="hidden" id="hidCsrfName" value="<?= csrf_token() ?>">
<input type="hidden" id="hidCsrfHash" value="<?= csrf_hash() ?>">

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/select2.full.js') ?>"></script>
<script>
var BURL = '<?= base_url() ?>';
var BASE_PREFIX = '<?= $basePrefix ?>';

(function initStp2() {
    if (typeof $.fn.select2 === 'undefined') { setTimeout(initStp2, 100); return; }

// Select2 con AJAX para buscar productos
// Fix Select2: prevenir scroll de página al enfocar el input de búsqueda
$('#selectProducto').on('select2:open', function() {
    var f = document.querySelector('.select2-container--open .select2-search__field');
    if (f) f.focus({ preventScroll: true });
});

$('#selectProducto').select2({
    theme: 'bootstrap',
    placeholder: 'Escribe SKU o descripción...',
    minimumInputLength: 2,
    dropdownParent: $('body'),
    language: {
        inputTooShort: function() { return 'Escribe al menos 2 caracteres...'; },
        searching: function() { return 'Buscando...'; },
        noResults: function() { return 'No se encontraron productos.'; }
    },
    ajax: {
        url: BURL + BASE_PREFIX + '/productos/buscar',
        type: 'POST',
        dataType: 'json',
        delay: 300,
        data: function(params) {
            var csrf = {};
            csrf[$('#hidCsrfName').val()] = $('#hidCsrfHash').val();
            return $.extend({ q: params.term }, csrf);
        },
        processResults: function(data) {
            return {
                results: data.map(function(p) {
                    var piezas = parseInt(p.piezas, 10) || 0;
                    var sinStock = piezas <= 0;
                    return {
                        id: sinStock ? '' : p.sku,   // id vacío = no seleccionable
                        text: sinStock
                            ? '[' + p.sku + '] ' + p.descripcion + '  — Sin existencias'
                            : '[' + p.sku + '] ' + p.descripcion + '  (' + piezas + ' pzs)',
                        sku: p.sku,
                        descripcion: p.descripcion,
                        piezas: piezas,
                        precio: parseFloat(p.precio) || 0,
                        precioMayoreo: parseFloat(p.precioMayoreo) || 0,
                        disabled: sinStock
                    };
                })
            };
        }
    },
    // Render personalizado: los sin stock aparecen en gris con ícono
    templateResult: function(item) {
        if (item.loading) return item.text;
        if (item.disabled) {
            return $('<span style="color:#aaa;cursor:not-allowed;">⊘ ' + item.text + '</span>');
        }
        return item.text;
    }
});

// Bloquear decimales en el campo cantidad
$('#inputCantidad').on('keydown', function(e) {
    if (e.key === '.' || e.key === ',') { e.preventDefault(); }
});
$('#inputCantidad').on('input', function() {
    var v = $(this).val();
    if (v.indexOf('.') !== -1 || v.indexOf(',') !== -1) {
        $(this).val(v.replace(/[.,].*/,''));
    }
});

// Mostrar stock y precios al seleccionar producto
$('#selectProducto').on('select2:select', function(e) {
    var data  = e.params.data;
    var stock = data.piezas || 0;

    // Mostrar precios
    $('#precioMenudeo').text('$' + (data.precio || 0).toFixed(2));
    $('#precioMayoreo').text('$' + (data.precioMayoreo || 0).toFixed(2));
    $('#preciosProducto').show();

    if (stock <= 0) {
        // Producto existe pero sin existencias — bloquear
        $('#stockDisponible').text('').removeClass('text-muted').addClass('text-danger');
        $('#stockDisponible').text('— Sin existencias disponibles');
        $('#inputCantidad').attr('max', 0).val(0);
        $('#btnAgregar').prop('disabled', true);
        $('#msgAgregar').html(
            '<span class="text-danger"><strong>Producto sin stock.</strong> ' +
            'Existe en el catálogo pero no hay piezas disponibles en este momento.</span>'
        );
    } else {
        $('#stockDisponible').text('— Stock disponible: ' + stock + ' pzs')
            .removeClass('text-danger').addClass('text-muted');
        $('#inputCantidad').attr('max', stock).val(Math.min(parseInt($('#inputCantidad').val(), 10) || 1, stock));
        $('#btnAgregar').prop('disabled', false);
        $('#msgAgregar').html('');
    }
});
$('#selectProducto').on('select2:clear select2:unselect', function() {
    $('#stockDisponible').text('');
    $('#inputCantidad').removeAttr('max');
    $('#preciosProducto').hide();
    $('#precioMenudeo').text('—');
    $('#precioMayoreo').text('—');
});

// Agregar producto
$('#btnAgregar').on('click', function() {
    var sku      = $('#selectProducto').val();
    var cantidad = parseInt($('#inputCantidad').val(), 10);
    var folio    = $('#hidFolio').val();
    var stockMax = parseInt($('#inputCantidad').attr('max'), 10);

    if (!sku) {
        $('#msgAgregar').html('<span class="text-danger">Selecciona un producto antes de agregar.</span>');
        return;
    }
    if (isNaN(cantidad) || cantidad < 1) {
        $('#msgAgregar').html('<span class="text-danger">La cantidad debe ser al menos 1.</span>');
        $('#inputCantidad').val(1).focus();
        return;
    }
    // Rechazar decimales — verificar el valor crudo del input
    var valorRaw = $('#inputCantidad').val();
    if (valorRaw.indexOf('.') !== -1 || valorRaw.indexOf(',') !== -1) {
        $('#msgAgregar').html('<span class="text-danger">La cantidad debe ser un número entero, sin decimales.</span>');
        $('#inputCantidad').val('').focus();
        return;
    }
    if (cantidad !== Math.floor(cantidad)) {
        $('#msgAgregar').html('<span class="text-danger">La cantidad debe ser un número entero, sin decimales.</span>');
        $('#inputCantidad').val('').focus();
        return;
    }
    if (!isNaN(stockMax) && cantidad > stockMax) {
        $('#msgAgregar').html('<span class="text-danger">No hay suficiente stock. Máximo disponible: ' + stockMax + ' pzs.</span>');
        $('#inputCantidad').val(stockMax).focus();
        return;
    }

    var csrf = {};
    csrf[$('#hidCsrfName').val()] = $('#hidCsrfHash').val();

    $.post(BURL + BASE_PREFIX + '/nota/agregarProducto', $.extend({
        sku: sku,
        cantidad: cantidad,
        folio: folio
    }, csrf), function(resp) {
        if (resp.success) {
            renderCarrito(resp);
            $('#msgAgregar').html('<span class="text-success">Producto agregado.</span>');
            $('#selectProducto').val(null).trigger('change');
            $('#inputCantidad').val(1);
            $('#preciosProducto').hide();
            $('#precioMenudeo').text('—');
            $('#precioMayoreo').text('—');
            $('#hidCsrfHash').val(resp.csrf_hash);
        } else {
            $('#msgAgregar').html('<span class="text-danger">' + (resp.message || 'Error al agregar.') + '</span>');
        }
    }, 'json').fail(function() {
        $('#msgAgregar').html('<span class="text-danger">Error de comunicación.</span>');
    });
});

// Quitar producto
$(document).on('click', '.btn-quitar', function() {
    var idLinea = $(this).data('id');
    var folio   = $(this).data('folio');
    var csrf = {};
    csrf[$('#hidCsrfName').val()] = $('#hidCsrfHash').val();

    $.post(BURL + BASE_PREFIX + '/nota/eliminarProducto', $.extend({
        idLinea: idLinea,
        folio: folio
    }, csrf), function(resp) {
        if (resp.success) {
            renderCarrito(resp);
            $('#hidCsrfHash').val(resp.csrf_hash);
        }
    }, 'json');
});

function fmt(n) {
    return '$' + parseFloat(n || 0).toFixed(2);
}

function renderCarrito(resp) {
    var filas = '';
    if (resp.detalle && resp.detalle.length > 0) {
        resp.detalle.forEach(function(d) {
            var partesDesc = [d.descripcion || d.estilo, d.talla, d.color].filter(function(p) {
                return p !== null && p !== undefined && String(p).trim() !== '';
            });
            filas += '<tr id="row-' + d.Id_Notas_2 + '">'
                + '<td class="col-sku">' + escHtml(d.sku) + '</td>'
                + '<td class="col-desc">' + escHtml(partesDesc.join(' — ')) + '</td>'
                + '<td class="text-right col-precio">' + fmt(d.precio) + '</td>'
                + '<td class="text-right col-cant">' + d.cantidad + '</td>'
                + '<td class="text-right col-importe">' + fmt(d.importe) + '</td>'
                + '<td class="text-center col-accion">'
                + '<button class="btn btn-sm btn-outline-danger btn-quitar" data-id="' + d.Id_Notas_2 + '" data-folio="' + resp.folio + '">&times;</button>'
                + '</td></tr>';
        });
    } else {
        filas = '<tr id="rowVacio"><td colspan="6" class="text-center text-muted py-4">Sin productos aún.</td></tr>';
    }
    $('#tbodyCarrito').html(filas);
    $('#spTotalPiezas').text(resp.totalPiezas || 0);
    $('#spImporte').text(fmt(resp.sumaImportes));

    if (resp.esMayoreo) {
        $('#badgeTipo').text('Mayoreo').removeClass('badge-secondary').addClass('badge-success');
    } else {
        $('#badgeTipo').text('Menudeo').removeClass('badge-success').addClass('badge-secondary');
    }
}

function escHtml(str) {
    return $('<div>').text(str).html();
}

// ── Guard "Siguiente: Confirmar" ──────────────────────────────
function hayProductos() {
    // Si el único tr es el de "Sin productos aún." → no hay
    return $('#tbodyCarrito tr:not(#rowVacio)').length > 0;
}

function actualizarBtnSiguiente() {
    var hay = hayProductos();
    $('#btnSiguiente').prop('disabled', !hay).toggleClass('btn-secondary', !hay).toggleClass('btn-success', hay);
    $('#alertSinProductos').toggle(!hay);
}

$('#btnSiguiente').on('click', function() {
    if (!hayProductos()) {
        $('#alertSinProductos').show();
        return;
    }
    window.location.href = $(this).data('url');
});

// Estado inicial al cargar la página
actualizarBtnSiguiente();

// Actualizar estado después de cada cambio en el carrito
var _renderCarritoOrig = renderCarrito;
renderCarrito = function(resp) {
    _renderCarritoOrig(resp);
    actualizarBtnSiguiente();
};

})(); // fin initStp2
</script>
<?= $this->endSection() ?>
