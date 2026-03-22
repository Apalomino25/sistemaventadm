<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if(!isset($data['cierres']) || empty($data['cierres'])){
    echo json_encode(['ok'=>false, 'error'=>'No se recibieron datos']);
    exit;
}

$usuarioID = $_SESSION['usuarioID'] ?? 1; // cambiar según tu sesión
$hoy = date('Y-m-d');

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO cierres (fecha, tipopago, total_ventas, total_recibido, fisico, observacion, usuarioID)
        VALUES (:fecha, :tipopago, :total_ventas, :total_recibido, :fisico, :observacion, :usuarioID)
    ");

    foreach($data['cierres'] as $cierre){
        $total_recibido = floatval($cierre['total_recibido'] ?? 0);
        $total_ventas   = floatval($cierre['total_ventas'] ?? 0);
        $fisico         = floatval($cierre['fisico'] ?? 0);
        $diferencia     = $fisico - $total_recibido;
        $observacion    = ($diferencia != 0) ? "Diferencia de S/ ".number_format($diferencia,2) : '';

        $stmt->execute([
            ':fecha' => $hoy,
            ':tipopago' => $cierre['tipopago'],
            ':total_ventas' => $total_ventas,
            ':total_recibido' => $total_recibido,
            ':fisico' => $fisico,
            ':observacion' => $observacion,
            ':usuarioID' => $usuarioID
        ]);
    }

    $conn->commit();
    echo json_encode(['ok'=>true]);
} catch(PDOException $e){
    $conn->rollBack();
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}