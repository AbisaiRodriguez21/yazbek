<?php

namespace App\Controllers;

/**
 * CfdiDiagController — Diagnóstico de timbrado CFDI 4.0
 *
 * SOLO PARA DESARROLLO/SANDBOX. Eliminar o proteger en producción.
 *
 * Acceso: http://localhost:8080/cfdi-diag
 */
class CfdiDiagController extends BaseController
{
    public function index(): string
    {
        $cerPath = APPPATH . 'ThirdParty/csd/csd.cer';
        $keyPath = APPPATH . 'ThirdParty/csd/csd.key';

        // Limpiar OPcache para asegurar que estemos ejecutando el código
        // más reciente del DfactureService (importante después de cambios).
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $resultado = [];
        $resultado[] = '<h2>Diagnóstico CFDI 4.0</h2>';

        // ── 0. Verificación del entorno PHP ───────────────────────────────
        $resultado[] = '<h3>0. Entorno PHP</h3>';
        $resultado[] = 'PHP version: <b>' . PHP_VERSION . '</b><br>';
        $extXsl     = extension_loaded('xsl');
        $extOpenssl = extension_loaded('openssl');
        $extDom     = extension_loaded('dom');
        $resultado[] = 'extensión <b>xsl</b>: ' . ($extXsl ? '✅ Sí' : '❌ <span style="color:red">NO — habilita "extension=xsl" en php.ini de XAMPP y reinicia Apache. Sin XSL se usa el método manual que es propenso a errores.</span>') . '<br>';
        $resultado[] = 'extensión <b>openssl</b>: ' . ($extOpenssl ? '✅ Sí' : '❌ NO') . '<br>';
        $resultado[] = 'extensión <b>dom</b>: ' . ($extDom ? '✅ Sí' : '❌ NO') . '<br>';
        $resultado[] = 'clase <b>XSLTProcessor</b>: ' . (class_exists('XSLTProcessor') ? '✅ disponible' : '❌ NO disponible') . '<br>';
        $resultado[] = 'OPcache: ' . (function_exists('opcache_reset') ? '✅ habilitado (reset aplicado)' : 'deshabilitado') . '<br>';

        // ── 1. Verificar archivos CSD ────────────────────────────────────
        $resultado[] = '<h3>1. Archivos CSD</h3>';
        $resultado[] = 'csd.cer: ' . (file_exists($cerPath) ? '✅ ' . filesize($cerPath) . ' bytes' : '❌ NO ENCONTRADO') . '<br>';
        $resultado[] = 'csd.key: ' . (file_exists($keyPath) ? '✅ ' . filesize($keyPath) . ' bytes' : '❌ NO ENCONTRADO') . '<br>';

        if (!file_exists($cerPath) || !file_exists($keyPath)) {
            return implode('', $resultado);
        }

        // ── 2. Parsear certificado ────────────────────────────────────────
        $resultado[] = '<h3>2. Certificado</h3>';
        $cerDer = file_get_contents($cerPath);
        $cerPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($cerDer), 64, "\n") . "-----END CERTIFICATE-----\n";
        $certRes = openssl_x509_read($cerPem);
        if ($certRes) {
            $certData = openssl_x509_parse($certRes);
            $serialHex = $certData['serialNumberHex'] ?? '';
            $noCert = '';
            for ($i = 0; $i + 1 < strlen($serialHex); $i += 2) {
                $byte = hexdec(substr($serialHex, $i, 2));
                if ($byte >= 0x30 && $byte <= 0x39) $noCert .= chr($byte);
            }
            $noCert = str_pad($noCert, 20, '0', STR_PAD_LEFT);
            $resultado[] = 'NoCertificado: <b>' . $noCert . '</b><br>';
            $resultado[] = 'Válido desde: ' . date('Y-m-d', $certData['validFrom_time_t']) . '<br>';
            $resultado[] = 'Válido hasta: ' . date('Y-m-d', $certData['validTo_time_t']) . '<br>';
            $resultado[] = 'RFC: ' . ($certData['subject']['serialNumber'] ?? '?') . '<br>';
        } else {
            $resultado[] = '❌ No se pudo parsear certificado: ' . openssl_error_string() . '<br>';
        }

        // ── 3. Verificar llave privada ────────────────────────────────────
        $resultado[] = '<h3>3. Llave privada</h3>';
        $keyPassword = '@053124Cortes';
        $keyDer = file_get_contents($keyPath);
        $keyB64 = chunk_split(base64_encode($keyDer), 64, "\n");
        $keyPem = "-----BEGIN ENCRYPTED PRIVATE KEY-----\n{$keyB64}-----END ENCRYPTED PRIVATE KEY-----\n";
        $privKey = openssl_pkey_get_private($keyPem, $keyPassword);
        if ($privKey) {
            $resultado[] = '✅ Llave privada leída correctamente<br>';
            // Verificar que coincide con el certificado
            $pubKey = openssl_pkey_get_public($cerPem);
            $testMsg = 'test_' . time();
            $firma = '';
            openssl_sign($testMsg, $firma, $privKey, OPENSSL_ALGO_SHA256);
            $verif = openssl_verify($testMsg, $firma, $pubKey, OPENSSL_ALGO_SHA256);
            $resultado[] = 'Par llave/cert coinciden: ' . ($verif === 1 ? '✅ SÍ' : '❌ NO (' . $verif . ')') . '<br>';
        } else {
            $resultado[] = '❌ No se pudo leer la llave: ' . openssl_error_string() . '<br>';
        }

