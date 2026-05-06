<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * NotaDetalleModel — líneas de detalle de una nota (tabla notas_2)
 */
class NotaDetalleModel extends Model
{
    protected $table         = 'notas_2';
    protected $primaryKey    = 'id';    // ajustar si la PK tiene otro nombre
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'folio',
        'sku',
        'cantidad',
        'precio',
        'importe',
        'descripcion',
        'color',
        'talla',
        'id_producto',
    ];

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Todos los productos de una nota por folio.
     */
    public function getPorFolio(int $folio): array
    {
        return $this->where('folio', $folio)->findAll();
    }

    /**
     * Agrega un producto al detalle de la nota.
     */
    public function agregarProducto(array $datos): int|string|false
    {
        return $this->insert($datos, true);
    }

    /**
     * Elimina una línea de detalle por su id.
     */
    public function eliminarLinea(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Suma total de piezas de una nota.
     */
    public function totalPiezas(int $folio): int
    {
        $result = $this->db->query(
            "SELECT SUM(cantidad) AS total FROM notas_2 WHERE folio = ?",
            [$folio]
        )->getRow();
        return (int) ($result->total ?? 0);
    }

    /**
     * Suma total de importes de una nota.
     */
    public function sumaImportes(int $folio): float
    {
        $result = $this->db->query(
            "SELECT SUM(importe) AS total FROM notas_2 WHERE folio = ?",
            [$folio]
        )->getRow();
        return (float) ($result->total ?? 0);
    }

    /**
     * Productos más vendidos (top N por SKU).
     */
    public function getMasVendidos(int $limite = 30): array
    {
        return $this->db->query(
            "SELECT sku, SUM(cantidad) AS totalVentas
             FROM notas_2
             GROUP BY sku
             ORDER BY SUM(cantidad) DESC
             LIMIT ?",
            [$limite]
        )->getResultArray();
    }
}
