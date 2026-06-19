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
                    btns += '<button class="btn btn-xs btn-outline-secondary"'
                          + ' onclick="cajaRevivirFolio(' + row.folio + ')">Revivir</button>';
                }
                if (idstatus === 5) {
                    btns += '<button class="btn btn-xs btn-outline-primary mr-1"'
                          + ' onclick="cajaVerFolioModal(' + row.folio + ')">Ver</button>';
                }
                // Ver Ticket: disponible para todos los status excepto cancelado
                if (idstatus !== 3) {
                    btns += '<button class="btn btn-xs btn-outline-dark mr-1"'
                          + ' onclick="cajaVerTicket(' + row.folio + ')">Ver Ticket</button>';
                }
                // Agregar Referencia (Transferencia / Depósito / Cargo con tarjeta)
                var _tp = (row.tipopago || '').toLowerCase();
                var _esRef = _tp.indexOf('transferencia') >= 0
                          || _tp.indexOf('deposito')       >= 0
                          || _tp.indexOf('depósito')       >= 0
                          || _tp.indexOf('cargo con tarjeta') >= 0;
                if (_esRef && idstatus !== 3) {
                    btns += '<button class="btn btn-xs btn-outline-secondary mr-1"'
                          + ' onclick="cajaAbrirModalReferencia('
                          + (row.mn_id_ref || 0) + ','
                          + '\'' + (row.tipopago_ref_nombre || row.tipopago || '').replace(/'/g,"\\'") + '\','
                          + '\'' + (row.mn_referencia_banco || '').replace(/'/g,"\\'") + '\','
                          + row.folio + ','
                          + (row.mn_tipo_ref || 0)
                          + ')">Referencia</button>';
                }
                // ── Botones de Facturación (Caja Nivel 2) ──
                if (esPadre && (idstatus === 5 || idstatus === 6) && idstatus !== 3) {
                    var sf  = parseInt(row.status_facturacion || 0, 10);
                    var uid = (row.uuid_fiscal || '').trim();
                    if (uid !== '') {
                        // Ya facturada — no mostrar nada en Acciones
                    } else if (sf === 1) {
                        btns += '<button class="btn btn-xs btn-primary mr-1"'
                              + ' onclick="cajaAbrirModalFactura(' + row.folio + ')">Facturar</button>';
                    } else {
                        btns += '<button class="btn btn-xs btn-outline-primary"'
                              + ' onclick="cajaAbrirModalFactura(' + row.folio + ')">Solicitar Factura</button>';
                    }
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

// ── Referencia bancaria desde Consulta Folios (Caja) ──────────────────
var _cajaRefMnId     = null;
var _cajaRefFolio    = null;
var _cajaRefMnTipoId = null;

function cajaAbrirModalReferencia(mnId, tipopago, refActual, folio, mnTipoId) {
    _cajaRefMnId     = mnId;
    _cajaRefFolio    = folio;
    _cajaRefMnTipoId = mnTipoId || 0;
    document.getElementById('cajaModalRefBancoFolio').textContent = '#' + folio;
    document.getElementById('cajaModalRefBancoTipo').textContent  = tipopago || '';
    document.getElementById('cajaInputRefBanco').value            = refActual || '';
    document.getElementById('cajaRefBancoError').classList.add('d-none');
    document.getElementById('cajaRefBancoOk').classList.add('d-none');
    $('#cajaModalRefBanco').modal('show');
    setTimeout(function() { document.getElementById('cajaInputRefBanco').focus(); }, 400);
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnCajaGuardarRefBanco').addEventListener('click', function() {
        var btn = this;
        var ref = document.getElementById('cajaInputRefBanco').value.trim();

        btn.disabled = true;
        btn.textContent = 'Guardando...';

        var fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('mn_id',      _cajaRefMnId     || 0);
        fd.append('mn_tipo_id', _cajaRefMnTipoId || 0);
        fd.append('folio',      _cajaRefFolio    || 0);
        fd.append('referencia', ref);

        fetch('<?= base_url('caja/monto/referencia') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Guardar';
            if (data.ok) {
                document.getElementById('cajaRefBancoOk').classList.remove('d-none');
                setTimeout(function() {
                    $('#cajaModalRefBanco').modal('hide');
                    $('#tablaCajaConsulta').DataTable().ajax.reload(null, false);
                }, 1200);
            } else {
                document.getElementById('cajaRefBancoError').textContent = data.msg || 'Error al guardar.';
                document.getElementById('cajaRefBancoError').classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Guardar';
            document.getElementById('cajaRefBancoError').textContent = 'Error de conexión.';
            document.getElementById('cajaRefBancoError').classList.remove('d-none');
        });
    });

    document.getElementById('cajaInputRefBanco').addEventListener('keypress', function(e) {
        if (e.which === 13) document.getElementById('btnCajaGuardarRefBanco').click();
    });
});

// ── Facturación desde Consulta Folios (Caja Nivel 2) ───────────────────

// Abre el modal pre-llenando datos vía AJAX (Escenario 1 y 2)
var _cajaSfFolioActivo = null;

