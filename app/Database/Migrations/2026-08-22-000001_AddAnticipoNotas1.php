<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración: Agrega anticipo a notas_1.
 *
 * Hasta ahora, si un folio hijo (referencia > 0) era o no "anticipo" se
 * decidía a fuerza en Caja (cualquier folio hijo = anticipo=1). Con esto el
 * vendedor puede marcar, al registrar el abono, si es un anticipo (parcial)
 * o si liquida la nota — y Caja respeta esa elección al confirmar el pago.
 *
 * Default = 1 para no cambiar el comportamiento de folios hijo ya
 * existentes ni de otros flujos que los crean.
 *
 * Ejecutar: php spark migrate
 * Deshacer: php spark migrate:rollback
 */
class AddAnticipoNotas1 extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('notas_1', [
            'anticipo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
                'after'      => 'referencia',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('notas_1', ['anticipo']);
    }
}
