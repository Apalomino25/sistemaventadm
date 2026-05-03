<?php
header('Content-Type: application/json');
require_once "../config/conexion.php";
require_once "../config/schema_helpers.php";

// Recibir JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['ventaID'])) {
    echo json_encode(["ok"=>false, "error"=>"No se recibió ventaID"]);
    exit;
}

$ventaID = intval($data['ventaID']);

try {
    asegurarColumnasPagos($conn);

    $conn->beginTransaction();

    // 🔹 Verificar si ya está anulada
    $stmt = $conn->prepare("SELECT estado FROM ventas WHERE ventaID = :ventaID");
    $stmt->execute([':ventaID' => $ventaID]);
    $venta = $stmt->fetch();

    if ($venta && $venta['estado'] == 0) {
        echo json_encode(["ok"=>false, "error"=>"La venta ya está anulada"]);
        exit;
    }

    // 🔹 Obtener detalle
    $stmt = $conn->prepare("
        SELECT productoID, cantidad 
        FROM detalleventa 
        WHERE ventaID = :ventaID
    ");
    
    $stmt->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmt->execute();

    $stmtPagos = $conn->prepare("
        UPDATE venta_pagos
        SET estado = 0
        WHERE ventaID = :ventaID
    ");
    $stmtPagos->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmtPagos->execute();

    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔹 Devolver stock
    foreach ($detalles as $item) {
        $stmtUpdate = $conn->prepare("
            UPDATE productos 
            SET stock = stock + :cantidad 
            WHERE productoID = :productoID
        ");
        $stmtUpdate->bindParam(':cantidad', $item['cantidad'], PDO::PARAM_INT);
        $stmtUpdate->bindParam(':productoID', $item['productoID'], PDO::PARAM_INT);
        $stmtUpdate->execute();
    }

    // 🔹 Anular venta
    $stmt = $conn->prepare("
        UPDATE ventas 
        SET estado = 0 
        WHERE ventaID = :ventaID
    ");
    $stmt->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmt->execute();

    $conn->commit();

    echo json_encode(["ok"=>true]);

} catch (Exception $e) {

    $conn->rollBack();
    echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
}
