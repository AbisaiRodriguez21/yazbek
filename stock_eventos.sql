-- ============================================================
--  Tabla de eventos de stock para sincronización en tiempo real
--  Ejecutar una sola vez en la base de datos del proyecto Yazbek
-- ============================================================

CREATE TABLE IF NOT EXISTS stock_eventos (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku         VARCHAR(50)  NOT NULL,
    nuevo_stock INT          NOT NULL,
    creado_en   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limpia automáticamente eventos mayores a 60 segundos
-- (evita que la tabla crezca indefinidamente)
-- Nota: requiere que el Event Scheduler esté activo en MySQL.
-- Si no lo está, ejecuta también: SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS limpiar_stock_eventos
    ON SCHEDULE EVERY 30 SECOND
    DO DELETE FROM stock_eventos WHERE creado_en < NOW() - INTERVAL 60 SECOND;
