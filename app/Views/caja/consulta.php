<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<style>
/* Vista previa de factura — imita el diseño del PDF final (CfdiPdfService) */
.cfdi-preview { font-size: 8.5pt; color: #111; }
.cfdi-preview table { width: 100%; border-collapse: collapse; }
.cfdi-preview .r { text-align: right; }
.cfdi-preview .c { text-align: center; }
.cfdi-header { display: flex; border: 1px solid #888; margin-bottom: 6px; }
.cfdi-header-left { width: 65%; padding: 8px 10px; border-right: 1px solid #888; }
.cfdi-header-right { width: 35%; }
.cfdi-emp-name { font-size: 13pt; font-weight: bold; color: #1a3a5c; margin-bottom: 3px; }
.cfdi-emp-data { font-size: 8pt; line-height: 1.6; }
.cfdi-tipo-box { background: #1a3a5c; color: #fff; font-weight: bold; font-size: 8.5pt; text-align: center; padding: 5px 6px; }
.cfdi-tipo-tabla td { font-size: 8pt; padding: 3px 6px; border-bottom: 1px solid #ddd; }
.cfdi-tipo-tabla .lbl { color: #1a3a5c; font-weight: bold; width: 50%; }
.cfdi-sec-title { background: #222; color: #fff; font-weight: bold; padding: 4px 6px; font-size: 8.5pt; margin: 4px 0 2px; }
.cfdi-cliente-box { border: 1px solid #aaa; padding: 6px 8px; font-size: 8pt; margin-bottom: 6px; }
.cfdi-conc th { background: #222; color: #fff; padding: 4px; font-size: 7.5pt; text-align: center; border: 1px solid #555; }
.cfdi-conc td { border: 1px solid #ddd; padding: 3px 4px; font-size: 8pt; }
.cfdi-imp-row td { background: #f0f0f0; font-size: 7pt; color: #555; padding: 2px 6px; border-left: 1px solid #ddd; border-right: 1px solid #ddd; }
.cfdi-tot { width: 280px; margin-left: auto; margin-top: 6px; }
.cfdi-tot td { padding: 3px 6px; font-size: 8.5pt; }
.cfdi-tot-final td { background: #1a3a5c; color: #fff; font-weight: bold; font-size: 9.5pt; }
.cfdi-pending-note { font-size: 7.5pt; color: #888; margin-top: 8px; font-style: italic; }
.cfdi-logo { max-height: 45px; max-width: 150px; margin-bottom: 6px; }
.cfdi-regimen-bar { border: 1px solid #888; padding: 4px 8px; font-size: 8pt; margin-bottom: 6px; }
.cfdi-pend { color: #aaa; font-style: italic; }
.cfdi-sbox { background: #f7f7f7; border: 1px dashed #ccc; padding: 6px 8px; font-size: 7.5pt; color: #999; margin-top: 6px; text-align: center; }
</style>

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
    5: '<span class="badge badge-success">Pagada</span>',
    6: '<span class="badge" style="background-color:#0d6e6e;color:#fff;">Pagado</span>'
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
                if (row.fecha_edicion) {
                    label += ' <span class="badge" style="background:#6f42c1;color:#fff;font-size:10px;" title="Editado el ' + row.fecha_edicion + '">Editado</span>';
                }
                return label;
            }},
            { data: null, orderable: false, searchable: false,
              render: function(data, type, row) {
                var idstatus = parseInt(row.idstatus, 10);
                var esPadre  = parseInt(row.referencia || 0, 10) === 0;
                var btns = '';
                /* Botones como <button> para evitar que el SPA los intercepte */
                if (idstatus !== 5) {
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
                // Ver Ticket: disponible para todos los status, incluyendo cancelado
                btns += '<button class="btn btn-xs btn-outline-dark mr-1"'
                      + ' onclick="cajaVerTicket(' + row.folio + ')">Ver Ticket</button>';
                // Comanda (solo productos, sin precios) — solo folios padre
                if (esPadre && idstatus !== 3) {
                    btns += '<button class="btn btn-xs btn-outline-dark mr-1"'
                          + ' onclick="cajaVerComanda(' + row.folio + ')">Comanda</button>';
                }
                // Agregar Referencia (Transferencia / Depósito / Cheque / Cargo con
                // tarjeta / Tarjeta Débito / Tarjeta Crédito)
                var _tp = (row.tipopago || '').toLowerCase();
                var _esRef = _tp.indexOf('transferencia') >= 0
                          || _tp.indexOf('deposito')       >= 0
                          || _tp.indexOf('depósito')       >= 0
                          || _tp.indexOf('cheque')         >= 0
                          || _tp.indexOf('cargo con tarjeta') >= 0
                          || _tp.indexOf('tarjeta debito')  >= 0
                          || _tp.indexOf('tarjeta débito')  >= 0
                          || _tp.indexOf('tarjeta credito') >= 0
                          || _tp.indexOf('tarjeta crédito') >= 0;
                if (_esRef && idstatus !== 3) {
                    btns += '<button class="btn btn-xs btn-outline-secondary mr-1"'
                          + ' onclick="cajaAbrirModalReferencia(' + row.folio + ')">Referencia</button>';
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
    // cajaVerificarPago() ya pide confirmación — no repetirla aquí.
    cajaVerificarPago(folio);
}

/* Ver Ticket de impresión */
function cajaVerTicket(folio) {
    window.open('<?= base_url('caja/folio/') ?>' + folio + '/ticket', '_blank',
        'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=500,height=600');
}

/* Comanda de impresión (solo productos) */
function cajaVerComanda(folio) {
    window.open('<?= base_url('caja/folio/') ?>' + folio + '/comanda', '_blank',
        'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=360,height=520');
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
            $('#modalVerFolioCaja').modal('hide');
            $('#tablaCajaConsulta').DataTable().ajax.reload(null, false);
        })
        .catch(function() { alert('Error al verificar el pago.'); });
}

// ── Referencia bancaria desde Consulta Folios (Caja) ──────────────────
// Un folio puede tener MÁS DE UNA forma de pago que requiera referencia
// (p.ej. Transferencia + Cargo con tarjeta) — se listan todas y cada una
// se guarda por separado.
var _cajaRefFolio = null;

function _cajaRefEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function cajaAbrirModalReferencia(folio) {
    _cajaRefFolio = folio;
    document.getElementById('cajaModalRefBancoFolio').textContent = '#' + folio;
    document.getElementById('cajaRefBancoLista').innerHTML =
        '<p class="text-muted text-center py-2 mb-0">Cargando...</p>';
    document.getElementById('cajaRefBancoError').classList.add('d-none');
    $('#cajaModalRefBanco').modal('show');

    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fd.append('folio', folio);

    fetch('<?= base_url('caja/monto/referencias-pendientes') ?>', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var items = (data.ok && data.items) ? data.items : [];
        if (items.length === 0) {
            document.getElementById('cajaRefBancoLista').innerHTML =
                '<p class="text-muted text-center py-2 mb-0">Este folio no tiene formas de pago que requieran referencia.</p>';
            return;
        }
        var html = '';
        items.forEach(function(it, i) {
            html += '<div class="form-group mb-3' + (i < items.length - 1 ? ' pb-3 border-bottom' : '') + '">'
                  + '<label class="mb-1"><strong>' + _cajaRefEsc(it.tipo) + '</strong>'
                  + ' <span class="text-muted">($' + parseFloat(it.monto || 0).toFixed(2) + ')</span></label>'
                  + '<div class="input-group">'
                  + '<input type="text" class="form-control caja-ref-input" '
                  + 'data-id-notas="' + it.idNotas + '" data-tipo-id="' + it.idTipoPago + '" '
                  + 'value="' + _cajaRefEsc(it.referencia) + '" placeholder="Ej. 1234567890" maxlength="100">'
                  + '<div class="input-group-append">'
                  + '<button type="button" class="btn btn-primary caja-ref-guardar" '
                  + 'data-id-notas="' + it.idNotas + '" data-tipo-id="' + it.idTipoPago + '">Guardar</button>'
                  + '</div></div>'
                  + '<small class="caja-ref-status text-success d-none" data-tipo-id="' + it.idTipoPago + '">✔ Guardado</small>'
                  + '</div>';
        });
        document.getElementById('cajaRefBancoLista').innerHTML = html;
    })
    .catch(function() {
        document.getElementById('cajaRefBancoLista').innerHTML = '';
        document.getElementById('cajaRefBancoError').textContent = 'Error al cargar las formas de pago del folio.';
        document.getElementById('cajaRefBancoError').classList.remove('d-none');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('cajaRefBancoLista').addEventListener('click', function(e) {
        var btn = e.target.closest('.caja-ref-guardar');
        if (!btn) return;

        var idNotas = btn.getAttribute('data-id-notas');
        var tipoId  = btn.getAttribute('data-tipo-id');
        var input   = document.querySelector('.caja-ref-input[data-tipo-id="' + tipoId + '"]');
        var status  = document.querySelector('.caja-ref-status[data-tipo-id="' + tipoId + '"]');
        var ref     = input.value.trim();

        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = 'Guardando...';
        status.classList.add('d-none');
        document.getElementById('cajaRefBancoError').classList.add('d-none');

        var fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('mn_id',      idNotas);
        fd.append('mn_tipo_id', tipoId);
        fd.append('folio',      _cajaRefFolio || 0);
        fd.append('referencia', ref);

        fetch('<?= base_url('caja/monto/referencia') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = originalText;
            if (data.ok) {
                status.classList.remove('d-none');
                $('#tablaCajaConsulta').DataTable().ajax.reload(null, false);
            } else {
                document.getElementById('cajaRefBancoError').textContent = data.msg || 'Error al guardar.';
                document.getElementById('cajaRefBancoError').classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = originalText;
            document.getElementById('cajaRefBancoError').textContent = 'Error de conexión.';
            document.getElementById('cajaRefBancoError').classList.remove('d-none');
        });
    });

    document.getElementById('cajaRefBancoLista').addEventListener('keypress', function(e) {
        if (e.which === 13 && e.target.classList.contains('caja-ref-input')) {
            e.preventDefault();
            var tipoId = e.target.getAttribute('data-tipo-id');
            var btn = document.querySelector('.caja-ref-guardar[data-tipo-id="' + tipoId + '"]');
            if (btn) btn.click();
        }
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

// Datos fiscales validados en el paso 1, guardados para enviarlos al
// confirmar en el paso de vista previa.
var _cajaSfDatosFiscales = null;

function cajaSfMoneda(n) {
    return '$' + (Number(n) || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    // Paso 1 → Paso 2: valida datos fiscales y pide la vista previa
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
        btn.innerHTML = '<i class="simple-icon-refresh mr-1"></i> Cargando...';

        _cajaSfDatosFiscales = {
            rfcReceptor:           rfc,
            razonSocialReceptor:   rs,
            cpReceptor:            cp,
            usoCFDI:               document.getElementById('cajaSfUsoCFDI').value,
            regimenFiscalReceptor: document.getElementById('cajaSfRegimenFiscal').value,
            formaPagoCFDI:         document.getElementById('cajaSfFormaPago').value,
            metodoPagoCFDI:        'PUE'
        };

        var fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        Object.keys(_cajaSfDatosFiscales).forEach(function(k) { fd.append(k, _cajaSfDatosFiscales[k]); });

        fetch('<?= base_url('caja/folio/') ?>' + _cajaSfFolioActivo + '/previsualizar-factura', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
            if (!data.success) {
                err.textContent = data.message || 'Error al calcular la vista previa.';
                err.classList.remove('d-none');
                return;
            }
            cajaMostrarPreviewFactura(data);
        })
        .catch(function() {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
            err.textContent = 'Error de conexión.';
            err.classList.remove('d-none');
        });
    });

    // Paso 2 → Paso 1: regresar a editar datos fiscales
    document.getElementById('btnCajaSfPvRegresar').addEventListener('click', function() {
        $('#modalCajaFacturaPreview').modal('hide');
        $('#modalCajaSolicitarFactura').modal('show');
    });

    // Paso 2: confirmar y timbrar de verdad
    document.getElementById('btnCajaSfPvConfirmar').addEventListener('click', function() {
        var btn = this;
        var err = document.getElementById('cajaSfPvError');
        err.classList.add('d-none');
        btn.disabled    = true;
        btn.textContent = 'Procesando...';

        var fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        Object.keys(_cajaSfDatosFiscales).forEach(function(k) { fd.append(k, _cajaSfDatosFiscales[k]); });

        fetch('<?= base_url('caja/folio/') ?>' + _cajaSfFolioActivo + '/facturar', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled    = false;
            btn.innerHTML = '<i class="simple-icon-check mr-1"></i> Confirmar y Facturar';
            if (data.success) {
                $('#modalCajaFacturaPreview').modal('hide');
                alert('✔ ' + (data.mensaje || 'Factura generada correctamente.'));
                $('#tablaCajaConsulta').DataTable().ajax.reload(null, false);
            } else {
                err.textContent = data.mensaje || 'Error al generar la factura.';
                err.classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled    = false;
            btn.innerHTML = '<i class="simple-icon-check mr-1"></i> Confirmar y Facturar';
            err.textContent = 'Error de conexión.';
            err.classList.remove('d-none');
        });
    });
});

function cajaSfEsc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function cajaSfFechaLocal() {
    var d = new Date();
    function pad(n) { return String(n).padStart(2, '0'); }
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
        'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
}

function cajaMostrarPreviewFactura(data) {
    document.getElementById('cajaSfPvFolioNum').textContent  = '#' + data.folio;
    document.getElementById('cajaSfPvFolioNum2').textContent = data.folio;
    document.getElementById('cajaSfPvFecha').textContent     = cajaSfFechaLocal() + ' (aprox.)';
    document.getElementById('cajaSfPvLugarExp').textContent  = data.emisor.cp;

    document.getElementById('cajaSfPvEmpNombre').textContent    = data.emisor.razonSocial;
    document.getElementById('cajaSfPvEmpRfc').textContent       = data.emisor.rfc;
    document.getElementById('cajaSfPvEmpDireccion').textContent = data.emisor.direccion;
    document.getElementById('cajaSfPvEmpCiudad').textContent    = data.emisor.ciudad;
    document.getElementById('cajaSfPvRegimenEmisor').textContent = data.emisor.regimen + ' / ' + data.emisor.regimenTexto;

    document.getElementById('cajaSfPvRfc').textContent              = data.datosFiscales.rfcReceptor;
    document.getElementById('cajaSfPvRazonSocial').textContent      = data.datosFiscales.razonSocialReceptor;
    document.getElementById('cajaSfPvCP').textContent               = data.datosFiscales.cpReceptor;
    document.getElementById('cajaSfPvRegimenReceptor').textContent  = data.datosFiscales.regimenFiscalReceptor;
    document.getElementById('cajaSfPvUsoCFDI').textContent          = data.datosFiscales.usoCFDI;
    document.getElementById('cajaSfPvFormaPago').textContent        = data.datosFiscales.formaPagoTexto + ' / ' + data.datosFiscales.metodoPago;

    var tbody = document.getElementById('cajaSfPvConceptos');
    tbody.innerHTML = '';
    data.conceptos.forEach(function(c) {
        var total = (Number(c.importe) || 0) + (Number(c.iva) || 0);
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="c">' + c.cantidad + '</td>' +
            '<td>' + cajaSfEsc(c.sku ? (c.sku + ' ' + c.descripcion) : c.descripcion) + '</td>' +
            '<td class="r">' + cajaSfMoneda(c.valorUnitario) + '</td>' +
            '<td class="r">' + cajaSfMoneda(0) + '</td>' +
            '<td class="r">' + cajaSfMoneda(c.importe) + '</td>' +
            '<td class="r">' + cajaSfMoneda(c.iva) + '</td>' +
            '<td class="r"></td>' +
            '<td class="r"></td>' +
            '<td class="r">' + cajaSfMoneda(total) + '</td>';
        tbody.appendChild(tr);

        var trImp = document.createElement('tr');
        trImp.className = 'cfdi-imp-row';
        trImp.innerHTML = '<td colspan="9">Impuesto: ' + cajaSfMoneda(c.importe) + ' x [002{IVA} Tasa 0.160000] = ' + cajaSfMoneda(c.iva) + '</td>';
        tbody.appendChild(trImp);
    });

    document.getElementById('cajaSfPvSubtotal').textContent = cajaSfMoneda(data.subtotal);
    document.getElementById('cajaSfPvIva').textContent      = cajaSfMoneda(data.iva);
    document.getElementById('cajaSfPvTotal').textContent    = cajaSfMoneda(data.total);
    document.getElementById('cajaSfPvError').classList.add('d-none');

    $('#modalCajaSolicitarFactura').modal('hide');
    $('#modalCajaFacturaPreview').modal('show');
}
</script>

<!-- Modal: Referencia bancaria (Caja) -->
<div class="modal fade" id="cajaModalRefBanco" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="simple-icon-tag mr-1"></i>
                    Referencia — Folio <span id="cajaModalRefBancoFolio"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="cajaRefBancoLista"></div>
                <div id="cajaRefBancoError" class="alert alert-danger py-1 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
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

<!-- Modal: Vista Previa de Factura Caja (paso 2) -->
<div class="modal fade" id="modalCajaFacturaPreview" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="simple-icon-eye mr-2"></i>Vista Previa de Factura — Folio <span id="cajaSfPvFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="cfdi-preview">
                    <div class="cfdi-header">
                        <div class="cfdi-header-left">
                            <img class="cfdi-logo" src="<?= base_url('assets/logos/logo_yazbek_01.png') ?>" alt="Logo" onerror="this.style.display='none'">
                            <div class="cfdi-emp-name" id="cajaSfPvEmpNombre"></div>
                            <div class="cfdi-emp-data">
                                <b>RFC:</b> <span id="cajaSfPvEmpRfc"></span><br>
                                <span id="cajaSfPvEmpDireccion"></span><br>
                                <span id="cajaSfPvEmpCiudad"></span>
                            </div>
                        </div>
                        <div class="cfdi-header-right">
                            <div class="cfdi-tipo-box">TIPO DE COMPROBANTE (I) FACTURA — VISTA PREVIA</div>
                            <table class="cfdi-tipo-tabla">
                                <tr><td class="lbl">Folio</td><td id="cajaSfPvFolioNum2"></td></tr>
                                <tr><td class="lbl">Folio Fiscal</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">No. Serie CSD Emisor</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">No. Serie CSD SAT</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">Fecha emisión</td><td id="cajaSfPvFecha"></td></tr>
                                <tr><td class="lbl">Fecha certificación</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">Lugar Expedición</td><td id="cajaSfPvLugarExp"></td></tr>
                                <tr><td class="lbl">Forma Pago</td><td id="cajaSfPvFormaPago"></td></tr>
                                <tr><td class="lbl">Moneda</td><td>MXN</td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="cfdi-sec-title">DATOS DEL CLIENTE</div>
                    <div class="cfdi-cliente-box">
                        <b id="cajaSfPvRazonSocial"></b><br>
                        <b>RFC:</b> <span id="cajaSfPvRfc"></span>
                        &nbsp;&nbsp; <b>CP Fiscal:</b> <span id="cajaSfPvCP"></span><br>
                        <b>Régimen fiscal del receptor:</b> <span id="cajaSfPvRegimenReceptor"></span><br>
                        <b>Uso CFDI:</b> <span id="cajaSfPvUsoCFDI"></span>
                    </div>

                    <div class="cfdi-regimen-bar">
                        <b>Régimen Fiscal del Emisor:</b> <span id="cajaSfPvRegimenEmisor"></span>
                    </div>

                    <table class="cfdi-conc">
                        <thead>
                            <tr>
                                <th style="width:8%">CANTIDAD</th>
                                <th style="width:30%">DESCRIPCIÓN</th>
                                <th style="width:11%" class="r">P. UNITARIO</th>
                                <th style="width:10%" class="r">DESCUENTO</th>
                                <th style="width:11%" class="r">IMPORTE</th>
                                <th style="width:10%" class="r">IVA</th>
                                <th style="width:9%" class="r">IEPS</th>
                                <th style="width:9%" class="r">IMP.EST.</th>
                                <th style="width:11%" class="r">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody id="cajaSfPvConceptos"></tbody>
                    </table>

                    <table style="width:100%; margin-top:6px;">
                        <tr>
                            <td style="width:55%; vertical-align:top; font-size:7pt;" class="cfdi-pend">
                                RFC PAC: se asigna al timbrar<br>Exportación: 01
                            </td>
                            <td style="width:45%; vertical-align:top;">
                                <table class="cfdi-tot" style="margin:0 0 0 auto;">
                                    <tr><td>Subtotal</td><td class="r" id="cajaSfPvSubtotal"></td></tr>
                                    <tr><td>Total IVA</td><td class="r" id="cajaSfPvIva"></td></tr>
                                    <tr class="cfdi-tot-final"><td>TOTAL</td><td class="r" id="cajaSfPvTotal"></td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <div class="cfdi-sbox">Sellos digitales, folio fiscal (UUID) y código QR se generan al confirmar el timbrado.</div>
                    <div class="cfdi-pending-note">Este es un cálculo preliminar hecho con los datos capturados; no tiene validez fiscal hasta confirmar y timbrar.</div>
                </div>
                <div id="cajaSfPvError" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCajaSfPvRegresar">Regresar</button>
                <button type="button" class="btn btn-success" id="btnCajaSfPvConfirmar">
                    <i class="simple-icon-check mr-1"></i> Confirmar y Facturar
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
