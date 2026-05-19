<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Consultar Folios</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Caja</a></li>
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

<div class="separator mb-5"></div>
<div class="row">
    <div class="col-12 mb-4">
        <table class="table responsive nowrap" id="tablaCajaConsulta" style="width:100%">
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

<?= $this->endSection() ?>

<?= $this->section('page_css') ?>
<?php /* DataTables CSS ya viene en el layout — no duplicar */ ?>
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

var URL_VER      = '<?= base_url('caja/venta/') ?>';
var URL_VERIFICAR = '<?= base_url('caja/pago/verificado/') ?>';
var URL_CANCELAR = '<?= base_url('caja/cancelar/') ?>';

(function initCajaConsultaTable() {
    var $table = $('#tablaCajaConsulta');
    if (!$table.length) return;

    if ($.fn.DataTable.isDataTable($table[0])) {
        $table.DataTable().destroy();
    }

    $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url:  '<?= base_url('caja/consulta/datatable') ?>',
            type: 'GET',
            error: function(xhr, err) {
                console.error('[CajaConsulta] DataTable AJAX error:', xhr.status, err, xhr.responseText);
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
            }, className: 'text-right' },
            { data: 'idstatus', render: function(data) {
                var sid = parseInt(data, 10);
                return STATUS_LABELS[sid] || '<span class="badge badge-secondary">Desconocido</span>';
            }},
            { data: null, orderable: false, searchable: false,
              render: function(data, type, row) {
                var idstatus = parseInt(row.idstatus, 10);
                var btns = '';
                /* Botones como <button> para evitar que el SPA los intercepte */
                if (idstatus !== 5) {
                    btns += '<button class="btn btn-xs btn-outline-primary mr-1"'
                          + ' onclick="window.location.href=\'' + URL_VER + row.folio + '\'">Ver</button>';
                }
                if (idstatus === 2) {
                    btns += '<button class="btn btn-xs btn-outline-success mr-1"'
                          + ' onclick="cajaVerificarPago(' + row.folio + ')">Verificar pago</button>';
                }
                if (idstatus !== 5) {
                    btns += '<button class="btn btn-xs btn-outline-danger"'
                          + ' onclick="cajaCancelarFolio(' + row.folio + ')">Cancelar</button>';
                }
                return btns || '<span class="text-muted">—</span>';
              }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            processing:   'Procesando...',
            search:       'Buscar:',
            lengthMenu:   'Mostrar _MENU_ registros',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:    'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            zeroRecords:  'No se encontraron resultados',
            emptyTable:   'No hay datos disponibles',
            paginate: {
                first:    'Primero',
                previous: 'Anterior',
                next:     'Siguiente',
                last:     'Último'
            }
        }
    });
})();

/* Cancelar: navegación completa para que la redirección del servidor funcione
   sin interferencia del SPA */
function cajaCancelarFolio(folio) {
    if (confirm('¿Cancelar el folio ' + folio + '?')) {
        window.location.href = URL_CANCELAR + folio;
    }
}

/* Verificar pago: fetch AJAX, muestra alert y recarga sólo la tabla */
function cajaVerificarPago(folio) {
    if (!confirm('¿Marcar el folio ' + folio + ' como pagado?')) return;
    fetch(URL_VERIFICAR + folio, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            alert(data.mensaje || 'Pago verificado.');
            $('#tablaCajaConsulta').DataTable().ajax.reload(null, false);
        })
        .catch(function() { alert('Error al verificar el pago.'); });
}
</script>
<?= $this->endSection() ?>
