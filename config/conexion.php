<?php

    $host = "localhost";
    $dbname = "sistemaventasdm";   // nombre de tu base de datos
    $user = "root";           // usuario de MySQL
    $password = "";           // contraseña (en XAMPP normalmente está vacía)

    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
        // Mostrar errores si algo falla
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } 
    catch(PDOException $e) {
        echo "Error de conexión: " . $e->getMessage();
}
?>