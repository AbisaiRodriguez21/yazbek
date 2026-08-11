<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Comanda: <?= $nota['folio'] ?> | Yazbek</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-size: 16px;
            font-family: Tahoma, sans-serif;
            margin: 0;
            padding: 4px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        .ticket-wrap { width: 100%; margin: 0 auto; }
        .center { text-align: center; }
        .right  { text-align: right; }
        td { padding: 2px 2px; vertical-align: top; }
        .sep td { border-top: 1px solid #7B7B7B; }
        .strong { font-weight: 600; }
        .red    { color: #FF0000; font-size: 18px; font-weight: 700; }
        .logo   { max-width: 90px; height: auto; }
        .comanda-titulo {
            font-size: 18px; font-weight: 700; letter-spacing: 2px;
            text-align: center; border: 2px solid #000; padding: 2px 0; margin: 2px 0;
        }
        .no-print { display: block; text-align: center; margin: 6px 0; }
        @page { margin: 0; }
        @media print {
            .no-print { display: none !important; }
            html, body { padding: 0; margin: 0; height: auto; }
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

        <tr>
            <td colspan="4" class="comanda-titulo">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>COMANDA</span>
                    <span style="color:#FF0000;"><?= $nota['folio'] ?></span>
                </div>
            </td>
        </tr>

        <!-- Vendedor, Fecha y Referencia -->
        <tr>
            <td colspan="4">
                Atendió: <?= esc($nota['vendedor']) ?>
                &nbsp; Fecha: <?= date('d/M/Y', strtotime($nota['fecha_inicial'])) ?>
                &nbsp; <span style="white-space:nowrap;">Hora: <?= date('h:i A', strtotime($nota['fecha_inicial'])) ?></span>
                <?php if (!empty($nota['referencia']) && (int)$nota['referencia'] !== 0): ?>
                    <br>Referencia: <?= $nota['referencia'] ?>
                <?php endif; ?>
            </td>
        </tr>

        <!-- Encabezado productos -->
        <tr>
            <td colspan="4" style="font-weight:600; border-bottom:1px solid #7B7B7B;">Productos</td>
        </tr>

        <!-- Productos (SOLO producto — sin precios ni pagos). SKU y cantidad
             en el mismo renglón para que notas de muchos productos no
             queden larguísimas al imprimir. -->
        <?php foreach ($productos as $prod): ?>
        <tr class="sep">
            <td colspan="3" style="font-size:16px; font-weight:600;"><?= esc($prod['sku'] ?? '') ?></td>
            <td class="right" style="font-size:16px; font-weight:700;"><?= $prod['cantidad'] ?></td>
        </tr>
        <tr>
            <td colspan="4">
                <span style="font-size:16px; font-weight:400;"><?= esc($prod['descripcion'] ?? '') ?></span>
                <?php if (!empty($prod['talla']) || !empty($prod['color'])): ?>
                    <br><span style="font-size:16px; color:#000;">
                        <?= esc($prod['talla'] ?? '') ?><?= (!empty($prod['talla']) && !empty($prod['color'])) ? ' — ' : '' ?><?= esc($prod['color'] ?? '') ?>
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <!-- Total piezas -->
        <tr class="sep">
            <td colspan="4" style="font-size:18px; font-weight:700;">
                Total Piezas:&nbsp;<?= $totalPiezas ?>
            </td>
        </tr>

    </table>

    <!-- Botón imprimir (solo en pantalla) -->
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 24px; font-size:14px; cursor:pointer;">
            🖨️ Imprimir
        </button>
    </div>
</div>

<script>
    // Auto-print al abrir el popup (igual que el ticket)
    window.onload = function () {
        setTimeout(function () { window.print(); }, 800);
    };
</script>
</body>
</html>
