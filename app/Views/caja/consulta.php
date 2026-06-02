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

<div class="separator mb-4"></div>

<div class="audit-card">
    <div class="audit-card-header">
        <i class="simple-icon-list"></i>
        <span>Registro de Folios</span>
        <span class="badge-count" id="totalFoliosCaja">—</span>
    </div>
    <div style="padding: 16px 20px 20px;">
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

<!-- Modal Ver Folio -->
<div class="modal fade" id="modalVerFolioCaja" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Folio</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="modalVerFolioCajaBody">
                <p class="text-center text-muted"><i>Cargando...</i></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_css') ?>
<?php /* DataTables CSS ya viene en el layout — no duplicar */ ?>
<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
/* DataTables JS ya cargado en el layout — no importar de nuevo */

function puedeRevivir(tipopago) {
    if (!tipopago) return false;
    var tp = (tipopago + '').toLowerCase();
    if (tp.indexOf('sin pagar') >= 0) return false;
    if (tp.indexOf('crédito') >= 0 || tp.indexOf('credito') >= 0) return false;
    if (tp.indexOf('tarjeta') >= 0) return false;
    return true;
}

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
            { data: 'idstatus', render: function(data, type, row) {
                var sid     = parseInt(data, 10);
                var label   = STATUS_LABELS[sid] || '<span class="badge badge-secondary">Desconocido</span>';
                var factura = parseInt(row.factura || 0, 10);
                var uuid    = row.uuid_fiscal || '';
                if (uuid) {
                    label += ' <span class="badge" style="background:#0056b3;color:#fff;font-size:10px;">Facturado ✓</span>';
                } else if (factura === 1) {
                    label += ' <span class="badge" style="background:#fd7e14;color:#fff;font-size:10px;">Fact. pendiente</span>';
                }
                return label;
            }},
            { data: null, orderable: false, searchable: false,
              render: function(data, type, row) {
                var idstatus = parseInt(row.idstatus, 10);
                var esPadre  = parseInt(row.referencia || 0, 10) === 0;
                var btns = '';
                /* Botones como <button> para evitar que el SPA los intercepte */
                if (idstatus !== 5 && idstatus !== 3) {
                    btns += '<button class="btn btn-xs btn-outline-primary mr-1"'
                          + ' onclick="cajaVerFolioModal(' + row.folio + ')">Ver</button>';
                }
                if (idstatus === 2) {
                    btns += '<button class="btn btn-xs btn-outline-success mr-1"'
                          + ' onclick="cajaVerificarPago(' + row.folio + ')">Verificar pago</button>';
                }
                if (idstatus !== 5 && idstatus !== 3) {
                    btns += '<button class="btn btn-xs btn-outline-danger mr-1"'
                          + ' onclick="cajaCancelarFolio(' + row.folio + ')">Cancelar</button>';
                }
                // Revivir: solo folios PADRE cancelados con método de pago permitido
                if (idstatus === 3 && esPadre && puedeRevivir(row.tipopago)) {
                    btns += '<button class="btn btn-xs btn-warning"'
                          + ' onclick="cajaRevivirFolio(' + row.folio + ')">Revivir</button>';
                }
                if (idstatus === 5) {
                    btns += '<button class="btn btn-xs btn-outline-primary mr-1"'
                          + ' onclick="cajaVerFolioModal(' + row.folio + ')">Ver</button>';
                    btns += '<button class="btn btn-xs btn-outline-dark mr-1"'
                          + ' onclick="cajaVerTicket(' + row.folio + ')">Ver Ticket</button>';
                }
                return btns || '<span class="text-muted">—</span>';
              }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        drawCallback: function(settings) {
            var total = settings.fnRecordsTotal();
            $('#totalFoliosCaja').text(total.toLocaleString() + ' folios');
        },
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
/* Ver Folio en modal flotante — usa el mismo endpoint HTML que admin */
function cajaVerFolioModal(folio) {
    $('#modalVerFolioCajaBody').html('<p class="text-center text-muted py-4"><i class="simple-icon-refresh"></i> Cargando...</p>');
    $('#modalVerFolioCaja').modal('show');
    $.post('<?= base_url('caja/folio/detalle') ?>', {
        folio: folio,
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
    }, function(html) {
        $('#modalVerFolioCajaBody').html(html);
    }).fail(function() {
        $('#modalVerFolioCajaBody').html('<div class="alert alert-danger">Error al cargar el folio.</div>');
    });
}

/* Cancelar desde dentro del modal */
function cajaModalCancelar() {
    var folio = ($('#folio_input_caja_modal').val() || '').trim();
    if (!folio) return;
    if (!confirm('¿Cancelar el folio ' + folio + '? Esta acción restaura el inventario.')) return;
    cajaCancelarFolio(folio);
    $('#modalVerFolioCaja').modal('hide');
}

/* Verificar pago desde dentro del modal */
function cajaModalVerificar() {
    var folio = ($('#folio_input_caja_modal').val() || '').trim();
    if (!folio) return;
    if (!confirm('¿Marcar el folio ' + folio + ' como pagado?')) return;
    $('#modalVerFolioCaja').modal('hide');
    cajaVerificarPago(folio);
}

/* Ver Ticket de impresión */
function cajaVerTicket(folio) {
    window.open('<?= base_url('caja/folio/') ?>' + folio + '/ticket', '_blank',
        'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=500,height=600');
}

function cajaCancelarFolio(folio) {
    if (confirm('¿Cancelar el folio ' + folio + '?')) {
        window.location.href = URL_CANCELAR + folio;
    }
}

/* Revivir nota cancelada */
function cajaRevivirFolio(folio) {
    if (!confirm('¿Revivir el folio ' + folio + '? Se volverá a descontar el stock de los productos.')) return;
    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fetch('<?= base_url('caja/folio/') ?>' + folio + '/revivir', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            if (data.advertencias && data.advertencias.length > 0) {
                alert('⚠ Folio #' + folio + ' revivido, pero hay productos con stock insuficiente:\n\n'
                    + data.advertencias.join('\n')
                    + '\n\nLas cantidades se ajustaron al stock disponible.');
            }
            $('#tablaCajaConsulta').DataTable().ajax.reload(null, false);
        } else {
            alert('No se pudo revivir el folio: ' + (data.error || 'error desconocido'));
        }
    })
    .catch(function() { alert('Error de conexión al revivir.'); });
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
