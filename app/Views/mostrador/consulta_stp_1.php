<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Consultar Folios</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('mostrador') ?>">Mostrador</a></li>
                <li class="breadcrumb-item active">Consulta</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-10 offset-md-1">

        <!-- Búsqueda -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-2">
                        <label>No. de Folio</label>
                        <input type="number" id="inputFolio" class="form-control" placeholder="Folio...">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label>Filtrar por Status</label>
                        <select id="selectStatus" class="form-control">
                            <option value="0">Todos</option>
                            <option value="1">Abierta</option>
                            <option value="2">En proceso</option>
                            <option value="3">Cancelada</option>
                            <option value="4">Anticipo</option>
                            <option value="5">Pagada</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <button id="btnBuscar" class="btn btn-primary btn-block">
                            <i class="iconsminds-search-1"></i> Buscar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div class="card">
            <div class="card-header font-weight-bold">Resultados</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0" id="tablaResultados">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Tipo Pago</th>
                                <th class="text-right">Total</th>
                                <th>Status</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyResultados">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Ingresa un folio o filtra por status y presiona Buscar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<input type="hidden" id="hidCsrfName" value="<?= csrf_token() ?>">
<input type="hidden" id="hidCsrfHash" value="<?= csrf_hash() ?>">

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
var STATUS_LABELS = {
    1: '<span class="badge badge-primary">Abierta</span>',
    2: '<span class="badge badge-info">En proceso</span>',
    3: '<span class="badge badge-danger">Cancelada</span>',
    4: '<span class="badge badge-warning">Anticipo</span>',
    5: '<span class="badge badge-success">Pagada</span>'
};

$('#btnBuscar').on('click', function() {
    buscar();
});
$('#inputFolio').on('keypress', function(e) {
    if (e.which === 13) buscar();
});

function buscar() {
    var folio  = $('#inputFolio').val().trim();
    var status = $('#selectStatus').val();
    var csrf   = {};
    csrf[$('#hidCsrfName').val()] = $('#hidCsrfHash').val();

    $('#tbodyResultados').html('<tr><td colspan="8" class="text-center py-3"><i class="iconsminds-loading-2"></i> Cargando...</td></tr>');

    $.post('<?= base_url('mostrador/ajax') ?>', $.extend({
        folio: folio,
        status: status
    }, csrf), function(resp) {
        $('#hidCsrfHash').val(resp.csrf_hash);
        if (!resp.data || resp.data.length === 0) {
            $('#tbodyResultados').html('<tr><td colspan="8" class="text-center text-muted py-4">Sin resultados.</td></tr>');
            return;
        }
        var filas = '';
        resp.data.forEach(function(n) {
            filas += '<tr>'
                + '<td>' + n.folio + '</td>'
                + '<td>' + (n.fecha_inicial ? n.fecha_inicial.substr(0,10) : '') + '</td>'
                + '<td>' + escHtml(n.cliente || '') + '</td>'
                + '<td>' + escHtml(n.vendedor || '') + '</td>'
                + '<td>' + escHtml(n.tipopago || '') + '</td>'
                + '<td class="text-right">$' + parseFloat(n.total || 0).toFixed(2) + '</td>'
                + '<td>' + (STATUS_LABELS[n.idstatus] || n.status || '') + '</td>'
                + '<td>'
                + accionesNota(n)
                + '</td>'
                + '</tr>';
        });
        $('#tbodyResultados').html(filas);
    }, 'json').fail(function() {
        $('#tbodyResultados').html('<tr><td colspan="8" class="text-center text-danger">Error de comunicación.</td></tr>');
    });
}

function accionesNota(n) {
    var btns = '';
    var idstatus = parseInt(n.idstatus, 10);

    if (idstatus === 1 || idstatus === 3 || idstatus === 4) {
        btns += '<a href="<?= base_url('mostrador/venta/') ?>' + n.folio + '/productos" class="btn btn-xs btn-outline-primary mr-1">Editar</a>';
    }
    if (idstatus === 1 || idstatus === 3) {
        btns += '<a href="<?= base_url('mostrador/venta/') ?>' + n.folio + '/duplicar" class="btn btn-xs btn-outline-secondary mr-1">Duplicar</a>';
    }
    if (idstatus !== 5) {
        btns += '<a href="<?= base_url('mostrador/venta/') ?>' + n.folio + '/cancelar" class="btn btn-xs btn-outline-danger"'
            + ' onclick="return confirm(\'¿Cancelar el folio ' + n.folio + '?\')">Cancelar</a>';
    }
    if (!btns) btns = '<span class="text-muted">—</span>';
    return btns;
}

function escHtml(str) {
    return $('<div>').text(str).html();
}
</script>
<?= $this->endSection() ?>
