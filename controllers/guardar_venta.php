<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


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




$total = $data["total"];
$total = floatval($data["total"]);
$pago = floatval($data["pago"]);
$vuelto = floatval($data["vuelto"]);
$productos = $data["productos"];
$tipoPago = $data['tipoPago'];
$clienteID = 1;
$usuarioID = 1;

try{

$conn->beginTransaction();

$conn->query("INSERT INTO ventas
(fecha,clienteID,usuarioID,total,tipoComprobante,estado,pago,vuelto,tipopago)
VALUES (NOW(),$clienteID,$usuarioID,$total,'TICKET',1,$pago,$vuelto,'$tipoPago')");

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

}catch(Exception $e){

$conn->rollBack();

echo json_encode([
    "ok"=>false,
    "error"=>$e->getMessage()
]);

}