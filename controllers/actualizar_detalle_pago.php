<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';

function responderDetallePago(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

if(empty($_SESSION['usuarioID'])){
    responderDetallePago(['ok' => false, 'error' => 'Sesion expirada.']);
}

$data = json_decode(file_get_contents('php://input'), true);
$detalleID = intval($data['detalleID'] ?? 0);
$tipoPago = strtolower(trim($data['tipoPago'] ?? ''));

if($detalleID <= 0 || !in_array($tipoPago, ['efectivo', 'yape', 'plin', 'transferencia'], true)){
    responderDetallePago(['ok' => false, 'error' => 'Datos invalidos.']);
}

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');

try {
    asegurarColumnasPagos($conn);

    $stmtCierre = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE fecha = ? AND estado = 1");
    $stmtCierre->execute([$hoy]);
    if((int)$stmtCierre->fetchColumn() > 0){
        throw new Exception('No se puede registrar el pago. El cierre de hoy ya fue realizado.');
    }

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT d.detalleID, d.ventaID, d.subtotal, d.estadoPago, v.estado
        FROM detalleventa d
        INNER JOIN ventas v ON v.ventaID = d.ventaID
        WHERE d.detalleID = ?
        FOR UPDATE
    ");
    $stmt->execute([$detalleID]);
    $detalle = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$detalle){
        throw new Exception('Detalle no encontrado.');
    }
    if((int)$detalle['estado'] !== 1){
        throw new Exception('No se puede cobrar un producto de una venta anulada.');
    }
    if($detalle['estadoPago'] !== 'pendiente'){
        throw new Exception('Este producto no esta pendiente.');
    }

    $fechaPago = date('Y-m-d H:i:s');
    $update = $conn->prepare("
        UPDATE detalleventa
        SET estadoPago = 'pagado',
            fechaPago = ?
        WHERE detalleID = ?
    ");
    $update->execute([$fechaPago, $detalleID]);

    $insertPago = $conn->prepare("
        INSERT INTO venta_pagos (ventaID, tipoPago, monto, fechaPago, estado)
        VALUES (?, ?, ?, ?, 1)
    ");
    $insertPago->execute([
        $detalle['ventaID'],
        $tipoPago,
        $detalle['subtotal'],
        $fechaPago
    ]);

    $stmtPendientes = $conn->prepare("
        SELECT COUNT(*)
        FROM detalleventa
        WHERE ventaID = ?
          AND estadoPago = 'pendiente'
    ");
    $stmtPendientes->execute([$detalle['ventaID']]);
    $pendientes = (int)$stmtPendientes->fetchColumn();

    $stmtTotalPagos = $conn->prepare("
        SELECT COALESCE(SUM(monto), 0)
        FROM venta_pagos
        WHERE ventaID = ?
          AND estado = 1
    ");
    $stmtTotalPagos->execute([$detalle['ventaID']]);
    $totalPagos = (float)$stmtTotalPagos->fetchColumn();

    $stmtTipos = $conn->prepare("
        SELECT COUNT(DISTINCT tipoPago)
        FROM venta_pagos
        WHERE ventaID = ?
          AND estado = 1
    ");
    $stmtTipos->execute([$detalle['ventaID']]);
    $tipos = (int)$stmtTipos->fetchColumn();

    $updateVenta = $conn->prepare("
        UPDATE ventas
        SET estadoPago = ?,
            fechaPago = CASE WHEN ? = 0 THEN ? ELSE fechaPago END,
            pago = ?,
            vuelto = 0,
            tipoPago = CASE WHEN ? > 1 THEN 'mixto' ELSE ? END
        WHERE ventaID = ?
    ");
    $updateVenta->execute([
        $pendientes === 0 ? 'pagado' : 'pendiente',
        $pendientes,
        $fechaPago,
        $totalPagos,
        $tipos,
        $tipoPago,
        $detalle['ventaID']
    ]);

    $conn->commit();
    responderDetallePago(['ok' => true, 'message' => 'Producto marcado como pagado. Entrara en el cierre de hoy.']);
} catch(Throwable $e){
    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }
    responderDetallePago(['ok' => false, 'error' => $e->getMessage()]);
}
?>
