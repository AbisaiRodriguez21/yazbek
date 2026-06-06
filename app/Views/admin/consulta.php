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
    6: '<span class="badge" style="background-color:#0d6e6e;color:#fff;">Liquidado</span>'
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

function accionesNota(n) {
    var btns = '';
    var idstatus   = parseInt(n.idstatus, 10);
    var referencia = parseInt(n.referencia || 0, 10);
    var esPadre    = referencia === 0;
    var BASE_PAGO  = '<?= base_url('admin/venta/') ?>';

    // Ver / Pagar: folios padre Abiertos o Anticipo
    if ((idstatus === 1 || idstatus === 4) && esPadre) {
        btns += '<a href="' + BASE_PAGO + n.folio + '/confirmar" class="btn btn-xs btn-outline-primary mr-1">Ver / Pagar</a>';
    }

    // Ver modal: folios hijo o notas pagadas/liquidadas/canceladas
    if (!esPadre || idstatus === 5 || idstatus === 6 || idstatus === 3) {
        btns += '<a href="#" class="btn btn-xs btn-outline-secondary mr-1"'
              + ' onclick="adminVerFolio(' + n.folio + '); return false;">Ver</a>';
    }

    // Liquidar: status Anticipo (4): solo padre. Status Pagada (5): padre e hijos. NO si Liquidado (6)
    if ((idstatus === 4 && esPadre) || idstatus === 5) {
        btns += '<a href="#" class="btn btn-xs btn-success mr-1"'
              + ' onclick="adminLiquidarAnticipo(' + n.folio + '); return false;">Liquidar</a>';
    }

    // Cancelar: cualquier nota que NO esté cancelada ni liquidada
    if (idstatus !== 3 && idstatus !== 6) {
        btns += '<a href="#" class="btn btn-xs btn-outline-danger mr-1"'
              + ' onclick="adminCancelarFolio(' + n.folio + '); return false;">Cancelar</a>';
    }

    // Revivir: solo folios PADRE cancelados con método de pago permitido
    if (idstatus === 3 && esPadre && puedeRevivir(n.tipopago)) {
        btns += '<a href="#" class="btn btn-xs btn-outline-secondary"'
              + ' onclick="adminRevivirFolio(' + n.folio + '); return false;">Revivir</a>';
    }

    // Pagada o Liquidada + padre: Ver Ticket
    if ((idstatus === 5 || idstatus === 6) && esPadre) {
        btns += '<a href="#" class="btn btn-xs btn-outline-dark mr-1"'
              + ' onclick="adminVerTicket(' + n.folio + '); return false;">Ver Ticket</a>';
    }

    // ── Botones de Facturación (solo Admin — acceso=1) ────────────
    // Solo notas padre, pagadas/liquidadas, no canceladas
    if (esPadre && (idstatus === 5 || idstatus === 6) && idstatus !== 3) {
        var sf  = parseInt(n.status_facturacion || 0, 10);
        var uid = (n.uuid_fiscal || '').trim();
        if (uid !== '') {
            // Ya facturada — no mostrar nada en Acciones (el badge ya aparece en Status)
        } else if (sf === 1) {
            btns += '<a href="#" class="btn btn-xs btn-primary mr-1"'
                  + ' onclick="adminAbrirModalFactura(' + n.folio + '); return false;">Facturar</a>';
        } else {
            btns += '<a href="#" class="btn btn-xs btn-outline-primary"'
                  + ' onclick="adminAbrirModalFactura(' + n.folio + '); return false;">Solicitar Factura</a>';
        }
    }

    if (!btns) btns = '<span class="text-muted">—</span>';
    return btns;
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
    if (!confirm('¿Liquidar el folio #' + folio + ' y todos sus pagos? Esta acción marcará la nota como Liquidado.')) return;
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