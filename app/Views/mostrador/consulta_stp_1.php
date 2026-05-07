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
        <div class="card">
            <div class="card-header font-weight-bold">Resultado de búsqueda</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0" id="tablaStp1">
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
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/datatables.responsive.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/vendor/dataTables.bootstrap4.min.js') ?>"></script>
<script>
var STATUS_LABELS = {
    1: '<span class="badge badge-primary">Abierta</span>',
    2: '<span class="badge badge-info">En proceso</span>',
    3: '<span class="badge badge-danger">Cancelada</span>',
    4: '<span class="badge badge-warning">Anticipo</span>',
    5: '<span class="badge badge-success">Pagada</span>'
};

$(document).ready(function() {
    var table = $('#tablaStp1').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= base_url('mostrador/consulta/datatable') ?>',
            type: 'GET'
        },
        columns: [
            { data: 'folio' },
            { data: 'fecha_inicial', render: function(data) {
                return data ? data.substr(0, 10) : '';
            }},
            { data: 'cliente' },
            { data: 'vendedor' },
            { data: 'tipopago' },
            { data: 'total', render: function(data) {
                return '$' + parseFloat(data || 0).toFixed(2);
            }, className: 'text-right'},
            { data: 'idstatus', render: function(data) {
                var sid = parseInt(data, 10);
                return STATUS_LABELS[sid] || '<span class="badge badge-secondary">Desconocido</span>';
            }},
            { data: null, render: function(data, type, row) {
                return accionesNota(row);
            }, orderable: false, searchable: false}
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            url: '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>'
        }
    });
});

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
</script>
<?= $this->endSection() ?>
