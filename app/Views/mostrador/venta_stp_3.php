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
            <a href="<?= base_url('mostrador/venta/' . (int)$nota['folio'] . '/productos') ?>"
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

                    <input type="hidden" id="inputDescuento" name="descuento" value="0">

                    <!-- Totales calculados -->
                    <table class="table table-sm mb-3">
                        <tr>
                            <th>Subtotal (sin IVA):</th>
                            <td class="text-right" id="tdSubtotal">—</td>
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
                            <option value="4">Anticipo</option>
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
                            <?php foreach ($pagosExistentes as $pe): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                <span class="text-muted">
                                    <?= esc($pe['descripcion']) ?>
                                    <?php if ($pe['anticipo']): ?>
                                    <span class="badge badge-warning">Anticipo</span>
                                    <?php endif; ?>
                                    <?php if (!empty($pe['folio_origen']) && (int)$pe['folio_origen'] !== (int)$nota['folio']): ?>
                                    <small class="text-info ml-1">Folio #<?= (int)$pe['folio_origen'] ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="text-muted">$<?= number_format($pe['monto'], 2) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>

                        <!-- Nuevos pagos (esta sesión) -->
                        <ul id="listaPagos" class="list-group">
                            <li class="list-group-item text-muted text-center" id="liSinPagos">Sin pagos nuevos aún.</li>
                        </ul>
                        <div class="mt-2 text-right">
                            <?php if (!empty($pagosExistentes)): ?>
                            <strong>Ya pagado: <span id="spYaPagado" class="text-muted">
                                $<?= number_format(array_sum(array_column($pagosExistentes, 'monto')), 2) ?>
                            </span></strong><br>
                            <?php endif; ?>
                            <strong>Nuevo pago: <span id="spMontoPagado" class="text-success">$0.00</span></strong><br>
                            <strong>Restante: <span id="spRestante" class="text-danger">$0.00</span></strong>
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="button" id="btnGuardar" class="btn btn-success btn-lg">
                            <i class="iconsminds-yes"></i> Cerrar Nota
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

<input type="hidden" id="hidCsrfName" value="<?= csrf_token() ?>">
<input type="hidden" id="hidCsrfHash" value="<?= csrf_hash() ?>">
<input type="hidden" id="hidSumaImportes" value="<?= $sumaImportes ?>">
<input type="hidden" id="hidFolio" value="<?= (int)$nota['folio'] ?>">
<!-- Total ya pagado en sesiones anteriores (anticipos, etc.) -->
<input type="hidden" id="hidYaPagado" value="<?= number_format(array_sum(array_column($pagosExistentes, 'monto')), 2, '.', '') ?>">

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
var pagos = [];   // solo pagos NUEVOS de esta sesión
var sinPagarSeleccionado = false;   // true cuando el usuario eligió "Sin Pagar" (a crédito)
var sumaImportes = parseFloat($('#hidSumaImportes').val()) || 0;
var yaPagado     = parseFloat($('#hidYaPagado').val()) || 0;
var descuento    = 0;

