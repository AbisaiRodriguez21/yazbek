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

        // ── Candado padre/hijo: un abono (hijo) necesita que el padre ya
        // esté facturado, para poder generar su REP contra ese UUID ──────
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

            // Un folio PADRE "Sin Pagar" (crédito) siempre se factura 99/PPD,
            // sin importar lo que haya mandado el modal — evita que una
            // pantalla que aún no conozca esta regla (p. ej. un modal viejo o
            // uno nuevo que se agregue después) facture mal una venta a
            // crédito.
            if ($this->esFolioPadreSinPagar($nota)) {
                $formaPagoCFDI  = '99';
                $metodoPagoCFDI = 'PPD';
            }

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
            // Excepción: un folio PADRE "Sin Pagar" (crédito) siempre se
            // factura 99/PPD, sin importar lo ya cobrado en sus abonos — así
            // es como el SAT espera una venta a crédito aún no liquidada.
            if ($this->esFolioPadreSinPagar($nota)) {
                $formaPagoCFDI  = '99';
                $metodoPagoCFDI = 'PPD';
            } else {
                $formaPagoCFDI  = $this->calcularFormaPagoDominante($folio) ?? ($nota['forma_pago_cfdi'] ?? '01');
                $metodoPagoCFDI = 'PUE';
            }
            $observaciones         = trim($nota['observaciones_factura'] ?? '');
        }

        // ── Validación mínima ────────────────────────────────────────────
        if (empty($rfcReceptor) || empty($razonSocialReceptor) || empty($cpReceptor)) {
            return [
                'success' => false,
                'message' => 'Faltan datos fiscales del receptor (RFC, Razón Social o CP).',
            ];
        }

        $esHijo = (int)($nota['referencia'] ?? 0) > 0;

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

        $repInfo = null;

        if ($esHijo) {
            // ── Folio HIJO (abono): se timbra como REP (Recepción de Pago),
            // apuntando al UUID del folio padre — no es una venta nueva. ──
            $repInfo = $this->prepararDatosRep($nota);

            $datosPago = [
                'folioHijo'             => $folio,
                'uuidPadre'             => $repInfo['uuidPadre'],
                'folioPadre'            => $repInfo['folioPadre'],
                'montoPago'             => $repInfo['montoPago'],
                'saldoAnterior'         => $repInfo['saldoAnterior'],
                'numParcialidad'        => $repInfo['numParcialidad'],
                'formaDePagoP'          => $repInfo['formaDePagoP'],
                'rfcReceptor'           => $rfcReceptor,
                'razonSocialReceptor'   => $razonSocialReceptor,
                'cpReceptor'            => $cpReceptor,
                'regimenFiscalReceptor' => $regimenFiscalReceptor,
            ];

            try {
                $resultado = $dfacture->timbrarREP($datosPago);
            } catch (\Throwable $e) {
                $msg = 'Excepción al timbrar REP: ' . $e->getMessage();
                $db->query(
                    "UPDATE notas_1 SET cfdi_error = ? WHERE folio = ?",
                    [substr($msg, 0, 500), $folio]
                );
                AuditService::log(AuditService::FACTURA_ERROR, 'notas_1', $folio,
                    "Error al timbrar REP folio #{$folio} (padre #{$repInfo['folioPadre']}): " . substr($msg, 0, 200),
                    null,
                    ['rfc' => $rfcReceptor, 'forma_de_pago_p' => $repInfo['formaDePagoP'], 'error' => substr($msg, 0, 300)]);
                return ['success' => false, 'message' => $msg];
            }
        } else {
            $detalle = $this->cargarDetalleConPrecios($folio, $nota);

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
                $repInfo !== null
                    ? ['rfc' => $rfcReceptor, 'forma_de_pago_p' => $repInfo['formaDePagoP'], 'error' => substr($msg, 0, 300)]
                    : ['rfc' => $rfcReceptor, 'forma_pago' => $formaPagoCFDI, 'metodo_pago' => $metodoPagoCFDI, 'error' => substr($msg, 0, 300)]);
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
        $correoTxt   = $correoCliente !== '' ? $correoCliente : '(sin correo)';
        $tipoTxt     = $esAbono ? "REP — abono del folio #{$folioPadre}" : "venta completa";
        $formaTxt    = $repInfo !== null
            ? "Forma de pago del abono: {$repInfo['formaDePagoP']} (parcialidad {$repInfo['numParcialidad']})"
            : "Forma {$formaPagoCFDI} / Método {$metodoPagoCFDI}";
        AuditService::log(AuditService::FACTURA_EMITIDA, 'notas_1', $folio,
            "Factura ({$tipoTxt}) del folio #{$folio} timbrada. UUID {$uuid}. "
            . "Receptor {$rfcReceptor} ({$razonSocialReceptor}). Total $" . number_format($totalFactura, 2) . ". "
            . "{$formaTxt}. "
            . "Correo -> {$correoTxt}: " . ($correoEnviado ? 'ENVIADO' : 'NO enviado'),
            null,
            [
                'uuid'          => $uuid,
                'tipo'          => $esAbono ? 'rep' : 'venta_completa',
                'folio_padre'   => $esAbono ? $folioPadre : null,
                'rfc_receptor'  => $rfcReceptor,
                'razon_social'  => $razonSocialReceptor,
                'uso_cfdi'      => $usoCFDI,
                'total'         => round($totalFactura, 2),
                'forma_pago'    => $repInfo !== null ? $repInfo['formaDePagoP'] : $formaPagoCFDI,
                'metodo_pago'   => $repInfo !== null ? null : $metodoPagoCFDI,
                'num_parcialidad' => $repInfo['numParcialidad'] ?? null,
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

            // Un folio PADRE "Sin Pagar" (crédito) siempre se factura 99/PPD,
            // sin importar lo que haya mandado el modal.
            if ($this->esFolioPadreSinPagar($nota)) {
                $formaPagoCFDI  = '99';
                $metodoPagoCFDI = 'PPD';
            }
        } else {
            $rfcReceptor           = $nota['rfc_receptor']           ?? '';
            $razonSocialReceptor   = $nota['razon_social_receptor']  ?? '';
            $cpReceptor            = $nota['cp_receptor']            ?? '';
            $usoCFDI               = $nota['uso_cfdi']               ?? 'S01';
            $regimenFiscalReceptor = $nota['regimen_fiscal_receptor'] ?? '616';
            if ($this->esFolioPadreSinPagar($nota)) {
                $formaPagoCFDI  = '99';
                $metodoPagoCFDI = 'PPD';
            } else {
                $formaPagoCFDI  = $this->calcularFormaPagoDominante($folio) ?? ($nota['forma_pago_cfdi'] ?? '01');
                $metodoPagoCFDI = 'PUE';
            }
            $observaciones         = trim($nota['observaciones_factura'] ?? '');
        }

        if (empty($rfcReceptor) || empty($razonSocialReceptor) || empty($cpReceptor)) {
            return [
                'success' => false,
                'message' => 'Faltan datos fiscales del receptor (RFC, Razón Social o CP).',
            ];
        }

        try {
            $dfacture = new DfactureService();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al inicializar DfactureService: ' . $e->getMessage()];
        }

        // Datos del emisor (para que la vista previa se vea como el PDF final)
        $cfgRows = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
        $cfg     = array_column($cfgRows, 'valor', 'clave');
        $emisor  = $dfacture->obtenerEmisor();
        $emisorInfo = [
            'razonSocial'   => $cfg['empresa_razon_social'] ?? '',
            'rfc'           => preg_replace('/^RFC\s*/i', '', trim($cfg['empresa_rfc'] ?? '')),
            'direccion'     => $cfg['empresa_sucursal'] ?? '',
            'ciudad'        => $cfg['empresa_ciudad']   ?? '',
            'regimen'       => $emisor['regimen'],
            'regimenTexto'  => $cfg['empresa_regimen'] ?? '',
            'cp'            => $emisor['cp'],
        ];
        $datosFiscalesInfo = [
            'rfcReceptor'           => $rfcReceptor,
            'razonSocialReceptor'   => $razonSocialReceptor,
            'cpReceptor'            => $cpReceptor,
            'usoCFDI'               => $usoCFDI,
            'regimenFiscalReceptor' => $regimenFiscalReceptor,
        ];

        $esHijo = (int)($nota['referencia'] ?? 0) > 0;

        if ($esHijo) {
            // ── Vista previa de REP (folio hijo / abono) ──────────────────
            $rep = $this->prepararDatosRep($nota);

            return [
                'success'       => true,
                'tipo'          => 'rep',
                'folio'         => $folio,
                'emisor'        => $emisorInfo,
                'datosFiscales' => $datosFiscalesInfo,
                'rep'           => [
                    'folioPadre'     => $rep['folioPadre'],
                    'uuidPadre'      => $rep['uuidPadre'],
                    'montoPago'      => $rep['montoPago'],
                    'saldoAnterior'  => $rep['saldoAnterior'],
                    'saldoInsoluto'  => max(0.0, round($rep['saldoAnterior'] - $rep['montoPago'], 2)),
                    'numParcialidad' => $rep['numParcialidad'],
                    'formaDePagoP'   => $rep['formaDePagoP'],
                    'formaDePagoPTexto' => CfdiPdfService::nombreFormaPago($rep['formaDePagoP']),
                ],
                'observaciones' => $observaciones,
            ];
        }

        // ── Vista previa de factura de Ingreso (folio padre) ──────────────
        $detalle = $this->cargarDetalleConPrecios($folio, $nota);
        $calculo = $dfacture->previsualizarDatos($nota, $detalle);

        return [
            'success'       => true,
            'tipo'          => 'ingreso',
            'folio'         => $folio,
            'emisor'        => $emisorInfo,
            'datosFiscales' => $datosFiscalesInfo + [
                'formaPago'      => $formaPagoCFDI,
                'formaPagoTexto' => CfdiPdfService::nombreFormaPago($formaPagoCFDI),
                'metodoPago'     => $metodoPagoCFDI,
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

    // tipopago.id = 7 → "Sin Pagar" (crédito). Mismo id usado en el mapa de
    // calcularFormaPagoDominante() y en BaseController::esFolioPadreSinPagar().
    private const TIPO_PAGO_SIN_PAGAR_ID = 7;

    // ─────────────────────────────────────────────────────────────────────
    // Un folio PADRE (no un folio hijo/abono) cuyo propio tipoPago es "Sin
    // Pagar" (crédito) debe facturarse con FormaPago=99 (Por definir) y
    // MetodoPago=PPD (Pago en Parcialidades o Diferido) — así es como el SAT
    // espera una venta a crédito que todavía no se liquida por completo,
    // sin importar qué se haya cobrado ya en sus abonos.
    // ─────────────────────────────────────────────────────────────────────
    private function esFolioPadreSinPagar(array $nota): bool
    {
        $esHijo = (int)($nota['referencia'] ?? 0) > 0;
        if ($esHijo) {
            return false;
        }
        return (int)($nota['tipoPago'] ?? 0) === self::TIPO_PAGO_SIN_PAGAR_ID;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Candado padre/hijo. Con REP la relación es la opuesta a como era con
    // el esquema viejo de "mini-factura por abono":
    //   · Hijo  → bloqueado si el PADRE aún NO está facturado (el REP
    //             necesita el UUID de una factura que ya exista).
    //   · Padre → sin candado por sus hijos; se puede facturar en cualquier
    //             momento (idealmente apenas se cierra la venta, 99/PPD).
    //     Si ya está facturado, eso ya lo bloquea el chequeo de
    //     uuid_fiscal más arriba en procesar()/previsualizar().
    // Devuelve el mensaje de error, o null si se puede facturar.
    // ─────────────────────────────────────────────────────────────────────
    private function validarExclusionPadreHijo(array $nota): ?string
    {
        $folioPadre = (int)($nota['referencia'] ?? 0);
        if ($folioPadre <= 0) {
            return null; // es el padre — sin restricción por sus hijos
        }

        $p = $this->db->query(
            "SELECT COALESCE(uuid_fiscal, '') AS u FROM notas_1 WHERE folio = ? LIMIT 1",
            [$folioPadre]
        )->getRowArray();

        if (empty($p['u'])) {
            return "Primero factura el folio padre #{$folioPadre} completo. "
                 . "Los abonos se facturan como Recepción de Pago (REP), y necesitan el UUID de esa factura.";
        }

        return null;
    }

    // tipopago.id → clave SAT c_FormaPago, para la forma de pago REAL de un
    // abono específico (no la "dominante" de calcularFormaPagoDominante()):
    // cada REP representa un solo pago con su propio método, tal como se
    // registró en ese folio hijo.
    private function formaSatDeTipoPago(int $idTipoPago): string
    {
        $mapaSat = [
            1  => '01', // Contado (Efectivo)
            4  => '02', // Cheque
            5  => '03', // Transferencia
            6  => '03', // Depósito
            8  => '04', // Cargo con tarjeta
            9  => '28', // Tarjeta Débito
            10 => '04', // Tarjeta Crédito
        ];
        return $mapaSat[$idTipoPago] ?? '01';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Calcula los datos que necesita el REP de un folio hijo: monto del
    // abono, saldo antes/después del pago (contra el total del padre y los
    // REP ya emitidos para él) y el número de parcialidad. Requiere que
    // validarExclusionPadreHijo() ya haya confirmado que el padre tiene
    // uuid_fiscal.
    // ─────────────────────────────────────────────────────────────────────
    private function prepararDatosRep(array $nota): array
    {
        $db         = $this->db;
        $folioPadre = (int)($nota['referencia'] ?? 0);
        $idNota     = (int)($nota['Id_Notas_1'] ?? 0);

        $padre = $db->query(
            "SELECT folio, uuid_fiscal, total FROM notas_1 WHERE folio = ? LIMIT 1",
            [$folioPadre]
        )->getRowArray();

        $totalPadre = (float)($padre['total'] ?? 0);

        // REP ya emitidos contra este padre (otros hijos ya facturados)
        $prev = $db->query(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS suma
             FROM notas_1
             WHERE referencia = ? AND status != 3 AND COALESCE(uuid_fiscal, '') <> ''",
            [$folioPadre]
        )->getRowArray();

        $numParcialidad = (int)($prev['n'] ?? 0) + 1;
        $saldoAnterior  = round($totalPadre - (float)($prev['suma'] ?? 0), 2);

        // Monto realmente confirmado del abono (lo que Caja recibió en
        // montosnotas), no lo declarado al registrarlo — si difieren, el
        // REP debe reflejar lo que de verdad entró. Respaldo: notas_1.total.
        $montoRow  = $db->query(
            "SELECT COALESCE(SUM(monto), 0) AS monto FROM montosnotas WHERE idNotas = ?",
            [$idNota]
        )->getRowArray();
        $montoPago = round((float)($montoRow['monto'] ?? 0), 2);
        if ($montoPago <= 0) {
            $montoPago = round((float)($nota['total'] ?? 0), 2);
        }

        return [
            'uuidPadre'      => $padre['uuid_fiscal'] ?? '',
            'folioPadre'     => $folioPadre,
            'totalPadre'     => $totalPadre,
            'montoPago'      => $montoPago,
            'saldoAnterior'  => $saldoAnterior,
            'numParcialidad' => $numParcialidad,
            'formaDePagoP'   => $this->formaSatDeTipoPago((int)($nota['tipoPago'] ?? 0)),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Carga el detalle de productos de un folio y resuelve precio/importe
    // por línea según si la nota fue a precio mayoreo o menudeo.
    // Usado tanto por procesar() como por previsualizar() (solo folio padre;
    // los hijos ya no llevan "productos", se facturan como REP).
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
