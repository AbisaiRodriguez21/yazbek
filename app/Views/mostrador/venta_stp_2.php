<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2-bootstrap.min.css') ?>">
<style>
.carrito-table td, .carrito-table th { vertical-align: middle; }
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
               class="btn btn-outline-danger mr-2"
               onclick="return confirm('¿Cancelar esta nota?')">
                <i class="iconsminds-close"></i> Cancelar Nota
            </a>
            <a href="<?= base_url($basePrefix . '/venta/' . (int)$nota['folio'] . '/confirmar') ?>"
               class="btn btn-success">
                <i class="iconsminds-arrow-right"></i> Confirmar
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Panel izquierdo: info + buscador -->
    <div class="col-md-4">
        <div class="card mb-3" id="panelResumen">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Resumen</div>
            <div class="card-body">
                <p class="mb-1"><strong>Folio:</strong> <?= (int)$nota['folio'] ?></p>
                <p class="mb-1"><strong>Cliente:</strong> <?= esc($nota['cliente'] ?? '') ?></p>
                <p class="mb-1"><strong>Vendedor:</strong> <?= esc($usuario['nombre']) ?></p>
                <p class="mb-1"><strong>Fecha:</strong> <?= date('Y-m-d', strtotime($nota['fecha_inicial'])) ?></p>
                <hr>
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

        <!-- Agregar producto -->
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Agregar Producto</div>
            <div class="card-body">
                <div class="form-group">
                    <label>Buscar por SKU / Descripción</label>
                    <select id="selectProducto" class="form-control select2" style="width:100%">
                        <option value="">Escribe para buscar...</option>
                    </select>
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

    <!-- Panel derecho: carrito -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Productos en la Nota</div>
            <div class="card-body p-0 pt-2">
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-striped mb-0 carrito-table" id="tablaCarrito">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th class="text-right">Precio</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Importe</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCarrito">
                            <?php foreach ($detalle as $d): ?>
                            <tr id="row-<?= (int)$d['Id_Notas_2'] ?>">
                                <td><?= esc($d['sku']) ?></td>
                                <td><?= esc(($d['descripcion'] ?? $d['estilo']) . (!empty($d['color']) ? ' — ' . $d['color'] : '')) ?></td>
                                <td class="text-right">$<?= number_format($d['precio'], 2) ?></td>
                                <td class="text-right"><?= (int)$d['cantidad'] ?></td>
                                <td class="text-right">$<?= number_format($d['importe'], 2) ?></td>
                                <td class="text-center">
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
                <a href="<?= base_url($basePrefix . '/venta/' . (int)$nota['folio'] . '/confirmar') ?>"
                   class="btn btn-success">
                    Siguiente: Confirmar <i class="iconsminds-arrow-right ml-1"></i>
                </a>
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
$('#selectProducto').select2({
    theme: 'bootstrap',
    placeholder: 'Escribe SKU o descripción...',
    minimumInputLength: 2,
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
                    return {
                        id: p.sku,
                        text: '[' + p.sku + '] ' + p.descripcion + '  (' + p.piezas + ' pzs)',
                        sku: p.sku,
                        descripcion: p.descripcion,
                        piezas: parseInt(p.piezas, 10) || 0
                    };
                })
            };
        }
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

// Mostrar stock al seleccionar producto
$('#selectProducto').on('select2:select', function(e) {
    var data = e.params.data;
    var stock = data.piezas || 0;
    $('#stockDisponible').text('— Stock disponible: ' + stock + ' pzs');
    $('#inputCantidad').attr('max', stock).val(Math.min(parseInt($('#inputCantidad').val(), 10) || 1, stock || 1));
    $('#msgAgregar').html('');
});
$('#selectProducto').on('select2:clear select2:unselect', function() {
    $('#stockDisponible').text('');
    $('#inputCantidad').removeAttr('max');
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
            filas += '<tr id="row-' + d.Id_Notas_2 + '">'
                + '<td>' + escHtml(d.sku) + '</td>'
                + '<td>' + escHtml((d.descripcion || d.estilo) + (d.color ? ' — ' + d.color : '')) + '</td>'
                + '<td class="text-right">' + fmt(d.precio) + '</td>'
                + '<td class="text-right">' + d.cantidad + '</td>'
                + '<td class="text-right">' + fmt(d.importe) + '</td>'
                + '<td class="text-center">'
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

})(); // fin initStp2
</script>
<?= $this->endSection() ?>
