<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    // ──────────────────────────────────────────────────────────────
    // CORRECCIÓN DE ENCODING
    // Corrige doble-codificación UTF-8/latin1 en resultados de BD.
    // Úsalos en cualquier controlador antes de pasar datos a la vista.
    // ──────────────────────────────────────────────────────────────

    /**
     * Corrige encoding en un solo string.
     */
    protected function fixEnc(?string $str): string
    {
        return fix_enc($str ?? '');
    }

    /**
     * Corrige encoding en una sola fila (array asociativo).
     */
    protected function fixRow(array $row): array
    {
        return fix_enc_row($row);
    }

    /**
     * Corrige encoding en un array de filas (resultado de getResultArray()).
     */
    protected function fixRows(array $rows): array
    {
        return fix_enc_rows($rows);
    }

    // ──────────────────────────────────────────────────────────────
    // FORMA DE PAGO DOMINANTE (CFDI)
    // Cuando un folio se pagó con varios métodos combinados, la factura
    // debe llevar el método que representa el MAYOR monto (no el que se
    // haya seleccionado a mano, casi siempre "01 Efectivo" por default).
    // Se calcula sobre los pagos YA REGISTRADOS en montosnotas (folio +
    // sus folios hijo/anticipos no cancelados), que es el desglose real
    // de lo cobrado.
    // ──────────────────────────────────────────────────────────────
    protected function calcularFormaPagoDominante(int $folio): ?string
    {
        // Mapa tipopago.id → clave SAT c_FormaPago
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

        $db  = \Config\Database::connect();
        $row = $db->query(
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
}
