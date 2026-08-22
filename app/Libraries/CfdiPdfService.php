<?php

namespace App\Libraries;

/**
 * CfdiPdfService
 * Genera PDF de factura CFDI 4.0 con formato igual al documento de referencia.
 * Datos del encabezado se leen dinámicamente desde ticket_config.
 * Requiere: composer require mpdf/mpdf
 */
class CfdiPdfService
{
    public function generarPDF(string $xmlTimbrado, array $config = [], string $observaciones = ''): string
    {
        // ── Parsear XML ──────────────────────────────────────────────────
        $dom = new \DOMDocument();
        $dom->loadXML($xmlTimbrado);
        $comp = $dom->documentElement;

        $folio      = $comp->getAttribute('Folio');
        $serie      = $comp->getAttribute('Serie');
        $fecha      = $comp->getAttribute('Fecha');
        $subtotalV  = (float)$comp->getAttribute('SubTotal');
        $descuentoV = (float)$comp->getAttribute('Descuento');
        $totalV     = (float)$comp->getAttribute('Total');
        $moneda     = $comp->getAttribute('Moneda');
        $formaPago  = $comp->getAttribute('FormaPago');
        $formaPagoLabel = self::nombreFormaPago($formaPago);
        $metodoPago = $comp->getAttribute('MetodoPago');
        $noCert     = $comp->getAttribute('NoCertificado');
        $exportacion = $comp->getAttribute('Exportacion');
        $lugar      = $comp->getAttribute('LugarExpedicion');

        $emisorNode    = $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Emisor')->item(0);
        $emisorRfc     = $emisorNode ? $emisorNode->getAttribute('Rfc')            : '';
        $emisorRegimen = $emisorNode ? $emisorNode->getAttribute('RegimenFiscal')  : '';

        $receptorNode    = $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Receptor')->item(0);
        $receptorRfc     = $receptorNode ? $receptorNode->getAttribute('Rfc')                     : '';
        $receptorNombre  = $receptorNode ? $receptorNode->getAttribute('Nombre')                  : '';
        $receptorCP      = $receptorNode ? $receptorNode->getAttribute('DomicilioFiscalReceptor')  : '';
        $receptorRegimen = $receptorNode ? $receptorNode->getAttribute('RegimenFiscalReceptor')    : '';
        $usoCFDI         = $receptorNode ? $receptorNode->getAttribute('UsoCFDI')                  : '';

        // ── Timbre ───────────────────────────────────────────────────────
        $tfd = $dom->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'TimbreFiscalDigital')->item(0);
        $uuid          = $tfd ? $tfd->getAttribute('UUID')              : '';
        $fechaTimbrado = $tfd ? $tfd->getAttribute('FechaTimbrado')     : '';
        $noCertSAT     = $tfd ? $tfd->getAttribute('NoCertificadoSAT') : '';
        $selloCFD      = $tfd ? $tfd->getAttribute('SelloCFD')          : '';
        $selloSAT      = $tfd ? $tfd->getAttribute('SelloSAT')          : '';
        $rfcPAC        = $tfd ? $tfd->getAttribute('RfcProvCertif')     : '';
        $versionTfd    = $tfd ? $tfd->getAttribute('Version')           : '1.1';

        // Cadena original del complemento
        $cadenaComplemento = '||' . implode('|', [$versionTfd, $uuid, $fechaTimbrado, $selloCFD, $noCertSAT]) . '||';

        // ── Impuestos globales ───────────────────────────────────────────
        $impGlobal = null;
        foreach ($dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Impuestos') as $n) {
            if ($n->parentNode === $comp) { $impGlobal = $n; break; }
        }
        $totalIva = $impGlobal ? (float)$impGlobal->getAttribute('TotalImpuestosTrasladados') : 0.0;

