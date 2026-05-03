<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/auth_helpers.php';

function responderBusquedaInventario(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

requerirAdministradorJson();

$codigo = trim((string)($_GET['codigo'] ?? ''));
$productoID = intval($_GET['productoID'] ?? 0);

if($codigo === '' && $productoID <= 0){
    responderBusquedaInventario(['ok' => false, 'error' => 'Ingrese codigo o producto.']);
}

try {
    asegurarColumnasInventario($conn);

    if($productoID > 0){
        $stmt = $conn->prepare("
            SELECT productoID, codigo, nombre, descripcion, precioCompra,
                   precioVenta, stock, fechaVencimiento, estado, categoriaID
            FROM productos
            WHERE productoID = ?
            LIMIT 1
        ");
        $stmt->execute([$productoID]);
    } else {
        $stmt = $conn->prepare("
            SELECT productoID, codigo, nombre, descripcion, precioCompra,
                   precioVenta, stock, fechaVencimiento, estado, categoriaID
            FROM productos
            WHERE codigo = ?
            LIMIT 1
        ");
        $stmt->execute([$codigo]);
    }

    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$producto){
        responderBusquedaInventario(['ok' => true, 'existe' => false]);
    }

    responderBusquedaInventario([
        'ok' => true,
        'existe' => true,
        'producto' => $producto
    ]);
} catch(Throwable $e){
    responderBusquedaInventario(['ok' => false, 'error' => $e->getMessage()]);
}
?>
