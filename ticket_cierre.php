<?php
if(!isset($_GET['id'])){
    exit("No se especifico cierre");
}

$id = trim($_GET['id']);

require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/schema_helpers.php';

asegurarColumnasPagos($conn);

if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $id)){
    $fecha = $id;
} else {
    $stmtFecha = $conn->prepare("SELECT fecha FROM cierres WHERE cierreID = ? LIMIT 1");
    $stmtFecha->execute([intval($id)]);
    $fecha = $stmtFecha->fetchColumn();

    if(!$fecha){
        exit("Cierre no encontrado");
    }
}

$stmt = $conn->prepare("
    SELECT c.*, u.usuario
    FROM cierres c
    LEFT JOIN usuarios u ON c.usuarioID = u.usuarioID
    WHERE c.fecha = ?
    ORDER BY c.tipopago
");
$stmt->execute([$fecha]);
$cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(!$cierres){
    exit("Cierre no encontrado");
}

$totales = [
    'total_ventas' => 0,
    'total_pagado' => 0,
    'total_pendiente' => 0,
    'total_pendientes_cobrados' => 0,
    'total_recibido' => 0,
    'fisico' => 0,
    'diferencia' => 0,
    'total_ganancia' => 0
];

foreach($cierres as $c){
    foreach($totales as $campo => $_){
        $totales[$campo] += floatval($c[$campo] ?? 0);
    }
}

function dinero($valor){
    return number_format((float)$valor, 2, '.', '');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cierre <?= htmlspecialchars($fecha) ?></title>
<link rel="stylesheet" href="assets/css/ticket.css?v=<?= filemtime(__DIR__ . '/assets/css/ticket.css') ?>">
</head>
<body>
<div class="ticket">
    <div class="center">
        <img src="assets/img/logo.png" alt="Logo Dulces Majaderias" class="logo">
        <h2>Cierre de Caja</h2>
        <p>Dulces Majaderias</p>
    </div>

    <div class="line"></div>
    <p>Fecha: <?= date('d-m-Y', strtotime($fecha)) ?></p>
    <p>Usuario: <?= htmlspecialchars($cierres[0]['usuario'] ?? '') ?></p>
    <div class="line"></div>

    <?php foreach($cierres as $c): ?>
        <div class="item">
            <div class="item-name"><?= htmlspecialchars(strtoupper($c['tipopago'])) ?></div>
            <div class="item-values"><span>Ventas</span><span>S/ <?= dinero($c['total_ventas']) ?></span></div>
            <div class="item-values"><span>Recibido</span><span>S/ <?= dinero($c['total_recibido']) ?></span></div>
            <div class="item-values"><span>Fisico</span><span>S/ <?= dinero($c['fisico']) ?></span></div>
            <div class="item-values"><span>Diferencia</span><span>S/ <?= dinero($c['diferencia']) ?></span></div>
        </div>
    <?php endforeach; ?>

    <div class="line"></div>
    <div class="totals">
        <div><span>TOTAL VENTAS</span><b>S/ <?= dinero($totales['total_ventas']) ?></b></div>
        <div><span>PAGADO</span><span>S/ <?= dinero($totales['total_pagado']) ?></span></div>
        <div><span>PENDIENTE</span><span>S/ <?= dinero($totales['total_pendiente']) ?></span></div>
        <div><span>BOLETAS PEND.</span><span>S/ <?= dinero($totales['total_pendientes_cobrados']) ?></span></div>
        <div><span>RECIBIDO</span><span>S/ <?= dinero($totales['total_recibido']) ?></span></div>
        <div><span>FISICO</span><span>S/ <?= dinero($totales['fisico']) ?></span></div>
        <div><span>DIFERENCIA</span><span>S/ <?= dinero($totales['diferencia']) ?></span></div>
        <div><span>GANANCIA</span><span>S/ <?= dinero($totales['total_ganancia']) ?></span></div>
    </div>
    <div class="line"></div>
    <p class="center">Cierre diario</p>
</div>
</body>
</html>
