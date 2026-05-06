<?php

namespace App\Models;

use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table         = 'clientes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'nombre',
        'direccion',
        'CP',
        'estado',
        'ciudad',
        'RFC',
        'fechaIngreso',
        'telefono',
        'celular',
        'mail',
        'comoNosConoce',
        'NombreEmpresa',
        'razonSocial',
    ];

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Busca clientes por nombre o empresa (búsqueda parcial).
     */
    public function buscar(string $termino): array
    {
        return $this->groupStart()
                        ->like('nombre', $termino)
                        ->orLike('NombreEmpresa', $termino)
                        ->orLike('RFC', $termino)
                    ->groupEnd()
                    ->orderBy('nombre', 'ASC')
                    ->findAll(50);
    }

    /**
     * Devuelve todos los clientes ordenados por nombre.
     */
    public function getTodos(): array
    {
        return $this->orderBy('nombre', 'ASC')->findAll();
    }

    /**
     * Devuelve un cliente con sus datos completos por ID.
     */
    public function getDatos(int $id): array|null
    {
        return $this->find($id);
    }
}