        // ── 4. Test de credenciales IDEA15 ───────────────────────────────
        $resultado[] = '<h3>4. Test de credenciales IDEA15</h3>';
        $resultado[] = 'URL: http://timbrado.demodfacture.com/api/consultaTimbres<br>';

        $credParams = http_build_query(['user' => 'DEMOCortes', 'password' => 'cfdi']);
        $chCred = curl_init('http://timbrado.demodfacture.com/api/consultaTimbres');
        curl_setopt_array($chCred, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $credParams,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $credResp = curl_exec($chCred);
        $credHttp = curl_getinfo($chCred, CURLINFO_HTTP_CODE);
        $credErr  = curl_error($chCred);
        curl_close($chCred);

        if ($credErr) {
            $resultado[] = '❌ cURL error: ' . htmlspecialchars($credErr) . '<br>';
        } else {
            $credData   = json_decode($credResp, true);
            $credCodigo = (string)($credData['codigo'] ?? '?');
            if ($credCodigo === '101') {
                $resultado[] = '❌ <b>Credenciales INVÁLIDAS</b> (código 101) — DEMOCortes/cfdi ya no funciona. Contacta a IDEA15.<br>';
                $resultado[] = 'Respuesta raw: <code>' . htmlspecialchars($credResp) . '</code><br>';
            } else {
                $resultado[] = '✅ <b>Credenciales OK</b> — HTTP ' . $credHttp . '<br>';
                $resultado[] = 'Respuesta: <code>' . htmlspecialchars($credResp) . '</code><br>';
            }
        }

        // ── 5. Construir y firmar XML de prueba ───────────────────────────
        $resultado[] = '<h3>5. XML de prueba (datos mínimos, matemáticamente consistentes)</h3>';

        try {
            // Datos de prueba con números perfectamente consistentes
            $folio = 'TEST' . date('His');
            $fecha = date('Y-m-d\TH:i:s');

            // Item: 1 pieza × $100.00 = $100.00, IVA = $16.00, Total = $116.00
            // ValorUnitario con 6 decimales (igual que DfactureService)
            $valorUnitario = '100.000000';
            $cantidad      = '1';
            $importe       = '100.00';
            $ivaImporte    = '16.00';
            $subtotal      = '100.00';
            $iva           = '16.00';
            $total         = '116.00';

            // Cargar NoCertificado y Certificado
            $certificado = base64_encode($cerDer);
            $certData2   = openssl_x509_parse($certRes);
            $serialHex2  = $certData2['serialNumberHex'] ?? '';
            $noCert2     = '';
            for ($i = 0; $i + 1 < strlen($serialHex2); $i += 2) {
                $byte = hexdec(substr($serialHex2, $i, 2));
                if ($byte >= 0x30 && $byte <= 0x39) $noCert2 .= chr($byte);
            }
            $noCert2 = str_pad($noCert2, 20, '0', STR_PAD_LEFT);

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<cfdi:Comprobante' .
                ' xmlns:cfdi="http://www.sat.gob.mx/cfd/4"' .
                ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
                ' xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd"' .
                ' Version="4.0"' .
                ' Folio="' . $folio . '"' .
                ' Fecha="' . $fecha . '"' .
                ' Sello=""' .
                ' FormaPago="01"' .
                ' NoCertificado="' . $noCert2 . '"' .
                ' Certificado="' . $certificado . '"' .
                ' SubTotal="' . $subtotal . '"' .
                ' Moneda="MXN"' .
                ' Total="' . $total . '"' .
                ' TipoDeComprobante="I"' .
                ' Exportacion="01"' .
                ' MetodoPago="PUE"' .
                ' LugarExpedicion="72420">' . "\n";
            $xml .= '    <cfdi:Emisor Rfc="CORA030324B46" Nombre="ABDIEL ABISAI CORTES RODRIGUEZ" RegimenFiscal="612"/>' . "\n";
            $xml .= '    <cfdi:Receptor Rfc="EERE8402219P5" Nombre="EDMUNDO ECHEVERRIA RAMOS" DomicilioFiscalReceptor="72270" RegimenFiscalReceptor="612" UsoCFDI="G01"/>' . "\n";
            $xml .= '    <cfdi:Conceptos>' . "\n";
            $xml .= '        <cfdi:Concepto ClaveProdServ="01010101" Cantidad="' . $cantidad . '" ClaveUnidad="ACT" Descripcion="Prueba diagnostico" ValorUnitario="' . $valorUnitario . '" Importe="' . $importe . '" ObjetoImp="02">';
            $xml .= '<cfdi:Impuestos><cfdi:Traslados>';
            $xml .= '<cfdi:Traslado Base="' . $importe . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $ivaImporte . '"/>';
            $xml .= '</cfdi:Traslados></cfdi:Impuestos>';
            $xml .= '</cfdi:Concepto>' . "\n";
            $xml .= '    </cfdi:Conceptos>' . "\n";
            $xml .= '    <cfdi:Impuestos TotalImpuestosTrasladados="' . $iva . '">' . "\n";
            $xml .= '        <cfdi:Traslados>' . "\n";
            $xml .= '            <cfdi:Traslado Base="' . $subtotal . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $iva . '"/>' . "\n";
            $xml .= '        </cfdi:Traslados>' . "\n";
            $xml .= '    </cfdi:Impuestos>' . "\n";
            $xml .= '</cfdi:Comprobante>';

            $resultado[] = 'XML construido ✅<br>';
            $resultado[] = '<details><summary>Ver XML</summary><pre>' . htmlspecialchars($xml) . '</pre></details>';

            // ── Generar cadena original ────────────────────────────────────
            $cadena = $this->generarCadenaOriginal($xml);
            $resultado[] = '<br>Cadena original:<br><code style="font-size:11px;word-break:break-all;">' . htmlspecialchars($cadena) . '</code><br>';
            $resultado[] = 'Empieza con ||: ' . (substr($cadena, 0, 2) === '||' ? '✅' : '❌') . '<br>';
            $resultado[] = 'Empieza con |||: ' . (substr($cadena, 0, 3) === '|||' ? '❌ TRIPLE PIPE' : '✅ No') . '<br>';

            // ── Firmar ─────────────────────────────────────────────────────
            $firma = '';
            if (!openssl_sign($cadena, $firma, $privKey, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('openssl_sign falló: ' . openssl_error_string());
            }
            $sello = base64_encode($firma);

            // Verificar localmente
            $pubKey2 = openssl_pkey_get_public($cerPem);
            $verif2 = openssl_verify($cadena, $firma, $pubKey2, OPENSSL_ALGO_SHA256);
            $resultado[] = 'Verificación local sello: ' . ($verif2 === 1 ? '✅ OK' : '❌ FALLO (' . $verif2 . ')') . '<br>';

            // Insertar sello en XML
            $xmlFinal = str_replace('Sello=""', 'Sello="' . $sello . '"', $xml);
            $resultado[] = 'XML sellado ✅<br>';

            // ── Enviar a IDEA15 ────────────────────────────────────────────
            $resultado[] = '<h3>6. Envío a IDEA15 sandbox</h3>';
            $resultado[] = 'URL: http://timbrado.demodfacture.com/api/timbrarCFDI40<br>';
            $resultado[] = 'Usuario: DEMOCortes<br>';

            $xmlB64 = base64_encode($xmlFinal);

            // Construir el POST body como application/x-www-form-urlencoded
            // (IDEA15 requiere este Content-Type; multipart/form-data devuelve error 101)
            $postParams = [
                'user'     => 'DEMOCortes',
                'password' => 'cfdi',
                'xml'      => $xmlB64,
            ];
            $postBody = http_build_query($postParams);

            $resultado[] = 'POST body (primeros 80 chars): <code>' . htmlspecialchars(substr($postBody, 0, 80)) . '...</code><br>';
            $resultado[] = 'Longitud POST body: <b>' . strlen($postBody) . '</b> bytes<br>';
            $resultado[] = 'Longitud XML base64: <b>' . strlen($xmlB64) . '</b> chars<br>';

            $ch = curl_init('http://timbrado.demodfacture.com/api/timbrarCFDI40');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postBody,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $respRaw  = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $resultado[] = '❌ cURL error: ' . htmlspecialchars($curlErr) . '<br>';
            } else {
                $resultado[] = 'HTTP Code: <b>' . $httpCode . '</b><br>';
                $resultado[] = 'Respuesta raw:<br><pre style="background:#f4f4f4;padding:10px;overflow:auto;max-height:400px;">' . htmlspecialchars($respRaw) . '</pre>';
                $resp = json_decode($respRaw, true);
                if ($resp) {
                    $codigo  = $resp['codigo']  ?? $resp['Codigo']  ?? '?';
                    $mensaje = $resp['mensaje'] ?? $resp['message'] ?? '?';
                    $uuid    = $resp['uuid']    ?? $resp['UUID']    ?? '';
                    $resultado[] = '<br>Código: <b>' . htmlspecialchars($codigo) . '</b><br>';
                    $resultado[] = 'Mensaje: <b>' . htmlspecialchars($mensaje) . '</b><br>';
                    if ($uuid) {
                        $resultado[] = 'UUID: <b style="color:green">' . htmlspecialchars($uuid) . '</b><br>';
                        $resultado[] = '<br>🎉 <b style="color:green">TIMBRADO EXITOSO!</b><br>';
                    }
                }
            }

            // ── 7. TEST ALTERNATIVO: cadena SIN TipoDeComprobante ─────────
            // IDEA15 mostró en su email la cadena como "599.95||01" (doble pipe),
            // lo que puede significar que NO incluyen TipoDeComprobante.
            // Este test lo envía sin él para verificar.
            $resultado[] = '<h3>7. Test alternativo — cadena SIN TipoDeComprobante</h3>';
            $resultado[] = '<i>Si este da UUID pero el anterior no, significa que IDEA15 no incluye TipoDeComprobante.</i><br><br>';

            $cadena2 = $this->generarCadenaOriginalSinTipo($xml);
            $resultado[] = 'Cadena (sin TipoDeComp):<br><code style="font-size:11px;word-break:break-all;">' . htmlspecialchars($cadena2) . '</code><br><br>';

            $firma2 = '';
            openssl_sign($cadena2, $firma2, $privKey, OPENSSL_ALGO_SHA256);
            $sello2 = base64_encode($firma2);
            $verif3 = openssl_verify($cadena2, $firma2, openssl_pkey_get_public($cerPem), OPENSSL_ALGO_SHA256);
            $resultado[] = 'Verificación local: ' . ($verif3 === 1 ? '✅ OK' : '❌') . '<br>';

            // $xml ya tiene NoCertificado y Certificado llenos; solo falta insertar el nuevo sello
            $xmlFinal2 = str_replace('Sello=""', 'Sello="' . $sello2 . '"', $xml);

            $ch2 = curl_init('http://timbrado.demodfacture.com/api/timbrarCFDI40');
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'user'     => 'DEMOCortes',
                    'password' => 'cfdi',
                    'xml'      => base64_encode($xmlFinal2),
                ]),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $respRaw2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            $resultado[] = 'HTTP Code: <b>' . $httpCode2 . '</b><br>';
            $resultado[] = 'Respuesta:<br><pre style="background:#f4f4f4;padding:10px;overflow:auto;max-height:300px;">' . htmlspecialchars($respRaw2) . '</pre>';
            $resp2 = json_decode($respRaw2, true);
            if (!empty($resp2['uuid'])) {
                $resultado[] = '🎉 <b style="color:green">UUID SIN TipoDeComprobante: ' . htmlspecialchars($resp2['uuid']) . '</b><br>';
                $resultado[] = '→ CONFIRMADO: IDEA15 NO incluye TipoDeComprobante en la cadena.<br>';
            } elseif (($resp2['codigo'] ?? '') === '506') {
                $resultado[] = '✅ <b style="color:orange">Error 506: previamente timbrado — esto significa que SÍ funcionó (o funcionará con folio nuevo)</b><br>';
            } elseif (($resp2['codigo'] ?? '') === 'CFDI40102') {
                $resultado[] = '→ Sigue CFDI40102 sin TipoDeComprobante también — el problema es otro.<br>';
            }

