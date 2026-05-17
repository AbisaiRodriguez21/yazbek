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
    ];

    // Sin timestamps automáticos (la tabla no los tiene)
    protected $useTimestamps = false;

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Busca un usuario por email y contraseña para el login.
     */
    public function verificarLogin(string $mail, string $pass): array|null
    {
        return $this->where('mail', $mail)
                    ->where('pass', $pass)
                    ->first();
    }

    /**
     * Devuelve todos los usuarios ordenados por Id.
     */
    public function getTodos(): array
    {
        return $this->orderBy('Id', 'ASC')->findAll();
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
