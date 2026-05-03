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
    responderJson([
        "ok"=>false,
        "error"=>"No llegaron datos validos"
    ]);
}

if(empty($_SESSION['usuarioID'])){
    responderJson([
        "ok"=>false,
        "error"=>"Sesion expirada. Vuelve a iniciar sesion."
    ]);
}

// 🔥 TOKEN (anti-duplicados)
$token = $data["token"] ?? null;

if(!$token){
    responderJson([
        "ok"=>false,
        "error"=>"Token no enviado"
    ]);
}

// Datos de la venta
$total = floatval($data["total"] ?? 0);
$pago = floatval($data["pago"] ?? 0);
$vuelto = floatval($data["vuelto"] ?? 0);
$productos = $data["productos"] ?? [];
$pagos = $data["pagos"] ?? [];
$tipoPago = $data['tipoPago'] ?? 'efectivo';
$estadoPago = $data['estadoPago'] ?? 'pagado';
$clienteID = intval($data['clienteID'] ?? 0);
$usuarioID = $_SESSION['usuarioID'];

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');

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

    // 🔹 Validar si ya hay cierre del día
    $stmtCierre = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE fecha = :hoy AND estado = 1");
    $stmtCierre->execute([':hoy' => $hoy]);
    $existeCierre = $stmtCierre->fetchColumn();

    if($existeCierre > 0){
        responderJson([
            "ok" => false,
            "error" => "No se puede registrar la venta. El cierre del día ya fue realizado."
        ]);
    }

    // 🔹 Iniciar transacción
    $conn->beginTransaction();

    $stmtProducto = $conn->prepare("SELECT p.precioVenta, p.categoriaID, p.stock,
               c.nombre AS categoriaNombre
        FROM productos p
        LEFT JOIN categoria c ON c.categoriaID = p.categoriaID
        WHERE p.productoID = ? AND p.estado = 1
        FOR UPDATE");

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
            throw new Exception("Stock insuficiente para el producto ID ".$productoID);
        }

        $categoriaNombre = strtolower(trim($productoBD["categoriaNombre"] ?? ""));
        $precioEditable = $categoriaNombre === CATEGORIA_PRECIO_EDITABLE_NOMBRE
            || intval($productoBD["categoriaID"]) === CATEGORIA_PRECIO_EDITABLE_ID;
        $precio = $precioEditable ? floatval($prod["precio"] ?? 0) : floatval($productoBD["precioVenta"]);

        if($precio <= 0){
            throw new Exception("Precio invalido para el producto ID ".$productoID);
        }

        $subtotal = $cantidad * $precio;
        $estadoDetallePago = strtolower(trim($prod['estadoPago'] ?? $estadoPago));
        if(!in_array($estadoDetallePago, ['pagado', 'pendiente'], true)){
            throw new Exception("Estado de pago invalido en producto");
        }
        $total += $subtotal;
        if($estadoDetallePago === 'pagado'){
            $totalDetallePagado += $subtotal;
        }

        $productosValidados[] = [
            "productoID" => $productoID,
            "cantidad" => $cantidad,
            "precio" => $precio,
            "subtotal" => $subtotal,
            "estadoPago" => $estadoDetallePago
        ];
    }

    if(empty($productosValidados)){
        throw new Exception("No hay productos en la venta");
    }

    $estadoPago = strtolower(trim($estadoPago));
    if(!in_array($estadoPago, ['pagado', 'pendiente'], true)){
        throw new Exception("Estado de pago invalido");
    }

    $hayPendientesDetalle = $totalDetallePagado < $total - 0.01;
    $estadoPago = $hayPendientesDetalle ? 'pendiente' : 'pagado';

    $pagosValidados = [];
    $totalPagos = 0;
    foreach($pagos as $pagoItem){
        $tipoPagoItem = strtolower(trim($pagoItem['tipoPago'] ?? ''));
        $montoPagoItem = round(floatval($pagoItem['monto'] ?? 0), 2);
        if($montoPagoItem <= 0) continue;
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
            throw new Exception("Los pagos deben sumar el total de productos pagados");
        }
        $pago = $totalPagos;
        $vuelto = 0;
        $tipoPago = count($pagosValidados) > 1 ? 'mixto' : $pagosValidados[0]['tipoPago'];
    } elseif($estadoPago === "pendiente"){
        $pago = 0;
        $vuelto = 0;
        $fechaPago = null;
    } elseif($tipoPago !== "efectivo"){
        $pago = $total;
        $vuelto = 0;
    } else {
        if($pago < $total){
            throw new Exception("El pago no puede ser menor al total");
        }
        $vuelto = $pago - $total;
    }

    if($totalDetallePagado > 0){
        $fechaPago = date('Y-m-d H:i:s');
    }

    // 🔥 INSERT con token
    $stmtVenta = $conn->prepare("INSERT INTO ventas
    (fecha,clienteID,usuarioID,total,tipoComprobante,estado,pago,vuelto,tipopago,estadoPago,fechaPago,token)
    VALUES (NOW(),?,?,?,?,?,?,?,?,?,?,?)");

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
        $fechaPago,
        $token
    ]);

    $ventaID = $conn->lastInsertId();

    // 🔹 Insertar detalle1
    $stmtDetalle = $conn->prepare("INSERT INTO detalleventa
    (ventaID,productoID,cantidad,precioUnitario,subtotal,estadoPago,fechaPago)
    VALUES (?,?,?,?,?,?,?)");

    // 🔹 Actualizar stock
    $stmtStock = $conn->prepare("UPDATE productos
    SET stock = stock - ?
    WHERE productoID = ?");

    foreach($productosValidados as $prod){

        $stmtDetalle->execute([
            $ventaID,
            $prod["productoID"],
            $prod["cantidad"],
            $prod["precio"],
            $prod["subtotal"],
            $prod["estadoPago"],
            $prod["estadoPago"] === 'pagado' ? $fechaPago : null
        ]);

        $stmtStock->execute([
            $prod["cantidad"],
            $prod["productoID"]
        ]);
    }

    $conn->commit();

    responderJson([
        "ok"=>true,
        "ventaID"=>$ventaID
    ]);

} catch(Throwable $e){

    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
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
    } elseif($estadoPago === 'pagado' && $pago > 0){
        $stmtPago = $conn->prepare("
            INSERT INTO venta_pagos (ventaID, tipoPago, monto, fechaPago, estado)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmtPago->execute([$ventaID, $tipoPago, $pago, $fechaPago]);
    }

    // 🔥 Error por token duplicado
    if($e->getCode() == 23000){
        responderJson([
            "ok"=>false,
            "error"=>"Venta duplicada detectada"
        ]);
    } else {
        responderJson([
            "ok"=>false,
            "error"=>$e->getMessage()
        ]);
    }
}
?>