            // ── 8. TEST FIRMA ALTERNATIVA (openssl_private_encrypt) ───────────
            // Si openssl_sign tiene un bug en XAMPP/Windows, esta técnica alternativa
            // produce la firma de forma diferente. Si esta da UUID y la otra no,
            // el bug está en cómo openssl_sign maneja la llave en esta plataforma.
            $resultado[] = '<h3>8. Test firma alternativa (openssl_private_encrypt)</h3>';
            $resultado[] = '<i>Usa SHA256 manual + RSA PKCS1v1.5. Si da UUID aquí pero no en sección 6 → bug en openssl_sign en XAMPP/Windows.</i><br><br>';

            // SHA256 del hash de la cadena (misma cadena que sección 6)
            $hashRaw   = hash('sha256', $cadena, true); // 32 bytes crudos
            $hashHex   = bin2hex($hashRaw);
            $resultado[] = 'SHA256 de la cadena: <code>' . $hashHex . '</code><br>';
            $resultado[] = 'Longitud sello (secc.6): <b>' . strlen($sello) . '</b> chars base64<br>';

            // Construir DigestInfo DER manualmente para SHA256
            // SEQUENCE { SEQUENCE { OID 2.16.840.1.101.3.4.2.1, NULL }, OCTET STRING(32) }
            $derPrefix  = "\x30\x31\x30\x0d\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x01\x05\x00\x04\x20";
            $digestInfo = $derPrefix . $hashRaw;

