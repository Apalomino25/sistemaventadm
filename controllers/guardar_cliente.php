<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';

function responderCliente(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

if(empty($_SESSION['usuarioID'])){
    responderCliente(['ok' => false, 'error' => 'Sesion expirada.']);
}

$data = json_decode(file_get_contents('php://input'), true);
if(!is_array($data)){
    responderCliente(['ok' => false, 'error' => 'No llegaron datos validos.']);
}

$nombre = trim((string)($data['nombre'] ?? ''));
$tipoDocumento = trim((string)($data['tipoDocumento'] ?? 'DNI'));
$numeroDocumento = trim((string)($data['numeroDocumento'] ?? ''));
$telefono = trim((string)($data['telefono'] ?? ''));
$direccion = trim((string)($data['direccion'] ?? ''));

if($nombre === ''){
    responderCliente(['ok' => false, 'error' => 'El nombre del cliente es obligatorio.']);
}

try {
    if($numeroDocumento !== ''){
        $stmtExiste = $conn->prepare("
            SELECT clienteID, nombre, tipoDocumento, numeroDocumento, telefono, direccion
            FROM clientes
            WHERE numeroDocumento = ?
            LIMIT 1
        ");
        $stmtExiste->execute([$numeroDocumento]);
        $cliente = $stmtExiste->fetch(PDO::FETCH_ASSOC);

        if($cliente){
            responderCliente([
                'ok' => true,
                'message' => 'El cliente ya existia. Fue seleccionado.',
                'cliente' => $cliente
            ]);
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO clientes (nombre, tipoDocumento, numeroDocumento, telefono, direccion)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nombre, $tipoDocumento, $numeroDocumento, $telefono, $direccion]);

    $stmtCliente = $conn->prepare("
        SELECT clienteID, nombre, tipoDocumento, numeroDocumento, telefono, direccion
        FROM clientes
        WHERE clienteID = ?
        LIMIT 1
    ");
    $stmtCliente->execute([$conn->lastInsertId()]);

    responderCliente([
        'ok' => true,
        'message' => 'Cliente registrado correctamente.',
        'cliente' => $stmtCliente->fetch(PDO::FETCH_ASSOC)
    ]);
} catch(Throwable $e){
    responderCliente(['ok' => false, 'error' => $e->getMessage()]);
}
?>
