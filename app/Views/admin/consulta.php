<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Consultar Folios<?php if (($tipo ?? '') === 'mayoreo'): ?> <small class="text-muted">— Mayoreo</small><?php endif; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Consulta</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= session()->getFlashdata('success') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= session()->getFlashdata('error') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header font-weight-bold">Resultado de búsqueda</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0" id="tablaAdminConsulta">
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
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Folio -->
<div class="modal fade" id="modalVerFolio" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Folio</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="modalVerFolioBody">
                <p class="text-center"><i>Cargando...</i></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
/* DataTables JS ya cargado en el layout — no importar de nuevo */
var STATUS_LABELS = {
    1: '<span class="badge badge-primary">Abierta</span>',
    2: '<span class="badge badge-info">En proceso</span>',
    3: '<span class="badge badge-danger">Cancelada</span>',
    4: '<span class="badge badge-warning">Anticipo</span>',
    5: '<span class="badge badge-success">Pagada</span>'
};

$(document).ready(function() {
    var tipoFiltro = '<?= esc($tipo ?? '') ?>';

    if ($.fn.DataTable.isDataTable('#tablaAdminConsulta')) {
        $('#tablaAdminConsulta').DataTable().destroy();
    }

    $('#tablaAdminConsulta').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= base_url('admin/consulta/datatable') ?>',
            type: 'GET',
            data: function(d) {
                if (tipoFiltro) d.tipo = tipoFiltro;
                return d;
            }
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

    if (idstatus !== 5) {
        btns += '<a href="#" class="btn btn-xs btn-outline-primary mr-1"'
              + ' onclick="adminVerFolio(' + n.folio + '); return false;">Ver</a>';
    }
    if (idstatus !== 5) {
        btns += '<a href="#" class="btn btn-xs btn-outline-danger"'
              + ' onclick="adminCancelarFolio(' + n.folio + '); return false;">Cancelar</a>';
    }
    if (!btns) btns = '<span class="text-muted">—</span>';
    return btns;
}

function adminVerFolio(folio) {
    $('#modalVerFolioBody').html('<p class="text-center"><i>Cargando...</i></p>');
    $('#modalVerFolio').modal('show');
    $.post('<?= base_url('admin/caja/ajax') ?>', { folio: folio }, function(html) {
        $('#modalVerFolioBody').html(html);
    }).fail(function() {
        $('#modalVerFolioBody').html('<p class="text-danger">Error al cargar el folio.</p>');
    });
}

function adminCancelarFolio(folio) {
    if (!confirm('¿Cancelar el folio ' + folio + '?')) return;
    $.post('<?= base_url('admin/caja/cancelar') ?>', { folio: folio }, function(resp) {
        if (resp === '1') {
            $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
        } else {
            alert('No se pudo cancelar el folio ' + folio + '.');
        }
    }).fail(function() {
        alert('Error de comunicación al cancelar.');
    });
}
</script>
<?= $this->endSection() ?>