function cajaAbrirModalFactura(folio) {
    _cajaSfFolioActivo = folio;
    document.getElementById('cajaSfFolioNum').textContent    = '#' + folio;
    document.getElementById('cajaSfError').classList.add('d-none');
    document.getElementById('cajaSfError').textContent       = '';
    document.getElementById('btnCajaSfFacturar').disabled    = false;
    document.getElementById('btnCajaSfFacturar').innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';

    ['cajaSfRfc','cajaSfRazonSocial','cajaSfCP'].forEach(function(id) {
        document.getElementById(id).value = '';
    });

    // Deshabilitar el botón mientras se cargan los datos fiscales para evitar
    // que el click prematuro vea el RFC vacío y muestre "El RFC es requerido".
    var btnFacturar = document.getElementById('btnCajaSfFacturar');
    btnFacturar.disabled = true;
    btnFacturar.innerHTML = '<i class="simple-icon-refresh mr-1"></i> Cargando...';

    $('#modalCajaSolicitarFactura').modal('show');

    fetch('<?= base_url('caja/folio/') ?>' + folio + '/datos-fiscales', {
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        document.getElementById('cajaSfRfc').value           = d.rfcReceptor           || '';
        document.getElementById('cajaSfRazonSocial').value   = d.razonSocialReceptor   || '';
        document.getElementById('cajaSfCP').value            = d.cpReceptor            || '';
        document.getElementById('cajaSfUsoCFDI').value       = d.usoCFDI               || 'S01';
        document.getElementById('cajaSfRegimenFiscal').value = d.regimenFiscalReceptor || '616';
        document.getElementById('cajaSfFormaPago').value     = d.formaPagoCFDI         || '01';
    })
    .catch(function() { /* campos vacíos, el usuario los llena manualmente */ })
    .finally(function() {
        btnFacturar.disabled = false;
        btnFacturar.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
    });
}

// Alias legacy
function cajaFacturar(folio) { cajaAbrirModalFactura(folio); }
function cajaSolicitarFactura(folio) { cajaAbrirModalFactura(folio); }

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnCajaSfFacturar').addEventListener('click', function() {
        var btn = this;
        var rfc = document.getElementById('cajaSfRfc').value.trim().toUpperCase();
        var rs  = document.getElementById('cajaSfRazonSocial').value.trim().toUpperCase();
        var cp  = document.getElementById('cajaSfCP').value.trim();
        var err = document.getElementById('cajaSfError');

        if (!rfc) { err.textContent = 'El RFC es requerido.';          err.classList.remove('d-none'); return; }
        if (!rs)  { err.textContent = 'La Razón Social es requerida.'; err.classList.remove('d-none'); return; }
        if (!cp)  { err.textContent = 'El Código Postal es requerido.'; err.classList.remove('d-none'); return; }

        err.classList.add('d-none');
        btn.disabled    = true;
        btn.textContent = 'Procesando...';

        var fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('rfcReceptor',           rfc);
        fd.append('razonSocialReceptor',   rs);
        fd.append('cpReceptor',            cp);
        fd.append('usoCFDI',               document.getElementById('cajaSfUsoCFDI').value);
        fd.append('regimenFiscalReceptor', document.getElementById('cajaSfRegimenFiscal').value);
        fd.append('formaPagoCFDI',         document.getElementById('cajaSfFormaPago').value);
        fd.append('metodoPagoCFDI',        'PUE');

        fetch('<?= base_url('caja/folio/') ?>' + _cajaSfFolioActivo + '/facturar', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled    = false;
            btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
            if (data.success) {
                $('#modalCajaSolicitarFactura').modal('hide');
                alert('✔ ' + (data.mensaje || 'Factura generada correctamente.'));
                $('#tablaCajaConsulta').DataTable().ajax.reload(null, false);
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

<!-- Modal: Referencia bancaria (Caja) -->
<div class="modal fade" id="cajaModalRefBanco" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="simple-icon-tag mr-1"></i>
                    Referencia — Folio <span id="cajaModalRefBancoFolio"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Método: <strong id="cajaModalRefBancoTipo"></strong></p>
                <div class="form-group mb-2">
                    <label>Número de referencia</label>
                    <input type="text" id="cajaInputRefBanco" class="form-control"
                           placeholder="Ej. 1234567890" maxlength="100">
                </div>
                <div id="cajaRefBancoError" class="alert alert-danger py-1 d-none"></div>
                <div id="cajaRefBancoOk"    class="alert alert-success py-1 d-none">✔ Guardado correctamente</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnCajaGuardarRefBanco">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Solicitar Factura Caja (Escenario 2) -->
<div class="modal fade" id="modalCajaSolicitarFactura" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="simple-icon-doc mr-2"></i>Solicitar Factura — Folio <span id="cajaSfFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Captura los datos fiscales del cliente. Al facturar, quedarán guardados en su perfil para futuras compras.</p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>RFC Receptor <span class="text-danger">*</span></label>
                            <input type="text" id="cajaSfRfc" class="form-control text-uppercase" maxlength="13" placeholder="XAXX010101000">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Razón Social / Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="cajaSfRazonSocial" class="form-control text-uppercase" maxlength="200">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>CP Fiscal <span class="text-danger">*</span></label>
                            <input type="text" id="cajaSfCP" class="form-control" maxlength="5" placeholder="72270">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Régimen Fiscal</label>
                            <select id="cajaSfRegimenFiscal" class="form-control">
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
                            <select id="cajaSfUsoCFDI" class="form-control">
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
                            <select id="cajaSfFormaPago" class="form-control">
                                <option value="01" selected>01 – Efectivo</option>
                                <option value="02">02 – Cheque nominativo</option>
                                <option value="03">03 – Transferencia electrónica</option>
                                <option value="04">04 – Tarjeta de crédito</option>
                                <option value="28">28 – Tarjeta de débito</option>
                                <option value="99">99 – Por definir</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="cajaSfError" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnCajaSfFacturar">
                    <i class="simple-icon-doc mr-1"></i> Facturar
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
