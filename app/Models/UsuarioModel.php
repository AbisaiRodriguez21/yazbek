<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table         = 'usuarios';
    protected $primaryKey    = 'Id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    // Corrige encoding automáticamente en cada consulta vía modelo
    protected $afterFind = ['fixEncoding'];

    protected $allowedFields = [
        'nombre',
        'usuario',
        'mail',
        'pass',
        'acceso',
        'celular',
        'tel',
        'dato1',
        'dato2',
        'notificaciones',
        'bandera',
        'eliminado',
    ];

    // Sin timestamps automáticos (la tabla no los tiene)
    protected $useTimestamps = false;

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Busca un usuario por email y contraseña para el login.
     * Solo devuelve usuarios activos (no eliminados).
     */
    public function verificarLogin(string $mail, string $pass): array|null
    {
        return $this->where('mail', $mail)
                    ->where('pass', $pass)
                    ->where('eliminado', 0)
                    ->first();
    }

    /**
     * Devuelve todos los usuarios activos ordenados por Id.
     */
    public function getTodos(): array
    {
        return $this->where('eliminado', 0)->orderBy('Id', 'ASC')->findAll();
    }

    /**
     * Marca al usuario como con ticket activo (bandera = 1).
     */
    public function activarBandera(int $id): bool
    {
        return $this->update($id, ['bandera' => 1]);
    }

    /**
     * Libera al usuario (bandera = 0).
     */
    public function liberarBandera(int $id): bool
    {
        return $this->update($id, ['bandera' => 0]);
    }

    /**
     * Actualiza la contraseña de un usuario.
     */
    public function cambiarPass(int $id, string $pass): bool
    {
        return $this->update($id, ['pass' => $pass]);
    }

    /**
     * Soft-delete: marca el usuario como eliminado.
     */
    public function softDelete(int $id): void
    {
        \Config\Database::connect()
            ->query("UPDATE usuarios SET eliminado = 1 WHERE Id = ?", [$id]);
    }

    /**
     * Restaura un usuario eliminado.
     */
    public function restaurar(int $id): void
    {
        \Config\Database::connect()
            ->query("UPDATE usuarios SET eliminado = 0 WHERE Id = ?", [$id]);
    }

    /**
     * Datatable server-side para usuarios eliminados.
     */
    public function getDatatableEliminados(int $start, int $length, string $search, string $orderDir): array
    {
        $db = \Config\Database::connect();

        $total = (int) $db->table($this->table)->where('eliminado', 1)->countAllResults();

        $q = $db->table($this->table)->where('eliminado', 1);

        if ($search !== '') {
            $q->groupStart()
              ->like('nombre', $search)
              ->orLike('mail', $search)
              ->groupEnd();
        }

        $filtered = (int) (clone $q)->countAllResults(false);

        $data = $q->orderBy('Id', strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC')
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
