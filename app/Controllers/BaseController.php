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
}
