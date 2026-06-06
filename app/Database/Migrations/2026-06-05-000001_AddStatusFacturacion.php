<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración: Agrega campo status_facturacion a notas_1.
 *
 * Valores:
 *   0 = No Requerida  — el cliente no pidió factura al cerrar la nota
 *   1 = Pendiente     — datos fiscales guardados, aún no timbrado
 *   2 = Facturada     — CFDI timbrado (uuid_fiscal ya existe)
 *
 * Ejecutar: php spark migrate
 * Deshacer: php spark migrate:rollback
 */
class AddStatusFacturacion extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('notas_1', [
            'status_facturacion' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
                'after'      => 'cfdi_error',
                'comment'    => '0=No Requerida, 1=Pendiente, 2=Facturada',
            ],
        ]);

        // Sincronizar notas ya existentes:
        // - Si ya tienen uuid_fiscal → Facturada (2)
        // - Si tienen factura=1 pero sin uuid → Pendiente (1)
        // - El resto → No Requerida (0) — ya es el default
        $this->db->query(
            "UPDATE notas_1
             SET status_facturacion = CASE
                 WHEN uuid_fiscal IS NOT NULL AND uuid_fiscal <> '' THEN 2
                 WHEN factura = 1                                   THEN 1
                 ELSE 0
             END"
        );
    }

    public function down(): void
    {
        $this->forge->dropColumn('notas_1', 'status_facturacion');
    }
}
