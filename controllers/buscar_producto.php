<?php

header('Content-Type: application/json');

require_once "../config/conexion.php";
require_once "../config/schema_helpers.php";

asegurarColumnasInventario($conn);

$busqueda = trim($_GET["codigo"] ?? $_GET["q"] ?? "");

if($busqueda === ""){
    echo json_encode(["error" => true]);
    exit;
}

$selectProducto = "SELECT p.productoID, p.codigo, p.nombre, p.descripcion, p.precioVenta,
                          p.stock, p.fechaVencimiento, p.categoriaID, c.nombre AS categoriaNombre
                   FROM productos p
                   LEFT JOIN categoria c ON c.categoriaID = p.categoriaID";

$stmtCodigo = $conn->prepare("
    $selectProducto
    WHERE p.codigo = ? AND p.estado = 1
    LIMIT 1
");
$stmtCodigo->execute([$busqueda]);

$producto = $stmtCodigo->fetch(PDO::FETCH_ASSOC);

if($producto){
    echo json_encode($producto);
    exit;
}

if(ctype_digit($busqueda)){
    $stmtID = $conn->prepare("
        $selectProducto
        WHERE p.productoID = ? AND p.estado = 1
        LIMIT 1
    ");
    $stmtID->execute([intval($busqueda)]);

    $producto = $stmtID->fetch(PDO::FETCH_ASSOC);

    if($producto){
        echo json_encode($producto);
        exit;
    }
}

$like = "%" . $busqueda . "%";
$likeInicio = $busqueda . "%";

$stmtNombre = $conn->prepare("
    $selectProducto
    WHERE p.estado = 1
      AND (p.nombre LIKE ? OR p.descripcion LIKE ? OR p.codigo LIKE ?)
    ORDER BY
      CASE WHEN p.stock > 0 THEN 0 ELSE 1 END,
      CASE WHEN p.nombre LIKE ? THEN 0 ELSE 1 END,
      CASE WHEN p.fechaVencimiento IS NULL THEN 1 ELSE 0 END,
      p.fechaVencimiento ASC,
      p.nombre ASC,
      p.descripcion ASC
");
$stmtNombre->execute([$like, $like, $like, $likeInicio]);

$productos = $stmtNombre->fetchAll(PDO::FETCH_ASSOC);

if($productos){
    echo json_encode([
        "multiple" => true,
        "productos" => $productos
    ]);
}else{
    echo json_encode(["error" => true]);
}

?>
