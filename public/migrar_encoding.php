<?php
/**
 * ============================================================
 * MIGRACIÓN DE ENCODING — CORRER UNA SOLA VEZ
 * Acceder en: http://localhost:8080/migrar_encoding.php
 * BORRAR este archivo después de ejecutar exitosamente.
 * ============================================================
 *
 * Qué hace:
 *  1. Reemplaza patrones de bytes corruptos conocidos (ej: Ñ → 3f e2 d4 c7 ff)
 *  2. Para datos con UTF-8 válido: los reescribe correctamente vía conexión utf8mb4
 *     (MySQL los guarda como latin1 limpio)
 *  3. Para datos en latin1 puro: los convierte a UTF-8 y los guarda
 *
 * Resultado: todas las columnas quedan con latin1 "limpio" y CI4 las
 * lee correctamente sin necesidad de fix_enc.
 * ============================================================
 */

// ── Seguridad: solo local ─────────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (! in_array($ip, ['127.0.0.1', '::1'])) {
    http_response_code(403);
    exit('Solo desde localhost.');
}

// ── Doble confirmación ────────────────────────────────────
$ejecutar = isset($_GET['run']) && $_GET['run'] === 'SI_EJECUTAR';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<title>Migración Encoding</title>
<style>
  body{font-family:monospace;background:#111;color:#ccc;padding:20px}
  .ok{color:#4f4}  .err{color:#f44}  .warn{color:#fa0}
  .box{background:#1a1a1a;padding:15px;border-radius:6px;margin-bottom:15px}
  h2{color:#fff} pre{margin:0;line-height:1.6}
  a.btn{display:inline-block;margin:10px 0;padding:10px 25px;background:#e44;
        color:#fff;text-decoration:none;border-radius:5px;font-size:16px}
</style>
</head><body>
<h2>🔧 Migración de Encoding — nissipro_0525</h2>
<?php

if (! $ejecutar): ?>
<div class="box">
  <p class="warn">⚠️  Este script modifica TODOS los campos de texto de las tablas:</p>
  <pre>  • clientes  (nombre, direccion, ciudad, estado, NombreEmpresa, razonSocial)
  • usuarios   (nombre, usuario)
  • productosyazbek (estilo, Descripcion_corta, Descripcion_Larga, Color, Talla)</pre>
  <p>Asegúrate de tener un respaldo antes de continuar.</p>
  <a class="btn" href="?run=SI_EJECUTAR">✅ Ejecutar migración</a>
</div>
<?php exit; endif;

// ═══════════════════════════════════════════════════════════
// CONEXIONES
// ═══════════════════════════════════════════════════════════
$conn_r = new mysqli('localhost', 'root', '', 'nissipro_0525'); // lectura latin1
if ($conn_r->connect_error) die('<span class="err">Error conexión latin1: '.$conn_r->connect_error.'</span>');
$conn_r->set_charset('latin1');

$conn_w = new mysqli('localhost', 'root', '', 'nissipro_0525'); // escritura utf8mb4
if ($conn_w->connect_error) die('<span class="err">Error conexión utf8mb4: '.$conn_w->connect_error.'</span>');
$conn_w->set_charset('utf8mb4');

// ═══════════════════════════════════════════════════════════
// PATRONES CORRUPTOS CONOCIDOS (bytes brutos → reemplazo en latin1)
// Identificados con diagnostico_enc.php
// ═══════════════════════════════════════════════════════════
$patrones_corruptos = [
    // Ñ → bytes 3f e2 d4 c7 ff
    "\x3f\xe2\xd4\xc7\xff" => "\xd1",   // Ñ en latin1

    // Posibles variantes de otros caracteres (agrega si aparecen más):
    // "\x3f\xe2\xd4\xc3\xbf" => "\xda",  // Ú
];

// ═══════════════════════════════════════════════════════════
// TABLAS Y COLUMNAS A MIGRAR
// ═══════════════════════════════════════════════════════════
$tablas = [
    'clientes'      => ['pk' => 'id',  'cols' => ['nombre','direccion','ciudad','estado','NombreEmpresa','razonSocial']],
    'usuarios'      => ['pk' => 'Id',  'cols' => ['nombre','usuario']],
    'productosyazbek'=>['pk' => 'id',  'cols' => ['estilo','Descripcion_corta','Descripcion_Larga','Color','Talla']],
];

$total_fixed   = 0;
$total_already = 0;
$total_errors  = 0;

echo '<div class="box"><pre>';

foreach ($tablas as $tabla => $cfg) {
    $pk   = $cfg['pk'];
    $cols = $cfg['cols'];

    echo "\n<b style='color:#fff'>━━━ $tabla ━━━</b>\n";

    foreach ($cols as $col) {

        $res = $conn_r->query("SELECT `$pk`, `$col` FROM `$tabla`");
        if (! $res) {
            echo "<span class='err'>  ✗ $col : {$conn_r->error}</span>\n";
            continue;
        }

        $fixed = 0; $already_ok = 0; $errs = 0;

        while ($row = $res->fetch_assoc()) {
            $id  = $row[$pk];
            $val = $row[$col];

            if ($val === null || $val === '') continue;

            // ── Paso 1: reemplazar patrones corruptos conocidos ──
            $nuevo = $val;
            foreach ($patrones_corruptos as $malo => $bueno) {
                $nuevo = str_replace($malo, $bueno, $nuevo);
            }

            // ── Paso 2: normalizar a UTF-8 correcto ──────────────
            if (mb_check_encoding($nuevo, 'UTF-8')) {
                // Son bytes UTF-8 guardados en columna latin1.
                // Al escribir con utf8mb4, MySQL los guarda como latin1 limpio.
                $final = $nuevo;
            } else {
                // Bytes latin1 puros (ej: é=0xE9, ñ=0xF1, etc.)
                // Convertir a UTF-8 para que MySQL los procese correctamente.
                $final = mb_convert_encoding($nuevo, 'UTF-8', 'ISO-8859-1');
            }

            // ── Paso 3: comparar y actualizar solo si cambió ─────
            // Leer valor actual con utf8mb4 para comparar
            $res2 = $conn_w->query("SELECT `$col` FROM `$tabla` WHERE `$pk`=$id LIMIT 1");
            $actual = $res2 ? $res2->fetch_assoc()[$col] : null;

            if ($actual === $final) {
                $already_ok++;
                continue;
            }

            $stmt = $conn_w->prepare("UPDATE `$tabla` SET `$col`=? WHERE `$pk`=?");
            if (! $stmt) {
                $errs++;
                continue;
            }
            $stmt->bind_param('ss', $final, $id);
            if ($stmt->execute()) {
                $fixed++;
            } else {
                $errs++;
                echo "<span class='err'>  ✗ $col id=$id : {$stmt->error}</span>\n";
            }
            $stmt->close();
        }

        $total_fixed   += $fixed;
        $total_already += $already_ok;
        $total_errors  += $errs;

        $status = $errs > 0 ? "<span class='err'>✗</span>" : "<span class='ok'>✓</span>";
        echo "  $status  $col : <span class='ok'>$fixed actualizados</span>, $already_ok ya OK, <span class='err'>$errs errores</span>\n";
    }
}

echo "\n<b style='color:#fff'>━━━ RESUMEN ━━━</b>\n";
echo "<span class='ok'>Total actualizados : $total_fixed</span>\n";
echo "Ya estaban correctos: $total_already\n";
if ($total_errors > 0)
    echo "<span class='err'>Errores            : $total_errors</span>\n";
else
    echo "<span class='ok'>Errores            : 0  ✓</span>\n";

echo '</pre></div>';

$conn_r->close();
$conn_w->close();

if ($total_errors === 0): ?>
<div class="box">
  <p class="ok">✅ Migración completada sin errores.</p>
  <p class="warn">⚠️  Borra o renombra este archivo ahora:<br>
  <code>public/migrar_encoding.php</code></p>
</div>
<?php else: ?>
<div class="box">
  <p class="err">❌ Hubo errores, revisa los mensajes arriba.</p>
</div>
<?php endif; ?>
</body></html>
