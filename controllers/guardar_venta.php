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
        $total += $subtotal;

        $productosValidados[] = [
            "productoID" => $productoID,
            "cantidad" => $cantidad,
            "precio" => $precio,
            "subtotal" => $subtotal
        ];
    }

    if(empty($productosValidados)){
        throw new Exception("No hay productos en la venta");
    }

    $estadoPago = strtolower(trim($estadoPago));
    if(!in_array($estadoPago, ['pagado', 'pendiente'], true)){
        throw new Exception("Estado de pago invalido");
    }

    if($estadoPago === "pendiente"){
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

    if($estadoPago === "pagado"){
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
    (ventaID,productoID,cantidad,precioUnitario,subtotal)
    VALUES (?,?,?,?,?)");

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
            $prod["subtotal"]
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
