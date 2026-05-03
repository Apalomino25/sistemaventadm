
<?php
session_start();
$rolSesion = strtolower(trim((string)($_SESSION['rol'] ?? '')));
$esAdmin = in_array($rolSesion, ['administrador', 'admin'], true);
?>

<!DOCTYPE html>
<html lang="es">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas</title>
    <link rel="stylesheet" href="../assets/css/home.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/home.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<header class="navbar">

    <div class="navbar-left">
        <img src="../assets/img/logo.png" class="logo" id="logo">
        <div class="titulo">
        <h1>Sistema de Ventas</h1>
        <p>Dulces Majaderias</p>
        </div>
    </div>

    <div class="navbar-medium">

    <div class="dropdown">
        <button class="menu-btn">
            <i class="fa-solid fa-cash-register"></i> <span class="titulo-botones">POS</span>
            <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-content">
            <a onclick="cargarPagina('pos.php')">
                <i class="fa-solid fa-clock"></i> Ventas
            </a>
            <a onclick="cargarPagina('historial.php')">
                <i class="fa-solid fa-clock"></i> Hist_Ventas
            </a>
        </div>
    </div>

    <div class="dropdown">
        <button class="menu-btn">
            <i class="fa-solid fa-lock"></i> <span class="titulo-botones">CIERRE</span>
            <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-content">
            <a onclick="cargarPagina('cierres.php')">
                <i class="fa-solid fa-cart-shopping"></i>Cierre
            </a>
            <a onclick="cargarPagina('historial_cierre.php')">
                <i class="fa-solid fa-chart-line"></i> hist_Cierres
            </a>
        </div>
    </div>

    <div class="dropdown">
        <button class="menu-btn">
            <i class="fa-solid fa-chart-column"></i> <span class="titulo-botones">REPORTES</span>
            <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-content">
            <a onclick="cargarPagina('reportes.php')"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a onclick="cargarPagina('reportes.php')"><i class="fa-solid fa-box"></i> Productos y Clientes</a>
        </div>
    </div>

    <?php if($esAdmin): ?>
    <div class="dropdown">
        <button class="menu-btn" onclick="cargarPagina('inventarios.php')">
            <i class="fa-solid fa-boxes-stacked"></i> <span class="titulo-botones">INVENTARIOS</span>
        </button>
    </div>
    <?php endif; ?>

    <div class="dropdown">
        <button class="menu-btn">
            <i class="fa-solid fa-truck"></i><span class="titulo-botones"> GUIAS</span>
            <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-content">
            <!-- <a href="#"><i class="fa-solid fa-file"></i> Nueva Guía</a> -->
            <a onclick="cargarPagina('hist_guias.html')">
                <i class="fa-solid fa-folder"></i> Historial Guias
            </a>
        </div>
    </div>
    </div>

<div class="navbar-right">
    <div class="user-dropdown">
        <div class="user-info" id="userBtn">
            <i class="fa-solid fa-circle-user"></i>
            <span>
                <?php echo $_SESSION['usuario'] ?? 'Usuario'; ?>
            </span>
            <i class="fa-solid fa-chevron-down" ></i>
        </div>
        <div class="user-menu" id="userMenu">
            <a href="../controllers/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </a>
        </div>
    </div>
</div>

</header>

<main id="contenido"></main>

<script src="../assets/js/pos.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/pos.js'); ?>"></script>
<script src="../assets/js/inventarios.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/inventarios.js'); ?>"></script>
<script src="../assets/js/home.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/home.js'); ?>"></script>

</body>
</html>

