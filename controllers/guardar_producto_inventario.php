<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/auth_helpers.php';

function responderInventario(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

requerirAdministradorJson();

if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
    responderInventario(['ok' => false, 'error' => 'Metodo no permitido.']);
}

$data = json_decode(file_get_contents('php://input'), true);
if(!is_array($data)){
    responderInventario(['ok' => false, 'error' => 'No llegaron datos validos.']);
}

$modo = trim((string)($data['modo'] ?? 'crear'));
$productoID = intval($data['productoID'] ?? 0);
$codigo = trim((string)($data['codigo'] ?? ''));
$nombre = trim((string)($data['nombre'] ?? ''));
$descripcion = trim((string)($data['descripcion'] ?? ''));
$categoriaID = intval($data['categoriaID'] ?? 0);
$precioCompra = floatval($data['precioCompra'] ?? 0);
$precioVenta = floatval($data['precioVenta'] ?? 0);
$stock = intval($data['stock'] ?? 0);
$fechaVencimiento = trim((string)($data['fechaVencimiento'] ?? ''));

if($codigo === '' || $stock < 0){
    responderInventario(['ok' => false, 'error' => 'Ingrese codigo y stock valido.']);
}

if($fechaVencimiento === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)){
    responderInventario(['ok' => false, 'error' => 'Fecha de vencimiento invalida.']);
}

if($precioVenta <= 0){
    responderInventario(['ok' => false, 'error' => 'Ingrese un precio de venta valido.']);
}

if($modo !== 'actualizar_stock'){
    if($stock <= 0){
        responderInventario(['ok' => false, 'error' => 'Ingrese stock inicial mayor a cero.']);
    }

    if($nombre === '' || $categoriaID <= 0 || $precioCompra <= 0){
        responderInventario(['ok' => false, 'error' => 'Complete los campos obligatorios.']);
    }
}

try {
    asegurarColumnasInventario($conn);
    asegurarTablasKardex($conn);
    inicializarKardexDesdeStock($conn, intval($_SESSION['usuarioID'] ?? 0) ?: null);

    $conn->beginTransaction();

    $stmtCodigo = $conn->prepare("
        SELECT productoID, nombre, stock
        FROM productos
        WHERE codigo = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmtCodigo->execute([$codigo]);
    $productoExistente = $stmtCodigo->fetch(PDO::FETCH_ASSOC);

    if($productoExistente){
        if($productoID > 0 && $productoID !== (int)$productoExistente['productoID']){
            throw new Exception('El codigo pertenece a otro producto.');
        }

        $stmtUpdate = $conn->prepare("
            UPDATE productos
            SET precioVenta = ?,
                fechaVencimiento = ?,
                estado = 1
            WHERE productoID = ?
        ");
        $stmtUpdate->execute([$precioVenta, $fechaVencimiento, $productoExistente['productoID']]);

        $stockAnterior = (int)$productoExistente['stock'];
        $diferencia = $stock - $stockAnterior;
        if($diferencia > 0){
            aplicarMovimientoStock(
                $conn,
                (int)$productoExistente['productoID'],
                'entrada',
                'ajuste_inventario',
                $diferencia,
                'producto',
                (int)$productoExistente['productoID'],
                null,
                $precioVenta,
                'Ajuste manual de inventario',
                intval($_SESSION['usuarioID'] ?? 0) ?: null
            );
        } elseif($diferencia < 0){
            aplicarMovimientoStock(
                $conn,
                (int)$productoExistente['productoID'],
                'salida',
                'ajuste_inventario',
                abs($diferencia),
                'producto',
                (int)$productoExistente['productoID'],
                null,
                $precioVenta,
                'Ajuste manual de inventario',
                intval($_SESSION['usuarioID'] ?? 0) ?: null
            );
        }

        $conn->commit();

        responderInventario([
            'ok' => true,
            'message' => 'Stock, vencimiento y precio actualizados. ' . $productoExistente['nombre'] . ' ahora tiene ' . $stock . ' unidades.',
            'productoID' => (int)$productoExistente['productoID']
        ]);
    }

    $stmtCategoria = $conn->prepare("SELECT COUNT(*) FROM categoria WHERE categoriaID = ?");
    $stmtCategoria->execute([$categoriaID]);
    if((int)$stmtCategoria->fetchColumn() === 0){
        throw new Exception('Categoria invalida.');
    }

    $stmt = $conn->prepare("
        INSERT INTO productos
        (codigo, nombre, descripcion, precioCompra, precioVenta, categoriaID, estado, stock, fechaVencimiento)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
    ");

    $stmt->execute([
        $codigo,
        $nombre,
        $descripcion,
        $precioCompra,
        $precioVenta,
        $categoriaID,
        0,
        $fechaVencimiento
    ]);

    $nuevoProductoID = (int)$conn->lastInsertId();
    aplicarMovimientoStock(
        $conn,
        $nuevoProductoID,
        'entrada',
        'stock_inicial',
        $stock,
        'producto',
        $nuevoProductoID,
        $precioCompra,
        $precioVenta,
        'Stock inicial del producto',
        intval($_SESSION['usuarioID'] ?? 0) ?: null
    );

    $conn->commit();

    responderInventario([
        'ok' => true,
        'message' => 'Producto/lote registrado correctamente.',
        'productoID' => $nuevoProductoID
    ]);
} catch(Throwable $e){
    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }
    responderInventario(['ok' => false, 'error' => $e->getMessage()]);
}
?>
