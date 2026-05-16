<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductoModel extends Model
{
    protected $table         = 'productosyazbek';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'estilo',
        'sku',
        'Descripcion_corta',
        'Descripcion_Larga',
        'Talla',
        'Color',
        'pMenudeo',
        'pMayoreo',
        'piezas',
        'PiezasMin',
        'PiezasMax',
    ];

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Lista base para inventario admin (ordenado por id).
     */
    public function getInventario(): array
    {
        return $this->select('id, sku, estilo, Descripcion_corta, Descripcion_Larga, Color, Talla, pMayoreo, pMenudeo, piezas')
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }

    /**
     * Busca productos por SKU o descripción.
     */
    public function buscar(string $termino): array
    {
        return $this->groupStart()
                        ->like('sku', $termino)
                        ->orLike('Descripcion_Larga', $termino)
                        ->orLike('estilo', $termino)
                        ->orLike('Color', $termino)
                    ->groupEnd()
                    ->orderBy('id', 'ASC')
                    ->findAll(100);
    }

    /**
     * Los más vendidos (requiere join con notas_2).
     * Se realiza como query raw para mantener la lógica original.
     */
    public function getMasVendidos(int $limite = 30): array
    {
        $db = \Config\Database::connect();
        return $db->query(
            "SELECT n2.sku, SUM(n2.cantidad) AS TotalVentas
             FROM notas_2 n2
             GROUP BY n2.sku
             ORDER BY SUM(n2.cantidad) DESC
             LIMIT ?",
            [$limite]
        )->getResultArray();
    }

    /**
     * Actualiza un campo individual de un producto (edición inline AJAX).
     */
    public function actualizarCampo(int $id, string $campo, mixed $valor): bool
    {
        // Whitelist de campos editables para evitar SQL injection
        $camposPermitidos = [
            'estilo', 'sku', 'Descripcion_corta', 'Descripcion_Larga',
            'Talla', 'Color', 'pMenudeo', 'pMayoreo', 'piezas',
            'PiezasMin', 'PiezasMax',
        ];

        if (! in_array($campo, $camposPermitidos)) {
            return false;
        }

        return $this->update($id, [$campo => $valor]);
    }
}