        // ── URL verificación SAT + QR ────────────────────────────────────
        $fe        = substr($selloCFD, -8);                  // últimos 8 chars del sello
        $totalFmt  = number_format($totalV, 6, '.', '');     // ej: 60.000000
        $urlSAT    = 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx'
                   . '?id='  . urlencode($uuid)
                   . '&re='  . urlencode($emisorRfc)
                   . '&rr='  . urlencode($receptorRfc)
                   . '&tt='  . $totalFmt
                   . '&fe='  . urlencode($fe);

        // Generar QR localmente con chillerlan/php-qrcode v6
        $qrHtml = '';
        try {
            $qrOptions = new \chillerlan\QRCode\QROptions([
                'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
                'eccLevel'        => \chillerlan\QRCode\Common\EccLevel::M,
                'scale'           => 4,
                'outputBase64'    => true,
            ]);
            $qrB64  = (new \chillerlan\QRCode\QRCode($qrOptions))->render($urlSAT);
            $qrHtml = '<img src="' . $qrB64 . '" style="width:90px;height:90px;" alt="QR SAT"/>';
        } catch (\Throwable $e) {
            // Si falla el QR, el PDF se genera igual sin él
        }

        // ── Datos empresa desde ticket_config ────────────────────────────
        $empNombre  = $config['empresa_razon_social'] ?? '';
        $empDir     = $config['empresa_sucursal']     ?? '';
        $empCiudad  = $config['empresa_ciudad']       ?? '';
        $empTel     = $config['empresa_telefono']     ?? '';
        $empRfcRaw  = $config['empresa_rfc']          ?? $emisorRfc;
        $empRegimen = $config['empresa_regimen']      ?? $emisorRegimen;
        // Quitar prefijo "RFC " si viene así
        $empRfc     = preg_replace('/^RFC\s*/i', '', $empRfcRaw);

        // ── Logo empresa (base64 para embeber en PDF) ─────────────────────
        $logoPath = FCPATH . 'assets/logos/logo_yazbek_01.png';
        $logoHtml = '';
        if (file_exists($logoPath)) {
            $logoB64  = base64_encode(file_get_contents($logoPath));
            $logoHtml = '<img src="data:image/png;base64,' . $logoB64 . '" style="max-height:55px;max-width:160px;" alt="Logo"/>';
        }

        // ── Conceptos ────────────────────────────────────────────────────
        $conceptosNodes = $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Concepto');
        $filasConceptos = '';
        $totalSinIva = 0;

        foreach ($conceptosNodes as $c) {
            $clave    = $c->getAttribute('NoIdentificacion'); // SKU del producto
            $desc     = $c->getAttribute('Descripcion');
            $cantidad = (float)$c->getAttribute('Cantidad');
            $unidad   = $c->getAttribute('ClaveUnidad') . ' / ' . $c->getAttribute('Unidad');
            $precio   = (float)$c->getAttribute('ValorUnitario');
            $importe  = (float)$c->getAttribute('Importe');
            $descItem = (float)$c->getAttribute('Descuento');
            $baseIva  = $importe - $descItem;

            // IVA del concepto
            $ivaConcepto = 0.0;
            $tasaIva     = 0.16;
            foreach ($c->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Traslado') as $t) {
                $ivaConcepto += (float)$t->getAttribute('Importe');
                $tasa = $t->getAttribute('TasaOCuota');
                if ($tasa) $tasaIva = (float)$tasa;
            }
            $totalLinea = $baseIva + $ivaConcepto;

            $filasConceptos .= '
            <tr>
              <td class="c">' . number_format($cantidad, 2) . '</td>
              <td>' . htmlspecialchars($clave . ' ' . $desc) . '</td>
              <td class="r">$ ' . number_format($precio, 2)   . '</td>
              <td class="r">$ ' . number_format($descItem, 2) . '</td>
              <td class="r">$ ' . number_format($baseIva, 2)  . '</td>
              <td class="r">$ ' . number_format($ivaConcepto, 2) . '</td>
              <td class="r"></td>
              <td class="r"></td>
              <td class="r">$ ' . number_format($totalLinea, 2) . '</td>
            </tr>
            <tr class="imp-row">
              <td colspan="9">
                Impuesto: $' . number_format($baseIva, 2) . ' x [002{IVA} Tasa ' . number_format($tasaIva, 6) . '] = $' . number_format($ivaConcepto, 2) . '
              </td>
            </tr>';
        }

