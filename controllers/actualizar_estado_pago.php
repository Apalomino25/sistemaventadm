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
        SELECT ventaID, total, estado, estadoPago, fechaPago
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

    $update = $conn->prepare("
        UPDATE ventas
        SET estadoPago = 'pagado',
            fechaPago = NOW(),
            pago = total,
            vuelto = 0
        WHERE ventaID = ?
          AND estado = 1
          AND estadoPago = 'pendiente'
    ");
    $update->execute([$ventaID]);

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
