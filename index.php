<?php
session_start();

if(isset($_SESSION['usuario'])){
    header("Location: views/home.php");
    exit;
}else{
    header("Location: views/login.html");
    exit;
}
?>
