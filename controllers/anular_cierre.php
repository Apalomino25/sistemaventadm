<?php
require_once "../config/conexion.php";

$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['cierreID'])){
    $id = intval($data['cierreID']);
    $stmt = $conn->prepare("UPDATE cierres SET estado = 0 WHERE cierreID = ?");
    if($stmt->execute([$id])){
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo anular']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
}