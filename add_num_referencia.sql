-- Agrega el campo num_referencia a montosnotas para guardar el número de referencia
-- de pagos con Tarjeta Credito / Tarjeta Debito.
-- Ejecutar una sola vez.

ALTER TABLE montosnotas
  ADD COLUMN num_referencia VARCHAR(100) NULL DEFAULT NULL
  AFTER cargos;
