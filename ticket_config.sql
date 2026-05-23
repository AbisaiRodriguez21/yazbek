-- ──────────────────────────────────────────────────────────────
-- Tabla: ticket_config
-- Guarda los textos editables del ticket de impresión
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ticket_config` (
  `id`       INT          NOT NULL AUTO_INCREMENT,
  `clave`    VARCHAR(100) NOT NULL,
  `valor`    TEXT         NOT NULL,
  `etiqueta` VARCHAR(200) NOT NULL COMMENT 'Nombre legible para el formulario admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores por defecto (igual al ticket actual)
INSERT INTO `ticket_config` (`clave`, `valor`, `etiqueta`) VALUES
('empresa_razon_social', 'PROMOSHAPE S. DE R.L. DE C.V.',                     'Razón Social'),
('empresa_sucursal',     'Sucursal 27 Poniente 904. Col. Chulavista',          'Dirección / Sucursal'),
('empresa_ciudad',       'Puebla, Pue. C.P. 72420',                           'Ciudad y C.P.'),
('empresa_telefono',     'Tel/Fax: (222) 1 30 29 29 / 2 96 65 30',            'Teléfono'),
('empresa_email_web',    'yazbekpuebla@hotmail.com | www.yazbekpuebla.com',    'Correo y Web'),
('empresa_rfc',          'RFC PRO060620G97',                                   'RFC'),
('empresa_regimen',      'Régimen General de Personas Morales',                'Régimen Fiscal'),
('msg_fiscal',           'Este ticket NO es comprobante Fiscal. Si requiere FACTURA favor de solicitarla el mismo día de su compra.', 'Aviso Fiscal'),
('msg_cambios',          'ESTIMADO CLIENTE NO SE REALIZAN CAMBIOS NI DEVOLUCIONES.', 'Política de Cambios'),
('msg_quejas',           'QUEJAS: sugerencias@yazbekpuebla.com',               'Contacto / Quejas'),
('msg_privacidad',       'Lo invitamos a checar nuestro aviso de privacidad en nuestra página www.nissipromo.com.mx', 'Aviso de Privacidad')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);
