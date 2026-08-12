<?php

namespace App\Libraries;

use App\Libraries\DfactureService;
use App\Libraries\CfdiPdfService;
use App\Libraries\CfdiEmailService;
use App\Libraries\AuditService;

/**
 * FacturacionService
 *
 * Centraliza la lógica de timbrado CFDI + envío de correo para una nota.
 * Puede ser llamado desde Admin y Caja sin duplicar código.
 *
 * Uso:
 *   $svc = new FacturacionService();
 *   $result = $svc->procesar($folio, $datosExtra);
 *
 *   // $datosExtra es opcional; si se omite se usan los datos guardados en notas_1.
 *   // Se requiere cuando el usuario llena los datos en la pantalla modal
 *   // (Escenario 2: cliente no pidió factura al cerrar).
 *
 * Retorna:
 *   ['success' => true,  'uuid' => '...']
 *   ['success' => false, 'message' => '...']
 */
class FacturacionService
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Procesa el timbrado de un folio.
    //
    // $datosExtra (array|null): si viene del modal (Escenario 2) debe incluir:
    //   rfcReceptor, razonSocialReceptor, cpReceptor, usoCFDI,
    //   regimenFiscalReceptor, formaPago, metodoPago
    //
    // Si $datosExtra es null, se usan los campos guardados en notas_1
    // (Escenario 1: ya se llenaron al cerrar la nota).
    // ─────────────────────────────────────────────────────────────────────
    public function procesar(int $folio, ?array $datosExtra = null): array
    {
        $db = $this->db;

        // ── Cargar nota ──────────────────────────────────────────────────
        $nota = $db->query(
            "SELECT * FROM notas_1 WHERE folio = ? LIMIT 1",
            [$folio]
        )->getRowArray();

        if (! $nota) {
            return ['success' => false, 'message' => "Folio #{$folio} no encontrado."];
        }

        // ── Verificar que no esté ya timbrada ────────────────────────────
        if (! empty($nota['uuid_fiscal'])) {
            return ['success' => false, 'message' => "El folio #{$folio} ya fue facturado (UUID: {$nota['uuid_fiscal']})."];
        }

        // ── Resolver datos fiscales ──────────────────────────────────────
        if ($datosExtra !== null) {
            // Escenario 2: datos vienen del modal
            $rfcReceptor           = strtoupper(trim($datosExtra['rfcReceptor']           ?? ''));
            $razonSocialReceptor   = strtoupper(trim($datosExtra['razonSocialReceptor']   ?? ''));
            $cpReceptor            = trim($datosExtra['cpReceptor']                       ?? '');
            $usoCFDI               = trim($datosExtra['usoCFDI']                          ?? 'S01');
            $regimenFiscalReceptor = trim($datosExtra['regimenFiscalReceptor']            ?? '616');
            $formaPagoCFDI         = trim($datosExtra['formaPago']                        ?? '01');
            $metodoPagoCFDI        = trim($datosExtra['metodoPago']                       ?? 'PUE');

            // Guardar/actualizar datos fiscales en notas_1 y en clientes
            $db->query(
                "UPDATE notas_1
                 SET factura = 1,
                     rfc_receptor           = ?,
                     razon_social_receptor  = ?,
                     cp_receptor            = ?,
                     uso_cfdi               = ?,
                     regimen_fiscal_receptor= ?,
                     forma_pago_cfdi        = ?,
                     status_facturacion     = 1
                 WHERE folio = ?",
                [$rfcReceptor, $razonSocialReceptor, $cpReceptor,
                 $usoCFDI, $regimenFiscalReceptor, $formaPagoCFDI, $folio]
            );

            // Actualizar datos fiscales del cliente (RFC, Razón Social, CP)
            // Solo se rellenan campos que estén vacíos o con valor '-'
            $idCliente = (int)($nota['idCliente'] ?? 0);
            if ($idCliente > 0 && ($rfcReceptor || $razonSocialReceptor || $cpReceptor)) {
                $db->query(
                    "UPDATE clientes SET
                        RFC         = IF(RFC         IS NULL OR RFC         = '' OR RFC         = '-', ?, RFC),
                        razonSocial = IF(razonSocial IS NULL OR razonSocial = '' OR razonSocial = '-', ?, razonSocial),
                        CP          = IF(CP          IS NULL OR CP          = '' OR CP          = '-', ?, CP)
                     WHERE id = ?",
                    [$rfcReceptor, $razonSocialReceptor, $cpReceptor, $idCliente]
                );
            }

            // Recargar nota con datos actualizados
            $nota = $db->query("SELECT * FROM notas_1 WHERE folio = ? LIMIT 1", [$folio])->getRowArray();

        } else {
            // Escenario 1: usar datos ya guardados en notas_1
            $rfcReceptor           = $nota['rfc_receptor']           ?? '';
            $razonSocialReceptor   = $nota['razon_social_receptor']  ?? '';
            $cpReceptor            = $nota['cp_receptor']            ?? '';
            $usoCFDI               = $nota['uso_cfdi']               ?? 'S01';
            $regimenFiscalReceptor = $nota['regimen_fiscal_receptor'] ?? '616';
            $formaPagoCFDI         = $nota['forma_pago_cfdi']        ?? '01';
            $metodoPagoCFDI        = 'PUE'; // default; no se guarda separado en notas_1 por ahora
        }

        // ── Validación mínima ────────────────────────────────────────────
        if (empty($rfcReceptor) || empty($razonSocialReceptor) || empty($cpReceptor)) {
            return [
                'success' => false,
                'message' => 'Faltan datos fiscales del receptor (RFC, Razón Social o CP).',
            ];
        }

        // ── Cargar detalle de productos ───────────────────────────────────
        $detalle = $this->cargarDetalleConPrecios($folio, $nota);

        // ── Cargar datos del cliente ──────────────────────────────────────
        $cliente = $db->query(
            "SELECT * FROM clientes WHERE id = ? LIMIT 1",
            [(int)($nota['idCliente'] ?? 0)]
        )->getRowArray() ?? [];

        // ── Timbrar con Dfacture ─────────────────────────────────────────
        try {
            $dfacture = new DfactureService();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al inicializar DfactureService: ' . $e->getMessage()];
        }

        $datosExtraTimbre = [
            'rfcReceptor'           => $rfcReceptor,
            'razonSocialReceptor'   => $razonSocialReceptor,
            'cpReceptor'            => $cpReceptor,
            'usoCFDI'               => $usoCFDI,
            'regimenFiscalReceptor' => $regimenFiscalReceptor,
            'formaPago'             => $formaPagoCFDI,
            'metodoPago'            => $metodoPagoCFDI,
        ];

        try {
            $resultado = $dfacture->timbrarNota($folio, $nota, $detalle, $cliente, $datosExtraTimbre);
        } catch (\Throwable $e) {
            $msg = 'Excepción al timbrar: ' . $e->getMessage();
            $db->query(
                "UPDATE notas_1 SET cfdi_error = ? WHERE folio = ?",
                [substr($msg, 0, 500), $folio]
            );
            return ['success' => false, 'message' => $msg];
        }

        if (! $resultado['success']) {
            $msg = $resultado['message'] ?? 'Error desconocido al timbrar.';
            $db->query(
                "UPDATE notas_1 SET cfdi_error = ? WHERE folio = ?",
                [substr($msg, 0, 500), $folio]
            );
            return ['success' => false, 'message' => $msg];
        }

        // ── Guardar UUID y XML en notas_1 ────────────────────────────────
        $uuid        = $resultado['uuid'];
        $xmlTimbrado = $resultado['xml'];

        $db->query(
            "UPDATE notas_1
             SET uuid_fiscal          = ?,
                 cfdi_xml             = ?,
                 cfdi_fecha_timbrado  = NOW(),
                 cfdi_error           = NULL,
                 status_facturacion   = 2
             WHERE folio = ?",
            [$uuid, $xmlTimbrado, $folio]
        );

        AuditService::log(AuditService::VENTA_CERRADA, 'notas_1', $folio,
            "CFDI timbrado desde Consulta Folios: UUID {$uuid}");

        // ── Enviar correo ─────────────────────────────────────────────────
        try {
            $idCliente     = (int)($nota['idCliente'] ?? 0);
            $correoCliente = '';
            $nombreCliente = $cliente['nombre'] ?? '';

            if ($idCliente > 0) {
                $clienteRow    = $db->query(
                    "SELECT mail, nombre FROM clientes WHERE id = ? LIMIT 1",
                    [$idCliente]
                )->getRowArray();
                $correoCliente = $clienteRow['mail']   ?? '';
                $nombreCliente = $clienteRow['nombre'] ?? $nombreCliente;
            }

            $cfgRows = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
            $cfgMap  = array_column($cfgRows, 'valor', 'clave');

            $xmlDecoded = base64_decode($xmlTimbrado);

            $pdfService = new CfdiPdfService();
            $pdfBytes   = $pdfService->generarPDF($xmlDecoded, $cfgMap);

            $emailService = new CfdiEmailService();
            $emailResult  = $emailService->enviar(
                $xmlDecoded,
                $pdfBytes,
                $uuid,
                (string)$folio,
                $correoCliente,
                $nombreCliente
            );

            if (! $emailResult['success']) {
                log_message('warning', "[FacturacionService] correo folio={$folio}: " . $emailResult['message']);
            }
        } catch (\Throwable $eEmail) {
            // El correo NO debe bloquear: la factura ya está timbrada
            log_message('error', "[FacturacionService] correo folio={$folio}: " . $eEmail->getMessage());
        }

        return ['success' => true, 'uuid' => $uuid];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Vista previa: calcula conceptos/subtotal/IVA/total SIN timbrar y SIN
    // guardar nada en BD. Se usa para mostrarle al usuario la factura antes
    // de que confirme y se llame a Dfacture.
    // ─────────────────────────────────────────────────────────────────────
    public function previsualizar(int $folio, ?array $datosExtra = null): array
    {
        $db = $this->db;

        $nota = $db->query(
            "SELECT * FROM notas_1 WHERE folio = ? LIMIT 1",
            [$folio]
        )->getRowArray();

        if (! $nota) {
            return ['success' => false, 'message' => "Folio #{$folio} no encontrado."];
        }

        if (! empty($nota['uuid_fiscal'])) {
            return ['success' => false, 'message' => "El folio #{$folio} ya fue facturado (UUID: {$nota['uuid_fiscal']})."];
        }

        if ($datosExtra !== null) {
            $rfcReceptor           = strtoupper(trim($datosExtra['rfcReceptor']           ?? ''));
            $razonSocialReceptor   = strtoupper(trim($datosExtra['razonSocialReceptor']   ?? ''));
            $cpReceptor            = trim($datosExtra['cpReceptor']                       ?? '');
            $usoCFDI               = trim($datosExtra['usoCFDI']                          ?? 'S01');
            $regimenFiscalReceptor = trim($datosExtra['regimenFiscalReceptor']            ?? '616');
            $formaPagoCFDI         = trim($datosExtra['formaPago']                        ?? '01');
            $metodoPagoCFDI        = trim($datosExtra['metodoPago']                       ?? 'PUE');
        } else {
            $rfcReceptor           = $nota['rfc_receptor']           ?? '';
            $razonSocialReceptor   = $nota['razon_social_receptor']  ?? '';
            $cpReceptor            = $nota['cp_receptor']            ?? '';
            $usoCFDI               = $nota['uso_cfdi']               ?? 'S01';
            $regimenFiscalReceptor = $nota['regimen_fiscal_receptor'] ?? '616';
            $formaPagoCFDI         = $nota['forma_pago_cfdi']        ?? '01';
            $metodoPagoCFDI        = 'PUE';
        }

        if (empty($rfcReceptor) || empty($razonSocialReceptor) || empty($cpReceptor)) {
            return [
                'success' => false,
                'message' => 'Faltan datos fiscales del receptor (RFC, Razón Social o CP).',
            ];
        }

        $detalle = $this->cargarDetalleConPrecios($folio, $nota);

        try {
            $dfacture = new DfactureService();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al inicializar DfactureService: ' . $e->getMessage()];
        }

        $calculo = $dfacture->previsualizarDatos($nota, $detalle);
        $emisor  = $dfacture->obtenerEmisor();

        // Datos del emisor (para que la vista previa se vea como el PDF final)
        $cfgRows = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
        $cfg     = array_column($cfgRows, 'valor', 'clave');

        return [
            'success'       => true,
            'folio'         => $folio,
            'emisor'        => [
                'razonSocial'   => $cfg['empresa_razon_social'] ?? '',
                'rfc'           => preg_replace('/^RFC\s*/i', '', trim($cfg['empresa_rfc'] ?? '')),
                'direccion'     => $cfg['empresa_sucursal'] ?? '',
                'ciudad'        => $cfg['empresa_ciudad']   ?? '',
                'regimen'       => $emisor['regimen'],
                'regimenTexto'  => $cfg['empresa_regimen'] ?? '',
                'cp'            => $emisor['cp'],
            ],
            'datosFiscales' => [
                'rfcReceptor'           => $rfcReceptor,
                'razonSocialReceptor'   => $razonSocialReceptor,
                'cpReceptor'            => $cpReceptor,
                'usoCFDI'               => $usoCFDI,
                'regimenFiscalReceptor' => $regimenFiscalReceptor,
                'formaPago'             => $formaPagoCFDI,
                'formaPagoTexto'        => CfdiPdfService::nombreFormaPago($formaPagoCFDI),
                'metodoPago'            => $metodoPagoCFDI,
            ],
            'conceptos' => $calculo['conceptos'],
            'subtotal'  => $calculo['subtotal'],
            'iva'       => $calculo['iva'],
            'total'     => $calculo['total'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Carga el detalle de productos de un folio y resuelve precio/importe
    // por línea según si la nota fue a precio mayoreo o menudeo.
    // Usado tanto por procesar() como por previsualizar().
    // ─────────────────────────────────────────────────────────────────────
    private function cargarDetalleConPrecios(int $folio, array $nota): array
    {
        $db = $this->db;

        $detalle = $db->query(
            "SELECT n2.cantidad,
                    n2.estilo AS sku,
                    CONCAT(COALESCE(p.estilo,''),'-',COALESCE(p.Descripcion_Larga,''),
                           '-',COALESCE(p.Talla,''),'-',COALESCE(p.Color,'')) AS descripcion,
                    n2.pUnitario,
                    n2.pUnitarioM,
                    (n2.cantidad * n2.pUnitario)  AS importeMenudeo,
                    (n2.cantidad * n2.pUnitarioM) AS importeMayoreo
             FROM notas_2 n2
             LEFT JOIN productosyazbek p ON p.sku = n2.estilo
             WHERE n2.folio = ?
             ORDER BY n2.Id_Notas_2 ASC",
            [$folio]
        )->getResultArray();

        // Detectar mayoreo/menudeo
        $storedSuma = (float)($nota['sumaImportes'] ?? 0);
        if ($storedSuma > 0) {
            $sumMenu = array_sum(array_map(fn($d) => $d['cantidad'] * (float)$d['pUnitario'],  $detalle));
            $sumMay  = array_sum(array_map(fn($d) => $d['cantidad'] * (float)($d['pUnitarioM'] ?: $d['pUnitario']), $detalle));
            $esMayoreo = abs($sumMay - $storedSuma) < abs($sumMenu - $storedSuma);
        } else {
            $esMayoreo = (int)($nota['precioMayoreo'] ?? 0) === 1;
        }
        foreach ($detalle as &$linea) {
            $usarMay = $esMayoreo && !empty($linea['pUnitarioM']) && (float)$linea['pUnitarioM'] > 0;
            $linea['precio']  = $usarMay ? (float)$linea['pUnitarioM']    : (float)$linea['pUnitario'];
            $linea['importe'] = $usarMay ? (float)$linea['importeMayoreo'] : (float)$linea['importeMenudeo'];
        }
        unset($linea);

        return $detalle;
    }
}
