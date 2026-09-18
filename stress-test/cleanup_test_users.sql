-- Yazbek · Stress test — limpieza de usuarios sintéticos y su rastro en audit_log.
--
-- Uso:
--   "C:\xampp\mysql\bin\mysql.exe" -u root nissipro_0525 < cleanup_test_users.sql

DELETE FROM audit_log WHERE usuario_nombre LIKE 'LoadTest %';
DELETE FROM usuarios WHERE mail LIKE 'loadtest\_%@stress.local';
