<?php
session_start();
header('Content-Type: application/json');

require_once "../config/conexion.php";
require_once "../config/schema_helpers.php";

$data = json_decode(file_get_contents("php://input"), true);

if(!$data || !isset($data['ventaID'])){
    echo json_encode(["ok" => false, "error" => "No se recibio ventaID"]);
    exit;
}

$ventaID = intval($data['ventaID']);
$usuarioID = intval($_SESSION['usuarioID'] ?? 0) ?: null;

try {
    asegurarColumnasPagos($conn);
    asegurarTablasKardex($conn);
    inicializarKardexDesdeStock($conn, $usuarioID);

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT estado
        FROM ventas
        WHERE ventaID = :ventaID
        FOR UPDATE
    ");
    $stmt->execute([':ventaID' => $ventaID]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$venta){
        throw new Exception("Venta no encontrada");
    }

    if((int)$venta['estado'] === 0){
        throw new Exception("La venta ya esta anulada");
    }

    $stmt = $conn->prepare("
        SELECT d.productoID, d.cantidad, d.precioUnitario, p.precioCompra
        FROM detalleventa d
        INNER JOIN productos p ON p.productoID = d.productoID
        WHERE d.ventaID = :ventaID
    ");
    $stmt->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmt->execute();
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtPagos = $conn->prepare("
        UPDATE venta_pagos
        SET estado = 0
        WHERE ventaID = :ventaID
    ");
    $stmtPagos->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmtPagos->execute();

    foreach($detalles as $item){
        aplicarMovimientoStock(
            $conn,
            (int)$item['productoID'],
            'entrada',
            'anulacion_venta',
            (int)$item['cantidad'],
            'venta',
            $ventaID,
            floatval($item['precioCompra']),
            floatval($item['precioUnitario']),
            'Anulacion de venta #' . $ventaID,
            $usuarioID
        );
    }

    $stmt = $conn->prepare("
        UPDATE ventas
        SET estado = 0
        WHERE ventaID = :ventaID
    ");
    $stmt->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmt->execute();

    $conn->commit();

    echo json_encode(["ok" => true]);
} catch(Throwable $e){
    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }

    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
?>
