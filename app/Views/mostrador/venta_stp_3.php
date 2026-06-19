<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Nota #<?= (int)$nota['folio'] ?> — Paso 3: Confirmar</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('mostrador') ?>">Mostrador</a></li>
                    <li class="breadcrumb-item active">Confirmar Nota</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <a href="<?= base_url(($base ?? 'mostrador') . '/venta/' . (int)$nota['folio'] . '/productos') ?>"
               class="btn btn-outline-secondary">
                <i class="iconsminds-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Resumen de la nota -->
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Datos de la Nota</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Folio</th><td><?= (int)$nota['folio'] ?></td></tr>
                    <tr><th>Cliente</th><td><?= esc($nota['cliente'] ?? '') ?></td></tr>
                    <tr><th>Vendedor</th><td><?= esc($nota['vendedor'] ?? $usuario['nombre']) ?></td></tr>
                    <tr><th>Fecha</th><td><?= date('Y-m-d', strtotime($nota['fecha_inicial'])) ?></td></tr>
                    <tr><th>Total Piezas</th>
                        <td>
                            <?= (int)$totalPiezas ?>
                            <?php if ($esMayoreo): ?>
                            <span class="badge badge-success ml-1">Mayoreo</span>
                            <?php else: ?>
                            <span class="badge badge-secondary ml-1">Menudeo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Detalle de productos -->
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Productos</div>
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th class="text-right">P.U.</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $d): ?>
                        <tr>
                            <td><?= esc($d['sku']) ?></td>
                            <td><?= esc($d['estilo']) ?></td>
                            <td class="text-right">$<?= number_format($d['precio'], 2) ?></td>
                            <td class="text-right"><?= (int)$d['cantidad'] ?></td>
                            <td class="text-right">$<?= number_format($d['importe'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Formulario de pago -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Cierre de Nota</div>
            <div class="card-body">

                <form id="formConfirmar">
                    <?= csrf_field() ?>
                    <input type="hidden" name="folio" value="<?= (int)$nota['folio'] ?>">
                    <input type="hidden" name="Id_Notas_1" id="hidIdNotas1" value="<?= (int)$nota['Id_Notas_1'] ?>">
                    <input type="hidden" name="totalPiezas" value="<?= (int)$totalPiezas ?>">

                    <!-- Campo de descuento visible -->
                    <div class="form-group">
                        <label for="inputDescuento">Descuento ($ pesos, sin IVA)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" id="inputDescuento" name="descuento"
                                   class="form-control" value="0" min="0" step="0.01"
                                   placeholder="0.00">
                        </div>
                        <small class="form-text text-muted">Se resta del subtotal antes de calcular el IVA.</small>
                    </div>

                    <!-- Totales calculados -->
                    <table class="table table-sm mb-3">
                        <tr>
                            <th>Subtotal (sin IVA):</th>
                            <td class="text-right" id="tdSubtotal">—</td>
                        </tr>
                        <tr id="trDescuento" style="display:none;">
                            <th class="text-danger">Descuento:</th>
                            <td class="text-right text-danger" id="tdDescuento">—</td>
                        </tr>
                        <tr id="trSubtotalConDesc" style="display:none;">
                            <th>Subtotal con descuento:</th>
                            <td class="text-right" id="tdSubtotalConDesc">—</td>
                        </tr>
                        <tr>
                            <th>IVA (16%):</th>
                            <td class="text-right" id="tdIva">—</td>
                        </tr>
                        <tr class="font-weight-bold">
                            <th>Total:</th>
                            <td class="text-right text-success" id="tdTotal">—</td>
                        </tr>
                    </table>
                    <input type="hidden" id="hidSubtotal" name="subtotal" value="<?= $sumaImportes ?>">
                    <input type="hidden" id="hidIva" name="iva" value="">
                    <input type="hidden" id="hidTotal" name="total" value="">

                    <!-- Estatus (se ajusta automáticamente según el restante) -->
                    <div class="form-group">
                        <label>Estatus de la nota <span class="text-danger">*</span></label>
                        <select name="estatus" id="selectEstatus" class="form-control">
                            <option value="1" selected>Abierta</option>
                            <option value="5">Pagada</option>
                        </select>
                        <small id="msgEstatus" class="form-text text-muted"></small>
                    </div>

                    <!-- Factura -->
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="chkFactura" name="factura" value="1">
                            <label class="custom-control-label" for="chkFactura">Requiere Factura</label>
                        </div>
                    </div>

                    <!-- Datos fiscales (se muestran solo si se marca Requiere Factura) -->
                    <div id="panelFactura" style="display:none;" class="card card-body bg-light mb-3 border-primary">
                        <h6 class="font-weight-bold mb-3"><i class="iconsminds-receipt-4"></i> Datos Fiscales del Receptor</h6>

                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label>RFC Receptor <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="rfcReceptor" name="rfcReceptor"
                                       maxlength="13" placeholder="XAXX010101000"
                                       value="<?= esc($nota['rfc_receptor'] ?? '') ?>">
                                <small class="text-muted">12 letras/números (persona moral) o 13 (física)</small>
                            </div>
                            <div class="form-group col-md-7">
                                <label>Razón Social / Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="razonSocialReceptor" name="razonSocialReceptor"
                                       placeholder="NOMBRE O RAZÓN SOCIAL"
                                       value="<?= esc($nota['razon_social_receptor'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>CP Fiscal Receptor <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cpReceptor" name="cpReceptor"
                                       maxlength="5" placeholder="64000"
                                       value="<?= esc($nota['cp_receptor'] ?? '') ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Régimen Fiscal Receptor <span class="text-danger">*</span></label>
                                <select class="form-control" id="regimenFiscalReceptor" name="regimenFiscalReceptor">
                                    <option value="616">616 – Sin obligaciones fiscales</option>
                                    <option value="601">601 – General de Ley Personas Morales</option>
                                    <option value="612">612 – Personas Físicas con Actividades Empresariales</option>
                                    <option value="621">621 – Incorporación Fiscal</option>
                                    <option value="626">626 – Régimen Simplificado de Confianza (RESICO)</option>
                                    <option value="605">605 – Sueldos y Salarios</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Uso del CFDI <span class="text-danger">*</span></label>
                                <select class="form-control" id="usoCFDI" name="usoCFDI">
                                    <option value="S01">S01 – Sin efectos fiscales</option>
                                    <option value="G01">G01 – Adquisición de mercancias</option>
                                    <option value="G03">G03 – Gastos en general</option>
                                    <option value="I01">I01 – Construcciones</option>
                                    <option value="D01">D01 – Honorarios médicos y dentales</option>
                                    <option value="CP01">CP01 – Pagos</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Forma de Pago</label>
                                <select class="form-control" id="formaPagoCFDI" name="formaPagoCFDI">
                                    <option value="01">01 – Efectivo</option>
                                    <option value="02">02 – Cheque nominativo</option>
                                    <option value="03">03 – Transferencia electrónica</option>
                                    <option value="04">04 – Tarjeta de crédito</option>
                                    <option value="28">28 – Tarjeta de débito</option>
                                    <option value="99">99 – Por definir</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Método de Pago</label>
                                <select class="form-control" id="metodoPagoCFDI" name="metodoPagoCFDI">
                                    <option value="PUE">PUE – Pago en una sola exhibición</option>
                                    <option value="PPD">PPD – Pago en parcialidades o diferido</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-warning py-2 mb-0" id="alertSinRFC" style="display:none;">
                            <small><i class="simple-icon-exclamation"></i> Este cliente no tiene RFC registrado. Puedes capturarlo aquí o ir al perfil del cliente para guardarlo.</small>
                        </div>
                    </div>

                    <!-- Pagos -->
                    <div class="card card-body bg-light mb-3" id="panelPagos">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Formas de Pago</strong>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalPago">
                                <i class="iconsminds-add"></i> Agregar Pago
                            </button>
                        </div>

                        <?php if (!empty($pagosExistentes)): ?>
                        <!-- Pagos ya registrados (solo lectura) -->
                        <p class="small text-muted mb-1">Pagos registrados anteriormente:</p>
                        <ul class="list-group mb-2" id="listaPagosExistentes">
                            <?php foreach ($pagosExistentes as $pe):
                                $cancelado = ((int)($pe['nota_status'] ?? 0) === 3);
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-1<?= $cancelado ? ' list-group-item-light' : '' ?>">
                                <span class="<?= $cancelado ? 'text-muted' : 'text-muted' ?>">
                                    <?php if ($cancelado): ?>
                                    <s><?= esc($pe['descripcion']) ?></s>
                                    <?php else: ?>
                                    <?= esc($pe['descripcion']) ?>
                                    <?php endif; ?>
                                    <?php if ($pe['anticipo'] && !$cancelado): ?>
                                    <span class="badge badge-warning">Anticipo</span>
                                    <?php endif; ?>
                                    <?php if ($cancelado): ?>
                                    <span class="badge badge-danger">Cancelada</span>
                                    <?php endif; ?>
                                    <?php if (!empty($pe['folio_origen']) && (int)$pe['folio_origen'] !== (int)$nota['folio']): ?>
                                    <small class="text-info ml-1">Folio #<?= (int)$pe['folio_origen'] ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="text-muted">
                                    <?php if ($cancelado): ?>
                                    <s>$<?= number_format($pe['monto'], 2) ?></s>
                                    <?php else: ?>
                                    $<?= number_format($pe['monto'], 2) ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>

                        <!-- Nuevos pagos (esta sesión) -->
                        <ul id="listaPagos" class="list-group">
                            <li class="list-group-item text-muted text-center" id="liSinPagos">Sin pagos nuevos aún.</li>
                        </ul>
                        <?php
                        // Solo sumar pagos de notas NO canceladas
                        $sumaPagosValidos = array_sum(array_column(
                            array_filter($pagosExistentes ?? [], fn($pe) => (int)($pe['nota_status'] ?? 0) !== 3),
                            'monto'
                        ));
                        ?>
                        <div class="mt-2 text-right">
                            <?php if (!empty($pagosExistentes)): ?>
                            <strong>Ya pagado: <span id="spYaPagado" class="text-muted">
                                $<?= number_format($sumaPagosValidos, 2) ?>
                            </span></strong><br>
                            <?php endif; ?>
                            <strong>Nuevo pago: <span id="spMontoPagado" class="text-success">$0.00</span></strong><br>
                            <strong id="lblRestante">Restante: </strong><span id="spRestante" class="text-danger font-weight-bold">$0.00</span>
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="button" id="btnGuardar" class="btn btn-success btn-lg">
                            <i class="iconsminds-yes"></i> Finalizar Nota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar pago -->
<div class="modal fade" id="modalPago" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Forma de Pago</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <!-- Indicador de restante -->
                <div class="alert alert-info py-2 mb-3 d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">Restante por pagar:</span>
                    <span class="font-weight-bold" id="modalRestanteLabel" style="font-size:1.15rem;">$0.00</span>
                </div>
                <div class="form-group">
                    <label>Tipo de Pago</label>
                    <select id="modalTipoPago" class="form-control">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($tipoPagos as $tp): ?>
                        <option value="<?= (int)$tp['id'] ?>"
                                data-desc="<?= esc($tp['descripcion']) ?>"
                                data-cargo="<?= (float)($tp['cargo_pct'] ?? 0) ?>">
                            <?= esc($tp['descripcion']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="number" id="modalMonto" class="form-control" min="0" step="0.01" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="modalAnticipo">
                        <label class="custom-control-label" for="modalAnticipo">Es Anticipo</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" id="btnAgregarPago" class="btn btn-primary">Agregar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: Referencia de tarjeta ══════════════════════════════════════ -->
<div class="modal fade" id="modalRefTarjeta" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="iconsminds-credit-card"></i> Referencia de pago</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:13px;">
                    Ingresa el número de referencia o autorización de la terminal.
                </p>
                <div class="form-group mb-0">
                    <label>No. de Referencia / Autorización</label>
                    <input type="text" id="inputRefTarjeta" class="form-control" placeholder="Ej. 123456">
                    <small id="refTarjetaError" class="text-danger d-none">Este campo es obligatorio.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelarRefTarjeta">Cancelar</button>
                <button type="button" id="btnConfirmarRefTarjeta" class="btn btn-primary">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: Email faltante ════════════════════════════════════════════ -->
<div class="modal fade" id="modalEmailFaltante" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📧 Correo del cliente</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:13px;">
                    Este cliente no tiene correo registrado. Es necesario para enviar la factura.
                </p>
                <div class="form-group mb-0">
                    <label>Correo electrónico</label>
                    <input type="email" id="inputEmailCliente" class="form-control" placeholder="ejemplo@correo.com">
                    <small id="emailError" class="text-danger d-none">Ingresa un correo válido.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btnGuardarEmail" class="btn btn-primary">Guardar y continuar</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="hidCsrfName" value="<?= csrf_token() ?>">
<input type="hidden" id="hidCsrfHash" value="<?= csrf_hash() ?>">
<input type="hidden" id="hidSumaImportes" name="sumaImportes" value="<?= $sumaImportes ?>">
<input type="hidden" id="hidFolio" value="<?= (int)$nota['folio'] ?>">
<!-- Total ya pagado en sesiones anteriores — excluye folios hijos cancelados -->
<input type="hidden" id="hidYaPagado" value="<?= number_format($sumaPagosValidos, 2, '.', '') ?>">

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
var pagos = [];   // solo pagos NUEVOS de esta sesión
var sinPagarSeleccionado = false;   // true cuando el usuario eligió "Sin Pagar" (a crédito)

// ¿Es folio hijo? (tiene referencia al padre)
var esHijo = <?= (int)($nota['referencia'] ?? 0) > 0 ? 'true' : 'false' ?>;

// Estatus actual de la nota
var notaIdStatus = <?= (int)($nota['idstatus'] ?? $nota['status'] ?? 0) ?>;

// Estado default del checkbox:
// - Folio hijo (anticipo) → siempre marcado
// - Nota Abierta (status 1) → marcado automáticamente porque el pago es parcial/anticipo
var defaultAnticipo = esHijo || notaIdStatus === 1;

// ID del tipo de pago "Sin Pagar" (busca por texto en el select)
function getSinPagarId() {
    var id = '';
    $('#modalTipoPago option').each(function() {
        if ($(this).text().trim() === 'Sin Pagar') { id = $(this).val(); }
    });
    return id;
}

// Al abrir el modal: anticipo según el tipo de folio; reset campos + muestra restante
$('#modalPago').on('show.bs.modal', function () {
    $('#modalAnticipo').prop('checked', defaultAnticipo);
    $('#modalTipoPago').val('');

    // Jalamos el restante actual del indicador de la página
    var restanteTexto = ($('#spRestante').text() || '$0.00').replace(/[^0-9.]/g, '');
    var restanteVal   = parseFloat(restanteTexto) || 0;

    // Mostrar en el indicador del modal
    $('#modalRestanteLabel').text('$' + restanteVal.toFixed(2));

    // Pre-llenar el monto con el restante (mínimo 0)
    $('#modalMonto').val(restanteVal > 0 ? restanteVal.toFixed(2) : 0);
});

// Referencia de tarjeta pendiente de confirmar
var _refTarjetaPendiente = '';

// Detecta si el tipo de pago seleccionado es tarjeta (Débito/Crédito)
// "Cargo con tarjeta" sigue la misma lógica que Transferencia/Depósito (sin modal de referencia inmediato)
function esTarjeta(desc) {
    if (!desc) return false;
    var d = desc.toLowerCase();
    return d.indexOf('tarjeta') !== -1 && d.indexOf('cargo') === -1;
}

// Al cambiar tipo de pago:
// "Sin Pagar" → forzar anticipo ON
// Tarjeta → pedir referencia
// Otro método → volver al default del folio
$('#modalTipoPago').on('change', function () {
    var sinPagarId = getSinPagarId();
    var desc = $(this).find('option:selected').data('desc') || '';
    if ($(this).val() === sinPagarId && sinPagarId !== '') {
        $('#modalAnticipo').prop('checked', true);
    } else {
        $('#modalAnticipo').prop('checked', defaultAnticipo);
    }
    // Si es tarjeta, limpiar referencia previa al cambiar selección
    if (!esTarjeta(desc)) {
        _refTarjetaPendiente = '';
    }
});
var sumaImportes = parseFloat($('#hidSumaImportes').val()) || 0;
var yaPagado     = parseFloat($('#hidYaPagado').val()) || 0;
var descuento    = 0;
function recalcular() {
    var desc          = parseFloat($('#inputDescuento').val()) || 0;
    // Validar que no supere el subtotal
    if (desc > sumaImportes) {
        desc = sumaImportes;
        $('#inputDescuento').val(desc.toFixed(2));
    }
    descuento         = desc;
    var subtotalBruto = sumaImportes;
    var subtotal      = Math.max(0, subtotalBruto - desc);
    var iva           = subtotal * 0.16;
    var total         = subtotal + iva;

    // Cargos de tarjeta de los pagos nuevos
    var cargos = 0;
    pagos.forEach(function(p) { cargos += parseFloat(p.cargo || 0); });
    total += cargos;

    $('#tdSubtotal').text('$' + subtotalBruto.toFixed(2));
    // Mostrar/ocultar filas de descuento
    if (desc > 0) {
        $('#trDescuento').show();
        $('#trSubtotalConDesc').show();
        $('#tdDescuento').text('-$' + desc.toFixed(2));
        $('#tdSubtotalConDesc').text('$' + subtotal.toFixed(2));
    } else {
        $('#trDescuento').hide();
        $('#trSubtotalConDesc').hide();
    }
    $('#tdIva').text('$' + iva.toFixed(2));
    $('#tdTotal').text('$' + total.toFixed(2));
    $('#hidSubtotal').val(subtotal.toFixed(2));
    $('#hidIva').val(iva.toFixed(2));
    $('#hidTotal').val(total.toFixed(2));

    // Monto de los pagos nuevos en esta sesión
    var montoNuevo = pagos.reduce(function(acc, p) { return acc + parseFloat(p.monto); }, 0);
    
    // Restante = total - ya pagado antes - nuevos pagos ahora
    var restante = total - yaPagado - montoNuevo;
    var liquidado = restante <= 0.005;
    var cambio    = restante < -0.005 ? Math.abs(restante) : 0;

    $('#spMontoPagado').text('$' + montoNuevo.toFixed(2));

    // --- MODIFICACIÓN VISUAL AQUÍ ---
    if (liquidado) {
        // Si está pagado (ya sea exacto o con sobrante), forzamos a mostrar $0.00
        $('#lblRestante').text('Restante: ');
        $('#spRestante').text('$0.00')
                        .removeClass('text-danger').addClass('text-success');
    } else {
        // Saldo pendiente
        $('#lblRestante').text('Restante: ');
        $('#spRestante').text('$' + restante.toFixed(2))
                        .removeClass('text-success').addClass('text-danger');
    }
    // --------------------------------

    if (liquidado) {
        // Liquidado: poner Pagada automáticamente
        $('#selectEstatus').val('5');
        if (cambio > 0) {
            // Se mantiene tu mensaje útil en la parte inferior sobre cuánto cambio dar
            $('#msgEstatus').text('✔ Nota cubierta — cambio a entregar al cliente: $' + cambio.toFixed(2));
        } else {
            $('#msgEstatus').text('✔ Nota liquidada — se marcará como Pagada.');
        }
    } else {
        // Hay saldo pendiente: poner Abierta automáticamente
        $('#selectEstatus').val('1');
        $('#msgEstatus').text('⚠ Hay saldo pendiente — se guardará como Abierta. Cambia a Anticipo si aplica.');
    }
}

$('#inputDescuento').on('input', recalcular);

// Función central para agregar el pago al array (se llama después de confirmar referencia si aplica)
function agregarPagoConfirmado(tipo, desc, monto, cargo, anticipo, referencia) {
    // Si había "Sin Pagar" seleccionado antes, se reemplaza con pago real
    sinPagarSeleccionado = false;

    // Calcular cuánto falta por pagar antes de agregar este pago
    var yaPagadoActual = pagos.reduce(function(acc, p) { return acc + parseFloat(p.monto); }, 0);
    var totalActual    = parseFloat($('#hidTotal').val()) || 0;
    var yaPagadoPrev   = parseFloat($('#hidYaPagado').val()) || 0;
    var pendiente      = totalActual - yaPagadoPrev - yaPagadoActual;

    if (monto > pendiente + 0.005) {
        var excedente = (monto - pendiente).toFixed(2);
        var confirmar = confirm(
            '⚠ El pago de $' + monto.toFixed(2) + ' excede lo que resta por cobrar ($' + Math.max(0, pendiente).toFixed(2) + ').\n' +
            'Se está cobrando $' + excedente + ' de más.\n\n' +
            '¿Deseas agregarlo de todas formas?'
        );
        if (!confirmar) return;
    }

    pagos.push({ tipo: tipo, desc: desc, monto: monto, cargo: cargo, anticipo: anticipo, referencia: referencia || '' });
    renderPagos();
    recalcular();
    $('#modalPago').modal('hide');
    $('#modalTipoPago').val('');
    $('#modalMonto').val(0);
    $('#modalAnticipo').prop('checked', esHijo);
    _refTarjetaPendiente = '';
}

$('#btnAgregarPago').on('click', function() {
    var tipo     = $('#modalTipoPago').val();
    var desc     = $('#modalTipoPago option:selected').data('desc');
    var monto    = parseFloat($('#modalMonto').val()) || 0;
    var anticipo = $('#modalAnticipo').is(':checked') ? 1 : 0;
    var cargoPct = parseFloat($('#modalTipoPago option:selected').data('cargo')) || 0;
    var cargo    = monto * cargoPct / 100;

    // Sin selección: obligatorio elegir método
    if (!tipo) {
        alert('Debes seleccionar un método de pago.');
        return;
    }

    // "Sin Pagar" = a crédito: no requiere monto, solo marca la bandera
    if (desc === 'Sin Pagar') {
        sinPagarSeleccionado = true;
        pagos = [];
        renderPagos();
        recalcular();
        $('#modalPago').modal('hide');
        $('#modalTipoPago').val('');
        $('#modalMonto').val(0);
        $('#modalAnticipo').prop('checked', defaultAnticipo);
        return;
    }

    // Método de pago real: requiere monto
    if (monto <= 0) {
        alert('Ingresa un monto válido.');
        return;
    }

    // Si es tarjeta, pedir referencia antes de agregar
    if (esTarjeta(desc)) {
        // Guardar los datos del pago actual para usarlos al confirmar
        _refTarjetaPendiente = '';
        $('#inputRefTarjeta').val('');
        $('#refTarjetaError').addClass('d-none');

        // Guardar en variables temporales para el callback del modal de referencia
        $('#modalRefTarjeta').data('pago', { tipo: tipo, desc: desc, monto: monto, cargo: cargo, anticipo: anticipo });
        $('#modalPago').modal('hide');
        // Pequeño delay para que el primer modal termine de cerrar
        setTimeout(function() { $('#modalRefTarjeta').modal('show'); }, 350);
        return;
    }

    agregarPagoConfirmado(tipo, desc, monto, cargo, anticipo, '');
});

// Confirmar referencia de tarjeta
$('#btnConfirmarRefTarjeta').on('click', function() {
    var ref = $.trim($('#inputRefTarjeta').val());
    if (!ref) {
        $('#refTarjetaError').removeClass('d-none');
        return;
    }
    $('#refTarjetaError').addClass('d-none');
    var p = $('#modalRefTarjeta').data('pago');
    $('#modalRefTarjeta').modal('hide');
    agregarPagoConfirmado(p.tipo, p.desc, p.monto, p.cargo, p.anticipo, ref);
});

// Cancelar referencia: volver al modal de pago
$('#btnCancelarRefTarjeta').on('click', function() {
    $('#modalRefTarjeta').modal('hide');
    setTimeout(function() { $('#modalPago').modal('show'); }, 350);
});

function renderPagos() {
    if (pagos.length === 0) {
        if (sinPagarSeleccionado) {
            $('#listaPagos').html('<li class="list-group-item text-center"><span class="badge badge-secondary">Sin Pagar — A Crédito</span> <button type="button" class="btn btn-xs btn-outline-danger ml-2" id="btnQuitarSinPagar">&times;</button></li>');
        } else {
            $('#listaPagos').html('<li class="list-group-item text-muted text-center" id="liSinPagos">Sin pagos nuevos aún.</li>');
        }
        return;
    }
    var html = '';
    pagos.forEach(function(p, i) {
        var refLabel = p.referencia ? ' <small class="text-muted">Ref: ' + p.referencia + '</small>' : '';
        html += '<li class="list-group-item d-flex justify-content-between align-items-center">'
            + '<span>' + p.desc + (p.anticipo ? ' <span class="badge badge-warning">Anticipo</span>' : '') + refLabel + '</span>'
            + '<span>$' + parseFloat(p.monto).toFixed(2)
            + ' <button type="button" class="btn btn-xs btn-outline-danger ml-2 btn-del-pago" data-idx="' + i + '">&times;</button></span>'
            + '</li>';
    });
    $('#listaPagos').html(html);
}

$(document).on('click', '.btn-del-pago', function() {
    var idx = parseInt($(this).data('idx'), 10);
    pagos.splice(idx, 1);
    renderPagos();
    recalcular();
});

$(document).on('click', '#btnQuitarSinPagar', function() {
    sinPagarSeleccionado = false;
    renderPagos();
    recalcular();
});

// ── Verificar email antes de cerrar si requiere factura ──────────────
function ejecutarCierreNota() {
    // Si no hay pagos nuevos pero ya estaba cubierto con anticipo, se permite cerrar
    var total    = parseFloat($('#hidTotal').val()) || 0;
    var montoNuevo = pagos.reduce(function(acc, p) { return acc + parseFloat(p.monto); }, 0);
    // Obligatorio: debe haber elegido algún método de pago (real o "Sin Pagar")
    if (pagos.length === 0 && !sinPagarSeleccionado && yaPagado <= 0) {
        alert('Debes seleccionar al menos un método de pago.');
        return;
    }

    // Bloquear si hay saldo pendiente y NO eligió "Sin Pagar"
    // Excepción: si todos los pagos nuevos son anticipos, se permite pago parcial
    if (!sinPagarSeleccionado) {
        var todosAnticipo = pagos.length > 0 && pagos.every(function(p) { return p.anticipo === 1; });
        if (!todosAnticipo) {
            var restanteFinal = total - yaPagado - montoNuevo;
            if (restanteFinal > 0.005) {
                alert('Falta por pagar: $' + restanteFinal.toFixed(2) + '\nAgrega el monto restante antes de cerrar la nota.');
                return;
            }
        }
    }

    var csrf = {};
    csrf[$('#hidCsrfName').val()] = $('#hidCsrfHash').val();

    var payload = $.extend({
        folio:       $('#hidFolio').val(),
        Id_Notas_1:  $('#hidIdNotas1').val(),
        descuento:   $('#inputDescuento').val() || 0,
        subtotal:    $('#hidSubtotal').val(),
        iva:         $('#hidIva').val(),
        total:       $('#hidTotal').val(),
        estatus:     $('#selectEstatus').val(),
        factura:     $('#chkFactura').is(':checked') ? 1 : 0,
        pagos:       JSON.stringify(pagos),   // solo los pagos NUEVOS
        // Datos fiscales del receptor (solo se envían si se requiere factura)
        rfcReceptor:           $('#rfcReceptor').val(),
        razonSocialReceptor:   $('#razonSocialReceptor').val(),
        cpReceptor:            $('#cpReceptor').val(),
        regimenFiscalReceptor: $('#regimenFiscalReceptor').val(),
        usoCFDI:               $('#usoCFDI').val(),
        formaPagoCFDI:         $('#formaPagoCFDI').val(),
        metodoPagoCFDI:        $('#metodoPagoCFDI').val()
    }, csrf);

    $('#btnGuardar').prop('disabled', true).text('Guardando...');

    // URL dinamica: funciona desde /mostrador y desde /admin
        var postUrl = window.location.href.split('?')[0];

        $.ajax({
        type: 'POST',
        url: postUrl,
        data: payload,
        complete: function(xhr) {
            var resp = null;
            try { resp = JSON.parse(xhr.responseText); } catch(e) {}

            if (resp && resp.success) {
                // Redirigir segun el prefijo de la URL actual
                var esAdmin = window.location.pathname.indexOf('/admin/') !== -1;
                window.location.href = esAdmin
                    ? '<?= base_url('admin/consulta') ?>'
                    : '<?= base_url('mostrador/consulta') ?>';
                return;
            }

            // Mostrar el error real (JSON message, o texto plano del HTML)
            var msg;
            if (resp && resp.message) {
                msg = resp.message;
            } else {
                msg = 'HTTP ' + xhr.status + '\n'
                    + (xhr.responseText || '(sin respuesta)')
                          .replace(/<[^>]+>/g, ' ')
                          .replace(/\s+/g, ' ')
                          .trim()
                          .substring(0, 500);
            }
            alert('Error al guardar:\n' + msg);
            $('#btnGuardar').prop('disabled', false).text('Cerrar Nota');
        }
    });
}

$('#btnGuardar').on('click', function() {
    ejecutarCierreNota();
});

// Guardar email y continuar
$('#btnGuardarEmail').on('click', function() {
    var email = $('#inputEmailCliente').val().trim();
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        $('#emailError').removeClass('d-none');
        return;
    }
    $('#emailError').addClass('d-none');
    $('#btnGuardarEmail').prop('disabled', true).text('Guardando...');

    $.post('<?= base_url('admin/clientes/guardar-email') ?>', {
        id: idClienteActual,
        email: email,
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
    }).done(function(resp) {
        if (resp && resp.ok) {
            clienteEmailActual = email;
            $('#modalEmailFaltante').modal('hide');
        } else {
            alert('No se pudo guardar el correo. Intenta de nuevo.');
        }
    }).fail(function() {
        alert('Error de conexión al guardar el correo.');
    }).always(function() {
        $('#btnGuardarEmail').prop('disabled', false).text('Guardar y continuar');
    });
});

// Inicializar cálculo al cargar
recalcular();

// ── Lógica del panel "Requiere Factura" ─────────────────────────────
// Datos fiscales del cliente (pre-cargados desde PHP)
var clienteEmailActual = '<?= esc($clienteEmail ?? '') ?>';
var idClienteActual    = <?= (int)($idCliente ?? 0) ?>;
var clienteRFC         = '<?= esc($nota['rfc_receptor'] ?? '') ?>' || '<?= esc($clienteRFC ?? '') ?>';
var clienteRazonSocial = '<?= esc($nota['razon_social_receptor'] ?? '') ?>' || '<?= esc($clienteRazonSocial ?? '') ?>';
var clienteCP          = '<?= esc($nota['cp_receptor'] ?? '') ?>'    || '<?= esc($clienteCP ?? '') ?>';

$('#chkFactura').on('change', function () {
    if ($(this).is(':checked')) {
        // Verificar email del cliente en BD antes de mostrar panel
        $.post('<?= base_url('admin/clientes/datos') ?>', {
            id: idClienteActual,
            '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
        }, function(resp) {
            if (resp && resp.success && !resp.email) {
                // Sin correo — mostrar modal para capturarlo
                $('#inputEmailCliente').val('');
                $('#emailError').addClass('d-none');
                $('#modalEmailFaltante').modal('show');
            }
            // Independientemente, mostrar el panel fiscal
            clienteEmailActual = (resp && resp.email) ? resp.email : '';
        }, 'json').fail(function() {
            // Si falla el AJAX igual abrimos el panel
        });

        $('#panelFactura').slideDown(200);

        // Auto-rellenar solo si los campos están vacíos
        if (!$('#rfcReceptor').val() && clienteRFC) {
            $('#rfcReceptor').val(clienteRFC);
        }
        if (!$('#razonSocialReceptor').val() && clienteRazonSocial) {
            $('#razonSocialReceptor').val(clienteRazonSocial);
        }
        if (!$('#cpReceptor').val() && clienteCP) {
            $('#cpReceptor').val(clienteCP);
        }

        // Mostrar alerta si el cliente no tiene RFC
        if (!clienteRFC) {
            $('#alertSinRFC').show();
        }
    } else {
        $('#panelFactura').slideUp(200);
        $('#alertSinRFC').hide();
    }
});

// Validar campos fiscales antes de guardar
var btnGuardarOriginal = $('#btnGuardar').on('click', function () { }); // ya tiene su propio handler arriba
// Extender validación: si factura está marcada, verificar RFC y Razón Social
$(document).on('click', '#btnGuardar', function (e) {
    if ($('#chkFactura').is(':checked')) {
        var rfc = $.trim($('#rfcReceptor').val());
        var rs  = $.trim($('#razonSocialReceptor').val());
        var cp  = $.trim($('#cpReceptor').val());
        if (!rfc || !rs || !cp) {
            alert('Para facturar necesitas capturar: RFC, Razón Social y CP del receptor.');
            return false;
        }
        // Validación básica de formato RFC
        var rfcRegex = /^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
        if (!rfcRegex.test(rfc.toUpperCase().replace(/\s/g,''))) {
            alert('El RFC "' + rfc + '" no tiene el formato correcto (ej: XAXX010101000).');
            return false;
        }
    }
});
</script>
<?= $this->endSection() ?>
