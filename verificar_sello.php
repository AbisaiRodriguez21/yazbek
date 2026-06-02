<?php
/**
 * verificar_sello.php
 * Uso: php verificar_sello.php
 *
 * Lee el XML del cfdi_debug.txt, extrae Sello y Certificado,
 * reconstruye la cadena original y compara hashes para diagnosticar CFDI40102.
 *
 * Pegar en la raíz del proyecto y ejecutar desde cmd/PowerShell:
 *   cd C:\xampp\htdocs\yazbek   (o donde esté tu proyecto)
 *   php verificar_sello.php
 */

// ── 1. Leer el XML del debug ──────────────────────────────────────────────
$debugPath = __DIR__ . '/writable/cfdi_debug.txt';
if (!file_exists($debugPath)) {
    die("No se encontró writable/cfdi_debug.txt\n");
}

$contenido = file_get_contents($debugPath);

// Extraer el último bloque XML FINAL (el que se envió a IDEA15)
if (!preg_match('/=== XML FINAL \(enviado a IDEA15\) ===(.*?)(?===|\z)/s', $contenido, $m)) {
    // Fallback: cualquier bloque XML
    if (!preg_match('/<\?xml[^>]+>(.*?<\/cfdi:Comprobante>)/s', $contenido, $m)) {
        die("No se pudo extraer el XML del debug.\n");
    }
}
$xmlRaw = trim($m[1]);

// Asegurarnos de que empiece en <?xml
if (strpos($xmlRaw, '<?xml') === false) {
    $xmlRaw = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xmlRaw;
}

echo "=== XML extraído (" . strlen($xmlRaw) . " bytes) ===\n";
echo substr($xmlRaw, 0, 200) . "...\n\n";

// ── 2. Parsear XML ────────────────────────────────────────────────────────
$dom = new DOMDocument();
if (!$dom->loadXML($xmlRaw)) {
    die("Error al parsear XML.\n");
}
$comp = $dom->documentElement;

$selloB64   = $comp->getAttribute('Sello');
$certB64    = $comp->getAttribute('Certificado');
$noCert     = $comp->getAttribute('NoCertificado');

if (!$selloB64 || !$certB64) {
    die("El XML no tiene Sello o Certificado.\n");
}

echo "NoCertificado : $noCert\n";
echo "Sello (64 chars): " . substr($selloB64, 0, 64) . "...\n\n";

// ── 3. Reconstruir la cadena original (método manual) ────────────────────
// Para verificación exacta, usa el XSLT si tienes php_xsl habilitado.
// Esta función replica el XSLT de forma fiel (incluye Base en traslados globales).

function co(array &$campos, DOMElement $elem, string $attr): void {
    $val = trim(preg_replace('/\s+/', ' ', $elem->getAttribute($attr)));
    if ($val !== '') $campos[] = $val;
}

function generarCadena(DOMDocument $dom): string {
    $comp   = $dom->documentElement;
    $campos = [];

    $campos[] = $comp->getAttribute('Version');
    co($campos, $comp, 'Serie');
    co($campos, $comp, 'Folio');
    $campos[] = $comp->getAttribute('Fecha');
    co($campos, $comp, 'FormaPago');
    $campos[] = $comp->getAttribute('NoCertificado');
    co($campos, $comp, 'CondicionesDePago');
    $campos[] = $comp->getAttribute('SubTotal');
    co($campos, $comp, 'Descuento');
    $campos[] = $comp->getAttribute('Moneda');
    co($campos, $comp, 'TipoCambio');
    $campos[] = $comp->getAttribute('Total');
    $campos[] = $comp->getAttribute('TipoDeComprobante');
    $campos[] = $comp->getAttribute('Exportacion');
    co($campos, $comp, 'MetodoPago');
    $campos[] = $comp->getAttribute('LugarExpedicion');
    co($campos, $comp, 'Confirmacion');

    foreach ($comp->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) continue;
        switch ($child->localName) {
            case 'InformacionGlobal':
                $campos[] = $child->getAttribute('Periodicidad');
                $campos[] = $child->getAttribute('Meses');
                $campos[] = $child->getAttribute('Año');
                break;
            case 'CfdiRelacionados':
                $campos[] = $child->getAttribute('TipoRelacion');
                foreach ($child->childNodes as $r) {
                    if ($r->nodeType === XML_ELEMENT_NODE && $r->localName === 'CfdiRelacionado')
                        $campos[] = $r->getAttribute('UUID');
                }
                break;
            case 'Emisor':
                $campos[] = $child->getAttribute('Rfc');
                co($campos, $child, 'Nombre');
                $campos[] = $child->getAttribute('RegimenFiscal');
                co($campos, $child, 'FacAtrAdquirente');
                break;
            case 'Receptor':
                $campos[] = $child->getAttribute('Rfc');
                co($campos, $child, 'Nombre');
                co($campos, $child, 'DomicilioFiscalReceptor');
                co($campos, $child, 'ResidenciaFiscal');
                co($campos, $child, 'NumRegIdTrib');
                co($campos, $child, 'RegimenFiscalReceptor');
                $campos[] = $child->getAttribute('UsoCFDI');
                break;
            case 'Conceptos':
                foreach ($child->childNodes as $c) {
                    if ($c->nodeType !== XML_ELEMENT_NODE || $c->localName !== 'Concepto') continue;
                    $campos[] = $c->getAttribute('ClaveProdServ');
                    co($campos, $c, 'NoIdentificacion');
                    $campos[] = $c->getAttribute('Cantidad');
                    $campos[] = $c->getAttribute('ClaveUnidad');
                    co($campos, $c, 'Unidad');
                    $campos[] = $c->getAttribute('Descripcion');
                    $campos[] = $c->getAttribute('ValorUnitario');
                    $campos[] = $c->getAttribute('Importe');
                    co($campos, $c, 'Descuento');
                    $campos[] = $c->getAttribute('ObjetoImp');
                    foreach ($c->childNodes as $imp) {
                        if ($imp->nodeType !== XML_ELEMENT_NODE || $imp->localName !== 'Impuestos') continue;
                        foreach ($imp->childNodes as $grp) {
                            if ($grp->nodeType !== XML_ELEMENT_NODE) continue;
                            foreach ($grp->childNodes as $item) {
                                if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                                $campos[] = $item->getAttribute('Base');
                                $campos[] = $item->getAttribute('Impuesto');
                                $campos[] = $item->getAttribute('TipoFactor');
                                co($campos, $item, 'TasaOCuota');
                                co($campos, $item, 'Importe');
                            }
                        }
                    }
                }
                break;
            case 'Impuestos':
                co($campos, $child, 'TotalImpuestosRetenidos');
                co($campos, $child, 'TotalImpuestosTrasladados');
                foreach ($child->childNodes as $grp) {
                    if ($grp->nodeType !== XML_ELEMENT_NODE) continue;
                    foreach ($grp->childNodes as $item) {
                        if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                        if ($grp->localName === 'Retenciones') {
                            $campos[] = $item->getAttribute('Impuesto');
                            $campos[] = $item->getAttribute('Importe');
                        } else {
                            // XSLT SAT línea 108: Base es Requerido en traslados globales
                            $campos[] = $item->getAttribute('Base');
                            $campos[] = $item->getAttribute('Impuesto');
                            $campos[] = $item->getAttribute('TipoFactor');
                            co($campos, $item, 'TasaOCuota');
                            co($campos, $item, 'Importe');
                        }
                    }
                }
                break;
        }
    }
    return '||' . implode('|', $campos) . '||';
}

