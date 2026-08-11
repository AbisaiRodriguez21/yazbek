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

                    <?php if (($base ?? 'mostrador') === 'admin'): ?>
                    <!-- Descuento y Tipo de bordado/impresión: solo Admin -->
                    <div class="form-group">
                        <label for="inputDescuentoPct">Descuento (%)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">%</span>
                            </div>
                            <input type="number" id="inputDescuentoPct"
                                   class="form-control"
                                   value="<?= ($nota['descuento'] ?? 0) > 0 && $sumaImportes > 0 ? round((float)$nota['descuento'] / $sumaImportes * 100, 2) : '' ?>"
                                   min="0" max="100" step="0.1" placeholder="0"
                                   <?= !empty($descuentoFijo) ? 'readonly style="background:#f8f9fa;cursor:not-allowed;"' : '' ?>>
                        </div>
                        <input type="hidden" id="hidDescuentoDlr" name="descuento" value="<?= (float)($nota['descuento'] ?? 0) ?>">
                        <?php if (!empty($descuentoFijo)): ?>
                        <small class="form-text text-warning">
                            <i class="simple-icon-lock"></i> Descuento fijo de la nota original — no se puede modificar en pagos subsecuentes.
                        </small>
                        <?php else: ?>
                        <small class="form-text text-muted">= <span id="spanDescuentoDlr">$0.00</span> — se resta del subtotal antes de calcular el IVA.</small>
                        <?php endif; ?>
                    </div>

                    <!-- Tipo de impresión / bordado -->
                    <div class="form-row mb-3">
                        <div class="form-group col-md-6 mb-0">
                            <label for="selectImpresion">Tipo de bordado / impresión</label>
                            <select id="selectImpresion" name="impresion" class="form-control">
                                <?php foreach ($tiposImpresion as $ti): ?>
                                <option value="<?= (int)$ti['id'] ?>"
                                    <?= (int)($nota['tipoImpresion'] ?? 1) === (int)$ti['id'] ? 'selected' : '' ?>>
                                    <?= esc($ti['descripcion']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6 mb-0" id="divCargoImpresion">
                            <label for="inputCargoImpresion">Costo extra ($)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                </div>
                                <input type="number" id="inputCargoImpresion" name="cargoImpresion"
                                       class="form-control" min="0" step="0.01" placeholder="0.00"
                                       value="<?= ($nota['cargoPorImpresion'] ?? 0) > 0 ? number_format((float)$nota['cargoPorImpresion'], 2, '.', '') : '' ?>">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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
                        <tr id="trCargo" style="display:none;">
                            <th>Cargo extra:</th>
                            <td class="text-right" id="tdCargo">—</td>
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

                    <div class="form-group">
                        <label>Forma(s) de Pago (con las que pagará el cliente) <span class="text-danger">*</span></label>
                        <div class="card card-body bg-light mb-2" id="panelFormasPago">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Métodos agregados</strong>
                                <button type="button" class="btn btn-sm btn-primary" id="btnAgregarMetodoPago">
                                    <i class="iconsminds-add"></i> Agregar método
                                </button>
                            </div>
                            <ul id="listaMetodosPago" class="list-group mb-2">
                                <li class="list-group-item text-muted text-center" id="liSinMetodos">Sin métodos agregados aún.</li>
                            </ul>
                            <div class="text-right">
                                <strong>Asignado: <span id="spAsignadoMetodos" class="text-success">$0.00</span></strong><br>
                                <strong id="lblRestanteMetodos">Restante por asignar: </strong><span id="spRestanteMetodos" class="text-danger font-weight-bold">$0.00</span>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            <i class="simple-icon-info"></i> Al finalizar, la nota se envía a Caja con estas formas
                            de pago para que reciba el dinero y la cierre. Si eliges más de una, Caja verá y
                            recibirá cada una por separado.
                        </small>
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

                    <?php
                    // Solo sumar pagos de notas NO canceladas — se usa en el panel de pagos
                    // (admin) y en el hidden #hidYaPagado (ambos roles) más abajo.
                    $sumaPagosValidos = array_sum(array_column(
                        array_filter($pagosExistentes ?? [], fn($pe) => (int)($pe['nota_status'] ?? 0) !== 3),
                        'monto'
                    ));
                    ?>
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

<!-- ═══ MODAL: Agregar método de pago ════════════════════════════════════ -->
<div class="modal fade" id="modalMetodoPago" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Forma de Pago</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 mb-3 d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">Restante por asignar:</span>
                    <span class="font-weight-bold" id="modalMetodoRestanteLabel" style="font-size:1.15rem;">$0.00</span>
                </div>
                <div class="form-group">
                    <label>Tipo de Pago</label>
                    <select id="modalMetodoTipo" class="form-control">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($tipoPagos as $tp): ?>
                        <option value="<?= (int)$tp['id'] ?>"><?= esc($tp['descripcion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="number" id="modalMetodoMonto" class="form-control" min="0.01" step="0.01" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" id="btnConfirmarMetodoPago" class="btn btn-primary">Agregar</button>
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
var sumaImportes = parseFloat($('#hidSumaImportes').val()) || 0;
function recalcular() {
    var pct           = parseFloat($('#inputDescuentoPct').val()) || 0;
    if (pct > 100) { pct = 100; $('#inputDescuentoPct').val(100); }
    var desc          = Math.min((pct / 100) * sumaImportes, sumaImportes);
    $('#hidDescuentoDlr').val(desc.toFixed(2));
    $('#spanDescuentoDlr').text('$' + desc.toFixed(2));
    var cargoImp      = parseFloat($('#inputCargoImpresion').val()) || 0;
    var subtotalBruto = sumaImportes;
    var subtotal      = Math.max(0, subtotalBruto - desc);
    var baseIva       = subtotal + cargoImp;
    var iva           = baseIva * 0.16;
    var total         = baseIva + iva;

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
    if (cargoImp > 0) {
        $('#trCargo').show();
        $('#tdCargo').text('$' + cargoImp.toFixed(2));
    } else {
        $('#trCargo').hide();
    }
    $('#tdTotal').text('$' + total.toFixed(2));
    $('#hidSubtotal').val(subtotal.toFixed(2));
    $('#hidIva').val(iva.toFixed(2));
    $('#hidTotal').val(total.toFixed(2));
    renderMetodosPago();
}

$('#inputDescuentoPct').on('input', recalcular);
$('#inputCargoImpresion').on('input', recalcular);

// ── Formas de Pago (varios métodos posibles) ──────────────────────────
var metodosPago = [];

function totalNota() {
    return parseFloat($('#hidTotal').val()) || 0;
}
function asignadoMetodos() {
    return metodosPago.reduce(function(acc, m) { return acc + m.monto; }, 0);
}
function restanteMetodos() {
    return Math.max(0, totalNota() - asignadoMetodos());
}

function renderMetodosPago() {
    if (metodosPago.length === 0) {
        $('#listaMetodosPago').html('<li class="list-group-item text-muted text-center" id="liSinMetodos">Sin métodos agregados aún.</li>');
    } else {
        var html = '';
        metodosPago.forEach(function(m, i) {
            html += '<li class="list-group-item d-flex justify-content-between align-items-center">'
                + '<span>' + m.desc + '</span>'
                + '<span>$' + m.monto.toFixed(2)
                + ' <button type="button" class="btn btn-xs btn-outline-danger ml-2 btn-quitar-metodo" data-idx="' + i + '">&times;</button></span>'
                + '</li>';
        });
        $('#listaMetodosPago').html(html);
    }
    $('#spAsignadoMetodos').text('$' + asignadoMetodos().toFixed(2));
    var restante = restanteMetodos();
    if (restante <= 0.005) {
        $('#lblRestanteMetodos').text('Restante por asignar: ');
        $('#spRestanteMetodos').text('$0.00').removeClass('text-danger').addClass('text-success');
    } else {
        $('#lblRestanteMetodos').text('Restante por asignar: ');
        $('#spRestanteMetodos').text('$' + restante.toFixed(2)).removeClass('text-success').addClass('text-danger');
    }
}

$('#btnAgregarMetodoPago').on('click', function() {
    $('#modalMetodoTipo').val('');
    var restante = restanteMetodos();
    $('#modalMetodoMonto').val(restante > 0 ? restante.toFixed(2) : 0);
    $('#modalMetodoRestanteLabel').text('$' + restante.toFixed(2));
    $('#modalMetodoPago').modal('show');
});

$('#btnConfirmarMetodoPago').on('click', function() {
    var tipo  = $('#modalMetodoTipo').val();
    var desc  = $('#modalMetodoTipo option:selected').text();
    var monto = parseFloat($('#modalMetodoMonto').val()) || 0;

    if (!tipo) { alert('Selecciona un tipo de pago.'); return; }
    if (monto <= 0) { alert('Ingresa un monto válido.'); return; }

    var restante = restanteMetodos();
    if (monto > restante + 0.005) {
        if (!confirm('⚠ El monto de $' + monto.toFixed(2) + ' excede lo que resta por asignar ($' + restante.toFixed(2) + ').\n¿Agregarlo de todas formas?')) {
            return;
        }
    }

    metodosPago.push({ tipo: tipo, desc: desc, monto: monto });
    renderMetodosPago();
    $('#modalMetodoPago').modal('hide');
});

$(document).on('click', '.btn-quitar-metodo', function() {
    var idx = parseInt($(this).data('idx'), 10);
    metodosPago.splice(idx, 1);
    renderMetodosPago();
});

// ── Verificar email antes de cerrar si requiere factura ──────────────
function ejecutarCierreNota() {
    // No se cobra aquí: solo se indican las formas de pago con las que
    // pagará el cliente, para que Caja (o Admin desde su Verificar Caja) las
    // vea al recibir el folio.
    if (metodosPago.length === 0) {
        alert('Agrega al menos una forma de pago.');
        return;
    }
    var restante = restanteMetodos();
    if (restante > 0.005) {
        alert('Falta por asignar: $' + restante.toFixed(2) + '\nAgrega el monto restante antes de finalizar.');
        return;
    }

    var csrf = {};
    csrf[$('#hidCsrfName').val()] = $('#hidCsrfHash').val();

    var payload = $.extend({
        folio:           $('#hidFolio').val(),
        Id_Notas_1:      $('#hidIdNotas1').val(),
        descuento:       $('#hidDescuentoDlr').val() || 0,
        impresion:       $('#selectImpresion').val() || 1,
        cargoImpresion:  $('#inputCargoImpresion').val() || 0,
        subtotal:    $('#hidSubtotal').val(),
        iva:         $('#hidIva').val(),
        total:       $('#hidTotal').val(),
        metodosPago: JSON.stringify(metodosPago),
        factura:     $('#chkFactura').is(':checked') ? 1 : 0,
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
                // Nota finalizada: abrir el ticket en una ventana pequeña
                // (igual que el botón "Ver Ticket") listo para imprimir, y
                // mandar la ventana principal a Consultar Folios.
                var esAdmin     = window.location.pathname.indexOf('/admin/') !== -1;
                var folioActual = $('#hidFolio').val();
                var ticketUrl   = (esAdmin
                    ? '<?= base_url('admin/folio/') ?>'
                    : '<?= base_url('mostrador/folio/') ?>')
                    + folioActual + '/ticket';
                window.open(ticketUrl, '_blank',
                    'toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=200,width=500,height=600');
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
