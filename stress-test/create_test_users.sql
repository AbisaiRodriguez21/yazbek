-- Yazbek · Stress test — usuarios sintéticos para simular logins concurrentes.
-- Re-ejecutable: borra e inserta de nuevo. NO reutiliza cuentas reales del personal
-- para no ensuciar audit_log con actividad falsa atribuida a empleados de verdad.
--
-- Uso:
--   "C:\xampp\mysql\bin\mysql.exe" -u root nissipro_0525 < create_test_users.sql

DELETE FROM usuarios WHERE mail LIKE 'loadtest\_%@stress.local';

INSERT INTO usuarios (nombre, usuario, mail, pass, acceso, eliminado) VALUES
('LoadTest Admin 1',     'loadtest_admin_1',     'loadtest_admin_1@stress.local',     'LoadTest#2026', 1, 0),
('LoadTest Admin 2',     'loadtest_admin_2',     'loadtest_admin_2@stress.local',     'LoadTest#2026', 1, 0),
('LoadTest Caja 1',      'loadtest_caja_1',      'loadtest_caja_1@stress.local',      'LoadTest#2026', 2, 0),
('LoadTest Caja 2',      'loadtest_caja_2',      'loadtest_caja_2@stress.local',      'LoadTest#2026', 2, 0),
('LoadTest Caja 3',      'loadtest_caja_3',      'loadtest_caja_3@stress.local',      'LoadTest#2026', 2, 0),
('LoadTest Mostrador 1', 'loadtest_mostrador_1', 'loadtest_mostrador_1@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 2', 'loadtest_mostrador_2', 'loadtest_mostrador_2@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 3', 'loadtest_mostrador_3', 'loadtest_mostrador_3@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 4', 'loadtest_mostrador_4', 'loadtest_mostrador_4@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 5', 'loadtest_mostrador_5', 'loadtest_mostrador_5@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 6', 'loadtest_mostrador_6', 'loadtest_mostrador_6@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 7', 'loadtest_mostrador_7', 'loadtest_mostrador_7@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 8', 'loadtest_mostrador_8', 'loadtest_mostrador_8@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 9', 'loadtest_mostrador_9', 'loadtest_mostrador_9@stress.local', 'LoadTest#2026', 3, 0),
('LoadTest Mostrador 10','loadtest_mostrador_10','loadtest_mostrador_10@stress.local', 'LoadTest#2026', 3, 0);
