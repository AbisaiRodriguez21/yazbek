<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
/* Vista previa de factura — mismo diseño que Consultar Folios / Facturación */
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

/* Igual que en Consultar Folios: limitar alto del modal y hacer scroll solo
   en el cuerpo para que los botones siempre queden visibles. */
#modalCajaSfFacturaPreview .modal-dialog { max-height: 90vh; }
#modalCajaSfFacturaPreview .modal-content { max-height: 90vh; }
#modalCajaSfFacturaPreview .modal-body { overflow-y: auto; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Verificar Caja</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Verificar Caja</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="separator mb-5"></div>

<div class="row">
    <!-- Panel izquierdo: búsqueda -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-4">Buscar folio</h5>
                <div class="form-group">
                    <input type="number" id="inputFolio" class="form-control form-control-lg"
                           placeholder="Folio" min="1">
                </div>
                <button id="btnBuscar" class="btn btn-success btn-block">
                    <i class="iconsminds-magnifi-glass mr-1"></i> Mostrar Información
                </button>
                <div id="mensajeBusqueda" class="mt-3"></div>
            </div>
        </div>
    </div>

    <!-- Panel derecho: detalles del folio -->
    <div class="col-md-8 mb-4">
        <div class="card" id="panelDetalle">
            <div class="card-body text-muted">
                <p class="mb-0"><i class="iconsminds-receipt-4 mr-2"></i> Detalles folio</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Solicitar Factura (igual que en Consultar Folios) -->
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
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea id="cajaSfObservaciones" class="form-control" rows="2"
                              placeholder="Notas o números de ticket para esta factura (opcional)"></textarea>
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

<!-- Modal: Vista Previa de Factura (paso 2) -->
<div class="modal fade" id="modalCajaSfFacturaPreview" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="simple-icon-eye mr-2"></i>Vista Previa de Factura — Folio <span id="cajaSfPvFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="cfdi-preview" id="cajaSfPvContenido">
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

                    <div id="cajaSfPvObservacionesBox" class="cfdi-cliente-box d-none">
                        <b>Observaciones:</b> <span id="cajaSfPvObservaciones"></span>
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
                                    <tr id="cajaSfPvDescuentoRow" style="display:none;"><td>Descuento</td><td class="r" id="cajaSfPvDescuento"></td></tr>
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
                <button type="button" class="btn btn-outline-secondary" id="btnCajaSfPvDescargar">
                    <i class="simple-icon-cloud-download mr-1"></i> Descargar Vista Previa
                </button>
                <button type="button" class="btn btn-secondary" id="btnCajaSfPvRegresar">Regresar</button>
                <button type="button" class="btn btn-success" id="btnCajaSfPvConfirmar">
                    <i class="simple-icon-check mr-1"></i> Confirmar y Facturar
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
var baseUrl = '<?= base_url() ?>';
var csrfName = '<?= csrf_token() ?>';
var csrfHash = '<?= csrf_hash() ?>';

$(window).on('pageshow', function(e) {
    if (e.originalEvent.persisted) { location.reload(); }
});

$('#inputFolio').on('keypress', function(e) {
    if (e.which === 13) $('#btnBuscar').trigger('click');
});

$('#btnBuscar').on('click', function() {
    var folio = $.trim($('#inputFolio').val());
    if (!folio || isNaN(folio)) {
        $('#mensajeBusqueda').html('<div class="alert alert-warning py-2">Ingresa un número de folio válido.</div>');
        return;
    }
    $('#mensajeBusqueda').html('');
    $('#panelDetalle').html(
        '<div class="card-body text-center text-muted py-4">'
        + '<div class="spinner-border spinner-border-sm mr-2"></div> Cargando...</div>'
    );

    var data = {};
    data[csrfName] = csrfHash;
    data.folio = folio;

    $.post(baseUrl + 'caja/folio/detalle', data, function(html) {
        $('#panelDetalle').html(
            '<div class="card-header"><h5 class="mb-0">Folio #' + folio + '</h5></div>'
            + '<div class="card-body">' + html + '</div>'
        );
        // Renovar token CSRF para siguientes peticiones
        $.get(baseUrl + 'csrf-refresh', function(r) {
            if (r && r.hash) csrfHash = r.hash;
        }).fail(function(){});
    }).fail(function() {
        $('#panelDetalle').html(
            '<div class="card-body"><div class="alert alert-danger mb-0">Error al consultar el folio.</div></div>'
        );
    });
});

