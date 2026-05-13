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
        $db = \Config\Database::connect();
        $s  = $db->escape('%' . strtolower(trim($termino)) . '%');
        return $this->where(
                    "(LOWER(nombre) LIKE {$s}
                      OR LOWER(NombreEmpresa) LIKE {$s}
                      OR LOWER(RFC) LIKE {$s})"
                )
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

    /**
     * Server-side DataTables: devuelve registros paginados + total.
     */
    public function getDatatable(int $start, int $length, string $search, string $orderCol, string $orderDir): array
    {
        $cols = ['nombre', 'RFC', 'celular', 'telefono', 'mail'];
        $orderCol = $cols[$orderCol] ?? 'nombre';
        $orderDir = $orderDir === 'desc' ? 'DESC' : 'ASC';

        $db = \Config\Database::connect();

        // Total sin filtro
        $total = $db->table($this->table)->countAllResults();

        // Query con filtro
        $builder = $db->table($this->table)
                      ->select('id, nombre, RFC, celular, telefono, mail, NombreEmpresa, razonSocial, direccion, CP, estado, ciudad');

        if ($search !== '') {
            // Búsqueda case-insensitive con LOWER() para que funcione en mayúsculas y minúsculas
            $s = $db->escape('%' . strtolower(trim($search)) . '%');
            $builder->where("(LOWER(nombre) LIKE {$s} OR LOWER(RFC) LIKE {$s} OR LOWER(NombreEmpresa) LIKE {$s} OR LOWER(mail) LIKE {$s})");
        }

        $filtered = $builder->countAllResults(false);
        $data     = $builder->orderBy($orderCol, $orderDir)
                            ->limit($length, $start)
                            ->get()->getResultArray();

        return [
            'total'    => $total,
            'filtered' => $filtered,
            'data'     => $data,
        ];
    }
}
