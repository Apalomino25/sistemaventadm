<?php
ob_start();
if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

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

$rawInput = file_get_contents('php://input');
if($rawInput === '' && PHP_SAPI === 'cli'){
    $rawInput = file_get_contents('php://stdin');
}

$data = json_decode($rawInput, true);
if(!is_array($data)){
    responderCliente(['ok' => false, 'error' => 'No llegaron datos validos.']);
}

$clienteID = (int)($data['clienteID'] ?? 0);
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
              AND clienteID <> ?
            LIMIT 1
        ");
        $stmtExiste->execute([$numeroDocumento, $clienteID]);
        $cliente = $stmtExiste->fetch(PDO::FETCH_ASSOC);

        if($cliente){
            if($clienteID > 0){
                responderCliente([
                    'ok' => false,
                    'error' => 'Ese documento ya pertenece a otro cliente.'
                ]);
            }

            responderCliente([
                'ok' => true,
                'message' => 'El cliente ya existia. Fue seleccionado.',
                'cliente' => $cliente
            ]);
        }
    }

    if($clienteID > 0){
        $stmt = $conn->prepare("
            UPDATE clientes
            SET nombre = ?, tipoDocumento = ?, numeroDocumento = ?, telefono = ?, direccion = ?
            WHERE clienteID = ?
            LIMIT 1
        ");
        $stmt->execute([$nombre, $tipoDocumento, $numeroDocumento, $telefono, $direccion, $clienteID]);

        if($stmt->rowCount() === 0){
            $stmtExisteID = $conn->prepare("SELECT COUNT(*) FROM clientes WHERE clienteID = ?");
            $stmtExisteID->execute([$clienteID]);
            if((int)$stmtExisteID->fetchColumn() === 0){
                responderCliente(['ok' => false, 'error' => 'Cliente no encontrado.']);
            }
        }

        $stmtCliente = $conn->prepare("
            SELECT clienteID, nombre, tipoDocumento, numeroDocumento, telefono, direccion
            FROM clientes
            WHERE clienteID = ?
            LIMIT 1
        ");
        $stmtCliente->execute([$clienteID]);

        responderCliente([
            'ok' => true,
            'message' => 'Cliente actualizado correctamente.',
            'cliente' => $stmtCliente->fetch(PDO::FETCH_ASSOC)
        ]);
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