/* Cancelar nota — llamado desde el HTML generado por folioDetalleAjax */
function cajaModalCancelar() {
    var folio = ($('#folio_input_caja_modal').val() || '').trim();
    if (!folio) return;
    if (!confirm('¿Cancelar la nota #' + folio + '? Se restaurará el inventario.')) return;
    var fd = {};
    fd[csrfName] = csrfHash;
    fd.folio = folio;
    $.post(baseUrl + 'caja/cancelar', fd, function(resp) {
        alert('Nota cancelada correctamente.');
        $('#btnBuscar').trigger('click');
    }).fail(function() { alert('Error al cancelar la nota.'); });
}

/* Ver Ticket — llamado desde el HTML generado por folioDetalleAjax */
function cajaVerTicket(folio) {
    window.open(baseUrl + 'caja/folio/' + folio + '/ticket', '_blank',
        'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=500,height=600');
}

/* Verificar pago — llamado desde el HTML generado por folioDetalleAjax */
function cajaModalVerificar() {
    var folio = ($('#folio_input_caja_modal').val() || '').trim();
    if (!folio) return;
    if (!confirm('¿Verificar el pago del folio #' + folio + '?')) return;
    $.get(baseUrl + 'caja/pago/verificado/' + folio, function(resp) {
        alert('Pago verificado correctamente.');
        $('#btnBuscar').trigger('click');
    }).fail(function() { alert('Error al verificar el pago.'); });
}

/* Solicitar Factura — llamado desde el HTML generado por folioDetalleAjax */
var _cajaSfFolioActivo = null;

function cajaAbrirModalFactura(folio) {
    _cajaSfFolioActivo = folio;
    document.getElementById('cajaSfFolioNum').textContent  = '#' + folio;
    document.getElementById('cajaSfError').classList.add('d-none');
    document.getElementById('cajaSfError').textContent     = '';

    ['cajaSfRfc','cajaSfRazonSocial','cajaSfCP','cajaSfObservaciones'].forEach(function(id) {
        document.getElementById(id).value = '';
    });

    // Deshabilitar el botón mientras se cargan los datos fiscales para evitar
    // que el click prematuro vea el RFC vacío y muestre "El RFC es requerido".
    var btnFacturar = document.getElementById('btnCajaSfFacturar');
    btnFacturar.disabled = true;
    btnFacturar.innerHTML = '<i class="simple-icon-refresh mr-1"></i> Cargando...';

    $('#modalCajaSolicitarFactura').modal('show');

    fetch(baseUrl + 'caja/folio/' + folio + '/datos-fiscales', { credentials: 'same-origin' })
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
            btnFacturar.disabled  = false;
            btnFacturar.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
        });
}

// Alias legacy (mismos nombres que usa folioDetalleAjax en otras pantallas)
function cajaFacturar(folio) { cajaAbrirModalFactura(folio); }
function cajaSolicitarFactura(folio) { cajaAbrirModalFactura(folio); }

var _cajaSfDatosFiscales = null;

