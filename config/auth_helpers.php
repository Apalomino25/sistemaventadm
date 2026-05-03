<?php
function esAdministrador(): bool {
    $rol = strtolower(trim((string)($_SESSION['rol'] ?? '')));
    return in_array($rol, ['administrador', 'admin'], true);
}

function requerirAdministradorHtml(): void {
    if(!esAdministrador()){
        http_response_code(403);
        echo "<div class='container'><h2>Acceso denegado</h2><p>Solo el administrador puede ingresar al modulo de inventarios.</p></div>";
        exit;
    }
}

function requerirAdministradorJson(): void {
    if(!esAdministrador()){
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Solo el administrador puede realizar esta accion.']);
        exit;
    }
}
?>
