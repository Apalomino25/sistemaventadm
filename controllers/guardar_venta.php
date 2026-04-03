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

    // 🔹 Iniciar transacción para guardar la venta
    $conn->beginTransaction();

    $conn->query("INSERT INTO ventas
    (fecha,clienteID,usuarioID,total,tipoComprobante,estado,pago,vuelto,tipopago,estadoPago)
    VALUES (NOW(),$clienteID,$usuarioID,$total,'TICKET',1,$pago,$vuelto,'$tipoPago','$estadoPago')");

    $ventaID = $conn->lastInsertId();

    foreach($productos as $prod){
        $productoID = $prod["productoID"];
        $cantidad = $prod["cantidad"];
        $precio = $prod["precio"];
        $subtotal = $prod["subtotal"];

        $conn->query("INSERT INTO detalleventa
        (ventaID,productoID,cantidad,precioUnitario,subtotal)
        VALUES ($ventaID,$productoID,$cantidad,$precio,$subtotal)");

        $conn->query("UPDATE productos
        SET stock = stock - $cantidad
        WHERE productoID = $productoID");
    }

    $conn->commit();

    echo json_encode([
        "ok"=>true,
        "ventaID"=>$ventaID
    ]);

} catch(Exception $e){

    $conn->rollBack();

    echo json_encode([
        "ok"=>false,
        "error"=>$e->getMessage()
    ]);
}
?>