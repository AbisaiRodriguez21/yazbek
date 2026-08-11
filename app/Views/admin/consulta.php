<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
/* ── Dropdown de acciones en DataTable ────────────────────── */
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
body > .dd-acc-menu .dropdown-item i {
    font-size: .9rem;
    width: 16px;
    text-align: center;
    flex-shrink: 0;
}
body > .dd-acc-menu .dropdown-item.text-success { color: #28a745 !important; }
body > .dd-acc-menu .dropdown-item.text-success:hover { background: #f0fff4; }
body > .dd-acc-menu .dropdown-item.text-danger  { color: #dc3545 !important; }
body > .dd-acc-menu .dropdown-item.text-danger:hover  { background: #fff5f5; }
body > .dd-acc-menu .dropdown-divider { margin: 4px 0; }
</style>
<?= $this->endSection() ?>

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

<div class="separator mb-4"></div>

<div class="audit-card">
    <div class="audit-card-header">
        <i class="simple-icon-list"></i>
        <span>Registro de Folios</span>
        <span class="badge-count" id="totalFoliosConsulta">&mdash;</span>
    </div>
    <div style="padding: 16px 20px 20px;">
        <table class="table responsive nowrap" id="tablaAdminConsulta" style="width:100%">
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

<!-- Modal Nuevo Pago Anticipo (Admin) -->
<div class="modal fade" id="modalAdminNuevoPago" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Pago — Folio <span id="anpFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Total nota</small>
                        <strong id="anpTotal">$0.00</strong>
                    </div>
                </div>
                <div class="form-group">
                    <label>Método de pago <span class="text-danger">*</span></label>
                    <select id="anpTipoPago" class="form-control">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($tiposPago as $tp): ?>
                        <option value="<?= (int)$tp['id'] ?>"><?= esc($tp['tipo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto del pago <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="number" id="anpMonto" class="form-control" min="0.01" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div id="anpError" class="alert alert-danger d-none"></div>
                <div id="anpSuccess" class="alert alert-success d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAdminGuardarPago">
                    <i class="iconsminds-money-2 mr-1"></i> Registrar Pago
                </button>
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
    5: '<span class="badge badge-success">Pagada</span>',
    6: '<span class="badge" style="background-color:#0d6e6e;color:#fff;">Pagado</span>'
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
                if (row.fecha_edicion) {
                    label += ' <span class="badge" style="background:#6f42c1;color:#fff;font-size:10px;" title="Editado el ' + row.fecha_edicion + '">Editado</span>';
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
            $('#totalFoliosConsulta').text(total.toLocaleString() + ' folios');
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

// Escapa una cadena para usarla como argumento en un atributo onclick (comilla simple)
function _sq(s) { return "'" + String(s || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'") + "'"; }

function accionesNota(n) {
    var idstatus   = parseInt(n.idstatus, 10);
    var referencia = parseInt(n.referencia || 0, 10);
    var esPadre    = referencia === 0;
    var BASE_PAGO  = '<?= base_url('admin/venta/') ?>';
    var items      = '';

    // Ver / Pagar
    if ((idstatus === 1 || idstatus === 4) && esPadre) {
        items += '<a class="dropdown-item" href="' + BASE_PAGO + n.folio + '/confirmar">'
               + '<i class="simple-icon-wallet"></i>Ver / Pagar</a>';
    }

    // "Sin Pagar" (crédito, se pagará en varios abonos) en proceso: se puede
    // registrar un abono (folio hijo pendiente de recibirse y confirmarse).
    if (idstatus === 2 && esPadre && esSinPagar(n.tipoPagoPropio)) {
        items += '<a class="dropdown-item" href="' + BASE_PAGO + n.folio + '/abono">'
               + '<i class="simple-icon-wallet"></i>Ver / Abonar</a>';
    }

    // Editar productos — solo folios padre aún no verificados/cobrados
    // (1=Abierta, 2=En proceso). Reutiliza el Paso 2 con los productos ya
    // cargados; al continuar al Paso 3 se reconfirma forma de pago.
    if ((idstatus === 1 || idstatus === 2) && esPadre) {
        items += '<a class="dropdown-item" href="' + BASE_PAGO + n.folio + '/productos">'
               + '<i class="simple-icon-pencil"></i>Editar productos</a>';
    }

    // Ver modal
    if (!esPadre || idstatus === 2 || idstatus === 5 || idstatus === 6 || idstatus === 3) {
        items += '<a class="dropdown-item" href="#" onclick="adminVerFolio(' + n.folio + '); return false;">'
               + '<i class="simple-icon-eye"></i>Ver detalle</a>';
    }

    // Ver Ticket — disponible para todos los folios (padre e hijo), incluyendo cancelados
    items += '<a class="dropdown-item" href="#" onclick="adminVerTicket(' + n.folio + '); return false;">'
           + '<i class="simple-icon-printer"></i>Ver Ticket</a>';

    // Comanda (solo productos, sin precios) — solo folios padre (los hijos
    // son abonos, sin productos propios) y no cancelados
    if (esPadre && idstatus !== 3) {
        items += '<a class="dropdown-item" href="#" onclick="adminVerComanda(' + n.folio + '); return false;">'
               + '<i class="simple-icon-note"></i>Comanda</a>';
    }

    // Agregar Referencia (Transferencia / Depósito / Cheque / Cargo con
    // tarjeta / Tarjeta Débito / Tarjeta Crédito)
    var _tp = (n.tipopago || '').toLowerCase();
    var _esRef = _tp.indexOf('transferencia') >= 0
              || _tp.indexOf('deposito')       >= 0
              || _tp.indexOf('depósito')  >= 0
              || _tp.indexOf('cheque')         >= 0
              || _tp.indexOf('cargo con tarjeta') >= 0
              || _tp.indexOf('tarjeta debito')  >= 0
              || _tp.indexOf('tarjeta débito')  >= 0
              || _tp.indexOf('tarjeta credito') >= 0
              || _tp.indexOf('tarjeta crédito') >= 0;
    if (_esRef) {
        items += '<div class="dropdown-divider"></div>';
        items += '<a class="dropdown-item" href="#" onclick="adminAbrirModalReferencia('
               + n.folio
               + '); return false;"><i class="simple-icon-tag"></i>Agregar Referencia</a>';
        items += '<div class="dropdown-divider"></div>';
    }

    // Liquidar
    if ((idstatus === 4 && esPadre) || idstatus === 5) {
        items += '<a class="dropdown-item text-success" href="#" onclick="adminLiquidarAnticipo(' + n.folio + '); return false;">'
               + '<i class="simple-icon-check"></i>Liquidar</a>';
    }

    // Cancelar
    if (idstatus !== 3 && idstatus !== 6) {
        items += '<a class="dropdown-item text-danger" href="#" onclick="adminCancelarFolio(' + n.folio + '); return false;">'
               + '<i class="simple-icon-close"></i>Cancelar</a>';
    }

    // Revivir
    if (idstatus === 3 && esPadre && puedeRevivir(n.tipopago)) {
        items += '<a class="dropdown-item" href="#" onclick="adminRevivirFolio(' + n.folio + '); return false;">'
               + '<i class="simple-icon-refresh"></i>Revivir</a>';
    }

    // Facturación
    if (esPadre && (idstatus === 5 || idstatus === 6) && idstatus !== 3) {
        var sf  = parseInt(n.status_facturacion || 0, 10);
        var uid = (n.uuid_fiscal || '').trim();
        if (uid === '') {
            items += '<div class="dropdown-divider"></div>';
            if (sf === 1) {
                items += '<a class="dropdown-item" href="#" onclick="adminAbrirModalFactura(' + n.folio + '); return false;">'
                       + '<i class="simple-icon-doc"></i>Facturar</a>';
            } else {
                items += '<a class="dropdown-item" href="#" onclick="adminAbrirModalFactura(' + n.folio + '); return false;">'
                       + '<i class="simple-icon-doc"></i>Solicitar Factura</a>';
            }
        }
    }

    if (!items) return '<span class="text-muted">—</span>';

    var mid = 'accmenu_' + n.folio;
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

// fn_muestra_modal: alias legacy conservado por compatibilidad
function fn_muestra_modal() { fn_liquidar_modal(); }

function fn_modal_calcelar_nota() {
    var folio = document.getElementById('folio_input') ? document.getElementById('folio_input').value : 0;
    if (!folio) return;
    if (!confirm('¿Cancelar el folio ' + folio + '? Esta acción restaura el inventario.')) return;
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';
    var fd = new FormData();
    fd.append(csrfName, csrfHash);
    fd.append('folio', folio);
    fetch('<?= base_url('admin/caja/cancelar') ?>', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.text(); })
    .then(function(resp) {
        $('#modalVerFolio').modal('hide');
        $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
    })
    .catch(function() { alert('Error de conexión.'); });
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

var anpFolioActivo = null;

function adminNuevoPago(folio, total) {
    anpFolioActivo = folio;
    document.getElementById('anpFolioNum').textContent = '#' + folio;
    document.getElementById('anpTotal').textContent    = '$' + parseFloat(total).toFixed(2);
    document.getElementById('anpTipoPago').value = '';
    document.getElementById('anpMonto').value    = '';
    document.getElementById('anpError').classList.add('d-none');
    document.getElementById('anpSuccess').classList.add('d-none');
    document.getElementById('btnAdminGuardarPago').disabled = false;
    document.getElementById('btnAdminGuardarPago').innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';
    $('#modalAdminNuevoPago').modal('show');
}

document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = '<?= csrf_token() ?>';
    var csrfHash  = '<?= csrf_hash() ?>';

    document.getElementById('btnAdminGuardarPago').addEventListener('click', function () {
        var btn      = this;
        var tipoPago = document.getElementById('anpTipoPago').value;
        var monto    = parseFloat(document.getElementById('anpMonto').value);
        var errEl    = document.getElementById('anpError');
        var sucEl    = document.getElementById('anpSuccess');

        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');

        if (!tipoPago) {
            errEl.textContent = 'Selecciona un método de pago.';
            errEl.classList.remove('d-none'); return;
        }
        if (!monto || monto <= 0) {
            errEl.textContent = 'Ingresa un monto válido mayor a $0.';
            errEl.classList.remove('d-none'); return;
        }

        btn.disabled = true;
        btn.textContent = 'Guardando...';

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);
        fd.append('folio',    anpFolioActivo);
        fd.append('tipoPago', tipoPago);
        fd.append('monto',    monto);

        fetch('<?= base_url('mostrador/anticipo/nuevo-pago') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';
            if (!data.ok) {
                errEl.textContent = data.error || 'Error al registrar el pago.';
                errEl.classList.remove('d-none'); return;
            }
            var msg = 'Pago registrado. Folio hijo: #' + data.folioHijo
                    + ' | Pagado: $' + parseFloat(data.totalPagado).toFixed(2)
                    + ' de $' + parseFloat(data.totalNota).toFixed(2);
            if (data.liquidado) msg += ' — ¡Nota liquidada!';
            sucEl.textContent = msg;
            sucEl.classList.remove('d-none');
            btn.disabled = true;
            setTimeout(function() {
                $('#modalAdminNuevoPago').modal('hide');
                $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
            }, 1800);
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';
            errEl.textContent = 'Error de conexión.';
            errEl.classList.remove('d-none');
        });
    });
});

function fn_liquidar_modal() {
    var folio = document.getElementById('folio_input') ? document.getElementById('folio_input').value : 0;
    if (!folio) return;
    $('#modalVerFolio').modal('hide');
    adminLiquidarAnticipo(parseInt(folio, 10));
}

function adminLiquidarAnticipo(folio) {
    if (!confirm('¿Liquidar el folio #' + folio + ' y todos sus pagos? Esta acción marcará la nota como Pagado.')) return;
    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fetch('<?= base_url('admin/folio/') ?>' + folio + '/liquidar', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
        } else {
            alert(data.error || 'No se pudo liquidar.');
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function adminVerTicket(folio) {
    var url = '<?= base_url('admin/folio/') ?>' + folio + '/ticket';
    window.open(url, '_blank', 'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=500,height=600');
}

function adminVerComanda(folio) {
    var url = '<?= base_url('admin/folio/') ?>' + folio + '/comanda';
    window.open(url, '_blank', 'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=360,height=520');
}

function adminCancelarFolio(folio) {
    if (!confirm('¿Cancelar el folio ' + folio + '? Esta acción restaura el inventario.')) return;
    $.post('<?= base_url('admin/caja/cancelar') ?>', {
        folio: folio,
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
    }, function(resp) {
        if (resp === '1') {
            $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
        } else {
            alert('No se pudo cancelar el folio ' + folio + '.');
        }
    }).fail(function() {
        alert('Error de comunicación al cancelar.');
    });
}

function adminRevivirFolio(folio) {
    if (!confirm('¿Revivir el folio ' + folio + '? Se volverá a descontar el stock de los productos.')) return;
    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fetch('<?= base_url('admin/folio/') ?>' + folio + '/revivir', {
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
                window.location.href = '<?= base_url('admin/venta/') ?>' + folio + '/productos';
            } else {
                $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
            }
        } else {
            alert('No se pudo revivir el folio: ' + (data.error || 'error desconocido'));
        }
    })
    .catch(function() { alert('Error de conexión al revivir.'); });
}

// ── Referencia bancaria desde Consulta Folios ──────────────────────────
// Un folio puede tener MÁS DE UNA forma de pago que requiera referencia
// (p.ej. Transferencia + Cargo con tarjeta) — se listan todas y cada una
// se guarda por separado.
var _refFolio = null;

function _refEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function adminAbrirModalReferencia(folio) {
    _refFolio = folio;
    document.getElementById('modalRefBancoFolio').textContent = '#' + folio;
    document.getElementById('refBancoLista').innerHTML =
        '<p class="text-muted text-center py-2 mb-0">Cargando...</p>';
    document.getElementById('refBancoError').classList.add('d-none');
    $('#modalRefBanco').modal('show');

    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fd.append('folio', folio);

    fetch('<?= base_url('admin/caja/monto/referencias-pendientes') ?>', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var items = (data.ok && data.items) ? data.items : [];
        if (items.length === 0) {
            document.getElementById('refBancoLista').innerHTML =
                '<p class="text-muted text-center py-2 mb-0">Este folio no tiene formas de pago que requieran referencia.</p>';
            return;
        }
        var html = '';
        items.forEach(function(it, i) {
            html += '<div class="form-group mb-3' + (i < items.length - 1 ? ' pb-3 border-bottom' : '') + '">'
                  + '<label class="mb-1"><strong>' + _refEsc(it.tipo) + '</strong>'
                  + ' <span class="text-muted">($' + parseFloat(it.monto || 0).toFixed(2) + ')</span></label>'
                  + '<div class="input-group">'
                  + '<input type="text" class="form-control ref-input" '
                  + 'data-id-notas="' + it.idNotas + '" data-tipo-id="' + it.idTipoPago + '" '
                  + 'value="' + _refEsc(it.referencia) + '" placeholder="Ej. 1234567890" maxlength="100">'
                  + '<div class="input-group-append">'
                  + '<button type="button" class="btn btn-primary ref-guardar" '
                  + 'data-id-notas="' + it.idNotas + '" data-tipo-id="' + it.idTipoPago + '">Guardar</button>'
                  + '</div></div>'
                  + '<small class="ref-status text-success d-none" data-tipo-id="' + it.idTipoPago + '">✔ Guardado</small>'
                  + '</div>';
        });
        document.getElementById('refBancoLista').innerHTML = html;
    })
    .catch(function() {
        document.getElementById('refBancoLista').innerHTML = '';
        document.getElementById('refBancoError').textContent = 'Error al cargar las formas de pago del folio.';
        document.getElementById('refBancoError').classList.remove('d-none');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('refBancoLista').addEventListener('click', function(e) {
        var btn = e.target.closest('.ref-guardar');
        if (!btn) return;

        var idNotas = btn.getAttribute('data-id-notas');
        var tipoId  = btn.getAttribute('data-tipo-id');
        var input   = document.querySelector('.ref-input[data-tipo-id="' + tipoId + '"]');
        var status  = document.querySelector('.ref-status[data-tipo-id="' + tipoId + '"]');
        var ref     = input.value.trim();

        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = 'Guardando...';
        status.classList.add('d-none');
        document.getElementById('refBancoError').classList.add('d-none');

        var fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('mn_id',      idNotas);
        fd.append('mn_tipo_id', tipoId);
        fd.append('folio',      _refFolio || 0);
        fd.append('referencia', ref);

        fetch('<?= base_url('admin/caja/monto/referencia') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = originalText;
            if (data.ok) {
                status.classList.remove('d-none');
                $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
            } else {
                document.getElementById('refBancoError').textContent = data.msg || 'Error al guardar.';
                document.getElementById('refBancoError').classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = originalText;
            document.getElementById('refBancoError').textContent = 'Error de conexión.';
            document.getElementById('refBancoError').classList.remove('d-none');
        });
    });

    document.getElementById('refBancoLista').addEventListener('keypress', function(e) {
        if (e.which === 13 && e.target.classList.contains('ref-input')) {
            e.preventDefault();
            var tipoId = e.target.getAttribute('data-tipo-id');
            var btn = document.querySelector('.ref-guardar[data-tipo-id="' + tipoId + '"]');
            if (btn) btn.click();
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

// ── Facturación desde Consulta Folios ──────────────────────────────────

// Abre el modal pre-llenando datos vía AJAX (sirve para Escenario 1 y 2)
var _sfFolioActivo = null;

function adminAbrirModalFactura(folio) {
    _sfFolioActivo = folio;
    document.getElementById('sfFolioNum').textContent    = '#' + folio;
    document.getElementById('sfError').classList.add('d-none');
    document.getElementById('sfError').textContent       = '';
    document.getElementById('btnSfFacturar').disabled    = false;
    document.getElementById('btnSfFacturar').innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';

    // Limpiar campos mientras carga
    ['sfRfc','sfRazonSocial','sfCP'].forEach(function(id) {
        document.getElementById(id).value = '';
    });

    $('#modalSolicitarFactura').modal('show');

    // Obtener datos fiscales del servidor (nota + cliente como fallback)
    fetch('<?= base_url('admin/folio/') ?>' + folio + '/datos-fiscales', {
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        document.getElementById('sfRfc').value           = d.rfcReceptor           || '';
        document.getElementById('sfRazonSocial').value   = d.razonSocialReceptor   || '';
        document.getElementById('sfCP').value            = d.cpReceptor            || '';
        document.getElementById('sfUsoCFDI').value       = d.usoCFDI               || 'S01';
        document.getElementById('sfRegimenFiscal').value = d.regimenFiscalReceptor || '616';
        document.getElementById('sfFormaPago').value     = d.formaPagoCFDI         || '01';
        document.getElementById('sfMetodoPago').value    = 'PUE';
    })
    .catch(function() { /* campos vacíos, el usuario los llena manualmente */ });
}

// Alias legacy por si algún botón del modal interior lo llama
function adminFacturar(folio) { adminAbrirModalFactura(folio); }
function adminSolicitarFactura(folio) { adminAbrirModalFactura(folio); }

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnSfFacturar').addEventListener('click', function() {
        var btn = this;
        var rfc = document.getElementById('sfRfc').value.trim().toUpperCase();
        var rs  = document.getElementById('sfRazonSocial').value.trim().toUpperCase();
        var cp  = document.getElementById('sfCP').value.trim();
        var err = document.getElementById('sfError');

        if (!rfc)  { err.textContent = 'El RFC es requerido.';         err.classList.remove('d-none'); return; }
        if (!rs)   { err.textContent = 'La Razón Social es requerida.'; err.classList.remove('d-none'); return; }
        if (!cp)   { err.textContent = 'El Código Postal es requerido.'; err.classList.remove('d-none'); return; }

        err.classList.add('d-none');
        btn.disabled    = true;
        btn.textContent = 'Procesando...';

        var fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('rfcReceptor',           rfc);
        fd.append('razonSocialReceptor',   rs);
        fd.append('cpReceptor',            cp);
        fd.append('usoCFDI',               document.getElementById('sfUsoCFDI').value);
        fd.append('regimenFiscalReceptor', document.getElementById('sfRegimenFiscal').value);
        fd.append('formaPagoCFDI',         document.getElementById('sfFormaPago').value);
        fd.append('metodoPagoCFDI',        document.getElementById('sfMetodoPago').value);

        fetch('<?= base_url('admin/folio/') ?>' + _sfFolioActivo + '/facturar', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled    = false;
            btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
            if (data.success) {
                $('#modalSolicitarFactura').modal('hide');
                alert('✔ ' + (data.mensaje || 'Factura generada correctamente.'));
                $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
            } else {
                err.textContent = data.mensaje || 'Error al generar la factura.';
                err.classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled    = false;
            btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
            err.textContent = 'Error de conexión.';
            err.classList.remove('d-none');
        });
    });
});
</script>

<!-- Modal: Referencia bancaria -->
<div class="modal fade" id="modalRefBanco" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="simple-icon-tag mr-1"></i>
                    Referencia — Folio <span id="modalRefBancoFolio"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="refBancoLista"></div>
                <div id="refBancoError" class="alert alert-danger py-1 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Solicitar Factura (Escenario 2 — datos SAT) -->
<div class="modal fade" id="modalSolicitarFactura" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="simple-icon-doc mr-2"></i>Solicitar Factura — Folio <span id="sfFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Captura los datos fiscales del cliente. Al facturar, estos datos se guardarán en el perfil del cliente para sus próximas compras.</p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>RFC Receptor <span class="text-danger">*</span></label>
                            <input type="text" id="sfRfc" class="form-control text-uppercase" maxlength="13" placeholder="XAXX010101000">
                            <small class="text-muted">12 letras/números (moral) o 13 (física)</small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Razón Social / Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="sfRazonSocial" class="form-control text-uppercase" maxlength="200" placeholder="NOMBRE COMPLETO O RAZÓN SOCIAL">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>CP Fiscal <span class="text-danger">*</span></label>
                            <input type="text" id="sfCP" class="form-control" maxlength="5" placeholder="72270">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Régimen Fiscal</label>
                            <select id="sfRegimenFiscal" class="form-control">
                                <option value="616" selected>616 – Sin obligaciones fiscales</option>
                                <option value="601">601 – General de Ley Personas Morales</option>
                                <option value="612">612 – Personas Físicas con Actividades Empresariales</option>
                                <option value="621">621 – Incorporación Fiscal</option>
                                <option value="626">626 – Régimen Simplificado de Confianza (RESICO)</option>
                                <option value="605">605 – Sueldos y Salarios</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Uso del CFDI</label>
                            <select id="sfUsoCFDI" class="form-control">
                                <option value="S01" selected>S01 – Sin efectos fiscales</option>
                                <option value="G01">G01 – Adquisición de mercancias</option>
                                <option value="G03">G03 – Gastos en general</option>
                                <option value="I01">I01 – Construcciones</option>
                                <option value="D01">D01 – Honorarios médicos y dentales</option>
                                <option value="CP01">CP01 – Pagos</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Forma de Pago</label>
                            <select id="sfFormaPago" class="form-control">
                                <option value="01" selected>01 – Efectivo</option>
                                <option value="02">02 – Cheque nominativo</option>
                                <option value="03">03 – Transferencia electrónica</option>
                                <option value="04">04 – Tarjeta de crédito</option>
                                <option value="28">28 – Tarjeta de débito</option>
                                <option value="99">99 – Por definir</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Método de Pago</label>
                            <select id="sfMetodoPago" class="form-control">
                                <option value="PUE" selected>PUE – Pago en una sola exhibición</option>
                                <option value="PPD">PPD – Pago en parcialidades o diferido</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="sfError" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSfFacturar">
                    <i class="simple-icon-doc mr-1"></i> Facturar
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
                    