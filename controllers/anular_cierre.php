<?php
header('Content-Type: application/json');

require_once __DIR__ . "/../config/conexion.php";

$data = json_decode(file_get_contents("php://input"), true);
$id = trim($data['cierreID'] ?? $data['fecha'] ?? '');

if($id === ''){
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

try {
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $id)){
        $stmt = $conn->prepare("UPDATE cierres SET estado = 0 WHERE fecha = ? AND estado = 1");
        $stmt->execute([$id]);
    } else {
        $stmtFecha = $conn->prepare("SELECT fecha FROM cierres WHERE cierreID = ? LIMIT 1");
        $stmtFecha->execute([intval($id)]);
        $fecha = $stmtFecha->fetchColumn();

        if(!$fecha){
            echo json_encode(['success' => false, 'error' => 'Cierre no encontrado']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE cierres SET estado = 0 WHERE fecha = ? AND estado = 1");
        $stmt->execute([$fecha]);
    }

    echo json_encode(['success' => true]);
} catch(Throwable $e){
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
