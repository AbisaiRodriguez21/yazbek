<?php

namespace App\Controllers;

/**
 * ReportesController
 *
 * Módulo de Reportes (solo admin — acceso = 1).
 *
 * Migrado desde:
 *   AppNissi/Yazbek/reportes/generarReportes.php
 *   AppNissi/Yazbek/reportes/ReporteCorteCaja.php
 *   AppNissi/Yazbek/reportes/ReporteCorteCaja2.php
 *
 * Nota: La generación de Excel se migra a PhpSpreadsheet (reemplaza a PHPExcel).
 */
class ReportesController extends BaseController
{
    // ──────────────────────────────────────────────────────────────
    // GET /reportes  —  Dashboard de reportes
    // ──────────────────────────────────────────────────────────────
    public function index(): string
    {
        return view('reportes/index', [
            'usuario' => $this->getUsuarioSesion(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /reportes/cortecaja  —  Reporte de corte de caja (vista)
    // Migrado desde: reportes/ReporteCorteCaja.php
    // ──────────────────────────────────────────────────────────────
    public function corteCaja(): string
    {
        $fecha = $this->request->getGet('fecha') ?? date('Y-m-d');
        $db    = \Config\Database::connect();

        // Desglose por tipo de pago
        $desglose = $db->query(
            "SELECT tp.descripcion AS tipopago,
                    SUM(mn.monto)  AS monto,
                    SUM(mn.cargos) AS cargos,
                    SUM(mn.monto + mn.cargos) AS total
             FROM montosnotas mn
             INNER JOIN tipopago  tp ON mn.idTipoPago = tp.id
             INNER JOIN notas_1   n  ON mn.idNotas = n.Id_Notas_1
             WHERE mn.fecha LIKE ? AND n.status = 5
             GROUP BY tp.id
             ORDER BY tp.id ASC",
            ["{$fecha}%"]
        )->getResultArray();

        // Detalle de notas del día
        $notas = $db->query(
            "SELECT n.folio,
                    n.fecha_inicial,
                    c.nombre    AS cliente,
                    u.usuario   AS vendedor,
                    n.total,
                    tp.descripcion AS tipopago,
                    n.descuento,
                    n.iva,
                    n.subTotal
             FROM notas_1 n
             LEFT JOIN clientes    c  ON c.id = n.idCliente
             LEFT JOIN usuarios    u  ON u.Id = n.idVendedor
             LEFT JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             LEFT JOIN tipopago    tp ON mn.idTipoPago = tp.id
             WHERE n.fecha_inicial LIKE ? AND n.status = 5
             ORDER BY n.folio ASC",
            ["{$fecha}%"]
        )->getResultArray();

        $totalGeneral = array_sum(array_column($notas, 'total'));

        return view('reportes/corte_caja', [
            'usuario'      => $this->getUsuarioSesion(),
            'desglose'     => $desglose,
            'notas'        => $notas,
            'totalGeneral' => $totalGeneral,
            'fecha'        => $fecha,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /reportes/cortecaja2  —  Segunda versión del corte (más desglosado)
    // Migrado desde: reportes/ReporteCorteCaja2.php
    // ──────────────────────────────────────────────────────────────
    public function corteCaja2(): string
    {
        $fecha = $this->request->getGet('fecha') ?? date('Y-m-d');
        $db    = \Config\Database::connect();

        // Notas con detalle de productos vendidos
        $notas = $db->query(
            "SELECT n.folio,
                    c.nombre    AS cliente,
                    u.usuario   AS vendedor,
                    n2.sku,
                    n2.cantidad,
                    n2.precio,
                    n2.importe,
                    n.fecha_inicial,
                    tp.descripcion AS tipopago
             FROM notas_2 n2
             INNER JOIN notas_1   n  ON n2.folio = n.folio
             LEFT JOIN  clientes  c  ON c.id = n.idCliente
             LEFT JOIN  usuarios  u  ON u.Id = n.idVendedor
             LEFT JOIN  montosnotas mn ON mn.idNotas = n.Id_Notas_1
             LEFT JOIN  tipopago  tp ON mn.idTipoPago = tp.id
             WHERE n.fecha_inicial LIKE ? AND n.status = 5
             ORDER BY n.folio ASC, n2.Id_Notas_2 ASC",
            ["{$fecha}%"]
        )->getResultArray();

        return view('reportes/corte_caja2', [
            'usuario' => $this->getUsuarioSesion(),
            'notas'   => $notas,
            'fecha'   => $fecha,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /reportes/excel  —  Descarga el reporte como Excel
    // Migrado desde: reportes/ExcelReportCreator.php + generarReportes.php
    //
    // NOTA: La librería original PHPExcel está obsoleta.
    //       Se sustituye con PhpSpreadsheet (composer require phpoffice/phpspreadsheet).
    //       Este método genera el Excel directamente en memoria y lo descarga.
    // ──────────────────────────────────────────────────────────────
    public function generarExcel(): \CodeIgniter\HTTP\Response
    {
        $fecha = $this->request->getGet('fecha') ?? date('Y-m-d');
        $db    = \Config\Database::connect();

        $notas = $db->query(
            "SELECT n.folio,
                    DATE_FORMAT(n.fecha_inicial, '%d/%m/%Y') AS fecha,
                    c.nombre    AS cliente,
                    u.usuario   AS vendedor,
                    n.total,
                    n.descuento,
                    n.iva,
                    tp.descripcion AS tipopago
             FROM notas_1 n
             LEFT JOIN clientes    c  ON c.id = n.idCliente
             LEFT JOIN usuarios    u  ON u.Id = n.idVendedor
             LEFT JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             LEFT JOIN tipopago    tp ON mn.idTipoPago = tp.id
             WHERE n.fecha_inicial LIKE ? AND n.status = 5
             ORDER BY n.folio ASC",
            ["{$fecha}%"]
        )->getResultArray();

        // Generar CSV simple si PhpSpreadsheet no está disponible
        if (! class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return $this->generarCsv($notas, $fecha);
        }

        // ── Generar Excel con PhpSpreadsheet ──
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Corte {$fecha}");

        // Encabezados
        $encabezados = ['Folio', 'Fecha', 'Cliente', 'Vendedor', 'Total', 'Descuento', 'IVA', 'Tipo de Pago'];
        foreach ($encabezados as $col => $titulo) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $titulo);
        }

        // Datos
        foreach ($notas as $fila => $nota) {
            $sheet->setCellValueByColumnAndRow(1, $fila + 2, $nota['folio']);
            $sheet->setCellValueByColumnAndRow(2, $fila + 2, $nota['fecha']);
            $sheet->setCellValueByColumnAndRow(3, $fila + 2, $nota['cliente']);
            $sheet->setCellValueByColumnAndRow(4, $fila + 2, $nota['vendedor']);
            $sheet->setCellValueByColumnAndRow(5, $fila + 2, $nota['total']);
            $sheet->setCellValueByColumnAndRow(6, $fila + 2, $nota['descuento']);
            $sheet->setCellValueByColumnAndRow(7, $fila + 2, $nota['iva']);
            $sheet->setCellValueByColumnAndRow(8, $fila + 2, $nota['tipopago']);
        }

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'reporte_');
        $writer->save($tempFile);
        $contenido = file_get_contents($tempFile);
        unlink($tempFile);

        return $this->response
                    ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->setHeader('Content-Disposition', "attachment; filename=\"reporte_corte_{$fecha}.xlsx\"")
                    ->setBody($contenido);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: genera un CSV como fallback si PhpSpreadsheet no está
    // ──────────────────────────────────────────────────────────────
    private function generarCsv(array $notas, string $fecha): \CodeIgniter\HTTP\Response
    {
        $csv = "Folio,Fecha,Cliente,Vendedor,Total,Descuento,IVA,Tipo de Pago\n";
        foreach ($notas as $nota) {
            $csv .= implode(',', [
                $nota['folio'],
                $nota['fecha'],
                '"' . str_replace('"', '""', $nota['cliente'] ?? '') . '"',
                '"' . str_replace('"', '""', $nota['vendedor'] ?? '') . '"',
                $nota['total'],
                $nota['descuento'],
                $nota['iva'],
                '"' . str_replace('"', '""', $nota['tipopago'] ?? '') . '"',
            ]) . "\n";
        }

        return $this->response
                    ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                    ->setHeader('Content-Disposition', "attachment; filename=\"reporte_corte_{$fecha}.csv\"")
                    ->setBody("\xEF\xBB\xBF" . $csv); // BOM para UTF-8 en Excel
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: datos del usuario activo desde la sesión
    // ──────────────────────────────────────────────────────────────
    private function getUsuarioSesion(): array
    {
        $session = session();
        return [
            'Id'     => $session->get('user_id'),
            'nombre' => $session->get('user_nombre'),
            'mail'   => $session->get('user_email'),
            'acceso' => $session->get('user_acceso'),
        ];
    }
}
