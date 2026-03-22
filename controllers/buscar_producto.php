<?php

require_once "../config/conexion.php";

$codigo = $_GET["codigo"];

$sql = "SELECT productoID, codigo, nombre, descripcion, precioVenta,stock
        FROM productos
        WHERE codigo = ? AND estado = 1";

$stmt = $conn->prepare($sql);
$stmt->execute([$codigo]);

$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if($producto){
    echo json_encode($producto);
}else{
    echo json_encode(["error" => true]);
} 

?>