function recalcular() {
    var desc     = parseFloat($('#inputDescuento').val()) || 0;
    descuento    = desc;
    var subtotal = sumaImportes * (1 - desc / 100);
    var iva      = subtotal * 0.16;
    var total    = subtotal + iva;

    // Cargos de tarjeta de los pagos nuevos
    var cargos = 0;
    pagos.forEach(function(p) { cargos += parseFloat(p.cargo || 0); });
    total += cargos;
    // Redondear el total al peso más cercano (misma lógica que restante)
    total = Math.round(total);

    $('#tdSubtotal').text('$' + subtotal.toFixed(2));
    $('#tdIva').text('$' + iva.toFixed(2));
    $('#tdTotal').text('$' + total.toFixed(2));
    $('#hidSubtotal').val(subtotal.toFixed(2));
    $('#hidIva').val(iva.toFixed(2));
    $('#hidTotal').val(total.toFixed(2));

    // Monto de los pagos nuevos en esta sesión
    var montoNuevo = pagos.reduce(function(acc, p) { return acc + parseFloat(p.monto); }, 0);
    // Restante = total - ya pagado antes - nuevos pagos ahora
    var restante = total - yaPagado - montoNuevo;
    // Redondear al peso más cercano (< .50 baja, >= .50 sube)
    restante = Math.round(restante);
    var liquidado = restante <= 0;

    $('#spMontoPagado').text('$' + montoNuevo.toFixed(2));
    $('#spRestante').text('$' + Math.max(0, restante).toFixed(2));

    if (liquidado) {
        // Liquidado: poner Pagada automáticamente
        $('#spRestante').removeClass('text-danger').addClass('text-success');
        $('#selectEstatus').val('5');
        $('#msgEstatus').text('✔ Nota liquidada — se marcará como Pagada.');
    } else {
        // Hay saldo pendiente: poner Abierta automáticamente
        $('#spRestante').removeClass('text-success').addClass('text-danger');
        $('#selectEstatus').val('1');
        $('#msgEstatus').text('⚠ Hay saldo pendiente — se guardará como Abierta. Cambia a Anticipo si aplica.');
    }
}

$('#inputDescuento').on('input', recalcular);

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
        pagos = [];   // limpiar pagos reales si había alguno
        renderPagos();
        recalcular();
        $('#modalPago').modal('hide');
        $('#modalTipoPago').val('');
        $('#modalMonto').val(0);
        $('#modalAnticipo').prop('checked', false);
        return;
    }

    // Método de pago real: requiere monto
    if (monto <= 0) {
        alert('Ingresa un monto válido.');
        return;
    }

    // Si había "Sin Pagar" seleccionado antes, se reemplaza con pago real
    sinPagarSeleccionado = false;

    // Calcular cuánto falta por pagar antes de agregar este pago
    var yaPagadoActual = pagos.reduce(function(acc, p) { return acc + parseFloat(p.monto); }, 0);
    var totalActual    = parseFloat($('#hidTotal').val()) || 0;
    var yaPagadoPrev   = parseFloat($('#hidYaPagado').val()) || 0;
    var pendiente      = Math.round(totalActual - yaPagadoPrev - yaPagadoActual);

    if (monto > pendiente + 0.99) {
        var excedente = (monto - pendiente).toFixed(2);
        var confirmar = confirm(
            '⚠ El pago de $' + monto.toFixed(2) + ' excede lo que resta por cobrar ($' + Math.max(0, pendiente) + '.00).\n' +
            'Se está cobrando $' + excedente + ' de más.\n\n' +
            '¿Deseas agregarlo de todas formas?'
        );
        if (!confirmar) return;
    }

    pagos.push({ tipo: tipo, desc: desc, monto: monto, cargo: cargo, anticipo: anticipo });
    renderPagos();
    recalcular();
    $('#modalPago').modal('hide');
    $('#modalTipoPago').val('');
    $('#modalMonto').val(0);
    $('#modalAnticipo').prop('checked', false);
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
        html += '<li class="list-group-item d-flex justify-content-between align-items-center">'
            + '<span>' + p.desc + (p.anticipo ? ' <span class="badge badge-warning">Anticipo</span>' : '') + '</span>'
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

$('#btnGuardar').on('click', function() {
    // Si no hay pagos nuevos pero ya estaba cubierto con anticipo, se permite cerrar
    var total    = parseFloat($('#hidTotal').val()) || 0;
    var montoNuevo = pagos.reduce(function(acc, p) { return acc + parseFloat(p.monto); }, 0);
    // Obligatorio: debe haber elegido algún método de pago (real o "Sin Pagar")
    if (pagos.length === 0 && !sinPagarSeleccionado && yaPagado <= 0) {
        alert('Debes seleccionar al menos un método de pago.');
        return;
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
        pagos:       JSON.stringify(pagos)   // solo los pagos NUEVOS
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
});

// Inicializar cálculo al cargar
recalcular();
</script>
<?= $this->endSection() ?>