$cadenaReconstruida = generarCadena($dom);
$sha256Reconstruida = hash('sha256', $cadenaReconstruida);

echo "=== CADENA RECONSTRUIDA ===\n$cadenaReconstruida\n\n";
echo "=== SHA256 de la cadena ===\n$sha256Reconstruida\n\n";

// ── 4. Descifrar el Sello con la llave pública del Certificado ────────────
$certDer = base64_decode($certB64);
$cerPem  = "-----BEGIN CERTIFICATE-----\n"
         . chunk_split(base64_encode($certDer), 64, "\n")
         . "-----END CERTIFICATE-----\n";

$pubKey = openssl_pkey_get_public($cerPem);
if (!$pubKey) {
    die("No se pudo leer la llave pública del certificado: " . openssl_error_string() . "\n");
}

$selloBytes = base64_decode($selloB64);

// openssl_verify: recalcula sha256 de la cadena y compara con la firma
$result = openssl_verify($cadenaReconstruida, $selloBytes, $pubKey, OPENSSL_ALGO_SHA256);

echo "=== VERIFICACIÓN SELLO vs CADENA RECONSTRUIDA ===\n";
echo "openssl_verify resultado: ";
if ($result === 1) {
    echo "✅ VÁLIDO — el sello fue firmado con esta cadena.\n";
    echo "   → El XML es criptográficamente correcto. Si IDEA15 rechaza con CFDI40102\n";
    echo "     su XSLT produce una cadena diferente a la tuya. Habilita extension=xsl.\n";
} elseif ($result === 0) {
    echo "❌ INVÁLIDO — el sello NO corresponde a esta cadena.\n";
    echo "   → La cadena que firmaste es diferente a la reconstruida aquí.\n";
    echo "     Compara ambas cadena char por char.\n";
} else {
    echo "⚠️  ERROR openssl: " . openssl_error_string() . "\n";
}

// ── 5. RSA raw decrypt del sello → extraer hash SHA256 ───────────────────
// Nota: PHP no expone RSA_public_decrypt directamente vía openssl_*
// pero podemos usar openssl_public_decrypt con NO_PADDING para extraer
// el DigestInfo DER y el hash SHA256 embebido en la firma PKCS#1 v1.5
$rawDecrypt = '';
$certKeyDetails = openssl_pkey_details($pubKey);
$keySize = ($certKeyDetails['bits'] ?? 2048) / 8;

echo "\n=== RSA DECRYPT MANUAL DEL SELLO ===\n";
if (openssl_public_decrypt($selloBytes, $rawDecrypt, $pubKey, OPENSSL_NO_PADDING)) {
    $hex = bin2hex($rawDecrypt);
    echo "Raw decrypted (hex, último 32 bytes = SHA256):\n";
    echo strtoupper($hex) . "\n\n";
    // SHA256 DigestInfo: los últimos 32 bytes son el hash
    $sha256DelSello = substr($hex, -64);
    echo "SHA256 extraído del sello (lo que el PAC desencripta):\n";
    echo strtoupper($sha256DelSello) . "\n\n";
    echo "SHA256 de tu cadena reconstruida:\n";
    echo strtoupper($sha256Reconstruida) . "\n\n";
    if (strtoupper($sha256DelSello) === strtoupper($sha256Reconstruida)) {
        echo "✅ COINCIDEN — la cadena reconstruida aquí ES la que firmaste.\n";
    } else {
        echo "❌ NO COINCIDEN — la cadena que firmaste difiere de la reconstruida aquí.\n";
        echo "   Revisa los campos que generarCadena() produce vs tu sellarXML().\n";
    }
} else {
    echo "(No se pudo hacer RSA raw decrypt: " . openssl_error_string() . ")\n";
    echo "Usa el openssl_verify de arriba como referencia.\n";
}

echo "\nFin del diagnóstico.\n";
