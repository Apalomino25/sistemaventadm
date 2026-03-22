<?php
header('Content-Type: application/json');
require_once "../config/conexion.php";

// Recibir JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['ventaID'])) {
    echo json_encode(["ok"=>false, "error"=>"No se recibió ventaID"]);
    exit;
}

$ventaID = intval($data['ventaID']);

try {
    $stmt = $conn->prepare("UPDATE ventas SET estado = 0 WHERE ventaID = :ventaID");
    $stmt->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(["ok"=>true]);
} catch (Exception $e) {
    echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
}