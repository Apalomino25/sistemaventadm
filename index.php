<?php
session_start();

if(isset($_SESSION['usuario'])){
    header("Location: views/pos.html");
    exit;
}else{
    header("Location: views/login.html");
    exit;
}
?>
