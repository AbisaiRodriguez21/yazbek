<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Folio: <?= $nota['folio'] ?> | Yazbek</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-size: 13px;
            font-family: Tahoma, sans-serif;
            margin: 0;
            padding: 4px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        .ticket-wrap { width: 330px; margin: 0 auto; }
        .center { text-align: center; }
        .right  { text-align: right; }
        td { padding: 2px 2px; vertical-align: top; }
        .sep td { border-top: 1px solid #7B7B7B; }
        .strong { font-weight: 600; }
        .red    { color: #FF0000; font-size: 18px; font-weight: 700; }
        .logo   { max-width: 146px; height: auto; }
        .empresa { font-size: 11px; font-family: 'Trebuchet MS', sans-serif; line-height: 1.5; }
        .no-print { display: block; text-align: center; margin: 12px 0; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
<div class="ticket-wrap">
    <table>

        <!-- Logo -->
        <tr>
            <td colspan="4" class="center">
                <img src="<?= base_url('assets/logos/logo_yazbek_01.png') ?>" class="logo" alt="Yazbek" onerror="this.style.display='none'" />
            </td>
        </tr>

        <!-- Datos empresa -->
        <tr>
            <td colspan="4" class="center empresa">
                <?= esc($config['empresa_razon_social'] ?? 'PROMOSHAPE S. DE R.L. DE C.V.') ?><br>
                <?= esc($config['empresa_sucursal']     ?? 'Sucursal 27 Poniente 904. Col. Chulavista') ?><br>
                <?= esc($config['empresa_ciudad']       ?? 'Puebla, Pue. C.P. 72420') ?><br>
                <?= esc($config['empresa_telefono']     ?? 'Tel/Fax: (222) 1 30 29 29 / 2 96 65 30') ?><br>
                <?= esc($config['empresa_email_web']    ?? 'yazbekpuebla@hotmail.com | www.yazbekpuebla.com') ?><br>
                <?= esc($config['empresa_rfc']          ?? 'RFC PRO060620G97') ?><br>
                <?= esc($config['empresa_regimen']      ?? 'Régimen General de Personas Morales') ?>
            </td>
        </tr>

        <!-- Vendedor -->
        <tr>
            <td colspan="4" class="right">Atendió: <?= esc($nota['vendedor']) ?></td>
        </tr>

        <!-- Fecha y Folio -->
        <tr>
            <td colspan="4">
                Fecha: <?= date('d/M/Y', strtotime($nota['fecha_final'] ?? $nota['fecha_inicial'])) ?>
                &nbsp; Hora: <?= date('h:i A', strtotime($nota['fecha_final'] ?? $nota['fecha_inicial'])) ?><br>
                Folio: <span class="red"><?= $nota['folio'] ?></span>
                <?php if (!empty($nota['referencia']) && (int)$nota['referencia'] !== 0): ?>
                    &nbsp; Referencia: <?= $nota['referencia'] ?>
                <?php endif; ?>
            </td>
        </tr>

        <!-- Cliente -->
        <tr>
            <td colspan="4">
                Nombre del cliente: <?= esc($nota['NombreCliente']) ?><br>
                Tel: <?= esc($nota['telefono'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <td colspan="4">E-mail: <?= esc($nota['email'] ?? '') ?></td>
        </tr>
        <tr><td colspan="4">&nbsp;</td></tr>

        <?php if ($nota['status'] !== 'Cancelado'): ?>
        <!-- Encabezado productos -->
        <tr>
            <td colspan="4" style="font-weight:600; border-bottom:1px solid #7B7B7B;">Descripción</td>
        </tr>
        <?php endif; ?>

        <!-- Productos -->
        <?php foreach ($productos as $prod): ?>
        <tr class="sep">
            <td colspan="4">
                <span style="font-size:11px; color:#555;">SKU: <strong><?= esc($prod['sku'] ?? '') ?></strong></span><br>
                <?= esc($prod['descripcion'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <td style="width:30%; text-align:right;">
                Cantidad:<br>
                <span style="font-size:18px; font-weight:700;"><?= $prod['cantidad'] ?></span>
            </td>
            <td colspan="2" class="right">
                Precio Unitario:<br>$ <?= number_format($prod['pUnitario'] ?? 0, 2) ?>
            </td>
            <td class="right">
                Importe:<br>$ <?= number_format($prod['importe'] ?? 0, 2) ?>
            </td>
        </tr>
        <tr><td colspan="4" style="height:8px;"></td></tr>
        <?php endforeach; ?>

        <tr><td colspan="4">&nbsp;</td></tr>

        <?php if ($nota['status'] !== 'Cancelado'): ?>

        <!-- Total piezas -->
        <tr class="sep">
            <td colspan="2" style="font-size:18px; font-weight:700;">
                Total Piezas:&nbsp;<?= $nota['totalPiezas'] ?? '' ?>
            </td>
            <td colspan="2"></td>
        </tr>
        <tr><td colspan="4">&nbsp;</td></tr>

        <!-- Otros -->
        <tr class="sep">
            <td colspan="2" class="right">Otros:&nbsp;</td>
            <td colspan="2" class="right">
                <?php
                $ti = (int)($nota['tipoImpresion'] ?? 0);
                echo $ti === 1 ? 'Ninguno' : ($ti === 2 ? 'Impresión' : ($ti === 3 ? 'Bordado' : ''));
                ?>
            </td>
        </tr>
        <tr class="sep">
            <td colspan="2" class="right">Cargo por otros:&nbsp;</td>
            <td colspan="2" class="right">$ <?= number_format($nota['cargoPorImpresion'] ?? 0, 2) ?></td>
        </tr>
        <tr class="sep">
            <td colspan="2" class="right">Descuento:&nbsp;</td>
            <td colspan="2" class="right">-$ <?= number_format($nota['descuento'] ?? 0, 2) ?></td>
        </tr>
        <tr class="sep">
            <td colspan="2" class="right">SubTotal:&nbsp;</td>
            <td colspan="2" class="right">$ <?= number_format($nota['subTotal'] ?? 0, 2) ?></td>
        </tr>
        <tr class="sep">
            <td colspan="2" class="right">IVA:&nbsp;</td>
            <td colspan="2" class="right">$ <?= number_format($nota['iva'] ?? 0, 2) ?></td>
        </tr>
        <tr class="sep">
            <td colspan="2" class="right">SubTotal2:&nbsp;</td>
            <td colspan="2" class="right">$ <?= number_format($nota['subTotal2'] ?? 0, 2) ?></td>
        </tr>
        <tr class="sep">
            <td colspan="2" class="right red">Total:&nbsp;</td>
            <td colspan="2" class="right red">$ <?= number_format($nota['total'] ?? 0, 2) ?></td>
        </tr>

        <tr><td colspan="4">&nbsp;</td></tr>

        <?php if (! empty($pagosHijos)): ?>
        <!-- Folio padre con hijos: mostrar pagos de cada folio hijo -->
        <tr class="sep">
            <td colspan="4" class="strong">Pagos de Anticipo</td>
        </tr>
        <?php foreach ($pagosHijos as $hijo): ?>
        <tr class="sep">
            <td colspan="4" class="strong">Folio #<?= $hijo['folio'] ?></td>
        </tr>
        <?php if (!empty($hijo['fecha'])): ?>
        <tr>
            <td colspan="4">&nbsp;&nbsp;Fecha: <?= date('d/m/Y H:i', strtotime($hijo['fecha'])) ?></td>
        </tr>
        <?php endif; ?>
        <?php foreach ($hijo['pagos'] as $p): ?>
        <tr>
            <td colspan="4">&nbsp;&nbsp;Forma de Pago: <?= esc($p['tipopago']) ?></td>
        </tr>
        <tr>
            <td colspan="4">&nbsp;&nbsp;Monto: $ <?= number_format($p['monto'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <?php else: ?>
        <!-- Sin folios hijos: mostrar pagos directos del folio -->

        <!-- Anticipos desglosados (si hay más de 1) -->
        <?php if (count($anticipos) > 1 && empty($nota['referencia'])): ?>
        <tr class="sep">
            <td colspan="4" class="strong">Anticipo(s)</td>
        </tr>
        <?php foreach ($anticipos as $ant): ?>
        <tr class="sep">
            <td colspan="4">$<?= number_format($ant['monto'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="4">&nbsp;</td></tr>
        <?php endif; ?>

        <?php foreach ($pagos as $idx => $p): ?>
        <?php if ($idx > 0): ?>
        <tr class="sep"><td colspan="4">&nbsp;</td></tr>
        <?php endif; ?>
        <tr>
            <td colspan="4">Forma de Pago: <?= esc($p['tipopago']) ?></td>
        </tr>
        <tr>
            <td colspan="3">
                <?php if ((float)$p['monto'] <= 0): ?>
                    Monto a devolver: <span class="red">$ <?= number_format($p['monto'], 2) ?></span>
                <?php else: ?>
                    Monto: $ <?= number_format($p['monto'], 2) ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if (in_array($p['idTipoPago'] ?? 0, [2,3]) && $nota['status'] === 'Anticipo'): ?>
                    Cargo: $ <?= number_format($p['cargo'] ?? 0, 2) ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php if (($p['idTipoPago'] ?? 0) == 1): ?>
        <tr>
            <td colspan="4">Efectivo: $ <?= number_format($p['montoEfectivoIva'] ?? 0, 2) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="4">Es anticipo: <?= ($p['anticipo'] ?? 0) == 1 ? 'Si' : 'No' ?></td>
        </tr>
        <?php endforeach; ?>

        <?php endif; /* fin if pagosHijos */ ?>

        <tr><td colspan="4">&nbsp;</td></tr>

        <!-- Cantidad con letras -->
        <?php if ($letras): ?>
        <tr class="sep">
            <td colspan="4">Cantidad con letra: (<?= $letras ?>)</td>
        </tr>
        <?php endif; ?>
        <tr><td colspan="4">&nbsp;</td></tr>

        <?php endif; /* fin if no Cancelado */ ?>

        <!-- Estatus -->
        <tr class="sep" style="font-size:18px; font-weight:700; color:#FF0000;">
            <td colspan="4">Estatus: <span style="color:#FF0000;"><?= esc($nota['status']) ?></span></td>
        </tr>
        <!-- Factura -->
        <tr class="sep" style="font-size:18px; font-weight:700; color:#FF0000;">
            <td colspan="4">
                Factura: <span style="color:#FF0000;">
                    <?php
                        $facVal = (int)($nota['factura'] ?? 0);
                        $uuid   = $nota['uuid_fiscal'] ?? $nota['uuid_Fiscal'] ?? $nota['UUID_FISCAL'] ?? '';
                        if ($uuid) {
                            echo 'Facturado ✓';
                        } elseif ($facVal === 1) {
                            echo 'Si requiere';
                        } else {
                            echo 'No requiere';
                        }
                    ?>
                </span>
            </td>
        </tr>

        <tr><td colspan="4">&nbsp;</td></tr>

        <!-- Avisos legales -->
        <tr class="sep">
            <td colspan="4" style="font-size:11px;">
                <?= esc($config['msg_fiscal'] ?? 'Este ticket NO es comprobante Fiscal. Si requiere FACTURA favor de solicitarla el mismo día de su compra.') ?>
            </td>
        </tr>
        <tr class="sep">
            <td colspan="4" style="font-size:11px;">
                <?= esc($config['msg_cambios'] ?? 'ESTIMADO CLIENTE NO SE REALIZAN CAMBIOS NI DEVOLUCIONES.') ?><br>
                <?= esc($config['msg_quejas']  ?? 'QUEJAS: sugerencias@yazbekpuebla.com') ?>
            </td>
        </tr>
        <tr class="sep">
            <td colspan="4" style="font-size:11px;">
                <?= esc($config['msg_privacidad'] ?? 'Lo invitamos a checar nuestro aviso de privacidad en nuestra página www.nissipromo.com.mx') ?>
            </td>
        </tr>
        <tr><td colspan="4"><p>&nbsp;</p></td></tr>

    </table>

    <!-- Botón imprimir (solo en pantalla) -->
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 24px; font-size:14px; cursor:pointer;">
            🖨️ Imprimir
        </button>
    </div>
</div>

<script>
    // Auto-print al abrir el popup (igual que el original)
    window.onload = function () {
        // pequeño delay para que cargue la imagen del logo
        setTimeout(function () { window.print(); }, 800);
    };
</script>
</body>
</html>
