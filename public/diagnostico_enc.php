<?php
/**
 * DIAGNÓSTICO DE ENCODING — borrar después de usar
 * Acceder en: http://localhost:8080/diagnostico_enc.php
 */

$conn_latin1 = new mysqli('localhost', 'root', '', 'nissipro_0525');
$conn_latin1->set_charset('latin1');

$conn_utf8 = new mysqli('localhost', 'root', '', 'nissipro_0525');
$conn_utf8->set_charset('utf8mb4');

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px">';

echo "=== USUARIOS (primeros 5) ===\n";
$res = $conn_latin1->query("SELECT Id, nombre, usuario FROM usuarios LIMIT 5");
while ($row = $res->fetch_assoc()) {
    $nombre_raw = $row['nombre'] ?? '';
    $usuario_raw = $row['usuario'] ?? '';
    // Leer lo mismo con utf8mb4
    $res2 = $conn_utf8->query("SELECT nombre, usuario FROM usuarios WHERE Id={$row['Id']} LIMIT 1");
    $row2 = $res2->fetch_assoc();

    echo "ID {$row['Id']}:\n";
    echo "  nombre  latin1-hex : " . bin2hex($nombre_raw) . "\n";
    echo "  nombre  latin1-str : " . $nombre_raw . "\n";
    echo "  nombre  utf8mb4    : " . ($row2['nombre'] ?? '') . "\n";
    echo "  nombre  is_utf8    : " . (mb_check_encoding($nombre_raw, 'UTF-8') ? 'YES' : 'NO') . "\n";
    echo "  usuario latin1-hex : " . bin2hex($usuario_raw) . "\n";
    echo "  usuario utf8mb4    : " . ($row2['usuario'] ?? '') . "\n";
    echo "  usuario is_utf8    : " . (mb_check_encoding($usuario_raw, 'UTF-8') ? 'YES' : 'NO') . "\n\n";
}

echo "\n=== CLIENTES con acentos (primeros 5 que tengan chars > ASCII) ===\n";
$res = $conn_latin1->query(
    "SELECT id, nombre FROM clientes WHERE nombre REGEXP '[^A-Za-z0-9 .,-]' AND eliminado=0 LIMIT 5"
);
while ($row = $res->fetch_assoc()) {
    $nombre_raw = $row['nombre'] ?? '';
    $res2 = $conn_utf8->query("SELECT nombre FROM clientes WHERE id={$row['id']} LIMIT 1");
    $row2 = $res2->fetch_assoc();

    echo "ID {$row['id']}:\n";
    echo "  latin1-hex : " . bin2hex($nombre_raw) . "\n";
    echo "  latin1-str : " . $nombre_raw . "\n";
    echo "  utf8mb4    : " . ($row2['nombre'] ?? '') . "\n";
    echo "  is_utf8    : " . (mb_check_encoding($nombre_raw, 'UTF-8') ? 'YES' : 'NO') . "\n\n";
}

echo '</pre>';
$conn_latin1->close();
$conn_utf8->close();