            $firmaAlt = '';
            $okAlt    = openssl_private_encrypt($digestInfo, $firmaAlt, $privKey, OPENSSL_PKCS1_PADDING);
            $selloAlt = base64_encode($firmaAlt);

            $resultado[] = 'Firma alternativa OK: ' . ($okAlt ? '✅' : '❌ ' . openssl_error_string()) . '<br>';
            $resultado[] = 'Longitud sello alt: <b>' . strlen($selloAlt) . '</b> chars base64<br>';
            $resultado[] = 'Sellos IGUALES: ' . ($sello === $selloAlt ? '✅ Sí — misma firma' : '<b style="color:red">❌ DIFERENTES — bug en openssl_sign!</b>') . '<br>';

            if ($okAlt && $sello !== $selloAlt) {
                // Los sellos son diferentes — probar con el alternativo a IDEA15
                $resultado[] = '<br><b>→ Enviando a IDEA15 con firma alternativa...</b><br>';
                $xmlFinalAlt = str_replace('Sello=""', 'Sello="' . $selloAlt . '"', $xml);

                $chAlt = curl_init('http://timbrado.demodfacture.com/api/timbrarCFDI40');
                curl_setopt_array($chAlt, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query([
                        'user'     => 'DEMOCortes',
                        'password' => 'cfdi',
                        'xml'      => base64_encode($xmlFinalAlt),
                    ]),
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Accept: application/json',
                    ],
                    CURLOPT_TIMEOUT        => 45,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $respAlt     = curl_exec($chAlt);
                $httpAlt     = curl_getinfo($chAlt, CURLINFO_HTTP_CODE);
                curl_close($chAlt);
                $resultado[] = 'HTTP: <b>' . $httpAlt . '</b> — Respuesta: <pre style="background:#f4f4f4;padding:5px;">' . htmlspecialchars($respAlt) . '</pre>';
                $respAltData = json_decode($respAlt, true);
                if (!empty($respAltData['uuid'])) {
                    $resultado[] = '🎉 <b style="color:green">TIMBRADO EXITOSO con firma alternativa! UUID: ' . htmlspecialchars($respAltData['uuid']) . '</b><br>';
                }
            } else {
                $resultado[] = '<br>→ Sellos iguales. El problema NO es openssl_sign.<br>';
            }

