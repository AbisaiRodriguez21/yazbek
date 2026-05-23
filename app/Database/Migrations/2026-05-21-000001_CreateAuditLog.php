<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLog extends Migration
{
    public function up(): void
    {
        $exists = $this->db->query("SHOW TABLES LIKE 'audit_log'")->getRowArray();
        if ($exists) return;

        $this->db->query("
            CREATE TABLE `audit_log` (
                `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
                `fecha`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `usuario_id`      INT              NOT NULL DEFAULT 0,
                `usuario_nombre`  VARCHAR(80)      NOT NULL DEFAULT '',
                `rol`             TINYINT          NOT NULL DEFAULT 0
                                  COMMENT '1=admin 2=caja 3=mostrador 4=mostrador_may 0=sistema',
                `accion`          VARCHAR(50)      NOT NULL
                                  COMMENT 'VENTA_CREADA, NOTA_CANCELADA, LOGIN, etc.',
                `tabla`           VARCHAR(50)      NOT NULL DEFAULT '',
                `registro_id`     VARCHAR(80)      NULL
                                  COMMENT 'folio, id, sku, etc.',
                `descripcion`     VARCHAR(255)     NOT NULL DEFAULT '',
                `datos_antes`     JSON             NULL,
                `datos_despues`   JSON             NULL,
                `ip`              VARCHAR(45)      NULL,
                `ruta`            VARCHAR(200)     NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_fecha`    (`fecha`),
                INDEX `idx_usuario`  (`usuario_id`),
                INDEX `idx_accion`   (`accion`),
                INDEX `idx_tabla`    (`tabla`, `registro_id`),
                INDEX `idx_rol`      (`rol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `audit_log`");
    }
}
