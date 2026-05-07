<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Verificar Caja</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Verificar Caja</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="separator mb-5"></div>

<div class="row">
    <!-- Panel izquierdo: búsqueda -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-4">Buscar folio</h5>
                <div class="form-group">
                    <input type="number" id="inputFolio" class="form-control form-control-lg"
                           placeholder="Folio" min="1">
                </div>
                <button id="btnBuscar" class="btn btn-success btn-block">
                    <i class="iconsminds-magnifi-glass mr-1"></i> Mostrar Información
                </button>
                <div id="mensajeBusqueda" class="mt-3"></div>
            </div>
        </div>
    </div>

    <!-- Panel derecho: detalles del folio -->
    <div class="col-md-8 mb-4">
        <div class="card" id="panelDetalle">
            <div class="card-body text-muted">
                <p class="mb-0"><i class="iconsminds-receipt-4 mr-2"></i> Detalles folio</p>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
var baseUrl = '<?= base_url() ?>';

$(window).on('pageshow', function(e) {
    if (e.originalEvent.persisted) {
        location.reload();
    }
});

$('#inputFolio').on('keypress', function(e) {
    if (e.which === 13) $('#btnBuscar').trigger('click');
});

// Función global de calculadora (igual que el original blurFunction)
function blurFunction() {
    var m1 = parseFloat(document.getElementById('importe').value) || 0;
    var m2 = parseFloat(document.getElementById('pagar2').value) || 0;
    var m3 = m1 - m2;
    document.getElementById('resultado').value = m3.toFixed(2);
}

