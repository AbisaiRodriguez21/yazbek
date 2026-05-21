<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLiquidadoStatus extends Migration
{
    public function up(): void
    {
        // Agregar status 6 = Liquidado si no existe
        $exists = $this->db->query(
            "SELECT id FROM status WHERE id = 6 LIMIT 1"
        )->getRowArray();

        if (! $exists) {
            $this->db->query(
                "INSERT INTO status (id, nombre) VALUES (6, 'Liquidado')"
            );
        }
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM status WHERE id = 6");
    }
}
