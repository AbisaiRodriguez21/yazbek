<?= $this->extend('layouts/main') ?>

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

    ['cajaSfRfc','cajaSfRazonSocial','cajaSfCP'].forEach(function(id) {
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
    btn.disabled    = true;
    btn.textContent = 'Procesando...';

    var fd = new FormData();
    fd.append(csrfName, csrfHash);
    fd.append('rfcReceptor',           rfc);
    fd.append('razonSocialReceptor',   rs);
    fd.append('cpReceptor',            cp);
    fd.append('usoCFDI',               document.getElementById('cajaSfUsoCFDI').value);
    fd.append('regimenFiscalReceptor', document.getElementById('cajaSfRegimenFiscal').value);
    fd.append('formaPagoCFDI',         document.getElementById('cajaSfFormaPago').value);
    fd.append('metodoPagoCFDI',        'PUE');

    fetch(baseUrl + 'caja/folio/' + _cajaSfFolioActivo + '/facturar', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
            if (data.success) {
                $('#modalCajaSolicitarFactura').modal('hide');
                alert('✔ ' + (data.mensaje || 'Factura generada correctamente.'));
                $('#btnBuscar').trigger('click');
            } else {
                err.textContent = data.mensaje || 'Error al generar la factura.';
                err.classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
            err.textContent = 'Error de conexión.';
            err.classList.remove('d-none');
        });
});
</script>
<?= $this->endSection() ?>
