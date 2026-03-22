<?php
    session_start();
    // destruir todas las variables de sesión
    session_unset();
    // destruir la sesión
    session_destroy();
    // redirigir al login
    header("Location: ../views/login.html");
    exit;
?>