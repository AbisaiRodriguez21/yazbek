<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración: Agrega regimenFiscal y numero a clientes.
 *
 * Solicitado en observaciones del sistema: capturar el régimen fiscal SAT
 * del cliente al darlo de alta (antes solo se pedía al facturar) y separar
 * el número exterior/interior de la dirección en su propio campo.
 *
 * Ejecutar: php spark migrate
 * Deshacer: php spark migrate:rollback
 */
class AddRegimenFiscalNumeroClientes extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('clientes', [
            // Régimen fiscal SAT (616, 601, 612, 621, 626, 605...)
            'regimenFiscal' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'null'       => true,
                'default'    => null,
                'after'      => 'RFC',
            ],
            // Número exterior/interior de la dirección
            'numero' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
                'after'      => 'direccion',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('clientes', ['regimenFiscal', 'numero']);
    }
}
