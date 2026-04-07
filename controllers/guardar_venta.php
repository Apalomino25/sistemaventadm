<?php

session_start();

header('Content-Type: application/json');

include "../config/conexion.php";

$data = json_decode(file_get_contents("php://input"), true);

if(!$data){
    echo json_encode([
        "ok"=>false,
        "error"=>"No llegaron datos"
    ]);
    exit;
}

// 🔥 TOKEN (anti-duplicados)
$token = $data["token"] ?? null;

if(!$token){
    echo json_encode([
        "ok"=>false,
        "error"=>"Token no enviado"
    ]);
    exit;
}

// Datos de la venta
$total = floatval($data["total"]);
$pago = floatval($data["pago"]);
$vuelto = floatval($data["vuelto"]);
$productos = $data["productos"];
$tipoPago = $data['tipoPago'];
$estadoPago = $data['estadoPago'];
$clienteID = 1;
$usuarioID = $_SESSION['usuarioID'];

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');

try {

    // 🔹 Validar si ya hay cierre del día
    $stmtCierre = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE DATE(fecha) = :hoy");
    $stmtCierre->execute([':hoy' => $hoy]);
    $existeCierre = $stmtCierre->fetchColumn();

    if($existeCierre > 0){
        echo json_encode([
            "ok" => false,
            "error" => "No se puede registrar la venta. El cierre del día ya fue realizado."
        ]);
        exit;
    }

    // 🔹 Iniciar transacción
    $conn->beginTransaction();

    // 🔥 INSERT con token
    $stmtVenta = $conn->prepare("INSERT INTO ventas
    (fecha,clienteID,usuarioID,total,tipoComprobante,estado,pago,vuelto,tipopago,estadoPago,token)
    VALUES (NOW(),?,?,?,?,?,?,?,?,?,?)");

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
        $token
    ]);

    $ventaID = $conn->lastInsertId();

    // 🔹 Insertar detalle
    $stmtDetalle = $conn->prepare("INSERT INTO detalleventa
    (ventaID,productoID,cantidad,precioUnitario,subtotal)
    VALUES (?,?,?,?,?)");

    // 🔹 Actualizar stock
    $stmtStock = $conn->prepare("UPDATE productos
    SET stock = stock - ?
    WHERE productoID = ?");

    foreach($productos as $prod){

        $stmtDetalle->execute([
            $ventaID,
            $prod["productoID"],
            $prod["cantidad"],
            $prod["precio"],
            $prod["subtotal"]
        ]);

        $stmtStock->execute([
            $prod["cantidad"],
            $prod["productoID"]
        ]);
    }

    $conn->commit();

    echo json_encode([
        "ok"=>true,
        "ventaID"=>$ventaID
    ]);

} catch(PDOException $e){

    $conn->rollBack();

    // 🔥 Error por token duplicado
    if($e->getCode() == 23000){
        echo json_encode([
            "ok"=>false,
            "error"=>"Venta duplicada detectada"
        ]);
    } else {
        echo json_encode([
            "ok"=>false,
            "error"=>$e->getMessage()
        ]);
    }
}
?>