            // ── 9. TEST CADENA CON TipoDeComprobante VACÍO ("||") ─────────────
            // IDEA15 mostró en su email exactamente "599.95||01" — DOS pipes literales.
            // Eso significa campo VACÍO (string vacío), NO el valor "I".
            // Sección 6 produce |I| → no coincide con ||
            // Sección 7 produce nada → solo | → no coincide con ||
            // Esta sección produce || (campo vacío) → debería coincidir con IDEA15.
            $resultado[] = '<h3>9. ★ TEST CADENA CON TipoDeComprobante VACÍO (||)</h3>';
            $resultado[] = '<i>IDEA15 email mostró literalmente "599.95||01" = campo vacío entre Total y Exportacion.<br>Esta sección inserta string vacío ("") para producir exactamente ||.</i><br><br>';

            $cadena9 = $this->generarCadenaConTipoVacio($xml);
            $resultado[] = 'Cadena (tipo vacío):<br><code style="font-size:11px;word-break:break-all;background:#fffde7;padding:4px;">'
                . htmlspecialchars($cadena9) . '</code><br><br>';
            $resultado[] = '¿Contiene "||" donde va TipoDeComprobante? '
                . (strpos($cadena9, '116.00||01') !== false ? '✅ SÍ (116.00||01)' : '❌') . '<br>';

            $firma9 = '';
            openssl_sign($cadena9, $firma9, $privKey, OPENSSL_ALGO_SHA256);
            $sello9 = base64_encode($firma9);
            $sha9   = hash('sha256', $cadena9);
            $verif9 = openssl_verify($cadena9, $firma9, openssl_pkey_get_public($cerPem), OPENSSL_ALGO_SHA256);
            $resultado[] = 'SHA256: <code>' . $sha9 . '</code><br>';
            $resultado[] = 'Verificación local: ' . ($verif9 === 1 ? '✅ OK' : '❌') . '<br><br>';

