<?php

namespace App\Libraries;

/**
 * DfactureService  (IDEA15 / Dfacture REST API)
 *
 * Integración con el servicio de timbrado CFDI 4.0 de IDEA15.
 *
 * Documentación: Manual Servicio de Timbrado Rest (idea15.com)
 * Contacto soporte: integraciones@idea15.com  |  (222) 248-5252 Ext. 205
 *
 * ─── URLs ───────────────────────────────────────────────────────────────
 *   Sandbox    : http://timbrado.demodfacture.com/api/{metodo}
 *   Producción : https://timbrado.dfacture.com/api/{metodo}
 *
 * ─── Métodos usados ──────────────────────────────────────────────────────
 *   timbrarCFDI40  (user, password, xml:base64)
 *   recuperaXML    (user, password, uuid)
 *   CancelarCFDI   (user, password, rfcEmisor, rfcReceptor, total, uuid,
 *                   certificado, llave, password_llave, motivo, uuidRelacionado)
 *
 * ─── Códigos de respuesta relevantes ────────────────────────────────────
 *   100  Comprobante timbrado
 *   101  Usuario inválido
 *   301  XML mal formado
 *   401  Fecha/hora fuera de rango
 *   402  RFC emisor no en LCO
 *   506  Comprobante previamente timbrado
 */
class DfactureService
{
    // ─────────────────────────────────────────────────────────────────────
    // CREDENCIALES DE ACCESO (sandbox — proporcionadas por IDEA15)
    // ─────────────────────────────────────────────────────────────────────

    /** Usuario de timbrado */
    private string $usuario  = 'DEMOCortes';

    /** Contraseña de timbrado */
    private string $password = 'cfdi';

    /**
     * URL base del Web Service.
     * Sandbox : http://timbrado.demodfacture.com/api/
     * Producción: https://timbrado.dfacture.com/api/
     */
    private string $urlBase  = 'http://timbrado.demodfacture.com/api/';

    // ─────────────────────────────────────────────────────────────────────
    // DATOS DEL EMISOR — se cargan automáticamente desde ticket_config
    // ─────────────────────────────────────────────────────────────────────

    private string $rfcEmisor     = 'XAXX010101000';
    private string $nombreEmisor  = 'EMPRESA';
    private string $regimenFiscal = '612'; // Personas Físicas con Actividades Empresariales
    private string $cpEmisor      = '00000';
    private string $serie         = 'A';

    /** Almacena info diagnóstica del último XSLT (para debug temporal) */
    private string $xsltDebugInfo = '';

    /**
     * Mapa de texto de régimen (como se guarda en ticket_config)
     * al código SAT correspondiente.
     */
    private array $mapaRegimen = [
        'general de ley personas morales'          => '601',
        'personas morales con fines no lucrativos' => '603',
        'sueldos y salarios'                       => '605',
        'arrendamiento'                            => '606',
        'régimen de enajenación o adquisición'     => '607',
        'demás ingresos'                           => '608',
        'residentes en el extranjero'              => '610',
        'ingresos por dividendos'                  => '611',
        'personas físicas con actividades'         => '612',
        'ingresos por intereses'                   => '614',
        'sin obligaciones fiscales'                => '616',
        'incorporación fiscal'                     => '621',
        'actividades agrícolas'                    => '622',
        'opcional para grupos de sociedades'       => '623',
        'coordinados'                              => '624',
        'hidrocarburos'                            => '628',
        'simplificado de confianza'                => '626',
    ];

    /**
     * Carga los datos del emisor desde la tabla ticket_config.
     * Se llama automáticamente antes de cada timbrado.
     */
    private function cargarEmisorDesdeDB(): void
    {
        try {
            $db   = \Config\Database::connect();
            $rows = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
            $cfg  = array_column($rows, 'valor', 'clave');

            // RFC — puede venir como "RFC PRO060620G97" o solo "PRO060620G97"
            $rfcRaw = trim($cfg['empresa_rfc'] ?? '');
            $rfcRaw = preg_replace('/^RFC\s*/i', '', $rfcRaw); // quitar prefijo "RFC "
            if ($rfcRaw !== '') {
                $this->rfcEmisor = strtoupper($rfcRaw);
            }

            // Razón social
            $razon = trim($cfg['empresa_razon_social'] ?? '');
            if ($razon !== '') {
                $this->nombreEmisor = strtoupper($razon);
            }

            // CP — se extrae de empresa_ciudad: "Puebla, Pue. C.P. 72420"
            $ciudad = trim($cfg['empresa_ciudad'] ?? '');
            if (preg_match('/C\.?P\.?\s*(\d{5})/i', $ciudad, $m)) {
                $this->cpEmisor = $m[1];
            }

            // Régimen fiscal — mapear texto a código SAT
            $regimenTexto = strtolower(trim($cfg['empresa_regimen'] ?? ''));
            foreach ($this->mapaRegimen as $fragmento => $codigo) {
                if (str_contains($regimenTexto, $fragmento)) {
                    $this->regimenFiscal = $codigo;
                    break;
                }
            }

        } catch (\Throwable $e) {
            log_message('warning', '[DfactureService] No se pudo cargar emisor desde ticket_config: ' . $e->getMessage());
            // Continúa con los valores por defecto
        }
    }


