# Stress test de logins concurrentes — Yazbek

Simula varios usuarios logueados al mismo tiempo (login → dashboard → logout)
usando cuentas sintéticas dedicadas, sin tocar cuentas reales del personal.

## 1. Crear los usuarios de prueba (una sola vez)

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root nissipro_0525 < stress-test/create_test_users.sql
```

Crea 15 cuentas `loadtest_*@stress.local` (2 admin, 3 caja, 10 mostrador),
contraseña `LoadTest#2026`. Es re-ejecutable: borra y vuelve a crear.

## 2. Correr la prueba

Con el servidor de desarrollo (`php spark serve`, puerto 8080) ya corriendo:

```bash
k6 run stress-test/login-stress-test.js
```

Por defecto: rampa de 0→20 usuarios en 20s, se mantiene 60s, baja en 15s.

Ajustar la carga sin editar el archivo:

```bash
k6 run -e MAX_VUS=40 -e RAMP_UP=30s -e HOLD_TIME=2m stress-test/login-stress-test.js
```

### Importante sobre `localhost:8080` en este equipo

En esta máquina el puerto 8080 tiene **dos procesos distintos** escuchando:
el servidor de Yazbek (solo en IPv6, `::1`) y otra aplicación ajena (IPv4,
`0.0.0.0`). Por eso el script usa `http://[::1]:8080` por defecto. Si algún
día cambia, verifica primero con:

```bash
curl http://127.0.0.1:8080/login   # si esto NO es la pantalla de login de Yazbek, hay conflicto de puerto
curl http://[::1]:8080/login       # este debe ser Yazbek
```

Si vas a probar contra Apache/XAMPP en vez del servidor de desarrollo:

```bash
k6 run -e BASE_URL=http://localhost/yazbek/public stress-test/login-stress-test.js
```

## 3. Interpretar resultados

- `http_req_failed` / `login_failures` en 0% = el sistema aguantó la carga
  sin romperse (sin 500, sin sesiones cruzadas, sin logins fallidos).
- `http_req_duration p(95)` alto (varios segundos) con 0% de fallos = no se
  cayó, solo se puso lento. Con el servidor embebido de PHP (`php spark
  serve`) esto es esperado: en Windows no soporta múltiples workers
  (`PHP_CLI_SERVER_WORKERS` requiere `pcntl`, no disponible), así que
  procesa las peticiones **una a la vez**. Para medir concurrencia real
  (varias peticiones en paralelo, no en fila) sirve el proyecto con
  Apache/XAMPP en vez del dev server.

## 4. Limpiar cuentas de prueba

Cuando termines de probar del todo:

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root nissipro_0525 < stress-test/cleanup_test_users.sql
```

Borra las 15 cuentas `loadtest_*` y sus entradas en `audit_log`.
