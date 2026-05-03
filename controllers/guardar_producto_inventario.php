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

$codigo = trim((string)($data['codigo'] ?? ''));
$nombre = trim((string)($data['nombre'] ?? ''));
$descripcion = trim((string)($data['descripcion'] ?? ''));
$categoriaID = intval($data['categoriaID'] ?? 0);
$precioCompra = floatval($data['precioCompra'] ?? 0);
$precioVenta = floatval($data['precioVenta'] ?? 0);
$stock = intval($data['stock'] ?? 0);
$fechaVencimiento = trim((string)($data['fechaVencimiento'] ?? ''));

if($codigo === '' || $nombre === '' || $categoriaID <= 0 || $precioCompra <= 0 || $precioVenta <= 0 || $stock <= 0 || $fechaVencimiento === ''){
    responderInventario(['ok' => false, 'error' => 'Complete los campos obligatorios.']);
}

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)){
    responderInventario(['ok' => false, 'error' => 'Fecha de vencimiento invalida.']);
}

try {
    asegurarColumnasInventario($conn);

    $stmtCodigo = $conn->prepare("SELECT productoID FROM productos WHERE codigo = ? LIMIT 1");
    $stmtCodigo->execute([$codigo]);
    if($stmtCodigo->fetchColumn()){
        responderInventario([
            'ok' => false,
            'error' => 'Ya existe un producto con ese codigo. Para un nuevo lote usa otro codigo de barra.'
        ]);
    }

    $stmtCategoria = $conn->prepare("SELECT COUNT(*) FROM categoria WHERE categoriaID = ?");
    $stmtCategoria->execute([$categoriaID]);
    if((int)$stmtCategoria->fetchColumn() === 0){
        responderInventario(['ok' => false, 'error' => 'Categoria invalida.']);
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
        $stock,
        $fechaVencimiento
    ]);

    responderInventario([
        'ok' => true,
        'message' => 'Producto/lote registrado correctamente.',
        'productoID' => $conn->lastInsertId()
    ]);
} catch(Throwable $e){
    responderInventario(['ok' => false, 'error' => $e->getMessage()]);
}
?>
