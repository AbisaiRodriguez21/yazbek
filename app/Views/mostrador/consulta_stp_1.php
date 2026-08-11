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

<div class="separator mb-4"></div>

<div class="audit-card">
    <div class="audit-card-header">
        <i class="simple-icon-list"></i>
        <span>Registro de Folios</span>
        <span class="badge-count" id="totalFoliosMostrador">—</span>
    </div>
    <div style="padding: 16px 20px 20px;">
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
            { data: 'idstatus', render: function(data, type, row) {
                var sid    = parseInt(data, 10);
                var label  = STATUS_LABELS[sid] || '<span class="badge badge-secondary">Desconocido</span>';
                var factura = parseInt(row.factura || 0, 10);
                var uuid    = row.uuid_fiscal || '';
                if (uuid) {
                    label += ' <span class="badge" style="background:#0056b3;color:#fff;font-size:10px;">Facturado ✓</span>';
                } else if (factura === 1) {
                    label += ' <span class="badge" style="background:#fd7e14;color:#fff;font-size:10px;">Fact. pendiente</span>';
                }
                return label;
            }},
            { data: null, render: function(data, type, row) {
                return accionesNota(row);
            }, orderable: false, searchable: false}
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        drawCallback: function(settings) {
            var total = settings.fnRecordsTotal();
            $('#totalFoliosMostrador').text(total.toLocaleString() + ' folios');
        },
        language: {
            url: '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>'
        }
    });
});

function puedeRevivir(tipopago) {
    if (!tipopago) return false;
    var tp = (tipopago + '').toLowerCase();
    if (tp.indexOf('sin pagar') >= 0) return false;
    if (tp.indexOf('crédito') >= 0 || tp.indexOf('credito') >= 0) return false;
    if (tp.indexOf('tarjeta') >= 0) return false;
    return true;
}

// Solo las notas "Sin Pagar" (crédito) se pagan en varios abonos.
function esSinPagar(tipopago) {
    if (!tipopago) return false;
    var tp = (tipopago + '').toLowerCase();
    return tp.indexOf('sin pagar') >= 0 || tp.indexOf('crédito') >= 0 || tp.indexOf('credito') >= 0;
}

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
        // Los folios hijo no se pueden revivir de forma independiente
    } else {
        // Folio padre
        if (idstatus === 1 || idstatus === 4) {
            btns += '<a href="' + BASE + n.folio + '/confirmar" class="btn btn-xs btn-outline-primary mr-1">Ver / Pagar</a>';
        }
        // "Sin Pagar" (crédito, se pagará en varios abonos) en proceso: el
        // vendedor puede registrar abonos (folio hijo pendiente de que Caja
        // lo reciba y confirme). No aplica a otros tipos de pago — esos los
        // cobra Caja completos, de una sola vez.
        if (idstatus === 2 && esSinPagar(n.tipoPagoPropio)) {
            btns += '<a href="' + BASE + n.folio + '/abono" class="btn btn-xs btn-outline-primary mr-1">Ver / Abonar</a>';
        }
        if (idstatus === 2 || idstatus === 5) {
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
        if (idstatus === 3 && puedeRevivir(n.tipopago)) {
            btns += '<a href="#" class="btn btn-xs btn-warning"'
                  + ' onclick="mostradorRevivirFolio(' + n.folio + '); return false;">Revivir</a>';
        }
    }

    // Ver Ticket disponible para todos los folios (padre e hijo) excepto cancelados
    if (idstatus !== 3) {
        btns += '<a href="#" class="btn btn-xs btn-outline-dark mr-1"'
              + ' onclick="mostradorVerTicket(' + n.folio + '); return false;">Ver Ticket</a>';
    }

    // Comanda (solo productos, sin precios) — solo folios padre (los hijos
    // son abonos, sin productos propios)
    if (!esHijo && idstatus !== 3) {
        btns += '<a href="#" class="btn btn-xs btn-outline-dark mr-1"'
              + ' onclick="mostradorVerComanda(' + n.folio + '); return false;">Comanda</a>';
    }

    if (!btns) btns = '<span class="text-muted">—</span>';
    return btns;
}

// Abrir ticket en ventana nueva
function mostradorVerTicket(folio) {
    window.open('<?= base_url('mostrador/folio/') ?>' + folio + '/ticket', '_blank',
        'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=500,height=600');
}

// Abrir comanda (solo productos) en ventana nueva
function mostradorVerComanda(folio) {
    window.open('<?= base_url('mostrador/folio/') ?>' + folio + '/comanda', '_blank',
        'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=360,height=520');
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