$('#btnBuscar').on('click', function() {
    var folio = $.trim($('#inputFolio').val());
    if (!folio || isNaN(folio)) {
        $('#mensajeBusqueda').html('<div class="alert alert-warning py-2">Ingresa un número de folio válido.</div>');
        return;
    }
    $('#mensajeBusqueda').html('');
    $('#panelDetalle').html('<div class="card-body text-center text-muted"><div class="spinner-border spinner-border-sm mr-2"></div> Cargando...</div>');

    $.getJSON(baseUrl + 'caja/cobrar/ajax/' + folio, function(data) {
        if (data.error) {
            $('#panelDetalle').html('<div class="card-body"><div class="alert alert-danger mb-0">' + escapeHtml(data.error) + '</div></div>');
            return;
        }

        var n = data.nota;
        // Usar status_nombre directo de la BD (igual que el original usa s.nombre)
        var statusBadge = {1:'primary', 2:'info', 3:'danger', 4:'warning', 5:'success'};
        var sid = parseInt(n.status);
        var statusLabel = n.status_nombre || ({1:'Abierta', 2:'En proceso', 3:'Cancelada', 4:'Anticipo', 5:'Pagada'}[sid] || sid);
        var statusHtml = '<span class="badge badge-' + (statusBadge[sid] || 'secondary') + '">' + statusLabel + '</span>';

        // Tabla superior: Fecha (12h AM/PM desde controller), Nombre Cliente, Estatus, Folio
        var topTableHtml = '<table class="table table-sm table-bordered mb-3">'
            + '<tr>'
            +   '<td><strong>Fecha:</strong> ' + (n.fecha_inicial || '—') + '</td>'
            +   '<td><strong>Nombre Cliente:</strong> ' + escapeHtml(n.cliente_nombre || '—') + '</td>'
            + '</tr>'
            + '<tr>'
            +   '<td><strong>Estatus:</strong> ' + statusHtml + '</td>'
            +   '<td><strong>Folio:</strong> ' + n.folio + '</td>'
            + '</tr>'
            + '</table>';

        // Tabla de productos
        var productosHtml = '';
        if (data.detalle && data.detalle.length > 0) {
            productosHtml = '<h6 class="mt-3 mb-2">Productos</h6><table class="table table-sm table-bordered">'
                + '<thead class="thead-light"><tr><th>Cantidad</th><th>Descripción</th><th class="text-right">Precio Unit.</th><th class="text-right">Importe</th></tr></thead><tbody>';
            data.detalle.forEach(function(p) {
                productosHtml += '<tr>'
                    + '<td>' + p.cantidad + '</td>'
                    + '<td>' + escapeHtml(p.descripcion) + '</td>'
                    + '<td class="text-right">$ ' + parseFloat(p.pUnitario || 0).toFixed(2) + '</td>'
                    + '<td class="text-right">$ ' + parseFloat(p.importe || 0).toFixed(2) + '</td>'
                    + '</tr>';
            });
            productosHtml += '</tbody></table>';
        }

        // Sección Calculadora — igual estructura que el original (3 columnas en 1 fila)
        var totalNota = parseFloat(n.total || 0).toFixed(2);
        var calcHtml = '<h6 class="mt-3 mb-2">Calculadora</h6>'
            + '<input type="hidden" id="pagar2" value="' + totalNota + '">'
            + '<div class="row">'
            +   '<div class="col-sm-4 mb-2">'
            +     '<label>Ingresa la cantidad</label>'
            +     '<input type="text" id="importe" class="form-control" placeholder="Ingresa la cantidad"'
            +       ' onchange="blurFunction()" onblur="blurFunction()">'
            +   '</div>'
            +   '<div class="col-sm-4 mb-2">'
            +     '<label>Total</label>'
            +     '<input type="text" id="pagar" class="form-control" disabled value="' + totalNota + '">'
            +   '</div>'
            +   '<div class="col-sm-4 mb-2">'
            +     '<label>Cambio</label>'
            +     '<input type="text" id="resultado" class="form-control" disabled>'
            +   '</div>'
            + '</div>';

        // Información de montosnotas (forma de pago)
        // anticipo viene como int (0 o 1) desde el controller — igual que original: anticipo==1 → Si
        var pagosHtml = '';
        if (data.pagos && data.pagos.length > 0) {
            pagosHtml = '<h6 class="mt-3 mb-2">Forma de pago</h6>';
            data.pagos.forEach(function(p) {
                var esAnticipo = (p.anticipo === 1) ? 'Sí' : 'No';
                var cargoHtml = (p.idTipoPago === 2 || p.idTipoPago === 3)
                    ? ' <strong>Cargo:</strong> $ ' + parseFloat(p.cargos || 0).toFixed(2) : '';
                pagosHtml += '<p class="mb-1"><strong>Forma de Pago:</strong> ' + escapeHtml(p.tipo)
                    + ' <strong>Monto:</strong> $ ' + parseFloat(p.monto || 0).toFixed(2)
                    + cargoHtml
                    + ' <strong>Es anticipo:</strong> ' + esAnticipo + '</p>';
            });
        }

        // Sección Otros (por tipoImpresion) — igual que el original, siempre muestra todos
        var tipoImpresionMap = {1: 'Ninguno', 2: 'Impresión', 3: 'Bordado'};
        var tipoImpresion = tipoImpresionMap[parseInt(n.tipoImpresion) || 1] || 'Ninguno';
        var otrosHtml = '';

        // Resumen de totales — igual que el original: SIEMPRE muestra todos los campos
        var totalTableHtml = '<table class="table table-sm mb-3">'
            + '<tr><td colspan="3" class="text-right"><strong>Otros</strong></td>'
            +   '<td>' + tipoImpresion + '</td></tr>'
            + '<tr><td colspan="3" class="text-right"><strong>Cargo por otros</strong></td>'
            +   '<td>$ ' + parseFloat(n.cargoPorImpresion || 0).toFixed(2) + '</td></tr>'
            + '<tr><td colspan="3" class="text-right"><strong>Descuento</strong></td>'
            +   '<td>$ ' + parseFloat(n.descuento || 0).toFixed(2) + '</td></tr>'
            + '<tr><td colspan="3" class="text-right"><strong>SubTotal</strong></td>'
            +   '<td>$ ' + parseFloat(n.subTotal || 0).toFixed(2) + '</td></tr>'
            + '<tr><td colspan="3" class="text-right"><strong>Cargo TC/TD</strong></td>'
            +   '<td>$ ' + parseFloat(n.cargoTarjeta || 0).toFixed(2) + '</td></tr>'
            + '<tr><td colspan="3" class="text-right"><strong>SubTotal2</strong></td>'
            +   '<td>$ ' + parseFloat(n.subTotal2 || 0).toFixed(2) + '</td></tr>'
            + '<tr><td colspan="3" class="text-right"><strong>IVA</strong></td>'
            +   '<td>$ ' + parseFloat(n.iva || 0).toFixed(2) + '</td></tr>'
            + '<tr><td colspan="3" class="text-right"><strong>Total</strong></td>'
            +   '<td class="text-success font-weight-bold">$ ' + parseFloat(n.total || 0).toFixed(2) + '</td></tr>'
            + '</table>';

        // Cantidad con letra — viene del controller (AifLibNumber::toCurrency en mayúsculas)
        var cantidadLetraHtml = '<p class="mb-1"><strong>Cantidad con letra(</strong> '
            + (n.cantidadLetra || '') + '<strong>)</strong></p>';

        // Forma de pago, Estatus y Factura — igual que el original
        // factura viene como int (0 o 1) desde el controller
        var facturaText = (n.factura === 1) ? 'Si requiere' : 'No requiere';
        var facturaHtml = '<p class="mb-1"><strong>Estatus:</strong> ' + statusLabel + '</p>'
            + '<p class="mb-1"><strong>Factura:</strong> ' + facturaText + '</p>';

        // Botones de acción (solo si statusId != 3 && statusId != 5 && verificado != 'Pagado')
        var botonesHtml = '';
        if (sid !== 3 && sid !== 5 && (!n.verificado || n.verificado !== 'Pagado')) {
            botonesHtml = '<div class="mt-4">'
                + '<button class="btn btn-danger mr-2" onclick="cancelarNota(' + n.folio + ')"><i class="iconsminds-remove mr-1"></i>Cancelar Nota</button>'
                + '<button class="btn btn-success" onclick="pagoVerificado(' + n.folio + ')"><i class="iconsminds-yes mr-1"></i>Pago Verificado</button>'
                + '</div>';
        }

        var html = '<div class="card-header d-flex justify-content-between align-items-center">'
            + '<h5 class="mb-0">Folio #' + n.folio + '</h5>'
            + '</div>'
            + '<div class="card-body">'
            + topTableHtml
            + calcHtml
            + productosHtml
            + totalTableHtml
            + cantidadLetraHtml
            + pagosHtml
            + facturaHtml
            + botonesHtml
            + '</div>';

        $('#panelDetalle').html(html);

    }).fail(function(jqXHR, textStatus, errorThrown) {
        $('#panelDetalle').html('<div class="card-body"><div class="alert alert-danger mb-0">Error al consultar el folio: ' + (textStatus || 'Error de comunicación') + '</div></div>');
    });
});

function cancelarNota(folio) {
    if (!confirm('¿Cancelar la nota #' + folio + '?')) return;
    $.get(baseUrl + 'caja/cancelar/' + folio, function(resp) {
        alert('Nota cancelada correctamente.');
        $('#btnBuscar').trigger('click');
    }).fail(function() {
        alert('Error al cancelar la nota.');
    });
}

function pagoVerificado(folio) {
    if (!confirm('¿Verificar el pago del folio #' + folio + '?')) return;
    $.get(baseUrl + 'caja/pago/verificado/' + folio, function(resp) {
        alert('Pago verificado correctamente.');
        $('#btnBuscar').trigger('click');
    }).fail(function() {
        alert('Error al verificar el pago.');
    });
}

function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatCurrency(num) {
    return '$' + num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
<?= $this->endSection() ?>
