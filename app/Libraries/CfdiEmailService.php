<?php

namespace App\Libraries;

/**
 * CfdiEmailService
 * Envía la factura CFDI por correo al cliente y a Yazbek.
 * Adjunta el PDF y el XML.
 */
class CfdiEmailService
{
    /** Correo de la empresa (copia interna de todas las facturas) */
    private string $correoYazbek = 'facturacionyazbek0@gmail.com';

    public function __construct()
    {
        // Se puede sobrescribir desde ticket_config si se desea
        try {
            $db  = \Config\Database::connect();
            $cfg = $db->query("SELECT clave, valor FROM ticket_config")->getResultArray();
            $map = array_column($cfg, 'valor', 'clave');
            if (!empty($map['empresa_email_facturacion'])) {
                $this->correoYazbek = $map['empresa_email_facturacion'];
            }
        } catch (\Throwable $e) {
            // Usa el valor por defecto
        }
    }

    /**
     * Envía la factura por correo.
     *
     * @param string      $xmlTimbrado    XML timbrado completo (string)
     * @param string      $pdfBytes       Bytes del PDF generado
     * @param string      $uuid           UUID del CFDI
     * @param string      $folio          Folio de la nota
     * @param string      $correoCliente  Email del receptor (puede estar vacío)
     * @param string      $nombreCliente  Nombre del receptor
     * @return array  ['success' => bool, 'message' => string]
     */
    public function enviar(
        string $xmlTimbrado,
        string $pdfBytes,
        string $uuid,
        string $folio,
        string $correoCliente,
        string $nombreCliente,
        array  $foliosConsolidados = []
    ): array {
        try {
            $email = \Config\Services::email();

            // ── Destinatarios ────────────────────────────────────────────
            // Solo se envía al cliente. La copia a Yazbek se activa en producción.
            $correoClienteLimpio = trim($correoCliente);
            if (!$correoClienteLimpio || !filter_var($correoClienteLimpio, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'El cliente no tiene correo registrado, no se envió.'];
            }
            $email->setTo($correoClienteLimpio, $nombreCliente);
            $email->setCC($this->correoYazbek);

            // ── Asunto y cuerpo ──────────────────────────────────────────
            $email->setSubject('Factura CFDI ' . $folio . ' — UUID: ' . $uuid);
            $email->setMailType('html');
            $email->setMessage($this->construirCuerpo($uuid, $folio, $nombreCliente, $foliosConsolidados));

            // ── Adjuntos ─────────────────────────────────────────────────
            // PDF
            $nombrePdf = 'Factura_' . $folio . '_' . substr($uuid, 0, 8) . '.pdf';
            $email->attach($pdfBytes, 'inline', $nombrePdf, 'application/pdf');

            // XML
            $nombreXml = 'Factura_' . $folio . '_' . $uuid . '.xml';
            $email->attach($xmlTimbrado, 'inline', $nombreXml, 'application/xml');

            // ── Enviar ───────────────────────────────────────────────────
            if ($email->send()) {
                return ['success' => true, 'message' => 'Correo enviado a ' . ($correoClienteLimpio ?: $this->correoYazbek)];
            } else {
                return ['success' => false, 'message' => $email->printDebugger(['headers'])];
            }

        } catch (\Throwable $e) {
            log_message('error', '[CfdiEmailService] ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── HTML del cuerpo del correo ────────────────────────────────────────
    private function construirCuerpo(string $uuid, string $folio, string $nombreCliente, array $foliosConsolidados = []): string
    {
        // Bloque de folios consolidados (solo si aplica)
        $bloqueConsolidado = '';
        if (!empty($foliosConsolidados)) {
            $filas = '';
            foreach ($foliosConsolidados as $f) {
                $filas .= '<tr><td style="padding:3px 8px;border-bottom:1px solid #e8e8e8;">#' . htmlspecialchars((string)$f) . '</td></tr>';
            }
            $bloqueConsolidado = '
      <p style="margin:12px 0 4px;">Esta factura consolida los siguientes <strong>' . count($foliosConsolidados) . ' folios</strong>:</p>
      <table style="border-collapse:collapse;font-size:12px;width:100%;max-width:300px;margin-bottom:12px;">
        <thead><tr><th style="background:#1F4E79;color:#fff;padding:4px 8px;text-align:left;">Folio</th></tr></thead>
        <tbody>' . $filas . '</tbody>
      </table>';
        }

        return '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"/></head>
<body style="font-family:Arial,sans-serif;font-size:13px;color:#222;background:#f9f9f9;margin:0;padding:0;">
  <div style="max-width:580px;margin:20px auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.12);">
    <div style="background:#1F4E79;padding:20px 28px;">
      <h1 style="color:#fff;margin:0;font-size:20px;">Factura Electrónica</h1>
      <p style="color:#BDD7EE;margin:4px 0 0;font-size:12px;">Comprobante Fiscal Digital por Internet (CFDI 4.0)</p>
    </div>
    <div style="padding:24px 28px;">
      <p>Estimado(a) <strong>' . htmlspecialchars($nombreCliente) . '</strong>,</p>
      <p>Adjunto a este correo encontrará su factura electrónica correspondiente a la compra con folio <strong>' . htmlspecialchars($folio) . '</strong>.</p>
      ' . $bloqueConsolidado . '
      <div style="background:#F0F4FA;border-left:4px solid #1F4E79;padding:12px 16px;margin:16px 0;border-radius:3px;">
        <p style="margin:0;font-size:12px;"><strong>UUID (Folio Fiscal):</strong></p>
        <p style="margin:4px 0 0;font-family:monospace;font-size:11px;color:#1F4E79;">' . htmlspecialchars($uuid) . '</p>
      </div>

      <p>Los archivos adjuntos son:</p>
      <ul>
        <li><strong>PDF</strong> — Representación impresa de la factura</li>
        <li><strong>XML</strong> — Archivo fiscal original para cargar en su contabilidad</li>
      </ul>

      <p>Gracias por su compra.</p>
    </div>
    <div style="background:#F5F5F5;padding:12px 28px;text-align:center;font-size:11px;color:#888;">
      Este correo fue generado automáticamente. Por favor no lo ignore, contiene sus documentos fiscales.
    </div>
  </div>
</body>
</html>';
    }
}