    // ─────────────────────────────────────────────────────────────────────
    // MÉTODO PRINCIPAL: Timbrar una nota de venta
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Construye y timbra el CFDI 4.0 de una nota de venta.
     *
     * @param int   $folio      Folio de la nota
     * @param array $nota       Fila de notas_1 (subTotal, iva, total…)
     * @param array $detalle    Líneas de notas_2 (sku, descripcion, cantidad, precio, importe)
     * @param array $cliente    Fila de clientes (RFC, razonSocial, CP…)
     * @param array $datosExtra Datos capturados en la vista:
     *                          rfcReceptor, razonSocialReceptor, cpReceptor,
     *                          regimenFiscalReceptor, usoCFDI, formaPago, metodoPago
     *
     * @return array [
     *   'success' => bool,
     *   'uuid'    => string,   // UUID del CFDI timbrado
     *   'xml'     => string,   // XML timbrado en base64
     *   'message' => string,   // Mensaje de error si success=false
     * ]
     */
    public function timbrarNota(int $folio, array $nota, array $detalle, array $cliente, array $datosExtra = []): array
    {
        try {
            // 0. Cargar datos del emisor desde ticket_config (siempre frescos)
            $this->cargarEmisorDesdeDB();

            // 1. Construir XML CFDI 4.0
            $xmlRaw = $this->construirCFDI($folio, $nota, $detalle, $cliente, $datosExtra);

            // 2. Sellar con CSD (firma digital SHA-256 + RSA)
            $xmlRaw = $this->sellarXML($xmlRaw);

            // 3. Codificar en Base64 (requerido por el API)
            $xmlB64 = base64_encode($xmlRaw);

            // 4. Enviar al endpoint timbrarCFDI40
            $respuesta = $this->llamarServicio('timbrarCFDI40', [
                'user'     => $this->usuario,
                'password' => $this->password,
                'xml'      => $xmlB64,
            ]);

            // 5. Parsear respuesta
            return $this->parsearRespuestaTimbrado($respuesta);

        } catch (\Throwable $e) {
            log_message('error', '[DfactureService] timbrarNota folio=' . $folio . ' → ' . $e->getMessage());
            return [
                'success' => false,
                'uuid'    => '',
                'xml'     => '',
                'message' => $e->getMessage(),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CONSTRUIR XML CFDI 4.0
    // ─────────────────────────────────────────────────────────────────────

    private function construirCFDI(int $folio, array $nota, array $detalle, array $cliente, array $extras): string
    {
    $fecha = date('Y-m-d\TH:i:s');

    // ── Datos del receptor ──────────────────────────────────────────
    $rfcReceptor    = strtoupper(trim($extras['rfcReceptor']         ?? $cliente['RFC']         ?? 'XAXX010101000'));
    $nombreReceptor = strtoupper(trim($extras['razonSocialReceptor'] ?? $cliente['razonSocial'] ?? $cliente['nombre'] ?? 'PUBLICO EN GENERAL'));
    $cpReceptor     = trim($extras['cpReceptor']                     ?? $cliente['CP']          ?? $this->cpEmisor);
    $regReceptor    = trim($extras['regimenFiscalReceptor']          ?? '616');
    $usoCFDI        = trim($extras['usoCFDI']                        ?? 'S01');
    $formaPago      = trim($extras['formaPago']                      ?? '01');
    $metodoPago     = trim($extras['metodoPago']                     ?? 'PUE');

    if ($rfcReceptor === 'XAXX010101000') {
        $nombreReceptor = 'PUBLICO EN GENERAL';
        $cpReceptor     = $this->cpEmisor;
        $regReceptor    = '616';
        $usoCFDI        = 'S01';
    }

    // ── Conceptos ───────────────────────────────────────────────────
    $xmlConceptos = '';
    $sumaSubtotal = 0.0; 
    $sumaIva      = 0.0; 

    foreach ($detalle as $linea) {
        $cantidad = (int)($linea['cantidad'] ?? 1);
        $precioU  = (float)($linea['precio'] ?? $linea['pUnitario'] ?? 0);
        
        // CORRECCIÓN: Redondear el importe de la línea a 2 decimales desde el inicio
        $importe  = round((float)($linea['importe'] ?? ($cantidad * $precioU)), 2);
        $ivaLinea = round($importe * 0.16, 2);

        $sumaSubtotal += $importe;
        $sumaIva      += $ivaLinea;

        $valorUnitF  = number_format($precioU, 6, '.', '');
        $importeF    = number_format($importe, 2, '.', '');
        $ivaLineaF   = number_format($ivaLinea, 2, '.', '');
        
        $descripcion = htmlspecialchars($linea['descripcion'] ?? $linea['estilo'] ?? $linea['sku'] ?? 'Producto', ENT_XML1 | ENT_QUOTES);
        $sku         = htmlspecialchars($linea['sku'] ?? $linea['estilo'] ?? '', ENT_XML1 | ENT_QUOTES);
        
        // Dinamismo para claves (mantiene tus defaults si no existen)
        $claveProdServ = $linea['claveProdServ'] ?? '01010101';
        $claveUnidad   = $linea['claveUnidad']   ?? 'H87';
        $unidad        = $linea['unidad']        ?? 'PZA';

        $xmlConceptos .= "\n        " . '<cfdi:Concepto' .
            ' ClaveProdServ="' . $claveProdServ . '"' .
            ' NoIdentificacion="' . $sku . '"' .
            ' Cantidad="' . $cantidad . '"' .
            ' ClaveUnidad="' . $claveUnidad . '"' .
            ' Unidad="' . $unidad . '"' .
            ' Descripcion="' . $descripcion . '"' .
            ' ValorUnitario="' . $valorUnitF . '"' .
            ' Importe="' . $importeF . '"' .
            ' ObjetoImp="02">' .
            '<cfdi:Impuestos><cfdi:Traslados>' .
            '<cfdi:Traslado Base="' . $importeF . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $ivaLineaF . '"/>' .
            '</cfdi:Traslados></cfdi:Impuestos>' .
            '</cfdi:Concepto>';
    }

    $subtotalF = number_format($sumaSubtotal, 2, '.', '');
    $ivaF      = number_format($sumaIva,      2, '.', '');
    $totalF    = number_format($sumaSubtotal + $sumaIva, 2, '.', '');

    // ── XML completo CFDI 4.0 ────────────────────────────────────────
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<cfdi:Comprobante' .
        ' xmlns:cfdi="http://www.sat.gob.mx/cfd/4"' .
        ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
        ' xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd"' .
        ' Version="4.0"' .
        ' Serie="' . $this->serie . '"' .
        ' Folio="' . $folio . '"' .
        ' Fecha="' . $fecha . '"' .
        ' Sello=""' .
        ' FormaPago="' . $formaPago . '"' .
        ' NoCertificado=""' .
        ' Certificado=""' .
        ' SubTotal="' . $subtotalF . '"' .
        ' Moneda="MXN"' .
        ' Total="' . $totalF . '"' .
        ' TipoDeComprobante="I"' .
        ' Exportacion="01"' .
        ' MetodoPago="' . $metodoPago . '"' .
        ' LugarExpedicion="' . $this->cpEmisor . '">' . "\n";

    $xml .= '    <cfdi:Emisor' .
        ' Rfc="' . $this->rfcEmisor . '"' .
        ' Nombre="' . htmlspecialchars($this->nombreEmisor, ENT_XML1 | ENT_QUOTES) . '"' .
        ' RegimenFiscal="' . $this->regimenFiscal . '"/>' . "\n";

    $xml .= '    <cfdi:Receptor' .
        ' Rfc="' . $rfcReceptor . '"' .
        ' Nombre="' . htmlspecialchars($nombreReceptor, ENT_XML1 | ENT_QUOTES) . '"' .
        ' DomicilioFiscalReceptor="' . $cpReceptor . '"' .
        ' RegimenFiscalReceptor="' . $regReceptor . '"' .
        ' UsoCFDI="' . $usoCFDI . '"/>' . "\n";

    $xml .= '    <cfdi:Conceptos>' . $xmlConceptos . "\n    </cfdi:Conceptos>\n";

    $xml .= '    <cfdi:Impuestos TotalImpuestosTrasladados="' . $ivaF . '">' . "\n";
    $xml .= '        <cfdi:Traslados>' . "\n";
    $xml .= '            <cfdi:Traslado Base="' . $subtotalF . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $ivaF . '"/>' . "\n";
    $xml .= '        </cfdi:Traslados>' . "\n";
    $xml .= '    </cfdi:Impuestos>' . "\n";
    $xml .= '</cfdi:Comprobante>';

    return $xml;
    }

    // ─────────────────────────────────────────────────────────────────────
    // LLAMADA AL SERVICIO REST
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Ejecuta una petición POST al endpoint de IDEA15/Dfacture.
     * Los parámetros se envían como application/x-www-form-urlencoded.
     */
    private function llamarServicio(string $metodo, array $params = []): array
    {
        $url = rtrim($this->urlBase, '/') . '/' . $metodo;

        $postBody = http_build_query($params);

        $ch = curl_init($url);
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
            throw new \RuntimeException('cURL error al conectar con IDEA15: ' . $curlErr);
        }

        log_message('debug', '[DfactureService] ' . $metodo . ' HTTP=' . $httpCode . ' resp=' . $respRaw);

        // Debug a archivo para diagnóstico
        // Extraer primeros 120 chars del POST body (sin el xml completo) para no llenar el archivo
        $postDebug = preg_replace('/xml=[^&]{100}.*$/', 'xml=<base64 truncado...>', $postBody);
        file_put_contents(
            WRITEPATH . 'cfdi_debug.txt',
            "\n=== IDEA15 " . $metodo . " HTTP=" . $httpCode . " ===\n" .
            "POST body (parcial): " . $postDebug . "\n" .
            "Respuesta: " . $respRaw . "\n",
            FILE_APPEND
        );

        $data = json_decode($respRaw, true);
        if ($data === null) {
            throw new \RuntimeException('Respuesta no-JSON de IDEA15 (' . $httpCode . '): ' . substr($respRaw, 0, 400));
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PARSEAR RESPUESTA DE TIMBRADO
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Interpreta la respuesta de timbrarCFDI40.
     *
     * Campos de salida según el manual:
     *   codigo     → "100" = timbrado OK
     *   validate   → true/false
     *   mensaje    → Mensaje del servicio
     *   resulthash → (interno)
     *   xml        → XML timbrado en Base64
     *   uuid       → Folio fiscal (UUID)
     *   rid        → Identificador respuesta interno
     */
    private function parsearRespuestaTimbrado(array $resp): array
    {
        $codigo  = (string)($resp['codigo']  ?? $resp['Codigo']  ?? '');
        $exitoso = ($codigo === '100') || (!empty($resp['uuid']) && ($resp['validate'] ?? false));

        return [
            'success' => $exitoso,
            'uuid'    => $resp['uuid']    ?? $resp['UUID']    ?? '',
            'xml'     => $resp['xml']     ?? $resp['XML']     ?? '',
            'message' => $resp['mensaje'] ?? $resp['message'] ?? ($exitoso ? 'Comprobante timbrado' : 'Error código ' . $codigo),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // CANCELAR CFDI
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Cancela un CFDI ante el SAT.
     *
     * Requiere archivos CSD (.cer y .key) del emisor en Base64.
     *
     * @param string $uuid           UUID del CFDI a cancelar
     * @param string $rfcReceptor    RFC del receptor del CFDI
     * @param string $total          Total del CFDI (string con 2 decimales)
     * @param string $certificadoB64 Archivo .cer en Base64
     * @param string $llaveB64       Archivo .key en Base64
     * @param string $passwordLlave  Contraseña de la llave privada CSD
     * @param string $motivo         02=Errores sin relación (más común en mostrador)
     *                               01=Con relación → requiere $uuidRelacionado
     * @param string $uuidRelacionado UUID que sustituye (solo motivo 01)
     */
    public function cancelarCFDI(
        string $uuid,
        string $rfcReceptor,
        string $total,
        string $certificadoB64,
        string $llaveB64,
        string $passwordLlave,
        string $motivo = '02',
        string $uuidRelacionado = ''
    ): array {
        try {
            $params = [
                'user'           => $this->usuario,
                'password'       => $this->password,
                'rfcEmisor'      => $this->rfcEmisor,
                'rfcReceptor'    => $rfcReceptor,
                'total'          => $total,
                'uuid'           => $uuid,
                'certificado'    => $certificadoB64,
                'llave'          => $llaveB64,
                'password_llave' => $passwordLlave,
                'motivo'         => $motivo,
                'uuidRelacionado'=> $uuidRelacionado,
            ];

            $resp = $this->llamarServicio('CancelarCFDI', $params);

            // Código 201 = cancelado exitosamente, 202 = previamente cancelado
            $exitoso = in_array((string)($resp['codigo'] ?? ''), ['201', '202']);

            return [
                'success' => $exitoso,
                'message' => $resp['mensaje'] ?? $resp['message'] ?? ('Código ' . ($resp['codigo'] ?? '?')),
            ];

        } catch (\Throwable $e) {
            log_message('error', '[DfactureService] cancelarCFDI uuid=' . $uuid . ' → ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // RECUPERAR XML
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Recupera el XML timbrado por UUID.
     * Útil si se perdió el XML en la BD o para re-envío por correo.
     */
    public function recuperarXML(string $uuid): array
    {
        try {
            $resp = $this->llamarServicio('recuperaXML', [
                'user'     => $this->usuario,
                'password' => $this->password,
                'uuid'     => $uuid,
            ]);

            $exitoso = (string)($resp['codigo'] ?? '') === '800' || !empty($resp['xml']);

            return [
                'success' => $exitoso,
                'xml'     => $resp['xml']     ?? '',
                'message' => $resp['mensaje'] ?? $resp['message'] ?? ('Código ' . ($resp['codigo'] ?? '?')),
            ];

        } catch (\Throwable $e) {
            log_message('error', '[DfactureService] recuperarXML uuid=' . $uuid . ' → ' . $e->getMessage());
            return ['success' => false, 'xml' => '', 'message' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CONSULTAR TIMBRES DISPONIBLES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Consulta cuántos timbres quedan disponibles en la cuenta.
     */
    public function consultarTimbres(): array
    {
        try {
            $resp = $this->llamarServicio('consultaTimbres', [
                'user'     => $this->usuario,
                'password' => $this->password,
            ]);

            return [
                'success' => (string)($resp['codigo'] ?? '') !== '101',
                'timbres' => $resp['timbres'] ?? $resp['mensaje'] ?? '?',
                'message' => $resp['mensaje'] ?? '',
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'timbres' => '?', 'message' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS PÚBLICOS
    // ─────────────────────────────────────────────────────────────────────

    /** Cambiar credenciales en tiempo de ejecución */
    public function setCredenciales(string $usuario, string $password, string $urlBase = ''): void
    {
        $this->usuario  = $usuario;
        $this->password = $password;
        if ($urlBase) {
            $this->urlBase = $urlBase;
        }
    }

    /** Actualizar datos del emisor en tiempo de ejecución */
    public function setEmisor(string $rfc, string $nombre, string $regimen, string $cp): void
    {
        $this->rfcEmisor     = $rfc;
        $this->nombreEmisor  = $nombre;
        $this->regimenFiscal = $regimen;
        $this->cpEmisor      = $cp;
    }

    /** Devuelve el XML sin timbrar (útil para depuración) */
    public function previsualizarXML(int $folio, array $nota, array $detalle, array $cliente, array $extras = []): string
    {
        return $this->construirCFDI($folio, $nota, $detalle, $cliente, $extras);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FIRMA DIGITAL CON CSD (SHA-256 + RSA)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Sella un XML CFDI 4.0 con el CSD del emisor.
     *
     * Proceso:
     *  1. Lee el .cer → extrae NoCertificado (20 dígitos dec) y Certificado (base64)
     *  2. Inserta NoCertificado y Certificado en el XML
     *  3. Genera la cadena original (algoritmo SAT Anexo 20)
     *  4. Firma la cadena con SHA-256 + RSA usando la llave privada .key
     *  5. Inserta el Sello (base64 de la firma) en el XML
     *
     * @param string $xmlString XML construido con Sello="", NoCertificado="", Certificado=""
     * @return string XML con los tres campos llenos
     * @throws \RuntimeException si no se encuentran los archivos CSD o falla la firma
     */
    private function sellarXML(string $xmlString): string
    {
        $cerPath     = APPPATH . 'ThirdParty/csd/csd.cer';
        $keyPath     = APPPATH . 'ThirdParty/csd/csd.key';

        // Leer contraseña del .env (se guarda desde Admin → Certificado SAT)
        // Fallback al valor hardcodeado para no romper entornos sin .env configurado
        $keyPassword = $this->leerEnv('csd.password') ?: '@053124Cortes';

        if (!file_exists($cerPath)) {
            throw new \RuntimeException('CSD .cer no encontrado: ' . $cerPath);
        }
        if (!file_exists($keyPath)) {
            throw new \RuntimeException('CSD .key no encontrado: ' . $keyPath);
        }

        // ── 1. Certificado y NoCertificado ──────────────────────────────
        $cerDer      = file_get_contents($cerPath);
        $certificado = base64_encode($cerDer);

        // Envolver en PEM para que OpenSSL lo pueda parsear
        $cerPem       = "-----BEGIN CERTIFICATE-----\n"
                      . chunk_split(base64_encode($cerDer), 64, "\n")
                      . "-----END CERTIFICATE-----\n";
        $certResource = openssl_x509_read($cerPem);
        if (!$certResource) {
            throw new \RuntimeException('openssl_x509_read falló: ' . openssl_error_string());
        }
        $certData  = openssl_x509_parse($certResource);
        $serialHex = $certData['serialNumberHex'] ?? '';

        // Los certificados del SAT almacenan el número de serie como bytes ASCII
        // de los dígitos decimales (ej: "30 30 30 30 31..." = "00001...").
        // NO se debe convertir hex→decimal; se decodifica byte a byte como ASCII.
        $noCertificado = '';
        for ($i = 0; $i + 1 < strlen($serialHex); $i += 2) {
            $byte = hexdec(substr($serialHex, $i, 2));
            if ($byte >= 0x30 && $byte <= 0x39) {   // solo dígitos ASCII '0'-'9'
                $noCertificado .= chr($byte);
            }
        }
        if ($noCertificado === '') {
            // Fallback por si el formato es diferente
            $noCertificado = (string)($certData['serialNumber'] ?? '0');
        }
        $noCertificado = str_pad($noCertificado, 20, '0', STR_PAD_LEFT);

        // ── 2. Insertar NoCertificado y Certificado en el XML ───────────
        $xmlString = str_replace('NoCertificado=""', 'NoCertificado="' . $noCertificado . '"', $xmlString);
        $xmlString = str_replace('Certificado=""',   'Certificado="'   . $certificado   . '"', $xmlString);

        // ── 3. Cadena original ───────────────────────────────────────────
        // NOTA: IDEA15 usa un orden diferente al XSLT oficial del SAT en la sección
        // global de Impuestos (pone TotalImpuestosTrasladados DESPUÉS de los traslados).
        // Por eso usamos SIEMPRE el método manual, que ya está ajustado al orden de IDEA15.
        $cadenaXslt   = null; // No usar XSLT — orden incompatible con IDEA15
        $cadenaManual = $this->generarCadenaOriginal($xmlString);
        $cadenaOriginal = $cadenaManual;
        $metodo = 'MANUAL (ajustado al orden de IDEA15)';

        // ── DEBUG ────────────────────────────────────────────────────────
        $debugPath   = WRITEPATH . 'cfdi_debug.txt';
        $sha256Cadena = hash('sha256', $cadenaOriginal);
        file_put_contents($debugPath,
            "=== MÉTODO USADO: {$metodo} ===\n" .
            "=== PHP version: " . PHP_VERSION . " ===\n" .
            "=== Extensión xsl cargada: "     . (extension_loaded('xsl')     ? 'SÍ' : 'NO ❌ (habilita extension=xsl en php.ini)') . " ===\n" .
            "=== Extensión openssl cargada: " . (extension_loaded('openssl') ? 'SÍ' : 'NO ❌') . " ===\n" .
            "=== Extensión dom cargada: "     . (extension_loaded('dom')     ? 'SÍ' : 'NO ❌') . " ===\n\n" .
            "=== XML ===\n" . $xmlString .
            "\n\n=== CADENA XSLT ===\n"   . ($cadenaXslt   ?? '(null - XSLT no disponible o falló)') .
            "\n\n=== CADENA MANUAL ===\n" . $cadenaManual .
            "\n\n=== COINCIDEN XSLT y MANUAL: " . ($cadenaXslt !== null && $cadenaXslt === $cadenaManual ? 'SÍ ✓' : 'NO ✗') . " ===\n" .
            "\n=== CADENA QUE SE VA A FIRMAR ===\n" . $cadenaOriginal .
            "\n\n=== SHA256 de la cadena ===\n" . $sha256Cadena . "\n"
        );

        // ── 4. Firma SHA-256 + RSA ───────────────────────────────────────
        $keyDer    = file_get_contents($keyPath);
        $keyB64    = chunk_split(base64_encode($keyDer), 64, "\n");
        $keyPem    = "-----BEGIN ENCRYPTED PRIVATE KEY-----\n{$keyB64}-----END ENCRYPTED PRIVATE KEY-----\n";

        $privateKey = openssl_pkey_get_private($keyPem, $keyPassword);
        if (!$privateKey) {
            throw new \RuntimeException('No se pudo leer la llave CSD: ' . openssl_error_string());
        }

        $firma = '';
        if (!openssl_sign($cadenaOriginal, $firma, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('openssl_sign falló: ' . openssl_error_string());
        }
        $sello = base64_encode($firma);

        // ── Verificación local: la firma debe verificar contra el certificado ─
        $pubKey      = openssl_pkey_get_public($cerPem);
        $verifyLocal = openssl_verify($cadenaOriginal, $firma, $pubKey, OPENSSL_ALGO_SHA256);

        // ── 5. Insertar Sello ────────────────────────────────────────────
        $xmlFinal = str_replace('Sello=""', 'Sello="' . $sello . '"', $xmlString);

        // ── 6. Re-aplicar XSLT al XML FINAL (con el sello insertado) ──────
        // Esto confirma que cuando IDEA15 reciba el XML, el XSLT que aplique
        // sobre él generará la misma cadena que firmamos. Es la verificación
        // definitiva contra el error CFDI40102.
        $cadenaXmlFinal = $this->generarCadenaOriginalXSLT($xmlFinal);
        $coincideXmlFinal = ($cadenaXmlFinal !== null && $cadenaXmlFinal === $cadenaOriginal);

        file_put_contents(WRITEPATH . 'cfdi_debug.txt',
            file_get_contents(WRITEPATH . 'cfdi_debug.txt') .
            "\n=== VERIFICACIÓN LOCAL ===\n" .
            "NoCertificado : " . $noCertificado . "\n" .
            "Verificación firma (openssl_verify): " . ($verifyLocal === 1 ? 'OK ✓' : 'FALLO ✗ (' . $verifyLocal . ')') . "\n" .
            "Sello (64 chars): " . substr($sello, 0, 64) . "...\n" .
            "Cadena XML FINAL coincide con cadena firmada: " . ($coincideXmlFinal ? 'SÍ ✓' : 'NO ✗ (causará CFDI40102)') . "\n" .
            ($cadenaXmlFinal !== null ? "Cadena reconstruida del XML final:\n" . $cadenaXmlFinal . "\n" : "") .
            "\n=== XML FINAL (enviado a IDEA15) ===\n" . $xmlFinal . "\n",
            FILE_APPEND
        );

        // Si la verificación local falla, NO enviamos al PAC — sería CFDI40102 seguro
        if ($verifyLocal !== 1) {
            throw new \RuntimeException('Verificación local del sello falló. El sello NO coincide con la cadena firmada.');
        }

        log_message('debug', '[DfactureService] XML sellado OK — NoCert=' . $noCertificado);
        return $xmlFinal;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CADENA ORIGINAL VÍA XSLT OFICIAL DEL SAT
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera la cadena original aplicando el XSLT del SAT (embebido en app/ThirdParty/csd/).
     * Usa libxml_use_internal_errors para evitar que advertencias de libxml
     * se conviertan en excepciones PHP.
     * Devuelve null si falla, en cuyo caso se usa generarCadenaOriginal() como respaldo.
     */
    private function generarCadenaOriginalXSLT(string $xmlString): ?string
    {
        $xsltPath = APPPATH . 'ThirdParty/csd/cadenaoriginal_4_0.xslt';

        // ── Verificación de pre-requisitos ────────────────────────────────
        if (!extension_loaded('xsl')) {
            log_message('warning', '[DfactureService] Extensión php_xsl NO está cargada. Habilita "extension=xsl" en php.ini.');
            return null;
        }
        if (!class_exists('XSLTProcessor')) {
            log_message('warning', '[DfactureService] Clase XSLTProcessor no existe.');
            return null;
        }
        if (!file_exists($xsltPath) || filesize($xsltPath) < 500) {
            log_message('warning', '[DfactureService] XSLT no encontrado o vacío: ' . $xsltPath);
            return null;
        }

        $prevLibxmlErrors = libxml_use_internal_errors(true);

        try {
            $xmlDoc = new \DOMDocument();
            if (!$xmlDoc->loadXML($xmlString)) {
                log_message('warning', '[DfactureService] No se pudo parsear el XML al aplicar XSLT.');
                return null;
            }

            $xsltDoc = new \DOMDocument();
            if (!$xsltDoc->load($xsltPath)) {
                log_message('warning', '[DfactureService] No se pudo cargar el archivo XSLT.');
                return null;
            }

            $proc = new \XSLTProcessor();
            if (!$proc->importStylesheet($xsltDoc)) {
                log_message('warning', '[DfactureService] importStylesheet falló.');
                return null;
            }

            $resultado = $proc->transformToXML($xmlDoc);

            libxml_use_internal_errors($prevLibxmlErrors);
            libxml_clear_errors();

            if ($resultado === false || trim($resultado) === '') {
                log_message('warning', '[DfactureService] XSLT devolvió vacío.');
                return null;
            }

            // Remover cualquier declaración XML que el procesador haya añadido
            $resultado = preg_replace('/^<\?xml[^?]*\?>\s*/i', '', $resultado);
            $cadena    = trim($resultado);

            // IMPORTANTE: El XSLT oficial del SAT (cadenaoriginal_4_0.xslt) SÍ
            // produce los pipes "||" al inicio y al final por sí mismo. Por
            // eso NO los agregamos aquí. Si los agregáramos, generaríamos
            // "||||...||||" que NO coincide con lo que el PAC reconstruye y
            // resulta en error CFDI40102.
            if ($cadena === '' || substr($cadena, 0, 2) !== '||' || substr($cadena, -2) !== '||') {
                log_message('warning', '[DfactureService] XSLT output inesperado: ' . substr($cadena, 0, 80));
                return null;
            }

            log_message('debug', '[DfactureService] Cadena XSLT OK (' . strlen($cadena) . ' chars)');
            return $cadena;

        } catch (\Throwable $e) {
            libxml_use_internal_errors($prevLibxmlErrors);
            libxml_clear_errors();
            log_message('warning', '[DfactureService] XSLT error: ' . $e->getMessage());
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CADENA ORIGINAL CFDI 4.0  (Anexo 20 del SAT)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera la cadena original del CFDI 4.0 según el algoritmo del SAT.
     * Equivalente a aplicar el XSLT cadenaoriginal_4_0.xslt sobre el XML.
     *
     * Formato: ||campo1|campo2|...|campoN||
     * Solo se incluyen los campos con valor (normalize-space != '').
     */
    private function generarCadenaOriginal(string $xmlString): string
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xmlString);
        $comp   = $dom->documentElement;
        $campos = [];

        // ── Comprobante ──────────────────────────────────────────────────
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

        // ── Hijos del Comprobante ────────────────────────────────────────
        foreach ($comp->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            switch ($child->localName) {

                case 'InformacionGlobal':
                    $campos[] = $child->getAttribute('Periodicidad');
                    $campos[] = $child->getAttribute('Meses');
                    $campos[] = $child->getAttribute('Año');
                    break;

                case 'CfdiRelacionados':
                    $campos[] = $child->getAttribute('TipoRelacion');
                    foreach ($child->childNodes as $rel) {
                        if ($rel->nodeType === XML_ELEMENT_NODE && $rel->localName === 'CfdiRelacionado') {
                            $campos[] = $rel->getAttribute('UUID');
                        }
                    }
                    break;

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
                        if ($concepto->nodeType !== XML_ELEMENT_NODE || $concepto->localName !== 'Concepto') {
                            continue;
                        }
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

                        // Impuestos del concepto
                        foreach ($concepto->childNodes as $impNode) {
                            if ($impNode->nodeType !== XML_ELEMENT_NODE || $impNode->localName !== 'Impuestos') {
                                continue;
                            }
                            foreach ($impNode->childNodes as $grupo) {
                                if ($grupo->nodeType !== XML_ELEMENT_NODE) {
                                    continue;
                                }
                                foreach ($grupo->childNodes as $item) {
                                    if ($item->nodeType !== XML_ELEMENT_NODE) {
                                        continue;
                                    }
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
                            if ($grupo->nodeType !== XML_ELEMENT_NODE) {
                                continue;
                            }
                            foreach ($grupo->childNodes as $item) {
                                if ($item->nodeType !== XML_ELEMENT_NODE) {
                                    continue;
                                }
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
     * Agrega el valor de $attr al array $campos solo si existe y no está vacío.
     * (Equivalente al normalize-space() del XSLT de cadena original)
     */
    private function co(array &$campos, \DOMElement $elem, string $attr): void
    {
        $val = $elem->getAttribute($attr);
        // Simular el normalize-space() del XSLT del SAT
        $val = trim(preg_replace('/\s+/', ' ', $val));
        
        if ($val !== '') {
            $campos[] = $val;
        }
    }

    /**
     * Prueba rápida de credenciales — llama consultaTimbres y devuelve la respuesta raw.
     * Útil para diagnosticar si el error 101 es de auth o de firma.
     */
    public function probarCredenciales(): array
    {
        try {
            $resp = $this->llamarServicio('consultaTimbres', [
                'user'     => $this->usuario,
                'password' => $this->password,
            ]);
            return [
                'success' => true,
                'raw'     => $resp,
                'codigo'  => $resp['codigo'] ?? '?',
                'mensaje' => $resp['mensaje'] ?? '',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'raw' => [], 'codigo' => 'ERR', 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Convierte un número hexadecimal (string) a decimal (string).
     * Usa bcmath para evitar pérdida de precisión en números grandes
     * como los números de serie de certificados SAT (20 dígitos).
     */
    private function hexToDecimal(string $hex): string
    {
        $hex = ltrim(strtolower($hex), '0');
        if ($hex === '') {
            return '0';
        }
        $dec = '0';
        for ($i = 0; $i < strlen($hex); $i++) {
            $dec = bcadd(bcmul($dec, '16'), (string)hexdec($hex[$i]));
        }
        return $dec;
    }

    // ── Lee una clave del .env directamente (funciona en Windows y Linux) ──
    private function leerEnv(string $clave): string
    {
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) return '';
        foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $linea) {
            $trim = trim($linea);
            if ($trim === '' || str_starts_with($trim, '#') || !str_contains($trim, '=')) continue;
            [$k, $v] = explode('=', $trim, 2);
            if (trim($k) === $clave) return trim($v, " \t\r\n'\"");
        }
        return '';
    }
}