            $xmlFinal9 = str_replace('Sello=""', 'Sello="' . $sello9 . '"', $xml);
            $ch9 = curl_init('http://timbrado.demodfacture.com/api/timbrarCFDI40');
            curl_setopt_array($ch9, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'user'     => 'DEMOCortes',
                    'password' => 'cfdi',
                    'xml'      => base64_encode($xmlFinal9),
                ]),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $respRaw9  = curl_exec($ch9);
            $httpCode9 = curl_getinfo($ch9, CURLINFO_HTTP_CODE);
            curl_close($ch9);

            $resultado[] = 'HTTP Code: <b>' . $httpCode9 . '</b><br>';
            $resultado[] = 'Respuesta:<br><pre style="background:#f0fff0;padding:10px;overflow:auto;max-height:400px;">'
                . htmlspecialchars($respRaw9) . '</pre>';
            $resp9 = json_decode($respRaw9, true);
            $cod9  = $resp9['codigo'] ?? '?';
            if (!empty($resp9['uuid'])) {
                $resultado[] = '<br>🎉🎉🎉 <b style="color:green;font-size:16px">TIMBRADO EXITOSO CON || VACÍO! UUID: '
                    . htmlspecialchars($resp9['uuid']) . '</b><br>';
                $resultado[] = '<b style="color:green">→ CONFIRMADO: IDEA15 usa campo VACÍO para TipoDeComprobante en su cadena original.</b><br>';
            } elseif ($cod9 === '506') {
                $resultado[] = '✅ <b style="color:green">506 = previamente timbrado — TIMBRADO FUNCIONÓ con cadena ||</b><br>';
            } elseif ($cod9 === 'CFDI40102') {
                $resultado[] = '<b style="color:red">→ Sigue CFDI40102 con campo vacío también.</b><br>';
                $resultado[] = '→ El error NO es TipoDeComprobante. Contactar a IDEA15 para depuración directa.<br>';
            } else {
                $resultado[] = '→ Código: <b>' . htmlspecialchars($cod9) . '</b> Mensaje: '
                    . htmlspecialchars($resp9['mensaje'] ?? '') . '<br>';
            }

        } catch (\Throwable $e) {
            $resultado[] = '❌ Excepción: ' . htmlspecialchars($e->getMessage()) . '<br>';
        }

        return '<html><body style="font-family:monospace;padding:20px">'
            . implode('', $resultado)
            . '</body></html>';
    }

    /**
     * Genera la cadena original del CFDI 4.0 (copia del método en DfactureService)
     */
    private function generarCadenaOriginal(string $xmlString): string
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xmlString);
        $comp   = $dom->documentElement;
        $campos = [];

        $campos[] = $comp->getAttribute('Version');
        $this->co($campos, $comp, 'Serie');
        $this->co($campos, $comp, 'Folio');
        $campos[] = $comp->getAttribute('Fecha');
        $this->co($campos, $comp, 'FormaPago');
        $campos[] = $comp->getAttribute('NoCertificado');
        $this->co($campos, $comp, 'CondicionesDePago');
        $campos[] = $comp->getAttribute('SubTotal');
        $this->co($campos, $comp, 'Descuento');
        $campos[] = $comp->getAttribute('Moneda');
        $this->co($campos, $comp, 'TipoCambio');
        $campos[] = $comp->getAttribute('Total');
        $campos[] = $comp->getAttribute('TipoDeComprobante');
        $campos[] = $comp->getAttribute('Exportacion');
        $this->co($campos, $comp, 'MetodoPago');
        $campos[] = $comp->getAttribute('LugarExpedicion');
        $this->co($campos, $comp, 'Confirmacion');

        foreach ($comp->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            switch ($child->localName) {
                case 'Emisor':
                    $campos[] = $child->getAttribute('Rfc');
                    $this->co($campos, $child, 'Nombre');
                    $campos[] = $child->getAttribute('RegimenFiscal');
                    $this->co($campos, $child, 'FacAtrAdquirente');
                    break;
                case 'Receptor':
                    $campos[] = $child->getAttribute('Rfc');
                    $this->co($campos, $child, 'Nombre');
                    $this->co($campos, $child, 'DomicilioFiscalReceptor');
                    $this->co($campos, $child, 'ResidenciaFiscal');
                    $this->co($campos, $child, 'NumRegIdTrib');
                    $this->co($campos, $child, 'RegimenFiscalReceptor');
                    $campos[] = $child->getAttribute('UsoCFDI');
                    break;
                case 'Conceptos':
                    foreach ($child->childNodes as $concepto) {
                        if ($concepto->nodeType !== XML_ELEMENT_NODE || $concepto->localName !== 'Concepto') continue;
                        $campos[] = $concepto->getAttribute('ClaveProdServ');
                        $this->co($campos, $concepto, 'NoIdentificacion');
                        $campos[] = $concepto->getAttribute('Cantidad');
                        $campos[] = $concepto->getAttribute('ClaveUnidad');
                        $this->co($campos, $concepto, 'Unidad');
                        $campos[] = $concepto->getAttribute('Descripcion');
                        $campos[] = $concepto->getAttribute('ValorUnitario');
                        $campos[] = $concepto->getAttribute('Importe');
                        $this->co($campos, $concepto, 'Descuento');
                        $campos[] = $concepto->getAttribute('ObjetoImp');
                        foreach ($concepto->childNodes as $impNode) {
                            if ($impNode->nodeType !== XML_ELEMENT_NODE || $impNode->localName !== 'Impuestos') continue;
                            foreach ($impNode->childNodes as $grupo) {
                                if ($grupo->nodeType !== XML_ELEMENT_NODE) continue;
                                foreach ($grupo->childNodes as $item) {
                                    if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                                    $campos[] = $item->getAttribute('Base');
                                    $campos[] = $item->getAttribute('Impuesto');
                                    $campos[] = $item->getAttribute('TipoFactor');
                                    $this->co($campos, $item, 'TasaOCuota');
                                    $this->co($campos, $item, 'Importe');
                                }
                            }
                        }
                    }
                    break;
                case 'Impuestos':
                    // IDEA15 espera: items primero, totales al final
                    foreach ($child->childNodes as $grupo) {
                        if ($grupo->nodeType !== XML_ELEMENT_NODE) continue;
                        foreach ($grupo->childNodes as $item) {
                            if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                            if ($grupo->localName === 'Retenciones') {
                                $campos[] = $item->getAttribute('Impuesto');
                                $campos[] = $item->getAttribute('Importe');
                            } else {
                                $campos[] = $item->getAttribute('Base');
                                $campos[] = $item->getAttribute('Impuesto');
                                $campos[] = $item->getAttribute('TipoFactor');
                                $this->co($campos, $item, 'TasaOCuota');
                                $this->co($campos, $item, 'Importe');
                            }
                        }
                    }
                    $this->co($campos, $child, 'TotalImpuestosRetenidos');
                    $this->co($campos, $child, 'TotalImpuestosTrasladados');
                    break;
            }
        }

        return '||' . implode('|', $campos) . '||';
    }

    private function co(array &$campos, \DOMElement $elem, string $attr): void
    {
        $val = trim($elem->getAttribute($attr));
        if ($val !== '') $campos[] = $val;
    }

    /**
     * Genera cadena con TipoDeComprobante como campo VACÍO (string "").
     * Esto produce || (doble pipe) exactamente como mostró IDEA15 en su email:
     * "599.95||01" — no es ausencia del campo, es campo con valor vacío.
     */
    private function generarCadenaConTipoVacio(string $xmlString): string
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xmlString);
        $comp   = $dom->documentElement;
        $campos = [];

        $campos[] = $comp->getAttribute('Version');
        $this->co($campos, $comp, 'Serie');
        $this->co($campos, $comp, 'Folio');
        $campos[] = $comp->getAttribute('Fecha');
        $this->co($campos, $comp, 'FormaPago');
        $campos[] = $comp->getAttribute('NoCertificado');
        $this->co($campos, $comp, 'CondicionesDePago');
        $campos[] = $comp->getAttribute('SubTotal');
        $this->co($campos, $comp, 'Descuento');
        $campos[] = $comp->getAttribute('Moneda');
        $this->co($campos, $comp, 'TipoCambio');
        $campos[] = $comp->getAttribute('Total');
        // TipoDeComprobante como CAMPO VACÍO → produce || (igual al email de IDEA15)
        $campos[] = '';
        $campos[] = $comp->getAttribute('Exportacion');
        $this->co($campos, $comp, 'MetodoPago');
        $campos[] = $comp->getAttribute('LugarExpedicion');
        $this->co($campos, $comp, 'Confirmacion');

        foreach ($comp->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            switch ($child->localName) {
                case 'Emisor':
                    $campos[] = $child->getAttribute('Rfc');
                    $this->co($campos, $child, 'Nombre');
                    $campos[] = $child->getAttribute('RegimenFiscal');
                    $this->co($campos, $child, 'FacAtrAdquirente');
                    break;
                case 'Receptor':
                    $campos[] = $child->getAttribute('Rfc');
                    $this->co($campos, $child, 'Nombre');
                    $this->co($campos, $child, 'DomicilioFiscalReceptor');
                    $this->co($campos, $child, 'ResidenciaFiscal');
                    $this->co($campos, $child, 'NumRegIdTrib');
                    $this->co($campos, $child, 'RegimenFiscalReceptor');
                    $campos[] = $child->getAttribute('UsoCFDI');
                    break;
                case 'Conceptos':
                    foreach ($child->childNodes as $concepto) {
                        if ($concepto->nodeType !== XML_ELEMENT_NODE || $concepto->localName !== 'Concepto') continue;
                        $campos[] = $concepto->getAttribute('ClaveProdServ');
                        $this->co($campos, $concepto, 'NoIdentificacion');
                        $campos[] = $concepto->getAttribute('Cantidad');
                        $campos[] = $concepto->getAttribute('ClaveUnidad');
                        $this->co($campos, $concepto, 'Unidad');
                        $campos[] = $concepto->getAttribute('Descripcion');
                        $campos[] = $concepto->getAttribute('ValorUnitario');
                        $campos[] = $concepto->getAttribute('Importe');
                        $this->co($campos, $concepto, 'Descuento');
                        $campos[] = $concepto->getAttribute('ObjetoImp');
                        foreach ($concepto->childNodes as $impNode) {
                            if ($impNode->nodeType !== XML_ELEMENT_NODE || $impNode->localName !== 'Impuestos') continue;
                            foreach ($impNode->childNodes as $grupo) {
                                if ($grupo->nodeType !== XML_ELEMENT_NODE) continue;
                                foreach ($grupo->childNodes as $item) {
                                    if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                                    $campos[] = $item->getAttribute('Base');
                                    $campos[] = $item->getAttribute('Impuesto');
                                    $campos[] = $item->getAttribute('TipoFactor');
                                    $this->co($campos, $item, 'TasaOCuota');
                                    $this->co($campos, $item, 'Importe');
                                }
                            }
                        }
                    }
                    break;
                case 'Impuestos':
                    // IDEA15 espera: items primero, totales al final
                    foreach ($child->childNodes as $grupo) {
                        if ($grupo->nodeType !== XML_ELEMENT_NODE) continue;
                        foreach ($grupo->childNodes as $item) {
                            if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                            if ($grupo->localName === 'Retenciones') {
                                $campos[] = $item->getAttribute('Impuesto');
                                $campos[] = $item->getAttribute('Importe');
                            } else {
                                $campos[] = $item->getAttribute('Base');
                                $campos[] = $item->getAttribute('Impuesto');
                                $campos[] = $item->getAttribute('TipoFactor');
                                $this->co($campos, $item, 'TasaOCuota');
                                $this->co($campos, $item, 'Importe');
                            }
                        }
                    }
                    $this->co($campos, $child, 'TotalImpuestosRetenidos');
                    $this->co($campos, $child, 'TotalImpuestosTrasladados');
                    break;
            }
        }

        return '||' . implode('|', $campos) . '||';
    }

    /**
     * Igual que generarCadenaOriginal pero SIN TipoDeComprobante.
     * Sirve para probar si IDEA15 lo omite en su cómputo.
     */
    private function generarCadenaOriginalSinTipo(string $xmlString): string
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xmlString);
        $comp   = $dom->documentElement;
        $campos = [];

        $campos[] = $comp->getAttribute('Version');
        $this->co($campos, $comp, 'Serie');
        $this->co($campos, $comp, 'Folio');
        $campos[] = $comp->getAttribute('Fecha');
        $this->co($campos, $comp, 'FormaPago');
        $campos[] = $comp->getAttribute('NoCertificado');
        $this->co($campos, $comp, 'CondicionesDePago');
        $campos[] = $comp->getAttribute('SubTotal');
        $this->co($campos, $comp, 'Descuento');
        $campos[] = $comp->getAttribute('Moneda');
        $this->co($campos, $comp, 'TipoCambio');
        $campos[] = $comp->getAttribute('Total');
        // ← TipoDeComprobante OMITIDO intencionalmente
        $campos[] = $comp->getAttribute('Exportacion');
        $this->co($campos, $comp, 'MetodoPago');
        $campos[] = $comp->getAttribute('LugarExpedicion');
        $this->co($campos, $comp, 'Confirmacion');

        foreach ($comp->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            switch ($child->localName) {
                case 'Emisor':
                    $campos[] = $child->getAttribute('Rfc');
                    $this->co($campos, $child, 'Nombre');
                    $campos[] = $child->getAttribute('RegimenFiscal');
                    $this->co($campos, $child, 'FacAtrAdquirente');
                    break;
                case 'Receptor':
                    $campos[] = $child->getAttribute('Rfc');
                    $this->co($campos, $child, 'Nombre');
                    $this->co($campos, $child, 'DomicilioFiscalReceptor');
                    $this->co($campos, $child, 'ResidenciaFiscal');
                    $this->co($campos, $child, 'NumRegIdTrib');
                    $this->co($campos, $child, 'RegimenFiscalReceptor');
                    $campos[] = $child->getAttribute('UsoCFDI');
                    break;
                case 'Conceptos':
                    foreach ($child->childNodes as $concepto) {
                        if ($concepto->nodeType !== XML_ELEMENT_NODE || $concepto->localName !== 'Concepto') continue;
                        $campos[] = $concepto->getAttribute('ClaveProdServ');
                        $this->co($campos, $concepto, 'NoIdentificacion');
                        $campos[] = $concepto->getAttribute('Cantidad');
                        $campos[] = $concepto->getAttribute('ClaveUnidad');
                        $this->co($campos, $concepto, 'Unidad');
                        $campos[] = $concepto->getAttribute('Descripcion');
                        $campos[] = $concepto->getAttribute('ValorUnitario');
                        $campos[] = $concepto->getAttribute('Importe');
                        $this->co($campos, $concepto, 'Descuento');
                        $campos[] = $concepto->getAttribute('ObjetoImp');
                        foreach ($concepto->childNodes as $impNode) {
                            if ($impNode->nodeType !== XML_ELEMENT_NODE || $impNode->localName !== 'Impuestos') continue;
                            foreach ($impNode->childNodes as $grupo) {
                                if ($grupo->nodeType !== XML_ELEMENT_NODE) continue;
                                foreach ($grupo->childNodes as $item) {
                                    if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                                    $campos[] = $item->getAttribute('Base');
                                    $campos[] = $item->getAttribute('Impuesto');
                                    $campos[] = $item->getAttribute('TipoFactor');
                                    $this->co($campos, $item, 'TasaOCuota');
                                    $this->co($campos, $item, 'Importe');
                                }
                            }
                        }
                    }
                    break;
                case 'Impuestos':
                    // IDEA15 espera: items primero, totales al final
                    foreach ($child->childNodes as $grupo) {
                        if ($grupo->nodeType !== XML_ELEMENT_NODE) continue;
                        foreach ($grupo->childNodes as $item) {
                            if ($item->nodeType !== XML_ELEMENT_NODE) continue;
                            if ($grupo->localName === 'Retenciones') {
                                $campos[] = $item->getAttribute('Impuesto');
                                $campos[] = $item->getAttribute('Importe');
                            } else {
                                $campos[] = $item->getAttribute('Base');
                                $campos[] = $item->getAttribute('Impuesto');
                                $campos[] = $item->getAttribute('TipoFactor');
                                $this->co($campos, $item, 'TasaOCuota');
                                $this->co($campos, $item, 'Importe');
                            }
                        }
                    }
                    $this->co($campos, $child, 'TotalImpuestosRetenidos');
                    $this->co($campos, $child, 'TotalImpuestosTrasladados');
                    break;
            }
        }

        return '||' . implode('|', $campos) . '||';
    }
}
