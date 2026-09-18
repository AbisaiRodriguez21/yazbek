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

        // ── Candado padre/hijo: o factura completa, o por abonos, no ambas ─
        if ($err = $this->validarExclusionPadreHijo($nota)) {
            return ['success' => false, 'message' => $err];
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
            $observaciones         = trim($datosExtra['observaciones']                    ?? '');

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
                     observaciones_factura  = ?,
                     status_facturacion     = 1
                 WHERE folio = ?",
                [$rfcReceptor, $razonSocialReceptor, $cpReceptor,
                 $usoCFDI, $regimenFiscalReceptor, $formaPagoCFDI, $observaciones ?: null, $folio]
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
            // Se recalcula contra los pagos reales (montosnotas) en vez de
            // confiar en el valor guardado en la nota: si Caja registró un
            // pago distinto al que declaró el vendedor al cerrar, el guardado
            // queda desactualizado y la factura saldría con la forma de pago
            // equivocada.
            $formaPagoCFDI         = $this->calcularFormaPagoDominante($folio) ?? ($nota['forma_pago_cfdi'] ?? '01');
            $metodoPagoCFDI        = 'PUE'; // default; no se guarda separado en notas_1 por ahora
            $observaciones         = trim($nota['observaciones_factura'] ?? '');
        }

        // ── Validación mínima ────────────────────────────────────────────
        if (empty($rfcReceptor) || empty($razonSocialReceptor) || empty($cpReceptor)) {
            return [
                'success' => false,
                'message' => 'Faltan datos fiscales del receptor (RFC, Razón Social o CP).',
            ];
        }

        // ── Cargar detalle de productos (o concepto de abono si es folio hijo) ─
        // La forma y el método de pago se toman tal cual los eligió el usuario
        // en el modal (se sugieren PUE + forma real del abono, pero son editables).
        [$detalle, $nota] = $this->prepararDetalleYNota($folio, $nota);

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
            AuditService::log(AuditService::FACTURA_ERROR, 'notas_1', $folio,
                "Error al timbrar folio #{$folio} (RFC {$rfcReceptor}): " . substr($msg, 0, 200),
                null,
                ['rfc' => $rfcReceptor, 'forma_pago' => $formaPagoCFDI, 'metodo_pago' => $metodoPagoCFDI, 'error' => substr($msg, 0, 300)]);
            return ['success' => false, 'message' => $msg];
        }

        if (! $resultado['success']) {
            $msg = $resultado['message'] ?? 'Error desconocido al timbrar.';
            $db->query(
                "UPDATE notas_1 SET cfdi_error = ? WHERE folio = ?",
                [substr($msg, 0, 500), $folio]
            );
            AuditService::log(AuditService::FACTURA_ERROR, 'notas_1', $folio,
                "Timbrado rechazado folio #{$folio} (RFC {$rfcReceptor}): " . substr($msg, 0, 200),
                null,
                ['rfc' => $rfcReceptor, 'forma_pago' => $formaPagoCFDI, 'metodo_pago' => $metodoPagoCFDI, 'error' => substr($msg, 0, 300)]);
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

        // ── Enviar correo ─────────────────────────────────────────────────
        $esAbono       = (int)($nota['referencia'] ?? 0) > 0;
        $folioPadre    = (int)($nota['referencia'] ?? 0);
        $totalFactura  = (float)($nota['total'] ?? 0);
        $correoCliente = '';
        $correoEnviado = false;
        $correoMsg     = 'No se intentó enviar (sin correo del cliente).';

        try {
            $idCliente     = (int)($nota['idCliente'] ?? 0);
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
            $pdfBytes   = $pdfService->generarPDF($xmlDecoded, $cfgMap, $observaciones ?? '');

            $emailService = new CfdiEmailService();
            $emailResult  = $emailService->enviar(
                $xmlDecoded,
                $pdfBytes,
                $uuid,
                (string)$folio,
                $correoCliente,
                $nombreCliente
            );

            $correoEnviado = (bool)($emailResult['success'] ?? false);
            $correoMsg     = (string)($emailResult['message'] ?? ($correoEnviado ? 'Enviado' : 'Falló'));

            if (! $correoEnviado) {
                log_message('warning', "[FacturacionService] correo folio={$folio}: " . $correoMsg);
            }
        } catch (\Throwable $eEmail) {
            // El correo NO debe bloquear: la factura ya está timbrada
            $correoMsg = $eEmail->getMessage();
            log_message('error', "[FacturacionService] correo folio={$folio}: " . $correoMsg);
        }

        // ── Auditoría detallada de la factura ─────────────────────────────
        $correoTxt = $correoCliente !== '' ? $correoCliente : '(sin correo)';
        $tipoTxt   = $esAbono ? "abono del folio #{$folioPadre}" : "venta completa";
        AuditService::log(AuditService::FACTURA_EMITIDA, 'notas_1', $folio,
            "Factura ({$tipoTxt}) del folio #{$folio} timbrada. UUID {$uuid}. "
            . "Receptor {$rfcReceptor} ({$razonSocialReceptor}). Total $" . number_format($totalFactura, 2) . ". "
            . "Forma {$formaPagoCFDI} / Método {$metodoPagoCFDI}. "
            . "Correo -> {$correoTxt}: " . ($correoEnviado ? 'ENVIADO' : 'NO enviado'),
            null,
            [
                'uuid'          => $uuid,
                'tipo'          => $esAbono ? 'abono' : 'venta_completa',
                'folio_padre'   => $esAbono ? $folioPadre : null,
                'rfc_receptor'  => $rfcReceptor,
                'razon_social'  => $razonSocialReceptor,
                'uso_cfdi'      => $usoCFDI,
                'total'         => round($totalFactura, 2),
                'forma_pago'    => $formaPagoCFDI,
                'metodo_pago'   => $metodoPagoCFDI,
                'correo_destino'=> $correoCliente,
                'correo_enviado'=> $correoEnviado,
                'correo_detalle'=> $correoMsg,
            ]);

        // Log específico del correo (para poder filtrar envíos fallidos)
        AuditService::log(
            $correoEnviado ? AuditService::FACTURA_CORREO_OK : AuditService::FACTURA_CORREO_FALLIDO,
            'notas_1', $folio,
            ($correoEnviado
                ? "Factura del folio #{$folio} (UUID {$uuid}) enviada a {$correoTxt}"
                : "No se envió la factura del folio #{$folio} (UUID {$uuid}) a {$correoTxt}: {$correoMsg}"));

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

        if ($err = $this->validarExclusionPadreHijo($nota)) {
            return ['success' => false, 'message' => $err];
        }

        if ($datosExtra !== null) {
            $rfcReceptor           = strtoupper(trim($datosExtra['rfcReceptor']           ?? ''));
            $razonSocialReceptor   = strtoupper(trim($datosExtra['razonSocialReceptor']   ?? ''));
            $cpReceptor            = trim($datosExtra['cpReceptor']                       ?? '');
            $usoCFDI               = trim($datosExtra['usoCFDI']                          ?? 'S01');
            $regimenFiscalReceptor = trim($datosExtra['regimenFiscalReceptor']            ?? '616');
            $formaPagoCFDI         = trim($datosExtra['formaPago']                        ?? '01');
            $metodoPagoCFDI        = trim($datosExtra['metodoPago']                       ?? 'PUE');
            $observaciones         = trim($datosExtra['observaciones']                    ?? '');
        } else {
            $rfcReceptor           = $nota['rfc_receptor']           ?? '';
            $razonSocialReceptor   = $nota['razon_social_receptor']  ?? '';
            $cpReceptor            = $nota['cp_receptor']            ?? '';
            $usoCFDI               = $nota['uso_cfdi']               ?? 'S01';
            $regimenFiscalReceptor = $nota['regimen_fiscal_receptor'] ?? '616';
            $formaPagoCFDI         = $this->calcularFormaPagoDominante($folio) ?? ($nota['forma_pago_cfdi'] ?? '01');
            $metodoPagoCFDI        = 'PUE';
            $observaciones         = trim($nota['observaciones_factura'] ?? '');
        }

        if (empty($rfcReceptor) || empty($razonSocialReceptor) || empty($cpReceptor)) {
            return [
                'success' => false,
                'message' => 'Faltan datos fiscales del receptor (RFC, Razón Social o CP).',
            ];
        }

        [$detalle, $nota] = $this->prepararDetalleYNota($folio, $nota);

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
            'conceptos'     => $calculo['conceptos'],
            'subtotal'      => $calculo['subtotal'],
            'descuento'     => $calculo['descuento'],
            'iva'           => $calculo['iva'],
            'total'         => $calculo['total'],
            'observaciones' => $observaciones,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Forma de pago que predomina por monto entre lo realmente cobrado
    // (montosnotas), no lo que se haya guardado antes en la nota. Copia de
    // BaseController::calcularFormaPagoDominante() — aquí no se puede
    // heredar de un controlador, así que se repite la misma lógica.
    // ─────────────────────────────────────────────────────────────────────
    private function calcularFormaPagoDominante(int $folio): ?string
    {
        $mapaSat = [
            1  => '01', // Contado (Efectivo)
            4  => '02', // Cheque
            5  => '03', // Transferencia
            6  => '03', // Depósito (el SAT no tiene clave propia; se usa Transferencia)
            7  => '99', // Sin Pagar — no debería facturarse así, pero por seguridad
            8  => '04', // Cargo con tarjeta (genérico, se usa muy poco)
            9  => '28', // Tarjeta Débito
            10 => '04', // Tarjeta Crédito
        ];

        $row = $this->db->query(
            "SELECT mn.idTipoPago, SUM(mn.monto) AS total
             FROM notas_1 n
             INNER JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             WHERE (n.folio = ? OR n.referencia = ?) AND n.status != 3
             GROUP BY mn.idTipoPago
             ORDER BY total DESC
             LIMIT 1",
            [$folio, $folio]
        )->getRowArray();

        if (! $row) {
            return null;
        }

        return $mapaSat[(int) $row['idTipoPago']] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Carga el detalle de productos de un folio y resuelve precio/importe
    // por línea según si la nota fue a precio mayoreo o menudeo.
    // Usado tanto por procesar() como por previsualizar().
    // ─────────────────────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────
    // Candado de exclusión padre/hijo. Regla: una venta a crédito se factura
    // POR ABONO (cada hijo) O COMPLETA (el padre en una sola), nunca ambas.
    //   · Hijo  → bloqueado si el PADRE ya se facturó completo.
    //   · Padre → bloqueado si ALGÚN hijo (abono) ya se facturó.
    // Devuelve el mensaje de error, o null si se puede facturar.
    // ─────────────────────────────────────────────────────────────────────
    private function validarExclusionPadreHijo(array $nota): ?string
    {
        $db         = $this->db;
        $folioPadre = (int)($nota['referencia'] ?? 0);

        if ($folioPadre > 0) {
            // Es un folio hijo (abono)
            $p = $db->query(
                "SELECT COALESCE(uuid_fiscal, '') AS u FROM notas_1 WHERE folio = ? LIMIT 1",
                [$folioPadre]
            )->getRowArray();
            if (! empty($p['u'])) {
                return "La nota #{$folioPadre} ya fue facturada completa; sus abonos no se facturan por separado.";
            }
        } else {
            // Es el folio padre
            $folio = (int)($nota['folio'] ?? 0);
            $h = $db->query(
                "SELECT COUNT(*) AS c FROM notas_1
                  WHERE referencia = ? AND status != 3 AND COALESCE(uuid_fiscal, '') <> ''",
                [$folio]
            )->getRowArray();
            if ((int)($h['c'] ?? 0) > 0) {
                return "Esta nota ya tiene abonos facturados; se factura por abono, no en una sola factura.";
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Decide qué se factura según el folio:
    //   · Folio PADRE  → los productos de la nota (comportamiento normal).
    //   · Folio HIJO   → un solo concepto por el MONTO DEL ABONO de ese hijo,
    //                    sin necesidad de que el padre esté liquidado.
    // Devuelve [detalle, nota] — la nota puede venir ajustada al monto del abono.
    // ─────────────────────────────────────────────────────────────────────
    private function prepararDetalleYNota(int $folio, array $nota): array
    {
        $esHijo = (int)($nota['referencia'] ?? 0) > 0;
        if (! $esHijo) {
            return [$this->cargarDetalleConPrecios($folio, $nota), $nota];
        }
        return $this->detalleAbonoHijo($nota);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Construye un concepto único que representa el ABONO de un folio hijo,
    // extrayendo el IVA (16%) del monto pagado. Ajusta los totales de la nota
    // al monto del abono (sin descuentos ni cargos por impresión).
    //
    // ⚠️ FISCAL: el concepto usa ClaveProdServ 84111506 (abono/anticipo) y
    // ClaveUnidad ACT. Confirma con tu contador que esta forma de facturar
    // por abono es la correcta para tu operación (vs. factura PPD + REP).
    // ─────────────────────────────────────────────────────────────────────
    private function detalleAbonoHijo(array $nota): array
    {
        $db     = $this->db;
        $idNota = (int)($nota['Id_Notas_1'] ?? 0);

        // Monto realmente confirmado del abono (pagos registrados en este hijo)
        $row   = $db->query(
            "SELECT COALESCE(SUM(monto), 0) AS abono FROM montosnotas WHERE idNotas = ?",
            [$idNota]
        )->getRowArray();
        $abono = round((float)($row['abono'] ?? 0), 2);
        if ($abono <= 0) {
            $abono = round((float)($nota['total'] ?? 0), 2);
        }

        // Extraer IVA del monto pagado (el abono incluye IVA)
        $base = round($abono / 1.16, 2);
        $iva  = round($abono - $base, 2);

        $folioPadre = (int)($nota['referencia'] ?? 0);

        $detalle = [[
            'cantidad'      => 1,
            'sku'           => 'ABONO',
            'estilo'        => 'ABONO',
            'descripcion'   => 'Abono a cuenta de la nota #' . $folioPadre,
            'pUnitario'     => $base,
            'pUnitarioM'    => $base,
            'precio'        => $base,
            'importe'       => $base,
            'baseIva'       => $base,
            'iva'           => $iva,
            'claveProdServ' => '84111506',   // Servicios de facturación / abono-anticipo
            'claveUnidad'   => 'ACT',        // Actividad
            'unidad'        => 'Actividad',
        ]];

        // Ajustar la nota al monto del abono para que el CFDI cuadre exactamente
        $nota['subTotal']          = $base;
        $nota['sumaImportes']      = $base;
        $nota['iva']               = $iva;
        $nota['total']             = $abono;
        $nota['descuento']         = 0;
        $nota['cargoPorImpresion'] = 0;
        $nota['precioMayoreo']     = 0;

        return [$detalle, $nota];
    }

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
