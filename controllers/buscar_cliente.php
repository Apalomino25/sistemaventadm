<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conexion.php';

$q = trim($_GET['q'] ?? '');

if($q === ''){
    echo json_encode(['clientes' => []]);
    exit;
}

$like = '%' . $q . '%';

$stmt = $conn->prepare("
    SELECT clienteID, nombre, tipoDocumento, numeroDocumento, telefono, direccion
    FROM clientes
    WHERE nombre LIKE ?
       OR numeroDocumento LIKE ?
       OR telefono LIKE ?
    ORDER BY
       CASE WHEN nombre LIKE ? THEN 0 ELSE 1 END,
       nombre ASC
    LIMIT 12
");
$stmt->execute([$like, $like, $like, $q . '%']);

echo json_encode(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
?>
