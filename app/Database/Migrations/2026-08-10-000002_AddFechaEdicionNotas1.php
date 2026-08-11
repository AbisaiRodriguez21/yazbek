<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración: Agrega campo fecha_edicion a notas_1.
 *
 * Se llena únicamente cuando el Admin edita los productos de una nota que ya
 * había sido finalizada (status 2, "En proceso", pendiente de caja) y la
 * reconfirma en el Paso 3. Sirve como aviso visible de que el folio cambió
 * después de su primera finalización. Queda NULL si nunca se edita.
 *
 * Ejecutar: php spark migrate
 * Deshacer: php spark migrate:rollback
 */
class AddFechaEdicionNotas1 extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('notas_1', [
            'fecha_edicion' => [
                'type'    => 'TIMESTAMP',
                'null'    => true,
                'default' => null,
                'after'   => 'fecha_final',
                'comment' => 'Se llena al reconfirmar Paso 3 de una nota ya en proceso, tras editar productos',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('notas_1', 'fecha_edicion');
    }
}
