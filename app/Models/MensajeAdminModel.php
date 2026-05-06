<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * MensajeAdminModel — tabla mensajesadmin
 *
 * Se usa para mostrar avisos/imágenes en los dashboards de cada módulo.
 *   Id = 1 → banner/imagen principal
 *   Id = 2 → mensaje de texto
 */
class MensajeAdminModel extends Model
{
    protected $table         = 'mensajesadmin';
    protected $primaryKey    = 'Id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'imagen',
        'fecha',
        't_mensaje',
        'texto',
    ];

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Obtiene el banner principal (Id = 1).
     */
    public function getBanner(): array|null
    {
        return $this->find(1);
    }

    /**
     * Obtiene el mensaje de texto (Id = 2).
     */
    public function getMensaje(): array|null
    {
        return $this->find(2);
    }

    /**
     * Obtiene todos los mensajes ordenados por Id DESC (igual que el original).
     */
    public function getTodos(): array
    {
        return $this->orderBy('Id', 'DESC')->findAll();
    }
}
