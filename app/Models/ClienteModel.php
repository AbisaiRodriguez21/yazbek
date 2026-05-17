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

    // Corrige encoding automáticamente en cada consulta vía modelo
    protected $afterFind = ['fixEncoding'];

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
        'eliminado',
    ];

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Busca clientes activos por nombre o empresa (búsqueda parcial).
     */
    public function buscar(string $termino): array
    {
        $db = \Config\Database::connect();
        $s  = $db->escape('%' . strtolower(trim($termino)) . '%');
        return $this->where('eliminado', 0)
                ->where(
                    "(LOWER(nombre) LIKE {$s}
                      OR LOWER(NombreEmpresa) LIKE {$s}
                      OR LOWER(RFC) LIKE {$s})"
                )
                ->orderBy('nombre', 'ASC')
                ->findAll(50);
    }

    /**
     * Devuelve todos los clientes activos ordenados por nombre.
     */
    public function getTodos(): array
    {
        return $this->where('eliminado', 0)->orderBy('nombre', 'ASC')->findAll();
    }

    /**
     * Devuelve un cliente con sus datos completos por ID.
     */
    public function getDatos(int $id): array|null
    {
        return $this->find($id);
    }

    /**
     * Soft-delete: marca el cliente como eliminado.
     */
    public function softDelete(int $id): void
    {
        \Config\Database::connect()
            ->query("UPDATE clientes SET eliminado = 1 WHERE id = ?", [$id]);
    }

    /**
     * Restaura un cliente eliminado.
     */
    public function restaurar(int $id): void
    {
        \Config\Database::connect()
            ->query("UPDATE clientes SET eliminado = 0 WHERE id = ?", [$id]);
    }

    /**
     * Server-side DataTables para clientes ACTIVOS.
     */
    public function getDatatable(int $start, int $length, string $search, string $orderCol, string $orderDir): array
    {
        $cols = ['nombre', 'RFC', 'celular', 'telefono', 'mail'];
        $orderCol = $cols[$orderCol] ?? 'nombre';
        $orderDir = $orderDir === 'desc' ? 'DESC' : 'ASC';

        $db = \Config\Database::connect();

        $total = (int) $db->table($this->table)->where('eliminado', 0)->countAllResults();

        $builder = $db->table($this->table)
                      ->select('id, nombre, RFC, celular, telefono, mail, NombreEmpresa, razonSocial, direccion, CP, estado, ciudad')
                      ->where('eliminado', 0);

        if ($search !== '') {
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

    /**
     * Server-side DataTables para clientes ELIMINADOS (soft-deleted).
     */
    public function getDatatableEliminados(int $start, int $length, string $search, string $orderDir): array
    {
        $db = \Config\Database::connect();

        $total = (int) $db->table($this->table)->where('eliminado', 1)->countAllResults();

        $builder = $db->table($this->table)
                      ->select('id, nombre, RFC, celular, telefono, mail, NombreEmpresa')
                      ->where('eliminado', 1);

        if ($search !== '') {
            $s = $db->escape('%' . strtolower(trim($search)) . '%');
            $builder->where("(LOWER(nombre) LIKE {$s} OR LOWER(RFC) LIKE {$s} OR LOWER(NombreEmpresa) LIKE {$s})");
        }

        $filtered = $builder->countAllResults(false);
        $data     = $builder->orderBy('nombre', $orderDir)
                            ->limit($length, $start)
                            ->get()->getResultArray();

        return [
            'total'    => $total,
            'filtered' => $filtered,
            'data'     => fix_enc_rows($data),
        ];
    }

    // ──────────────────────────────────────────────
    // afterFind: corrección automática de encoding
    // ──────────────────────────────────────────────
    protected function fixEncoding(array $data): array
    {
        if (! isset($data['data'])) return $data;
        if ($data['singleton']) {
            if (is_array($data['data'])) {
                $data['data'] = fix_enc_row($data['data']);
            }
        } else {
            if (is_array($data['data'])) {
                $data['data'] = fix_enc_rows($data['data']);
            }
        }
        return $data;
    }
}
