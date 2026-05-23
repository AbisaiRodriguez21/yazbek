-- ──────────────────────────────────────────────────────────────
-- Tabla: audit_log
-- Registra todas las acciones importantes del sistema Yazbek
-- (ventas, cancelaciones, logins, etc.)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `fecha`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `usuario_id`      INT              NOT NULL DEFAULT 0,
    `usuario_nombre`  VARCHAR(80)      NOT NULL DEFAULT '',
    `rol`             TINYINT          NOT NULL DEFAULT 0
                      COMMENT '1=admin  2=caja  3=mostrador  4=mostrador_may  0=sistema',
    `accion`          VARCHAR(50)      NOT NULL
                      COMMENT 'VENTA_CREADA, NOTA_CANCELADA, LOGIN, LOGOUT, etc.',
    `tabla`           VARCHAR(50)      NOT NULL DEFAULT '',
    `registro_id`     VARCHAR(80)      NULL
                      COMMENT 'folio, id de registro, sku, etc.',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
