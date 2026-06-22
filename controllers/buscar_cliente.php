<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conexion.php';

$q = trim($_GET['q'] ?? '');

if($q === ''){
    $stmt = $conn->prepare("
        SELECT clienteID, nombre, tipoDocumento, numeroDocumento, telefono, direccion
        FROM clientes
        ORDER BY
           CASE WHEN LOWER(nombre) IN ('cliente general', 'general') THEN 0 ELSE 1 END,
           clienteID DESC
        LIMIT 10
    ");
    $stmt->execute();

    echo json_encode([
        'clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'modo' => 'recientes'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';
$inicio = $q . '%';

$stmt = $conn->prepare("
    SELECT clienteID, nombre, tipoDocumento, numeroDocumento, telefono, direccion
    FROM clientes
    WHERE (
        nombre LIKE ?
        OR numeroDocumento LIKE ?
        OR telefono LIKE ?
      )
    ORDER BY
       CASE
          WHEN nombre LIKE ? THEN 0
          WHEN numeroDocumento LIKE ? THEN 1
          ELSE 2
       END,
       nombre ASC
    LIMIT 12
");
$stmt->execute([$like, $like, $like, $inicio, $inicio]);

echo json_encode([
    'clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    'modo' => 'busqueda'
], JSON_UNESCAPED_UNICODE);
?>
