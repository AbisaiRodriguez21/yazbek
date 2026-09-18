// Yazbek · Stress test de logins concurrentes
//
// Simula varios usuarios logueados al mismo tiempo (login -> actividad ->
// logout) con una rampa gradual, para verificar que el sistema aguanta sin
// caerse. Usa las cuentas sintéticas creadas por create_test_users.sql — NO
// toca cuentas reales del personal.
//
// Requisitos:
//   1. k6 instalado (ya está en este equipo: C:\Program Files\k6\k6.exe)
//   2. Haber corrido create_test_users.sql contra la BD
//   3. El servidor de la app corriendo (php spark serve, o Apache/XAMPP)
//
// Uso básico (servidor de desarrollo en :8080):
//   k6 run stress-test/login-stress-test.js
//
// Ajustar carga sin tocar el archivo:
//   k6 run -e MAX_VUS=40 -e RAMP_UP=30s -e HOLD_TIME=2m stress-test/login-stress-test.js
//
// Contra Apache/XAMPP (recomendado para medir concurrencia real, ya que el
// servidor embebido de PHP en Windows atiende una petición a la vez):
//   k6 run -e BASE_URL=http://localhost/yazbek/public stress-test/login-stress-test.js

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Rate } from 'k6/metrics';

// OJO: en esta máquina el puerto 8080 tiene DOS procesos escuchando —
// el servidor de Yazbek solo en IPv6 (::1) y otra app ajena en IPv4
// (0.0.0.0). "localhost" puede resolver a cualquiera de las dos según
// la herramienta, así que usamos la dirección IPv6 explícita por defecto
// para no pegarle a la app equivocada. Verifica con:
//   curl http://127.0.0.1:8080/login   (¿te contesta Yazbek o algo más?)
//   curl http://[::1]:8080/login       (este SÍ debe ser Yazbek)
const BASE_URL = __ENV.BASE_URL || 'http://[::1]:8080';

// Debe coincidir con create_test_users.sql
const USERS = [
  { email: 'loadtest_admin_1@stress.local', pass: 'LoadTest#2026', home: '/admin' },
  { email: 'loadtest_admin_2@stress.local', pass: 'LoadTest#2026', home: '/admin' },
  { email: 'loadtest_caja_1@stress.local', pass: 'LoadTest#2026', home: '/caja' },
  { email: 'loadtest_caja_2@stress.local', pass: 'LoadTest#2026', home: '/caja' },
  { email: 'loadtest_caja_3@stress.local', pass: 'LoadTest#2026', home: '/caja' },
  { email: 'loadtest_mostrador_1@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_2@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_3@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_4@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_5@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_6@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_7@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_8@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_9@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
  { email: 'loadtest_mostrador_10@stress.local', pass: 'LoadTest#2026', home: '/mostrador' },
];

const loginFailures = new Rate('login_failures');
const loginSuccesses = new Counter('login_successes');

const MAX_VUS = Number(__ENV.MAX_VUS) || 20;

export const options = {
  scenarios: {
    concurrent_logins: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: __ENV.RAMP_UP || '20s', target: MAX_VUS }, // sube gradual, no de golpe
        { duration: __ENV.HOLD_TIME || '60s', target: MAX_VUS }, // mantiene N usuarios "logueados" activos
        { duration: __ENV.RAMP_DOWN || '15s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: {
    // Si estos umbrales se rompen, algo del sistema sí está sufriendo.
    http_req_failed: ['rate<0.05'],
    login_failures: ['rate<0.05'],
    http_req_duration: ['p(95)<3000'],
  },
};

function locationOf(res) {
  return res.headers['Location'] || res.headers['location'] || '';
}

export default function () {
  const user = USERS[__VU % USERS.length];

  group('login', () => {
    const loginPage = http.get(`${BASE_URL}/login`, { tags: { name: 'GET /login' } });
    check(loginPage, { 'pantalla de login carga (200)': (r) => r.status === 200 });

    const res = http.post(
      `${BASE_URL}/login`,
      { email: user.email, pass: user.pass },
      { redirects: 0, tags: { name: 'POST /login' } }
    );

    // CodeIgniter 4 responde 303 See Other en los redirect() tras un POST.
    const ok = (res.status === 302 || res.status === 303) && !locationOf(res).includes('/login');
    loginFailures.add(!ok);
    if (ok) loginSuccesses.add(1);

    check(res, {
      'login redirige (302/303)': (r) => r.status === 302 || r.status === 303,
      'no rebota de vuelta a /login': (r) => !locationOf(r).includes('/login'),
    });
  });

  sleep(Math.random() * 2 + 1); // pausa tipo "usuario real", no ráfaga instantánea

  group('actividad autenticada', () => {
    const dash = http.get(`${BASE_URL}${user.home}`, { tags: { name: 'GET dashboard' } });
    check(dash, { 'dashboard accesible logueado (200)': (r) => r.status === 200 });

    const poll = http.get(`${BASE_URL}/stock/poll`, { tags: { name: 'GET stock/poll' } });
    check(poll, { 'stock/poll responde (200)': (r) => r.status === 200 });
  });

  sleep(Math.random() * 3 + 1);

  group('logout', () => {
    const out = http.get(`${BASE_URL}/logout`, { redirects: 0, tags: { name: 'GET /logout' } });
    check(out, { 'logout redirige (302/303)': (r) => r.status === 302 || r.status === 303 });
  });
}