        // ── Totales ──────────────────────────────────────────────────────
        $descStr    = $descuentoV > 0 ? '- $ ' . number_format($descuentoV, 2) : '';
        $subtotalStr = '$ ' . number_format($subtotalV, 2);
        $ivaStr      = '$ ' . number_format($totalIva, 2);
        $totalStr    = '$ ' . number_format($totalV, 2);

        // ── HTML ─────────────────────────────────────────────────────────
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"/>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 8pt; color: #111; margin: 0; padding: 0; }
table { width: 100%; border-collapse: collapse; }
.r { text-align: right; }
.c { text-align: center; }

/* ── Encabezado ── */
.header-outer { border: 1px solid #888; margin-bottom: 4px; }
.header-left  { width: 65%; vertical-align: top; padding: 8px 10px; border-right: 1px solid #888; }
.header-right { width: 35%; vertical-align: top; padding: 0; }
.emp-name     { font-size: 13pt; font-weight: bold; color: #1a3a5c; margin-bottom: 3px; }
.emp-data     { font-size: 7.5pt; line-height: 1.6; }

/* ── Caja de tipo de comprobante ── */
.tipo-box     { background: #1a3a5c; color: #fff; font-weight: bold; font-size: 8pt;
                text-align: center; padding: 4px 6px; }
.tipo-tabla td { font-size: 7.5pt; padding: 2px 6px; border-bottom: 1px solid #ddd; }
.tipo-tabla .lbl { color: #1a3a5c; font-weight: bold; width: 55%; }

/* ── Sección cliente ── */
.sec-title    { background: #222; color: #fff; font-weight: bold; padding: 3px 6px;
                font-size: 8pt; margin: 4px 0 2px; }
.cliente-box  { border: 1px solid #aaa; padding: 5px 8px; font-size: 7.5pt; margin-bottom: 4px; }

/* ── Tabla conceptos ── */
.conc th      { background: #222; color: #fff; padding: 3px 4px; font-size: 7.5pt; text-align: center;
                border: 1px solid #555; }
.conc td      { border: 1px solid #ddd; padding: 2px 4px; font-size: 7.5pt; }
.imp-row td   { background: #f0f0f0; font-size: 7pt; color: #555; padding: 1px 6px;
                border-left: 1px solid #ddd; border-right: 1px solid #ddd; }

/* ── Totales ── */
.tot-row td   { padding: 2px 5px; font-size: 8pt; }
.tot-final td { background: #1a3a5c; color: #fff; font-weight: bold; font-size: 9pt; padding: 3px 5px; }

/* ── Sellos ── */
.sbox         { background: #f5f5f5; border: 1px solid #ccc; padding: 5px 7px;
                font-size: 6.5pt; word-break: break-all; margin-top: 4px; }
.sbox .lbl    { font-weight: bold; color: #1a3a5c; font-size: 7pt; }
.div          { border-top: 2px solid #1a3a5c; margin: 5px 0; }
</style></head><body>

<!-- ═══ ENCABEZADO ═══════════════════════════════════════════════════════ -->
<table class="header-outer">
<tr>
  <td class="header-left">
    ' . ($logoHtml ? '<div style="margin-bottom:6px;">' . $logoHtml . '</div>' : '') . '
    <div class="emp-name">' . htmlspecialchars($empNombre) . '</div>
    <div class="emp-data">
      <b>RFC:</b> ' . htmlspecialchars($empRfc) . '<br/>
      ' . htmlspecialchars($empDir) . '<br/>
      ' . htmlspecialchars($empCiudad) . '<br/>
      ' . htmlspecialchars($empTel) . '
    </div>
  </td>
  <td class="header-right">
    <div class="tipo-box">TIPO DE COMPROBANTE (I) FACTURA</div>
    <table class="tipo-tabla">
      <tr><td class="lbl">Folio</td>              <td>' . htmlspecialchars($folio) . '</td></tr>
      <tr><td class="lbl">Folio Fiscal</td>        <td style="font-size:6.5pt;">' . htmlspecialchars($uuid) . '</td></tr>
      <tr><td class="lbl">No. Serie CSD Emisor</td><td style="font-size:6.5pt;">' . htmlspecialchars($noCert) . '</td></tr>
      <tr><td class="lbl">No. Serie CSD SAT</td>   <td style="font-size:6.5pt;">' . htmlspecialchars($noCertSAT) . '</td></tr>
      <tr><td class="lbl">Fecha emisión</td>        <td>' . htmlspecialchars($fecha) . '</td></tr>
      <tr><td class="lbl">Fecha certificación</td>  <td>' . htmlspecialchars($fechaTimbrado) . '</td></tr>
      <tr><td class="lbl">Lugar Expedición</td>     <td>' . htmlspecialchars($lugar) . '</td></tr>
      <tr><td class="lbl">Forma Pago</td>           <td>' . htmlspecialchars($formaPagoLabel) . ' / ' . htmlspecialchars($metodoPago) . '</td></tr>
      <tr><td class="lbl">Moneda</td>               <td>' . htmlspecialchars($moneda) . '</td></tr>
    </table>
  </td>
</tr>
</table>

<!-- ═══ DATOS DEL CLIENTE ════════════════════════════════════════════════ -->
<div class="sec-title">DATOS DEL CLIENTE</div>
<div class="cliente-box">
  <b>' . htmlspecialchars($receptorNombre) . '</b><br/>
  <b>RFC:</b> ' . htmlspecialchars($receptorRfc) . '
  &nbsp;&nbsp; <b>CP Fiscal:</b> ' . htmlspecialchars($receptorCP) . '<br/>
  <b>Régimen fiscal del receptor:</b> ' . htmlspecialchars($receptorRegimen) . '<br/>
  <b>Uso CFDI:</b> ' . htmlspecialchars($usoCFDI) . '
</div>

<!-- ═══ RÉGIMEN EMISOR ═══════════════════════════════════════════════════ -->
<div style="border:1px solid #888;padding:3px 8px;font-size:7.5pt;margin-bottom:4px;">
  <b>Régimen Fiscal del Emisor: ' . htmlspecialchars($emisorRegimen) . ' / ' . htmlspecialchars($empRegimen) . '</b>
</div>

' . ($observaciones !== '' ? ('<div class="cliente-box"><b>Observaciones:</b> ' . nl2br(htmlspecialchars($observaciones)) . '</div>') : '') . '

<!-- ═══ CONCEPTOS ════════════════════════════════════════════════════════ -->
<table class="conc">
<thead>
<tr>
  <th style="width:6%">CANTIDAD</th>
  <th style="width:30%">DESCRIPCIÓN</th>
  <th style="width:9%" class="r">P. UNITARIO</th>
  <th style="width:9%" class="r">DESCUENTO</th>
  <th style="width:10%" class="r">IMPORTE</th>
  <th style="width:9%" class="r">IVA</th>
  <th style="width:6%" class="r">IEPS</th>
  <th style="width:7%" class="r">IMP.EST.</th>
  <th style="width:10%" class="r">TOTAL</th>
</tr>
</thead>
<tbody>
' . $filasConceptos . '
</tbody>
</table>

<!-- ═══ TOTALES ══════════════════════════════════════════════════════════ -->
<table style="width:100%; margin-top:6px;">
<tr>
  <td style="width:55%; vertical-align:top; font-size:7pt; color:#555;">
    <b>RFC PAC:</b> ' . htmlspecialchars($rfcPAC) . '<br/>
    <b>Exportación:</b> ' . htmlspecialchars($exportacion) . '
  </td>
  <td style="width:45%; vertical-align:top;">
    <table>
      ' . ($descStr ? '<tr class="tot-row"><td>Total Descuento</td><td class="r">' . $descStr . '</td></tr>' : '') . '
      <tr class="tot-row"><td>Total IVA</td>         <td class="r">' . $ivaStr . '</td></tr>
      <tr class="tot-row"><td>TOTAL</td>             <td class="r"><b>' . $totalStr . '</b></td></tr>
    </table>
  </td>
</tr>
</table>

<div class="div"></div>

<!-- ═══ SELLOS ═══════════════════════════════════════════════════════════ -->
<div class="sbox">
  <span class="lbl">SELLO DIGITAL DEL COMPROBANTE FISCAL:</span><br/>
  ' . htmlspecialchars(wordwrap($selloCFD, 115, "\n", true)) . '
</div>
<div class="sbox" style="margin-top:3px;">
  <span class="lbl">SELLO DIGITAL DEL TIMBRE FISCAL DIGITAL:</span><br/>
  ' . htmlspecialchars(wordwrap($selloSAT, 115, "\n", true)) . '
</div>
<div class="sbox" style="margin-top:3px;">
  <span class="lbl">CADENA ORIGINAL DEL COMPLEMENTO DE CERTIFICACIÓN DIGITAL DEL SAT:</span><br/>
  ' . htmlspecialchars(wordwrap($cadenaComplemento, 115, "\n", true)) . '
</div>

<!-- ═══ PIE: QR + leyenda ══════════════════════════════════════════════ -->
<table style="width:100%;margin-top:6px;">
<tr>
  <td style="width:110px;vertical-align:bottom;">
    ' . $qrHtml . '
    <div style="font-size:6pt;color:#555;text-align:center;margin-top:2px;">Verificar ante el SAT</div>
  </td>
  <td style="vertical-align:middle;text-align:center;font-size:7pt;color:#666;padding-left:10px;">
    Este documento es una representación impresa de un CFDI.<br/>
    Folio Fiscal (UUID): <b>' . htmlspecialchars($uuid) . '</b>
  </td>
</tr>
</table>

</body></html>';

        // ── Generar PDF ───────────────────────────────────────────────────
        $mpdf = new \Mpdf\Mpdf([
            'margin_top'    => 5,
            'margin_right'  => 7,
            'margin_bottom' => 7,
            'margin_left'   => 7,
            'format'        => 'A4',
        ]);
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * Traduce la clave del catálogo SAT c_FormaPago a "clave – nombre".
     * Público y estático para poder reutilizar el catálogo desde la
     * vista previa de factura (FacturacionService::previsualizar).
     */
    public static function nombreFormaPago(string $clave): string
    {
        $catalogo = [
            '01' => 'Efectivo',
            '02' => 'Cheque nominativo',
            '03' => 'Transferencia electrónica de fondos',
            '04' => 'Tarjeta de crédito',
            '05' => 'Monedero electrónico',
            '06' => 'Dinero electrónico',
            '08' => 'Vales de despensa',
            '12' => 'Dación en pago',
            '13' => 'Pago por subrogación',
            '14' => 'Pago por consignación',
            '15' => 'Condonación',
            '17' => 'Compensación',
            '23' => 'Novación',
            '24' => 'Confusión',
            '25' => 'Remisión de deuda',
            '26' => 'Prescripción o caducidad',
            '27' => 'A satisfacción del acreedor',
            '28' => 'Tarjeta de débito',
            '29' => 'Tarjeta de servicios',
            '30' => 'Aplicación de anticipos',
            '31' => 'Intermediario pagos',
            '99' => 'Por definir',
        ];

        $nombre = $catalogo[$clave] ?? null;

        return $nombre ?? $clave;
    }
}
