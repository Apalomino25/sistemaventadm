<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require "../config/conexion.php";
require_once "../config/schema_helpers.php";

const CATEGORIA_PRECIO_EDITABLE_NOMBRE = "prodeditable";
const CATEGORIA_PRECIO_EDITABLE_ID = 11;

function responderJson($payload) {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if(!is_array($data)){
    responderJson(["ok" => false, "error" => "No llegaron datos validos"]);
}

if(empty($_SESSION['usuarioID'])){
    responderJson(["ok" => false, "error" => "Sesion expirada. Vuelve a iniciar sesion."]);
}

$token = $data["token"] ?? null;
if(!$token){
    responderJson(["ok" => false, "error" => "Token no enviado"]);
}

$pago = floatval($data["pago"] ?? 0);
$vuelto = floatval($data["vuelto"] ?? 0);
$productos = $data["productos"] ?? [];
$pagos = $data["pagos"] ?? [];
$tipoPago = $data['tipoPago'] ?? 'efectivo';
$clienteID = intval($data['clienteID'] ?? 0);
$usuarioID = $_SESSION['usuarioID'];

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');
$fechaPago = null;

try {
    asegurarColumnasPagos($conn);

    if($clienteID <= 0){
        $clienteGeneral = obtenerClienteGeneral($conn);
        $clienteID = intval($clienteGeneral['clienteID']);
    }

    $stmtCliente = $conn->prepare("SELECT COUNT(*) FROM clientes WHERE clienteID = ?");
    $stmtCliente->execute([$clienteID]);
    if((int)$stmtCliente->fetchColumn() === 0){
        throw new Exception("Cliente invalido");
    }

    $stmtCierre = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE fecha = :hoy AND estado = 1");
    $stmtCierre->execute([':hoy' => $hoy]);
    if((int)$stmtCierre->fetchColumn() > 0){
        responderJson([
            "ok" => false,
            "error" => "No se puede registrar la venta. El cierre del dia ya fue realizado."
        ]);
    }

    $conn->beginTransaction();

    $stmtProducto = $conn->prepare("
        SELECT p.precioVenta, p.categoriaID, p.stock, c.nombre AS categoriaNombre
        FROM productos p
        LEFT JOIN categoria c ON c.categoriaID = p.categoriaID
        WHERE p.productoID = ? AND p.estado = 1
        FOR UPDATE
    ");

    $productosValidados = [];
    $total = 0;
    $totalDetallePagado = 0;

    foreach($productos as $prod){
        $productoID = intval($prod["productoID"] ?? 0);
        $cantidad = intval($prod["cantidad"] ?? 0);

        if($productoID <= 0 || $cantidad <= 0){
            throw new Exception("Producto o cantidad invalida");
        }

        $stmtProducto->execute([$productoID]);
        $productoBD = $stmtProducto->fetch(PDO::FETCH_ASSOC);

        if(!$productoBD){
            throw new Exception("Producto no encontrado o inactivo");
        }

        if(intval($productoBD["stock"]) < $cantidad){
            throw new Exception("Stock insuficiente para el producto ID " . $productoID);
        }

        $categoriaNombre = strtolower(trim($productoBD["categoriaNombre"] ?? ""));
        $precioEditable = $categoriaNombre === CATEGORIA_PRECIO_EDITABLE_NOMBRE
            || intval($productoBD["categoriaID"]) === CATEGORIA_PRECIO_EDITABLE_ID;
        $precio = $precioEditable ? floatval($prod["precio"] ?? 0) : floatval($productoBD["precioVenta"]);

        if($precio <= 0){
            throw new Exception("Precio invalido para el producto ID " . $productoID);
        }

        $subtotal = round($cantidad * $precio, 2);
        $montoPagado = round(floatval($prod['montoPagado'] ?? 0), 2);
        if($montoPagado < 0){
            $montoPagado = 0;
        }
        if($montoPagado > $subtotal){
            $montoPagado = $subtotal;
        }

        $saldoPendiente = round($subtotal - $montoPagado, 2);
        if($montoPagado >= $subtotal - 0.01){
            $estadoDetalle = 'pagado';
        } elseif($montoPagado > 0){
            $estadoDetalle = 'parcial';
        } else {
            $estadoDetalle = 'pendiente';
        }

        $total += $subtotal;
        $totalDetallePagado += $montoPagado;

        $productosValidados[] = [
            "productoID" => $productoID,
            "cantidad" => $cantidad,
            "precio" => $precio,
            "subtotal" => $subtotal,
            "estadoPago" => $estadoDetalle,
            "montoPagado" => $montoPagado,
            "saldoPendiente" => $saldoPendiente
        ];
    }

    if(empty($productosValidados)){
        throw new Exception("No hay productos en la venta");
    }

    $haySaldoPendiente = $totalDetallePagado < $total - 0.01;
    $estadoPago = $haySaldoPendiente ? ($totalDetallePagado > 0 ? 'parcial' : 'pendiente') : 'pagado';

    $pagosValidados = [];
    $totalPagos = 0;
    foreach($pagos as $pagoItem){
        $tipoPagoItem = strtolower(trim($pagoItem['tipoPago'] ?? ''));
        $montoPagoItem = round(floatval($pagoItem['monto'] ?? 0), 2);
        if($montoPagoItem <= 0){
            continue;
        }
        if(!in_array($tipoPagoItem, ['efectivo', 'yape', 'plin', 'transferencia'], true)){
            throw new Exception("Tipo de pago invalido");
        }
        $pagosValidados[] = [
            'tipoPago' => $tipoPagoItem,
            'monto' => $montoPagoItem
        ];
        $totalPagos += $montoPagoItem;
    }

    if(!empty($pagosValidados)){
        if(abs($totalPagos - $totalDetallePagado) > 0.01){
            throw new Exception("Los pagos deben sumar el total abonado en productos");
        }
        $pago = $totalPagos;
        $vuelto = 0;
        $tipoPago = count($pagosValidados) > 1 ? 'mixto' : $pagosValidados[0]['tipoPago'];
    } elseif($totalDetallePagado > 0){
        $pago = $totalDetallePagado;
        $vuelto = 0;
    } else {
        $pago = 0;
        $vuelto = 0;
    }

    if($totalDetallePagado > 0){
        $fechaPago = date('Y-m-d H:i:s');
    }

    $stmtVenta = $conn->prepare("
        INSERT INTO ventas
        (fecha, clienteID, usuarioID, total, tipoComprobante, estado, pago, vuelto, tipopago, estadoPago, fechaPago, token)
        VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtVenta->execute([
        $clienteID,
        $usuarioID,
        $total,
        'TICKET',
        1,
        $pago,
        $vuelto,
        $tipoPago,
        $estadoPago,
        $estadoPago === 'pagado' ? $fechaPago : null,
        $token
    ]);

    $ventaID = $conn->lastInsertId();

    $stmtDetalle = $conn->prepare("
        INSERT INTO detalleventa
        (ventaID, productoID, cantidad, precioUnitario, subtotal, estadoPago, montoPagado, saldoPendiente, fechaPago)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtStock = $conn->prepare("
        UPDATE productos
        SET stock = stock - ?
        WHERE productoID = ?
    ");

    foreach($productosValidados as $prod){
        $stmtDetalle->execute([
            $ventaID,
            $prod["productoID"],
            $prod["cantidad"],
            $prod["precio"],
            $prod["subtotal"],
            $prod["estadoPago"],
            $prod["montoPagado"],
            $prod["saldoPendiente"],
            $prod["montoPagado"] > 0 ? $fechaPago : null
        ]);

        $stmtStock->execute([
            $prod["cantidad"],
            $prod["productoID"]
        ]);
    }

    if(!empty($pagosValidados)){
        $stmtPago = $conn->prepare("
            INSERT INTO venta_pagos (ventaID, tipoPago, monto, fechaPago, estado)
            VALUES (?, ?, ?, ?, 1)
        ");
        foreach($pagosValidados as $pagoItem){
            $stmtPago->execute([
                $ventaID,
                $pagoItem['tipoPago'],
                $pagoItem['monto'],
                $fechaPago
            ]);
        }
    } elseif($pago > 0){
        $stmtPago = $conn->prepare("
            INSERT INTO venta_pagos (ventaID, tipoPago, monto, fechaPago, estado)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmtPago->execute([$ventaID, $tipoPago, $pago, $fechaPago]);
    }

    $conn->commit();

    responderJson([
        "ok" => true,
        "ventaID" => $ventaID
    ]);

} catch(Throwable $e){
    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }

    if($e->getCode() == 23000){
        responderJson(["ok" => false, "error" => "Venta duplicada detectada"]);
    }

    responderJson(["ok" => false, "error" => $e->getMessage()]);
}
?>
