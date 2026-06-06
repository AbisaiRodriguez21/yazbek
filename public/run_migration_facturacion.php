<?php
/**
 * Migración manual: Agrega campo status_facturacion a notas_1
 * ELIMINAR este archivo después de ejecutarlo una sola vez.
 *
 * Acceder en: http://localhost:8080/run_migration_facturacion.php
 */

$host = 'localhost';
$db   = 'nissipro_0525';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pasos = [];

    // 1. Verificar si la columna ya existe
    $existe = $pdo->query("SHOW COLUMNS FROM notas_1 LIKE 'status_facturacion'")->rowCount() > 0;

    if ($existe) {
        $pasos[] = ['ok' => true,  'msg' => 'La columna status_facturacion ya existe — nada que hacer.'];
    } else {
        // 2. Agregar columna
        $pdo->exec("ALTER TABLE notas_1 ADD COLUMN status_facturacion TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=No Requerida, 1=Pendiente, 2=Facturada'");
        $pasos[] = ['ok' => true, 'msg' => 'Columna status_facturacion agregada correctamente.'];

        // 3. Sincronizar datos existentes
        $afectadas = $pdo->exec("
            UPDATE notas_1
            SET status_facturacion = CASE
                WHEN uuid_fiscal IS NOT NULL AND uuid_fiscal <> '' THEN 2
                WHEN factura = 1                                   THEN 1
                ELSE 0
            END
        ");
        $pasos[] = ['ok' => true, 'msg' => "Notas sincronizadas: {$afectadas} filas actualizadas."];
    }

    // 4. Mostrar conteo
    $conteo = $pdo->query("SELECT status_facturacion, COUNT(*) AS cnt FROM notas_1 GROUP BY status_facturacion ORDER BY status_facturacion")->fetchAll(PDO::FETCH_ASSOC);
    $labels = [0 => 'No Requerida', 1 => 'Pendiente por Facturar', 2 => 'Facturada'];
    foreach ($conteo as $row) {
        $label = $labels[(int)$row['status_facturacion']] ?? 'Desconocido';
        $pasos[] = ['ok' => true, 'msg' => "→ {$label}: {$row['cnt']} notas"];
    }

    $exito = true;
} catch (Throwable $e) {
    $pasos[] = ['ok' => false, 'msg' => 'ERROR: ' . $e->getMessage()];
    $exito   = false;
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Migración — status_facturacion</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 700px; margin: 60px auto; padding: 0 20px; }
  h2   { color: #333; }
  .ok  { color: #198754; }
  .err { color: #dc3545; }
  .box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin-top: 20px; }
  .badge-ok  { background: #198754; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
  .badge-err { background: #dc3545; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
  .warning   { background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px 16px; margin-top: 20px; font-size: 13px; }
</style>
</head>
<body>
<h2>Migración: <code>status_facturacion</code></h2>
<div class="box">
<?php foreach ($pasos as $p): ?>
  <p <?= $p['ok'] ? 'class="ok"' : 'class="err"' ?>>
      <span class="<?= $p['ok'] ? 'badge-ok' : 'badge-err' ?>"><?= $p['ok'] ? 'OK' : 'ERROR' ?></span>
      &nbsp;<?= htmlspecialchars($p['msg']) ?>
  </p>
<?php endforeach; ?>
</div>

<?php if ($exito): ?>
<div class="warning">
    ⚠️ <strong>Elimina este archivo</strong> una vez que la migración esté completa:<br>
    <code>public/run_migration_facturacion.php</code>
</div>
<p style="margin-top:20px;">
    <a href="/admin/consulta" style="background:#0d6efd;color:#fff;padding:8px 18px;border-radius:4px;text-decoration:none;">
        → Ir a Consulta Folios
    </a>
</p>
<?php endif; ?>
</body>
</html>
