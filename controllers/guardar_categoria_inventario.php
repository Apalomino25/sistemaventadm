<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/auth_helpers.php';

function responderCategoriaInventario(array $payload): void {
    if(ob_get_length() !== false){
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

requerirAdministradorJson();

if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
    responderCategoriaInventario(['ok' => false, 'error' => 'Metodo no permitido.']);
}

$data = json_decode(file_get_contents('php://input'), true);
if(!is_array($data)){
    responderCategoriaInventario(['ok' => false, 'error' => 'No llegaron datos validos.']);
}

$accion = trim((string)($data['accion'] ?? 'crear'));
$nombre = trim((string)($data['nombre'] ?? ''));
$categoriaID = intval($data['categoriaID'] ?? 0);

try {
    if($accion === 'crear'){
        if($nombre === ''){
            responderCategoriaInventario(['ok' => false, 'error' => 'Ingrese el nombre de la categoria.']);
        }

        if(strlen($nombre) > 80){
            responderCategoriaInventario(['ok' => false, 'error' => 'El nombre de la categoria es demasiado largo.']);
        }

        $stmtExiste = $conn->prepare("
            SELECT COUNT(*)
            FROM categoria
            WHERE LOWER(nombre) = LOWER(?)
        ");
        $stmtExiste->execute([$nombre]);

        if((int)$stmtExiste->fetchColumn() > 0){
            responderCategoriaInventario(['ok' => false, 'error' => 'La categoria ya existe.']);
        }

        $stmt = $conn->prepare("INSERT INTO categoria (nombre) VALUES (?)");
        $stmt->execute([$nombre]);

        responderCategoriaInventario([
            'ok' => true,
            'message' => 'Categoria guardada correctamente.',
            'categoriaID' => (int)$conn->lastInsertId()
        ]);
    }

    if($accion === 'eliminar'){
        if($categoriaID <= 0){
            responderCategoriaInventario(['ok' => false, 'error' => 'Categoria invalida.']);
        }

        $stmtCategoria = $conn->prepare("SELECT nombre FROM categoria WHERE categoriaID = ? LIMIT 1");
        $stmtCategoria->execute([$categoriaID]);
        $categoria = $stmtCategoria->fetch(PDO::FETCH_ASSOC);

        if(!$categoria){
            responderCategoriaInventario(['ok' => false, 'error' => 'La categoria no existe.']);
        }

        $stmtUso = $conn->prepare("SELECT COUNT(*) FROM productos WHERE categoriaID = ?");
        $stmtUso->execute([$categoriaID]);

        if((int)$stmtUso->fetchColumn() > 0){
            responderCategoriaInventario(['ok' => false, 'error' => 'No se puede eliminar una categoria con productos asignados.']);
        }

        $stmt = $conn->prepare("DELETE FROM categoria WHERE categoriaID = ?");
        $stmt->execute([$categoriaID]);

        responderCategoriaInventario([
            'ok' => true,
            'message' => 'Categoria eliminada correctamente.'
        ]);
    }

    responderCategoriaInventario(['ok' => false, 'error' => 'Accion invalida.']);
} catch(Throwable $e){
    responderCategoriaInventario(['ok' => false, 'error' => $e->getMessage()]);
}
?>
