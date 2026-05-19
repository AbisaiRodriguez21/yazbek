<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEliminadoToUsuarios extends Migration
{
    public function up(): void
    {
        // Agrega columna eliminado si no existe
        $db = \Config\Database::connect();
        $fields = $db->getFieldNames('usuarios');
        if (! in_array('eliminado', $fields)) {
            $this->forge->addColumn('usuarios', [
                'eliminado' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'default'    => 0,
                    'after'      => 'bandera',
                ],
            ]);
        }
    }

    public function down(): void
    {
        $db = \Config\Database::connect();
        $fields = $db->getFieldNames('usuarios');
        if (in_array('eliminado', $fields)) {
            $this->forge->dropColumn('usuarios', 'eliminado');
        }
    }
}
