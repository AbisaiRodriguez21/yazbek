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
<style>
/* ── Dropdown de acciones en DataTable (igual que Admin → Consultar Folios) ── */
.btn-acciones {
    font-size: .78rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 4px;
    border: 1px solid #145388;
    color: #145388;
    background: #fff;
    transition: background .15s, color .15s;
    white-space: nowrap;
}
.btn-acciones:hover,
.btn-acciones:focus {
    background: #145388;
    color: #fff;
}
.btn-acciones .caret-icon {
    font-size: .65rem;
    margin-left: 4px;
    vertical-align: middle;
}

/* Menú flotante (se mueve al body para evitar overflow:hidden) */
body > .dd-acc-menu {
    border: none;
    border-radius: 6px;
    box-shadow: 0 4px 20px rgba(0,0,0,.18);
    min-width: 175px;
    padding: 4px 0;
}
body > .dd-acc-menu .dropdown-item {
    font-size: .82rem;
    padding: 6px 14px;
    display: flex;
    align-items: center;
    gap: 7px;
    color: #333;
    transition: background .12s;
}
body > .dd-acc-menu .dropdown-item:hover {
    background: #f0f5fb;
    color: #145388;
}
body > .dd-acc-menu .dropdown-item.text-danger  { color: #dc3545 !important; }
body > .dd-acc-menu .dropdown-item.text-danger:hover  { background: #fff5f5; }
body > .dd-acc-menu .dropdown-item.text-warning { color: #b8860b !important; }
body > .dd-acc-menu .dropdown-item.text-warning:hover { background: #fffaf0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
/* DataTables JS ya cargado en el layout — no importar de nuevo */
var USUARIO_ID     = <?= (int) ($usuario['Id'] ?? 0) ?>;
var USUARIO_ACCESO = <?= (int) ($usuario['acceso'] ?? 0) ?>;

function puedeEditarFolio(idVendedorNota) {
    return parseInt(idVendedorNota, 10) === USUARIO_ID
        || USUARIO_ACCESO === 1 || USUARIO_ACCESO === 3 || USUARIO_ACCESO === 4;
}

var STATUS_LABELS = {
    1: '<span class="badge badge-primary">Abierta</span>',
    2: '<span class="badge badge-info">En proceso</span>',
    3: '<span class="badge badge-danger">Cancelada</span>',
    4: '<span class="badge badge-warning">Anticipo</span>',
    5: '<span class="badge badge-success">Pagada</span>',
    6: '<span class="badge" style="background-color:#0d6e6e;color:#fff;">Pagado</span>'
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
                var esHijo = parseInt(row.referencia || 0, 10) > 0;
                var esAnticipo = parseInt(row.anticipo, 10) !== 0;
                var label;
                var pagadoTotal = parseFloat(row.pagado_total || 0);
                var totalNota   = parseFloat(row.total || 0);
                if (sid === 6 && esHijo && esAnticipo) {
                    label = '<span class="badge" style="background-color:#0d6e6e;color:#fff;">Anticipo Pagado</span>';
                } else if (sid === 2 && !esHijo && row.tipopago === 'A Crédito' && pagadoTotal >= totalNota - 0.99) {
                    label = '<span class="badge" style="background-color:#e83e8c;color:#fff;">Listo para Verificar</span>';
                } else if (sid === 2 && !esHijo && row.tipopago === 'A Crédito') {
                    label = '<span class="badge" style="background-color:#e67e22;color:#fff;">A Crédito</span>';
                } else {
                    label = STATUS_LABELS[sid] || '<span class="badge badge-secondary">Desconocido</span>';
                }
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

    // ── Custom dropdown (sin Bootstrap JS — evita conflicto con DataTable overflow:hidden) ──
    window._accMenu    = null;
    window._accMenuSrc = null;

    window.closeAccMenu = function() {
        if (window._accMenu && window._accMenu.parentNode) {
            window._accMenu.parentNode.removeChild(window._accMenu);
        }
        window._accMenu    = null;
        window._accMenuSrc = null;
    };

    window.openAccMenu = function(e, btn, menuId) {
        e.stopPropagation();
        // Toggle: si ya está abierto el mismo menú, cerrar
        if (window._accMenuSrc === menuId) { closeAccMenu(); return; }
        closeAccMenu();

        var src = document.getElementById(menuId);
        if (!src) return;

        var rect  = btn.getBoundingClientRect();
        var clone = src.cloneNode(true);
        clone.id  = '';
        clone.style.cssText = [
            'display:block',
            'position:fixed',
            'top:'   + (rect.bottom + 3) + 'px',
            'right:' + Math.round(window.innerWidth - rect.right) + 'px',
            'left:auto',
            'z-index:10000',
            'min-width:175px',
            'border-radius:6px',
            'background:#fff',
            'box-shadow:0 4px 20px rgba(0,0,0,.18)',
            'padding:4px 0',
            'border:none'
        ].join(';');

        document.body.appendChild(clone);
        window._accMenu    = clone;
        window._accMenuSrc = menuId;

        // Ajustar si el menú se sale del viewport por abajo
        requestAnimationFrame(function() {
            if (!window._accMenu) return;
            var mr = window._accMenu.getBoundingClientRect();
            if (mr.bottom > window.innerHeight - 8) {
                window._accMenu.style.top    = 'auto';
                window._accMenu.style.bottom = (window.innerHeight - rect.top + 3) + 'px';
            }
        });
    };

    // Cerrar el menú al hacer clic fuera
    document.addEventListener('click', function(ev) {
        if (window._accMenu && !window._accMenu.contains(ev.target)) {
            closeAccMenu();
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
    var idstatus   = parseInt(n.idstatus, 10);
    var referencia = parseInt(n.referencia || 0, 10);
    var esHijo     = referencia > 0;
    var BASE       = '<?= base_url('mostrador/venta/') ?>';
    var esPropio   = puedeEditarFolio(n.idVendedor);

    // Cualquier vendedor de Mostrador puede operar cualquier folio igual que
    // si fuera propio (puedeEditarFolio ya lo permite para acceso 1,3,4).
    // Esta rama queda como respaldo por si algún día se agrega otro rol con
    // acceso de solo lectura a esta misma vista.
    if (!esPropio) {
        return '<a href="#" class="btn btn-xs btn-outline-primary mr-1"'
             + ' onclick="mostradorVerFolio(' + n.folio + '); return false;">Verificar Pago</a>';
    }

    var items = '';

    if (esHijo) {
        // Folio hijo (anticipo): abrir modal con detalle del folio hijo
        items += '<a class="dropdown-item" href="#"'
              + ' onclick="mostradorVerFolio(' + n.folio + '); return false;">Verificar Pago</a>';
        if (idstatus !== 3 && idstatus !== 5) {
            items += '<a class="dropdown-item text-danger" href="#"'
                  + ' onclick="mostradorCancelarFolioBtn(' + n.folio + '); return false;">Cancelar</a>';
        }
        // Los folios hijo no se pueden revivir de forma independiente
    } else {
        // Folio padre
        if (idstatus === 1 || idstatus === 4) {
            items += '<a class="dropdown-item" href="' + BASE + n.folio + '/confirmar">Ver / Pagar</a>';
        }
        // "Sin Pagar" (crédito, se pagará en varios abonos) en proceso: el
        // vendedor puede registrar abonos (folio hijo pendiente de que Caja
        // lo reciba y confirme). No aplica a otros tipos de pago — esos los
        // cobra Caja completos, de una sola vez.
        if (idstatus === 2 && esSinPagar(n.tipoPagoPropio)) {
            items += '<a class="dropdown-item" href="' + BASE + n.folio + '/abono">Ver / Abonar</a>';
        }
        if (idstatus === 2 || idstatus === 5 || idstatus === 3) {
            items += '<a class="dropdown-item" href="#"'
                  + ' onclick="mostradorVerFolio(' + n.folio + '); return false;">Verificar Pago</a>';
        }
        if (idstatus === 1) {
            items += '<a class="dropdown-item" href="' + BASE + n.folio + '/productos">Editar</a>';
            items += '<a class="dropdown-item" href="' + BASE + n.folio + '/duplicar">Duplicar</a>';
        }
        if (idstatus !== 5 && idstatus !== 3) {
            items += '<a class="dropdown-item text-danger" href="#"'
                  + ' onclick="mostradorCancelarFolioBtn(' + n.folio + '); return false;">Cancelar</a>';
        }
        if (idstatus === 3 && puedeRevivir(n.tipopago)) {
            items += '<a class="dropdown-item text-warning" href="#"'
                  + ' onclick="mostradorRevivirFolio(' + n.folio + '); return false;">Revivir</a>';
        }
    }

    // Ver Ticket disponible para todos los folios (padre e hijo), incluyendo cancelados
    items += '<a class="dropdown-item" href="#"'
          + ' onclick="mostradorVerTicket(' + n.folio + '); return false;">Imprimir Ticket</a>';

    // Comanda (solo productos, sin precios) — solo folios padre (los hijos
    // son abonos, sin productos propios)
    if (!esHijo && idstatus !== 3) {
        items += '<a class="dropdown-item" href="#"'
              + ' onclick="mostradorVerComanda(' + n.folio + '); return false;">Comanda</a>';
    }

    if (!items) return '<span class="text-muted">—</span>';

    var mid = 'accmenuM_' + n.folio;
    return '<div>'
         + '<button class="btn-acciones" type="button"'
         + ' onclick="openAccMenu(event,this,\'' + mid + '\')">'
         + 'Acciones <i class="simple-icon-arrow-down caret-icon"></i>'
         + '</button>'
         + '<div id="' + mid + '" class="dd-acc-menu" style="display:none">'
         + items
         + '</div>'
         + '</div>';
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
