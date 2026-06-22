<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';
require_once __DIR__ . '/../config/auth_helpers.php';

function responderKardex(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

requerirAdministradorJson();

$productoID = intval($_GET['productoID'] ?? 0);
$codigo = trim((string)($_GET['codigo'] ?? ''));
$limite = intval($_GET['limite'] ?? 100);
$limite = max(10, min($limite, 300));
$usuarioID = intval($_SESSION['usuarioID'] ?? 0) ?: null;

if($productoID <= 0 && $codigo === ''){
    responderKardex(['ok' => false, 'error' => 'Seleccione un producto.']);
}

try {
    asegurarColumnasInventario($conn);
    asegurarTablasKardex($conn);
    inicializarKardexDesdeStock($conn, $usuarioID);

    if($productoID > 0){
        $stmtProducto = $conn->prepare("
            SELECT p.productoID, p.codigo, p.nombre, p.descripcion, p.precioCompra,
                   p.precioVenta, p.stock, p.fechaVencimiento, c.nombre AS categoria
            FROM productos p
            LEFT JOIN categoria c ON c.categoriaID = p.categoriaID
            WHERE p.productoID = ?
            LIMIT 1
        ");
        $stmtProducto->execute([$productoID]);
    } else {
        $stmtProducto = $conn->prepare("
            SELECT p.productoID, p.codigo, p.nombre, p.descripcion, p.precioCompra,
                   p.precioVenta, p.stock, p.fechaVencimiento, c.nombre AS categoria
            FROM productos p
            LEFT JOIN categoria c ON c.categoriaID = p.categoriaID
            WHERE p.codigo = ?
            LIMIT 1
        ");
        $stmtProducto->execute([$codigo]);
    }

    $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);
    if(!$producto){
        responderKardex(['ok' => false, 'error' => 'Producto no encontrado.']);
    }

    $stmtResumen = $conn->prepare("
        SELECT
            COALESCE(SUM(cantidadEntrada), 0) AS totalEntradas,
            COALESCE(SUM(cantidadSalida), 0) AS totalSalidas
        FROM kardex_movimientos
        WHERE productoID = ?
    ");
    $stmtResumen->execute([(int)$producto['productoID']]);
    $resumen = $stmtResumen->fetch(PDO::FETCH_ASSOC) ?: ['totalEntradas' => 0, 'totalSalidas' => 0];

    $stmtMovimientos = $conn->prepare("
        SELECT kardexID, fecha, tipoMovimiento, concepto, referenciaTipo, referenciaID,
               cantidadEntrada, cantidadSalida, saldoAnterior, saldoNuevo, costoUnitario,
               precioUnitario, totalMovimiento, observacion
        FROM kardex_movimientos
        WHERE productoID = ?
        ORDER BY fecha DESC, kardexID DESC
        LIMIT $limite
    ");
    $stmtMovimientos->execute([(int)$producto['productoID']]);
    $movimientos = $stmtMovimientos->fetchAll(PDO::FETCH_ASSOC);

    $saldoKardex = !empty($movimientos)
        ? (int)$movimientos[0]['saldoNuevo']
        : (int)$producto['stock'];

    responderKardex([
        'ok' => true,
        'producto' => $producto,
        'resumen' => [
            'totalEntradas' => (int)$resumen['totalEntradas'],
            'totalSalidas' => (int)$resumen['totalSalidas'],
            'saldoKardex' => $saldoKardex,
            'stockActual' => (int)$producto['stock'],
            'coincideStock' => $saldoKardex === (int)$producto['stock']
        ],
        'movimientos' => $movimientos
    ]);
} catch(Throwable $e){
    responderKardex(['ok' => false, 'error' => $e->getMessage()]);
}
?>
