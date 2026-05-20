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

<div class="separator mb-5"></div>
<div class="row">
    <div class="col-12 mb-4">
        <table class="table responsive nowrap" id="tablaStp1" style="width:100%">
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

<!-- Modal Detalle de Folio -->
<div class="modal fade" id="modalVerFolioMostrador" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Folio</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="modalVerFolioBodyM">
                <p class="text-center"><i>Cargando...</i></p>
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
var STATUS_LABELS = {
    1: '<span class="badge badge-primary">Abierta</span>',
    2: '<span class="badge badge-info">En proceso</span>',
    3: '<span class="badge badge-danger">Cancelada</span>',
    4: '<span class="badge badge-warning">Anticipo</span>',
    5: '<span class="badge badge-success">Pagada</span>'
};

var dtMostrador;

$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#tablaStp1')) {
        $('#tablaStp1').DataTable().destroy();
    }

    dtMostrador = $('#tablaStp1').DataTable({
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
    var idstatus   = parseInt(n.idstatus, 10);
    var referencia = parseInt(n.referencia || 0, 10);
    var esHijo     = referencia > 0;
    var BASE       = '<?= base_url('mostrador/venta/') ?>';

    if (esHijo) {
        // Folio hijo (anticipo): abrir modal con detalle del folio hijo
        btns += '<a href="#" class="btn btn-xs btn-outline-primary mr-1"'
              + ' onclick="mostradorVerFolio(' + n.folio + '); return false;">Ver</a>';
        if (idstatus !== 3 && idstatus !== 5) {
            btns += '<a href="#" class="btn btn-xs btn-outline-danger mr-1"'
                  + ' onclick="mostradorCancelarFolioBtn(' + n.folio + '); return false;">Cancelar</a>';
        }
        if (idstatus === 3) {
            btns += '<a href="#" class="btn btn-xs btn-warning"'
                  + ' onclick="mostradorRevivirFolio(' + n.folio + '); return false;">Revivir</a>';
        }
    } else {
        // Folio padre
        if (idstatus === 1 || idstatus === 4) {
            btns += '<a href="' + BASE + n.folio + '/confirmar" class="btn btn-xs btn-outline-primary mr-1">Ver / Pagar</a>';
        }
        if (idstatus === 5) {
            btns += '<a href="#" class="btn btn-xs btn-outline-secondary mr-1"'
                  + ' onclick="mostradorVerFolio(' + n.folio + '); return false;">Ver</a>';
        }
        if (idstatus === 1) {
            btns += '<a href="' + BASE + n.folio + '/productos" class="btn btn-xs btn-outline-secondary mr-1">Editar</a>';
            btns += '<a href="' + BASE + n.folio + '/duplicar" class="btn btn-xs btn-outline-secondary mr-1">Duplicar</a>';
        }
        if (idstatus !== 5 && idstatus !== 3) {
            btns += '<a href="#" class="btn btn-xs btn-outline-danger mr-1"'
                  + ' onclick="mostradorCancelarFolioBtn(' + n.folio + '); return false;">Cancelar</a>';
        }
        if (idstatus === 3) {
            btns += '<a href="#" class="btn btn-xs btn-warning"'
                  + ' onclick="mostradorRevivirFolio(' + n.folio + '); return false;">Revivir</a>';
        }
    }

    if (!btns) btns = '<span class="text-muted">—</span>';
    return btns;
}

// Abrir modal con detalle del folio
function mostradorVerFolio(folio) {
    $('#modalVerFolioBodyM').html('<p class="text-center"><i>Cargando...</i></p>');
    $('#modalVerFolioMostrador').modal('show');
    $.post('<?= base_url('mostrador/folio/ajax') ?>', {
        folio: folio,
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
    }, function(html) {
        $('#modalVerFolioBodyM').html(html);
    }).fail(function() {
        $('#modalVerFolioBodyM').html('<p class="text-danger">Error al cargar el folio.</p>');
    });
}

function mostradorRevivirFolio(folio) {
    if (!confirm('¿Revivir el folio ' + folio + '? Se volverá a descontar el stock de los productos.')) return;
    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fetch('<?= base_url('mostrador/folio/') ?>' + folio + '/revivir', {
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
            if (!data.esHijo) {
                // Nota padre: redirigir a step 2 para revisar/agregar productos
                window.location.href = '<?= base_url('mostrador/venta/') ?>' + folio + '/productos';
            } else {
                // Nota hijo (anticipo): solo recargar tabla
                if (dtMostrador) dtMostrador.ajax.reload(null, false);
            }
        } else {
            alert('No se pudo revivir el folio: ' + (data.error || 'error desconocido'));
        }
    })
    .catch(function() { alert('Error de conexión al revivir.'); });
}

// Cancelar desde los botones de la tabla (fuera del modal)
function mostradorCancelarFolioBtn(folio) {
    if (!confirm('¿Cancelar el folio ' + folio + '? Esta acción restaura el inventario.')) return;
    _mostradorDoCancelar(folio);
}

// Cancelar desde el botón dentro del modal
function fnCancelarFolioMostrador() {
    var el = document.getElementById('folio_input_m');
    if (!el) return;
    var folio = el.value;
    if (!folio) return;
    if (!confirm('¿Cancelar el folio ' + folio + '? Esta acción restaura el inventario.')) return;
    _mostradorDoCancelar(folio);
}

function _mostradorDoCancelar(folio) {
    $.post('<?= base_url('mostrador/folio/cancelar') ?>', {
        folio: folio,
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
    }, function(resp) {
        $('#modalVerFolioMostrador').modal('hide');
        if (dtMostrador) dtMostrador.ajax.reload(null, false);
    }, 'json').fail(function() {
        alert('Error al cancelar. Intenta de nuevo.');
    });
}
</script>
<?= $this->endSection() ?>
