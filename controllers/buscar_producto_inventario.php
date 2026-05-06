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

    $selectProducto = "
        SELECT productoID, codigo, nombre, descripcion, precioCompra,
               precioVenta, stock, fechaVencimiento, estado, categoriaID
        FROM productos
    ";

    if($productoID > 0){
        $stmt = $conn->prepare("
            $selectProducto
            WHERE productoID = ?
            LIMIT 1
        ");
        $stmt->execute([$productoID]);

        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$producto){
            responderBusquedaInventario(['ok' => true, 'existe' => false]);
        }

        responderBusquedaInventario([
            'ok' => true,
            'existe' => true,
            'producto' => $producto
        ]);
    } else {
        $stmt = $conn->prepare("
            $selectProducto
            WHERE codigo = ?
            LIMIT 1
        ");
        $stmt->execute([$codigo]);

        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if($producto){
            responderBusquedaInventario([
                'ok' => true,
                'existe' => true,
                'producto' => $producto
            ]);
        }

        $like = '%' . $codigo . '%';
        $likeInicio = $codigo . '%';

        $stmt = $conn->prepare("
            $selectProducto
            WHERE estado = 1
              AND (
                  nombre LIKE ?
                  OR descripcion LIKE ?
                  OR codigo LIKE ?
              )
            ORDER BY
                CASE WHEN nombre = ? THEN 0 ELSE 1 END,
                CASE WHEN codigo LIKE ? THEN 0 ELSE 1 END,
                CASE WHEN nombre LIKE ? THEN 0 ELSE 1 END,
                nombre ASC,
                fechaVencimiento ASC,
                productoID DESC
            LIMIT 20
        ");
        $stmt->execute([$like, $like, $like, $codigo, $likeInicio, $likeInicio]);

        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(count($productos) === 1){
            responderBusquedaInventario([
                'ok' => true,
                'existe' => true,
                'producto' => $productos[0]
            ]);
        }

        if(count($productos) > 1){
            responderBusquedaInventario([
                'ok' => true,
                'existe' => false,
                'multiple' => true,
                'productos' => $productos
            ]);
        }
    }

    responderBusquedaInventario(['ok' => true, 'existe' => false]);
} catch(Throwable $e){
    responderBusquedaInventario(['ok' => false, 'error' => $e->getMessage()]);
}
?>
