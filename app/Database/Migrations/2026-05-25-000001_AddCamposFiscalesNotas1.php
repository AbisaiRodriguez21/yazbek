<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración: Agrega campos fiscales a notas_1 para integración Dfacture.
 *
 * Ejecutar con:
 *   php spark migrate
 *
 * Deshacer con:
 *   php spark migrate:rollback
 */
class AddCamposFiscalesNotas1 extends Migration
{
    public function up(): void
    {
        $fields = [
            // UUID del CFDI timbrado por el SAT a través de Dfacture
            'uuid_fiscal' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
                'default'    => null,
                'after'      => 'factura',
            ],
            // RFC del receptor capturado en el cierre de nota
            'rfc_receptor' => [
                'type'       => 'VARCHAR',
                'constraint' => 13,
                'null'       => true,
                'default'    => null,
                'after'      => 'uuid_fiscal',
            ],
            // Razón social del receptor
            'razon_social_receptor' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
                'default'    => null,
                'after'      => 'rfc_receptor',
            ],
            // CP fiscal del receptor
            'cp_receptor' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'null'       => true,
                'default'    => null,
                'after'      => 'razon_social_receptor',
            ],
            // Uso del CFDI (S01, G01, G03, etc.)
            'uso_cfdi' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'null'       => true,
                'default'    => 'S01',
                'after'      => 'cp_receptor',
            ],
            // Régimen fiscal del receptor (616, 601, 612, etc.)
            'regimen_fiscal_receptor' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'null'       => true,
                'default'    => '616',
                'after'      => 'uso_cfdi',
            ],
            // Forma de pago CFDI (01=Efectivo, 03=Transferencia, etc.)
            'forma_pago_cfdi' => [
                'type'       => 'VARCHAR',
                'constraint' => 3,
                'null'       => true,
                'default'    => '01',
                'after'      => 'regimen_fiscal_receptor',
            ],
            // XML del CFDI timbrado (guardado por si se necesita reenviar)
            'cfdi_xml' => [
                'type' => 'MEDIUMTEXT',
                'null' => true,
                'after' => 'forma_pago_cfdi',
            ],
            // Fecha y hora en que se timbró
            'cfdi_fecha_timbrado' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'cfdi_xml',
            ],
            // Mensaje de error si el timbrado falló
            'cfdi_error' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
                'after'      => 'cfdi_fecha_timbrado',
            ],
        ];

        $this->forge->addColumn('notas_1', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('notas_1', [
            'uuid_fiscal',
            'rfc_receptor',
            'razon_social_receptor',
            'cp_receptor',
            'uso_cfdi',
            'regimen_fiscal_receptor',
            'forma_pago_cfdi',
            'cfdi_xml',
            'cfdi_fecha_timbrado',
            'cfdi_error',
        ]);
    }
}
