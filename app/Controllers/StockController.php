<?php

namespace App\Controllers;

/**
 * StockController
 *
 * Endpoint de polling corto para sincronización de stock en tiempo real.
 *
 * A diferencia de SSE (que mantiene una conexión abierta y bloquea un proceso
 * de Apache por usuario), este endpoint responde de inmediato y libera el proceso.
 * El JS en el layout hace una petición cada 2 segundos — Apache nunca se satura.
 *
 * Flujo:
 *   1. JS envía GET /stock/poll?since=<ultimo_id>
 *   2. PHP consulta stock_eventos WHERE id > ultimo_id
 *   3. Responde con JSON inmediatamente (proceso libre en <5ms)
 *   4. JS aplica los cambios en pantalla y vuelve a llamar en 2s
 */
class StockController extends BaseController
{
    /**
     * GET /stock/poll?since=<id>
     *
     * Retorna todos los eventos de stock ocurridos desde el id dado.
     * Respuesta inmediata — no bloquea procesos de Apache.
     */
    public function poll(): \CodeIgniter\HTTP\Response
    {
        $desdeId = (int)($this->request->getGet('since') ?? 0);
        $db      = \Config\Database::connect();

        // Si since=0 arrancar desde el último id actual (no reenviar histórico)
        if ($desdeId === 0) {
            $row     = $db->query("SELECT COALESCE(MAX(id), 0) AS m FROM stock_eventos")->getRowArray();
            $desdeId = (int)($row['m'] ?? 0);
            return $this->response
                ->setContentType('application/json')
                ->setJSON(['eventos' => [], 'last_id' => $desdeId]);
        }

        $eventos = $db->query(
            "SELECT id, sku, nuevo_stock
             FROM stock_eventos
             WHERE id > ?
             ORDER BY id ASC
             LIMIT 50",
            [$desdeId]
        )->getResultArray();

        $lastId = $desdeId;
        $items  = [];
        foreach ($eventos as $ev) {
            $lastId  = (int)$ev['id'];
            $items[] = ['sku' => $ev['sku'], 'stock' => (int)$ev['nuevo_stock']];
        }

        return $this->response
            ->setContentType('application/json')
            ->setJSON(['eventos' => $items, 'last_id' => $lastId]);
    }
}
