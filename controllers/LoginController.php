<?php
session_start();

header('Content-Type: application/json');

require_once "../config/conexion.php";

try {

    if($_SERVER["REQUEST_METHOD"] == "POST"){

        $usuario = $_POST["usuario"] ?? '';
        $contrasenia = $_POST["contrasenia"] ?? '';
        // $usuarioID = $POST["usuarioID"] ?? '';

        $sql = "SELECT * FROM usuarios 
                WHERE usuario = ? 
                AND contrasenia = ?
                -- AND usuarioID = ? 
                AND estado = 1";

        $stmt = $conn->prepare($sql);

        // ✅ CORRECTO EN PDO
        $stmt->execute([$usuario, $contrasenia]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user){

            $_SESSION["usuario"] = $user["usuario"];
            $_SESSION["rol"] = $user["rol"];
            $_SESSION["nombre"] = $user["nombre"];
            $_SESSION["usuarioID"] = $user["usuarioID"];

            echo json_encode([
                "status" => "ok"
            ]);

        } else {

            echo json_encode([
                "status" => "error",
                "mensaje" => "Usuario o contraseña incorrectos"
            ]);

        }

    }

} catch (Throwable $e){

    echo json_encode([
        "status" => "error",
        "mensaje" => "Error del servidor",
        "detalle" => $e->getMessage()
    ]);
}