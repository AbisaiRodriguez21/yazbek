<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración: Agrega observaciones_factura a notas_1.
 *
 * Solicitado en observaciones del sistema: al momento de facturar (antes de
 * timbrar) agregar un apartado de observaciones para escribir notas y
 * números de ticket, que quede impreso en el PDF de la factura.
 *
 * Ejecutar: php spark migrate
 * Deshacer: php spark migrate:rollback
 */
class AddObservacionesFacturaNotas1 extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('notas_1', [
            'observaciones_factura' => [
                'type'       => 'TEXT',
                'null'       => true,
                'default'    => null,
                'after'      => 'forma_pago_cfdi',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('notas_1', ['observaciones_factura']);
    }
}
