<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/auth_helpers.php';

function responderCompra(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

requerirAdministradorJson();

if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
    responderCompra(['ok' => false, 'error' => 'Metodo no permitido.']);
}

$data = json_decode(file_get_contents('php://input'), true);
if(!is_array($data)){
    responderCompra(['ok' => false, 'error' => 'No llegaron datos validos.']);
}

$productoID = intval($data['productoID'] ?? 0);
$cantidad = intval($data['cantidad'] ?? 0);
$precioCompra = round(floatval($data['precioCompra'] ?? 0), 2);
$precioVenta = round(floatval($data['precioVenta'] ?? 0), 2);
$fechaVencimientoRaw = trim((string)($data['fechaVencimiento'] ?? ''));
$proveedor = trim((string)($data['proveedor'] ?? ''));
$comprobante = trim((string)($data['comprobante'] ?? ''));
$observacion = trim((string)($data['observacion'] ?? ''));
$usuarioID = intval($_SESSION['usuarioID'] ?? 0) ?: null;

if($productoID <= 0){
    responderCompra(['ok' => false, 'error' => 'Seleccione un producto.']);
}

if($cantidad <= 0){
    responderCompra(['ok' => false, 'error' => 'Ingrese una cantidad mayor a cero.']);
}

if($precioCompra <= 0){
    responderCompra(['ok' => false, 'error' => 'Ingrese un precio de compra valido.']);
}

$fechaVencimiento = null;
if($fechaVencimientoRaw !== ''){
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimientoRaw)){
        responderCompra(['ok' => false, 'error' => 'Fecha de vencimiento invalida.']);
    }
    $fechaVencimiento = $fechaVencimientoRaw;
}

try {
    asegurarColumnasInventario($conn);
    asegurarTablasKardex($conn);
    inicializarKardexDesdeStock($conn, $usuarioID);

    $conn->beginTransaction();

    $stmtProducto = $conn->prepare("
        SELECT productoID, nombre, codigo, stock, precioVenta
        FROM productos
        WHERE productoID = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmtProducto->execute([$productoID]);
    $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

    if(!$producto){
        throw new Exception('Producto no encontrado.');
    }

    if($precioVenta <= 0){
        $precioVenta = round(floatval($producto['precioVenta'] ?? 0), 2);
    }

    if($precioVenta <= 0){
        throw new Exception('Ingrese un precio de venta valido.');
    }

    $subtotal = round($cantidad * $precioCompra, 2);

    $stmtCompra = $conn->prepare("
        INSERT INTO compras (fecha, proveedor, comprobante, total, observacion, usuarioID, estado)
        VALUES (NOW(), ?, ?, ?, ?, ?, 1)
    ");
    $stmtCompra->execute([
        $proveedor !== '' ? $proveedor : null,
        $comprobante !== '' ? $comprobante : null,
        $subtotal,
        $observacion !== '' ? $observacion : null,
        $usuarioID
    ]);
    $compraID = (int)$conn->lastInsertId();

    $stmtDetalle = $conn->prepare("
        INSERT INTO detallecompra
        (compraID, productoID, cantidad, precioCompra, precioVenta, subtotal, fechaVencimiento)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtDetalle->execute([
        $compraID,
        $productoID,
        $cantidad,
        $precioCompra,
        $precioVenta,
        $subtotal,
        $fechaVencimiento
    ]);

    $stmtUpdateProducto = $conn->prepare("
        UPDATE productos
        SET precioCompra = ?,
            precioVenta = ?,
            fechaVencimiento = COALESCE(?, fechaVencimiento),
            estado = 1
        WHERE productoID = ?
    ");
    $stmtUpdateProducto->execute([
        $precioCompra,
        $precioVenta,
        $fechaVencimiento,
        $productoID
    ]);

    $movimiento = aplicarMovimientoStock(
        $conn,
        $productoID,
        'entrada',
        'compra',
        $cantidad,
        'compra',
        $compraID,
        $precioCompra,
        $precioVenta,
        $comprobante !== '' ? 'Compra ' . $comprobante : 'Compra #' . $compraID,
        $usuarioID
    );

    $conn->commit();

    responderCompra([
        'ok' => true,
        'message' => 'Compra registrada. Stock actualizado de ' . $movimiento['saldoAnterior'] . ' a ' . $movimiento['saldoNuevo'] . '.',
        'compraID' => $compraID,
        'productoID' => $productoID,
        'stockNuevo' => $movimiento['saldoNuevo']
    ]);
} catch(Throwable $e){
    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }

    responderCompra(['ok' => false, 'error' => $e->getMessage()]);
}
?>