function cajaSfMoneda(n) {
    return '$' + (Number(n) || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

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

function cajaSfEstilosPreview() {
    return '.cfdi-preview{font-size:9pt;color:#111}' +
        '.cfdi-preview table{width:100%;border-collapse:collapse}' +
        '.cfdi-preview .r{text-align:right}.cfdi-preview .c{text-align:center}' +
        '.cfdi-header{display:flex;border:1px solid #888;margin-bottom:6px}' +
        '.cfdi-header-left{width:65%;padding:8px 10px;border-right:1px solid #888}' +
        '.cfdi-header-right{width:35%}' +
        '.cfdi-emp-name{font-size:13pt;font-weight:bold;color:#1a3a5c;margin-bottom:3px}' +
        '.cfdi-emp-data{font-size:8pt;line-height:1.6}' +
        '.cfdi-tipo-box{background:#1a3a5c;color:#fff;font-weight:bold;font-size:8.5pt;text-align:center;padding:5px 6px}' +
        '.cfdi-tipo-tabla td{font-size:8pt;padding:3px 6px;border-bottom:1px solid #ddd}' +
        '.cfdi-tipo-tabla .lbl{color:#1a3a5c;font-weight:bold;width:50%}' +
        '.cfdi-sec-title{background:#222;color:#fff;font-weight:bold;padding:4px 6px;font-size:8.5pt;margin:4px 0 2px}' +
        '.cfdi-cliente-box{border:1px solid #aaa;padding:6px 8px;font-size:8pt;margin-bottom:6px}' +
        '.cfdi-conc th{background:#222;color:#fff;padding:4px;font-size:7.5pt;text-align:center;border:1px solid #555}' +
        '.cfdi-conc td{border:1px solid #ddd;padding:3px 4px;font-size:8pt}' +
        '.cfdi-imp-row td{background:#f0f0f0;font-size:7pt;color:#555;padding:2px 6px;border-left:1px solid #ddd;border-right:1px solid #ddd}' +
        '.cfdi-tot{width:280px;margin-left:auto;margin-top:6px}' +
        '.cfdi-tot td{padding:3px 6px;font-size:8.5pt}' +
        '.cfdi-tot-final td{background:#1a3a5c;color:#fff;font-weight:bold;font-size:9.5pt}' +
        '.cfdi-pending-note{font-size:7.5pt;color:#888;margin-top:8px;font-style:italic}' +
        '.cfdi-logo{max-height:45px;max-width:150px;margin-bottom:6px}' +
        '.cfdi-regimen-bar{border:1px solid #888;padding:4px 8px;font-size:8pt;margin-bottom:6px}' +
        '.cfdi-pend{color:#aaa;font-style:italic}' +
        '.cfdi-sbox{background:#f7f7f7;border:1px dashed #ccc;padding:6px 8px;font-size:7.5pt;color:#999;margin-top:6px;text-align:center}';
}

function cajaSfMostrarPreviewFactura(data) {
    document.getElementById('cajaSfPvFolioNum').textContent  = '#' + data.folio;
    document.getElementById('cajaSfPvFolioNum2').textContent = data.folio;
    document.getElementById('cajaSfPvFecha').textContent     = cajaSfFechaLocal() + ' (aprox.)';
    document.getElementById('cajaSfPvLugarExp').textContent  = data.emisor.cp;

    document.getElementById('cajaSfPvEmpNombre').textContent    = data.emisor.razonSocial;
    document.getElementById('cajaSfPvEmpRfc').textContent       = data.emisor.rfc;
    document.getElementById('cajaSfPvEmpDireccion').textContent = data.emisor.direccion;
    document.getElementById('cajaSfPvEmpCiudad').textContent    = data.emisor.ciudad;
    document.getElementById('cajaSfPvRegimenEmisor').textContent = data.emisor.regimen + ' / ' + data.emisor.regimenTexto;

    var obsBox = document.getElementById('cajaSfPvObservacionesBox');
    if (data.observaciones) {
        document.getElementById('cajaSfPvObservaciones').textContent = data.observaciones;
        obsBox.classList.remove('d-none');
    } else {
        obsBox.classList.add('d-none');
    }

    document.getElementById('cajaSfPvRfc').textContent            = data.datosFiscales.rfcReceptor;
    document.getElementById('cajaSfPvRazonSocial').textContent    = data.datosFiscales.razonSocialReceptor;
    document.getElementById('cajaSfPvCP').textContent             = data.datosFiscales.cpReceptor;
    document.getElementById('cajaSfPvRegimenReceptor').textContent = data.datosFiscales.regimenFiscalReceptor;
    document.getElementById('cajaSfPvUsoCFDI').textContent        = data.datosFiscales.usoCFDI;
    document.getElementById('cajaSfPvFormaPago').textContent      = data.datosFiscales.formaPagoTexto + ' / ' + data.datosFiscales.metodoPago;

    var tbody = document.getElementById('cajaSfPvConceptos');
    tbody.innerHTML = '';
    data.conceptos.forEach(function (c) {
        var descuento = Number(c.descuento) || 0;
        var baseIva   = (Number(c.importe) || 0) - descuento;
        var total     = baseIva + (Number(c.iva) || 0);
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="c">' + c.cantidad + '</td>' +
            '<td>' + cajaSfEsc(c.sku ? (c.sku + ' ' + c.descripcion) : c.descripcion) + '</td>' +
            '<td class="r">' + cajaSfMoneda(c.valorUnitario) + '</td>' +
            '<td class="r">' + cajaSfMoneda(descuento) + '</td>' +
            '<td class="r">' + cajaSfMoneda(baseIva) + '</td>' +
            '<td class="r">' + cajaSfMoneda(c.iva) + '</td>' +
            '<td class="r"></td>' +
            '<td class="r"></td>' +
            '<td class="r">' + cajaSfMoneda(total) + '</td>';
        tbody.appendChild(tr);

        var trImp = document.createElement('tr');
        trImp.className = 'cfdi-imp-row';
        trImp.innerHTML = '<td colspan="9">Impuesto: ' + cajaSfMoneda(baseIva) + ' x [002{IVA} Tasa 0.160000] = ' + cajaSfMoneda(c.iva) + '</td>';
        tbody.appendChild(trImp);
    });

    document.getElementById('cajaSfPvSubtotal').textContent = cajaSfMoneda(data.subtotal);
    var descRow = document.getElementById('cajaSfPvDescuentoRow');
    if (Number(data.descuento) > 0) {
        descRow.style.display = '';
        document.getElementById('cajaSfPvDescuento').textContent = '- ' + cajaSfMoneda(data.descuento);
    } else {
        descRow.style.display = 'none';
    }
    document.getElementById('cajaSfPvIva').textContent   = cajaSfMoneda(data.iva);
    document.getElementById('cajaSfPvTotal').textContent = cajaSfMoneda(data.total);
    document.getElementById('cajaSfPvError').classList.add('d-none');

    $('#modalCajaSolicitarFactura').modal('hide');
    $('#modalCajaSfFacturaPreview').modal('show');
}

// Paso 1 → Paso 2: valida datos fiscales y pide la vista previa (NO timbra todavía)
document.getElementById('btnCajaSfFacturar').addEventListener('click', function() {
    var btn = this;
    var rfc = document.getElementById('cajaSfRfc').value.trim().toUpperCase();
    var rs  = document.getElementById('cajaSfRazonSocial').value.trim().toUpperCase();
    var cp  = document.getElementById('cajaSfCP').value.trim();
    var err = document.getElementById('cajaSfError');

    if (!rfc) { err.textContent = 'El RFC es requerido.';           err.classList.remove('d-none'); return; }
    if (!rs)  { err.textContent = 'La Razón Social es requerida.';  err.classList.remove('d-none'); return; }
    if (!cp)  { err.textContent = 'El Código Postal es requerido.'; err.classList.remove('d-none'); return; }

    err.classList.add('d-none');

    _cajaSfDatosFiscales = {
        rfcReceptor:           rfc,
        razonSocialReceptor:   rs,
        cpReceptor:            cp,
        usoCFDI:               document.getElementById('cajaSfUsoCFDI').value,
        regimenFiscalReceptor: document.getElementById('cajaSfRegimenFiscal').value,
        formaPagoCFDI:         document.getElementById('cajaSfFormaPago').value,
        metodoPagoCFDI:        'PUE',
        observacionesFactura:  document.getElementById('cajaSfObservaciones').value.trim()
    };

    btn.disabled  = true;
    btn.innerHTML = '<i class="simple-icon-refresh mr-1"></i> Cargando...';

    var fd = new FormData();
    fd.append(csrfName, csrfHash);
    Object.keys(_cajaSfDatosFiscales).forEach(function (k) { fd.append(k, _cajaSfDatosFiscales[k]); });

    fetch(baseUrl + 'caja/folio/' + _cajaSfFolioActivo + '/previsualizar-factura', {
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
            cajaSfMostrarPreviewFactura(data);
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
    $('#modalCajaSfFacturaPreview').modal('hide');
    $('#modalCajaSolicitarFactura').modal('show');
});

// Paso 2: descargar/imprimir la vista previa
document.getElementById('btnCajaSfPvDescargar').addEventListener('click', function() {
    var contenido = document.getElementById('cajaSfPvContenido').innerHTML;
    var folioTxt  = document.getElementById('cajaSfPvFolioNum').textContent;
    var w = window.open('', '_blank');
    w.document.write(
        '<!DOCTYPE html><html><head><title>Vista previa factura ' + folioTxt + '</title>' +
        '<style>body{font-family:Arial,sans-serif;margin:20px;}' + cajaSfEstilosPreview() + '</style>' +
        '</head><body>' + contenido + '</body></html>'
    );
    w.document.close();
    w.onload = function() { w.focus(); w.print(); };
});

// Paso 2: confirmar y timbrar de verdad
document.getElementById('btnCajaSfPvConfirmar').addEventListener('click', function() {
    var btn = this;
    var err = document.getElementById('cajaSfPvError');
    err.classList.add('d-none');
    btn.disabled    = true;
    btn.textContent = 'Procesando...';

    var fd = new FormData();
    fd.append(csrfName, csrfHash);
    Object.keys(_cajaSfDatosFiscales).forEach(function (k) { fd.append(k, _cajaSfDatosFiscales[k]); });

    fetch(baseUrl + 'caja/folio/' + _cajaSfFolioActivo + '/facturar', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-check mr-1"></i> Confirmar y Facturar';
            if (data.success) {
                $('#modalCajaSfFacturaPreview').modal('hide');
                alert('✔ ' + (data.mensaje || 'Factura generada correctamente.'));
                $('#btnBuscar').trigger('click');
            } else {
                err.textContent = data.mensaje || 'Error al generar la factura.';
                err.classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-check mr-1"></i> Confirmar y Facturar';
            err.textContent = 'Error de conexión.';
            err.classList.remove('d-none');
        });
});
</script>
<?= $this->endSection() ?>
