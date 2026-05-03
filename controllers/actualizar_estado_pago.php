<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';

function responderEstadoPago(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
    responderEstadoPago(['ok' => false, 'error' => 'Metodo no permitido.']);
}

$data = json_decode(file_get_contents('php://input'), true);
$ventaID = intval($data['ventaID'] ?? 0);
$estadoPago = strtolower(trim($data['estadoPago'] ?? ''));

if($ventaID <= 0 || !in_array($estadoPago, ['pagado', 'pendiente'], true)){
    responderEstadoPago(['ok' => false, 'error' => 'Datos invalidos.']);
}

try {
    asegurarColumnasPagos($conn);
    date_default_timezone_set('America/Lima');
    $hoy = date('Y-m-d');

    $stmtCierre = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE fecha = ? AND estado = 1");
    $stmtCierre->execute([$hoy]);
    if((int)$stmtCierre->fetchColumn() > 0){
        throw new Exception('No se puede registrar el pago. El cierre de hoy ya fue realizado.');
    }

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT ventaID, total, estado, estadoPago, fechaPago, tipoPago
        FROM ventas
        WHERE ventaID = ?
        FOR UPDATE
    ");
    $stmt->execute([$ventaID]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$venta){
        throw new Exception('Venta no encontrada.');
    }

    if((int)$venta['estado'] !== 1){
        throw new Exception('No se puede cambiar el pago de una venta anulada.');
    }

    if($venta['estadoPago'] !== 'pendiente'){
        throw new Exception('Solo las ventas pendientes permiten editar el estado de pago.');
    }

    if($estadoPago === 'pendiente'){
        $conn->commit();
        responderEstadoPago(['ok' => true, 'message' => 'La venta ya estaba pendiente.']);
    }

    $fechaPago = date('Y-m-d H:i:s');

    $stmtPendiente = $conn->prepare("
        SELECT COALESCE(SUM(saldoPendiente), 0)
        FROM detalleventa
        WHERE ventaID = ?
          AND estadoPago IN ('pendiente', 'parcial')
    ");
    $stmtPendiente->execute([$ventaID]);
    $saldoPendiente = (float)$stmtPendiente->fetchColumn();

    $updateDetalle = $conn->prepare("
        UPDATE detalleventa
        SET estadoPago = 'pagado',
            montoPagado = subtotal,
            saldoPendiente = 0,
            fechaPago = ?
        WHERE ventaID = ?
          AND estadoPago IN ('pendiente', 'parcial')
    ");
    $updateDetalle->execute([$fechaPago, $ventaID]);

    if($saldoPendiente > 0){
        $insertPago = $conn->prepare("
            INSERT INTO venta_pagos (ventaID, tipoPago, monto, fechaPago, estado)
            VALUES (?, ?, ?, ?, 1)
        ");
        $insertPago->execute([$ventaID, $venta['tipoPago'] ?: 'efectivo', $saldoPendiente, $fechaPago]);
    }

    $stmtTotalPagos = $conn->prepare("
        SELECT COALESCE(SUM(monto), 0)
        FROM venta_pagos
        WHERE ventaID = ?
          AND estado = 1
    ");
    $stmtTotalPagos->execute([$ventaID]);
    $totalPagos = (float)$stmtTotalPagos->fetchColumn();

    $update = $conn->prepare("
        UPDATE ventas
        SET estadoPago = 'pagado',
            fechaPago = ?,
            pago = ?,
            vuelto = 0
        WHERE ventaID = ?
          AND estado = 1
    ");
    $update->execute([$fechaPago, $totalPagos, $ventaID]);

    $conn->commit();

    responderEstadoPago([
        'ok' => true,
        'message' => 'Estado de pago actualizado. Esta boleta entrara en el cierre de hoy.'
    ]);
} catch(Throwable $e){
    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }

    responderEstadoPago(['ok' => false, 'error' => $e->getMessage()]);
}
?>
