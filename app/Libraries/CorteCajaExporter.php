<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

/**
 * CorteCajaExporter
 *
 * Genera el xlsx del Corte de Caja con dos hojas:
 *   CORTE CAJA     — estructura fija con secciones manuales + datos de BD
 *   DIARIO DE VENTAS — listado de todas las notas del período
 *
 * Uso:
 *   $content = CorteCajaExporter::build($rows, '08/06/2026');
 *
 * $rows debe tener por fila:
 *   folio, fecha (d/m/Y), cliente, vendedor, tipopago, monto,
 *   total_nota, estatus, referencia
 */
class CorteCajaExporter
{
    // Colores (sin #)
    private const BLUE_HDR  = '145388';  // azul oscuro encabezados
    private const BLUE_COL  = 'BDD7EE';  // azul claro columnas de tabla
    private const GRAY_SEC  = 'D6DCE4';  // gris sección
    private const GREEN_SUB = 'E2EFDA';  // verde suaves para subtotales
    private const RED_DIF   = 'FCE4D6';  // naranja suave para diferencia
    private const WHITE     = 'FFFFFF';

    // ─────────────────────────────────────────────────────────────────
    // Punto de entrada público
    // ─────────────────────────────────────────────────────────────────
    public static function build(array $rows, string $fechaLabel): string
    {
        [$agrupadas, $pagosEspeciales, $pagosCredito] = self::groupRows($rows);

        $spreadsheet = new Spreadsheet();

        // Calcular fila dinámica del TOTAL (3 = primera fila de datos, +1 por cada nota)
        $totalRow = 3 + count($agrupadas); // fila inmediatamente después del último dato

        // Hoja 1: CORTE CAJA
        $ws1 = $spreadsheet->getActiveSheet();
        $ws1->setTitle('CORTE CAJA');
        self::buildCorteCaja($ws1, $fechaLabel, $pagosEspeciales, $pagosCredito, $totalRow);

        // Hoja 2: DIARIO DE VENTAS
        $ws2 = $spreadsheet->createSheet();
        $ws2->setTitle('DIARIO DE VENTAS');
        self::buildDiarioVentas($ws2, $fechaLabel, array_values($agrupadas), $totalRow);

        $spreadsheet->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────
    // Agrupa rows por folio y separa secciones
    // ─────────────────────────────────────────────────────────────────
    private static function groupRows(array $rows): array
    {
        $agrupadas       = [];
        $pagosEspeciales = [];
        $pagosCredito    = [];

        $kwEsp = ['tarjeta', 'transferencia', 'deposito', 'transf', 'depos'];

        foreach ($rows as $row) {
            $f = $row['folio'];
            if (!isset($agrupadas[$f])) {
                $agrupadas[$f] = [
                    'folio'    => $f,
                    'cliente'  => $row['cliente'],
                    'vendedor' => $row['vendedor'],
                    'tipopago' => '',
                    'total'    => (float)($row['total_nota'] ?? 0),
                    'estatus'  => $row['estatus'],
                ];
            }

            $desc      = $row['tipopago'] ?? '';
            $monto     = (float)($row['monto'] ?? 0);
            $descLower = strtolower($desc);

            if ($desc === '') continue;

            if (strpos($descLower, 'sin pagar') !== false) {
                // Nota a crédito
                if (!isset($pagosCredito[$f])) {
                    $pagosCredito[$f] = [
                        'cliente' => $row['cliente'],
                        'nota'    => $f,
                        'importe' => (float)($row['total_nota'] ?? 0),
                    ];
                }
                if (strpos($agrupadas[$f]['tipopago'], 'A Crédito') === false) {
                    $agrupadas[$f]['tipopago'] .= ($agrupadas[$f]['tipopago'] ? ' | ' : '') . 'A Crédito';
                }
            } else {
                $linea = $desc . ' $' . number_format($monto, 2);
                // Evitar duplicados en el DIARIO
                if (strpos($agrupadas[$f]['tipopago'], $linea) === false) {
                    $agrupadas[$f]['tipopago'] .= ($agrupadas[$f]['tipopago'] ? ' | ' : '') . $linea;
                }

                $esEsp = false;
                foreach ($kwEsp as $kw) {
                    if (strpos($descLower, $kw) !== false) { $esEsp = true; break; }
                }
                if ($esEsp && $monto > 0) {
                    $pagosEspeciales[] = [
                        'folio'      => $f,
                        'cliente'    => $row['cliente'],
                        'metodo'     => $desc,
                        'importe'    => $monto,
                        'fecha'      => $row['fecha'],
                        'referencia' => $row['referencia'] ?? '',
                    ];
                }
            }
        }

        return [$agrupadas, $pagosEspeciales, $pagosCredito];
    }

    // ─────────────────────────────────────────────────────────────────
    // Hoja 1: CORTE CAJA
    // ─────────────────────────────────────────────────────────────────
    private static function buildCorteCaja($ws, string $fecha, array $pagosEsp, array $pagosCredito, int $totalRow = 38): void
    {
        // Anchos de columna
        $ws->getColumnDimension('A')->setWidth(20);
        $ws->getColumnDimension('B')->setWidth(24);
        $ws->getColumnDimension('C')->setWidth(16);
        $ws->getColumnDimension('D')->setWidth(16);
        $ws->getColumnDimension('E')->setWidth(16);
        $ws->getColumnDimension('F')->setWidth(22);

        // ── Fila 1: Título ──
        $ws->mergeCells('A1:C1');
        $ws->setCellValue('A1', 'CORTE DE CAJA');
        $ws->setCellValue('D1', 'FECHA:');
        $ws->setCellValue('E1', $fecha);
        self::hdr($ws, 'A1:F1', self::BLUE_HDR, self::WHITE, 14, true, 'center');
        self::hdr($ws, 'D1:F1', self::BLUE_HDR, self::WHITE, 12, true, 'left');

        // ── Fila 3: BILLETES ──
        $ws->setCellValue('A3', 'BILLETES');
        $ws->setCellValue('D3', 'IMPORTE');
        self::hdr($ws, 'A3:F3', self::GRAY_SEC, '000000', 11, true);

        foreach ([4 => 1000, 5 => 500, 6 => 200, 7 => 100, 8 => 50, 9 => 20] as $r => $v) {
            $ws->setCellValue('A'.$r, $v);
            $ws->setCellValue('D'.$r, 0);
            $ws->getStyle('A'.$r)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $ws->getStyle('D'.$r)->getNumberFormat()->setFormatCode('"$"#,##0.00');
        }
        self::border($ws, 'A3:F9');

        // ── Fila 10: MONEDAS ──
        $ws->setCellValue('A10', 'MONEDAS');
        self::hdr($ws, 'A10:F10', self::GRAY_SEC, '000000', 11, true);

        foreach ([11 => 10, 12 => 5, 13 => 2, 14 => 1, 15 => 0.5, 16 => 0.2, 17 => 0.1] as $r => $v) {
            $ws->setCellValue('A'.$r, $v);
            $ws->setCellValue('D'.$r, 0);
            $ws->getStyle('A'.$r)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $ws->getStyle('D'.$r)->getNumberFormat()->setFormatCode('"$"#,##0.00');
        }
        self::border($ws, 'A10:F17');

        // ── Fila 18: Subtotal efectivo ──
        $ws->mergeCells('A18:C18');
        $ws->setCellValue('A18', 'SUBTOTAL EFECTIVO');
        $ws->setCellValue('E18', '=SUM(D4:D17)');
        self::hdr($ws, 'A18:F18', self::GREEN_SUB, '000000', 11, true);
        self::border($ws, 'A18:F18');
        $ws->getStyle('E18')->getNumberFormat()->setFormatCode('"$"#,##0.00');

        // ── Fila 19: CHEQUES ──
        $ws->setCellValue('A19', 'CHEQUES');
        self::hdr($ws, 'A19:F19', self::GRAY_SEC, '000000', 11, true);

        self::tableHeader($ws, 20, ['No.', 'BANCO', 'NOTA', 'IMPORTE', '', '']);
        self::border($ws, 'A19:F21');

        $ws->mergeCells('A22:C22');
        $ws->setCellValue('A22', 'SUBTOTAL CHEQUES');
        $ws->setCellValue('E22', '=SUM(D21:D21)');
        self::hdr($ws, 'A22:F22', self::GREEN_SUB, '000000', 11, true);
        self::border($ws, 'A22:F22');
        $ws->getStyle('E22')->getNumberFormat()->setFormatCode('"$"#,##0.00');

        // ── Fila 23: GASTOS/VALES ──
        $ws->setCellValue('A23', 'GASTOS/VALES');
        self::hdr($ws, 'A23:F23', self::GRAY_SEC, '000000', 11, true);

        self::tableHeader($ws, 24, ['No.', 'DESCRIPCION', '', 'IMPORTE', '', '']);
        self::border($ws, 'A23:F26');

        $ws->mergeCells('A27:C27');
        $ws->setCellValue('A27', 'SUBTOTAL VALES');
        $ws->setCellValue('E27', '=SUM(D25:D26)');
        self::hdr($ws, 'A27:F27', self::GREEN_SUB, '000000', 11, true);
        self::border($ws, 'A27:F27');
        $ws->getStyle('E27')->getNumberFormat()->setFormatCode('"$"#,##0.00');

        // ── Fila 28: CRÉDITO ──
        $ws->setCellValue('A28', 'CRÉDITO');
        self::hdr($ws, 'A28:F28', self::GRAY_SEC, '000000', 11, true);

        self::tableHeader($ws, 29, ['CLIENTE', 'DESCRIPCION', 'NOTA', 'IMPORTE', '', '']);

        // Llenar crédito desde BD (máx 5 filas: 30-34)
        $r = 30;
        foreach (array_values($pagosCredito) as $pc) {
            if ($r > 34) break;
            $ws->setCellValue('A'.$r, $pc['cliente']);
            $ws->setCellValue('C'.$r, $pc['nota']);
            $ws->setCellValue('D'.$r, $pc['importe']);
            $ws->getStyle('D'.$r)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $r++;
        }
        self::border($ws, 'A28:F34');

        $ws->mergeCells('A35:C35');
        $ws->setCellValue('A35', 'SUBTOTAL A CRÉDITO');
        $ws->setCellValue('E35', '=SUM(D30:D34)');
        self::hdr($ws, 'A35:F35', self::GREEN_SUB, '000000', 11, true);
        self::border($ws, 'A35:F35');
        $ws->getStyle('E35')->getNumberFormat()->setFormatCode('"$"#,##0.00');

        // ── Fila 36: PAGOS TARJETA, TRANSF, DEPÓSITO ──
        $ws->setCellValue('A36', 'PAGOS TARJETA, TRANSF, DEPÓSITO');
        self::hdr($ws, 'A36:F36', self::GRAY_SEC, '000000', 11, true);

        self::tableHeader($ws, 37, ['FOLIO', 'CLIENTE', 'METODO', 'FECHA', 'IMPORTE', 'REFERENCIA']);

        $r = 38;
        foreach ($pagosEsp as $p) {
            if ($r > 61) break;
            $ws->setCellValue('A'.$r, $p['folio']);
            $ws->setCellValue('B'.$r, $p['cliente']);
            $ws->setCellValue('C'.$r, $p['metodo']);
            $ws->setCellValue('D'.$r, $p['fecha']);
            $ws->setCellValue('E'.$r, $p['importe']);
            $ws->setCellValueExplicit('F'.$r, (string)$p['referencia'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->getStyle('E'.$r)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $r++;
        }
        self::border($ws, 'A36:F61');

        $ws->mergeCells('A62:C62');
        $ws->setCellValue('A62', 'SUBTOTAL TRANSF-TARJ');
        $ws->setCellValue('E62', '=SUM(E38:E61)');
        self::hdr($ws, 'A62:F62', self::GREEN_SUB, '000000', 11, true);
        self::border($ws, 'A62:F62');
        $ws->getStyle('E62')->getNumberFormat()->setFormatCode('"$"#,##0.00');

        // ── Fila 63: GRAN TOTAL ──
        $ws->mergeCells('A63:C63');
        $ws->setCellValue('A63', 'GRAN TOTAL');
        $ws->setCellValue('E63', '=E18+E22+E27+E62');
        self::hdr($ws, 'A63:F63', self::BLUE_HDR, self::WHITE, 13, true, 'center');
        self::border($ws, 'A63:F63');
        $ws->getStyle('E63')->getNumberFormat()->setFormatCode('"$"#,##0.00');

        // ── Filas 64-68: Resumen ──
        $summaryLabels = [64 => 'VENTAS', 65 => 'CREDITO', 66 => 'DOCUMENTOS', 67 => 'EFECTIVO', 68 => 'DIFERENCIA'];
        foreach ($summaryLabels as $r => $label) {
            $ws->setCellValue('A'.$r, $label);
        }
        // VENTAS = total diario de ventas (referencia dinámica a la fila del TOTAL)
        $ws->setCellValue('B64', "='DIARIO DE VENTAS'!E{$totalRow}");
        $ws->setCellValue('B65', 0);
        $ws->setCellValue('B66', '=E22+E27+E62');
        $ws->setCellValue('B67', '=E18');
        $ws->setCellValue('B68', '=B64-B65-B66-B67');

        self::border($ws, 'A64:B68');
        foreach (['B64','B65','B66','B67','B68'] as $cell) {
            $ws->getStyle($cell)->getNumberFormat()->setFormatCode('"$"#,##0.00');
        }
        self::hdr($ws, 'A64:B64', self::GREEN_SUB, '000000', 11, true);
        self::hdr($ws, 'A68:B68', self::RED_DIF, '000000', 11, true);
    }

    // ─────────────────────────────────────────────────────────────────
    // Hoja 2: DIARIO DE VENTAS (empieza en fila 1)
    // ─────────────────────────────────────────────────────────────────
    private static function buildDiarioVentas($ws2, string $fecha, array $notas, int $totalRow = 38): void
    {
        $ws2->getColumnDimension('A')->setWidth(12);
        $ws2->getColumnDimension('B')->setWidth(30);
        $ws2->getColumnDimension('C')->setWidth(14);
        $ws2->getColumnDimension('D')->setWidth(26);
        $ws2->getColumnDimension('E')->setWidth(14);
        $ws2->getColumnDimension('F')->setWidth(16);

        // Fila 1: Título
        $ws2->mergeCells('A1:D1');
        $ws2->setCellValue('A1', 'YAZBEK — DIARIO DE VENTAS');
        $ws2->setCellValue('E1', 'FECHA:');
        $ws2->setCellValue('F1', $fecha);
        self::hdr($ws2, 'A1:F1', self::BLUE_HDR, self::WHITE, 13, true, 'center');

        // Fila 2: Encabezados
        self::tableHeader($ws2, 2, ['FOLIO', 'CLIENTE', 'REALIZÓ', 'FORMA DE PAGO', 'IMPORTE', 'ESTATUS']);

        // Filas de datos desde fila 3 — sin límite fijo
        $r = 3;
        foreach ($notas as $n) {
            $ws2->setCellValue('A'.$r, $n['folio']);
            $ws2->setCellValue('B'.$r, $n['cliente']);
            $ws2->setCellValue('C'.$r, $n['vendedor']);
            $ws2->setCellValue('D'.$r, $n['tipopago']);
            $ws2->setCellValue('E'.$r, $n['total']);
            $ws2->setCellValue('F'.$r, $n['estatus']);
            $ws2->getStyle('E'.$r)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $r++;
        }

        // Borde sobre los datos (filas 2 hasta la última fila de datos)
        $lastDataRow = $totalRow - 1;
        if ($lastDataRow >= 2) {
            self::border($ws2, "A2:F{$lastDataRow}");
        }

        // Fila TOTAL: inmediatamente después del último dato
        $ws2->mergeCells("A{$totalRow}:D{$totalRow}");
        $ws2->setCellValue("A{$totalRow}", 'TOTAL DEL DÍA');
        $ws2->setCellValue("E{$totalRow}", "=SUM(E3:E{$lastDataRow})");
        self::hdr($ws2, "A{$totalRow}:F{$totalRow}", self::GREEN_SUB, '000000', 11, true);
        self::border($ws2, "A{$totalRow}:F{$totalRow}");
        $ws2->getStyle("E{$totalRow}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers de estilo
    // ─────────────────────────────────────────────────────────────────

    /** Aplica fondo, color de texto, tamaño y alineación a un rango */
    private static function hdr($ws, string $range, string $bg, string $fg, int $size = 11, bool $bold = false, string $align = ''): void
    {
        $s = $ws->getStyle($range);
        $s->getFont()->setBold($bold)->setSize($size)->getColor()->setRGB($fg);
        $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
        if ($align === 'center') {
            $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    /** Aplica borde delgado en todos los lados a un rango */
    private static function border($ws, string $range): void
    {
        $ws->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(Border::BORDER_THIN)
           ->getColor()->setRGB('AAAAAA');
    }

    /** Escribe la fila de encabezados de una tabla en azul claro */
    private static function tableHeader($ws, int $row, array $cols): void
    {
        $letters = ['A','B','C','D','E','F'];
        foreach ($cols as $i => $label) {
            if (!isset($letters[$i])) break;
            $ws->setCellValue($letters[$i].$row, $label);
        }
        self::hdr($ws, 'A'.$row.':F'.$row, self::BLUE_COL, '000000', 10, true, 'center');
    }
}
