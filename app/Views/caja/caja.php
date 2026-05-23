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
var csrfName = '<?= csrf_token() ?>';
var csrfHash = '<?= csrf_hash() ?>';

$(window).on('pageshow', function(e) {
    if (e.originalEvent.persisted) { location.reload(); }
});

$('#inputFolio').on('keypress', function(e) {
    if (e.which === 13) $('#btnBuscar').trigger('click');
});

$('#btnBuscar').on('click', function() {
    var folio = $.trim($('#inputFolio').val());
    if (!folio || isNaN(folio)) {
        $('#mensajeBusqueda').html('<div class="alert alert-warning py-2">Ingresa un número de folio válido.</div>');
        return;
    }
    $('#mensajeBusqueda').html('');
    $('#panelDetalle').html(
        '<div class="card-body text-center text-muted py-4">'
        + '<div class="spinner-border spinner-border-sm mr-2"></div> Cargando...</div>'
    );

    var data = {};
    data[csrfName] = csrfHash;
    data.folio = folio;

    $.post(baseUrl + 'caja/folio/detalle', data, function(html) {
        $('#panelDetalle').html(
            '<div class="card-header"><h5 class="mb-0">Folio #' + folio + '</h5></div>'
            + '<div class="card-body">' + html + '</div>'
        );
        // Renovar token CSRF para siguientes peticiones
        $.get(baseUrl + 'csrf-refresh', function(r) {
            if (r && r.hash) csrfHash = r.hash;
        }).fail(function(){});
    }).fail(function() {
        $('#panelDetalle').html(
            '<div class="card-body"><div class="alert alert-danger mb-0">Error al consultar el folio.</div></div>'
        );
    });
});

/* Cancelar nota — llamado desde el HTML generado por folioDetalleAjax */
function cajaModalCancelar() {
    var folio = ($('#folio_input_caja_modal').val() || '').trim();
    if (!folio) return;
    if (!confirm('¿Cancelar la nota #' + folio + '? Se restaurará el inventario.')) return;
    var fd = {};
    fd[csrfName] = csrfHash;
    fd.folio = folio;
    $.post(baseUrl + 'caja/cancelar', fd, function(resp) {
        alert('Nota cancelada correctamente.');
        $('#btnBuscar').trigger('click');
    }).fail(function() { alert('Error al cancelar la nota.'); });
}

/* Verificar pago — llamado desde el HTML generado por folioDetalleAjax */
function cajaModalVerificar() {
    var folio = ($('#folio_input_caja_modal').val() || '').trim();
    if (!folio) return;
    if (!confirm('¿Verificar el pago del folio #' + folio + '?')) return;
    $.get(baseUrl + 'caja/pago/verificado/' + folio, function(resp) {
        alert('Pago verificado correctamente.');
        $('#btnBuscar').trigger('click');
    }).fail(function() { alert('Error al verificar el pago.'); });
}
</script>
<?= $this->endSection() ?>
