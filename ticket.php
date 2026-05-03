<?php
if(!isset($_GET['id'])) {
    exit("No se especifico ID de venta");
}

$ventaID = intval($_GET['id']);

require_once __DIR__ . '/config/conexion.php';

try {
    $stmtVenta = $conn->prepare("
        SELECT v.fecha, v.total, v.tipoPago, v.pago, v.vuelto, u.nombre AS vendedor,
               c.nombre AS cliente
        FROM ventas v
        INNER JOIN usuarios u ON v.usuarioID = u.usuarioID
        INNER JOIN clientes c ON c.clienteID = v.clienteID
        WHERE v.ventaID = :ventaID
    ");
    $stmtVenta->execute([':ventaID' => $ventaID]);
    $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

    if(!$venta) {
        exit("Venta no encontrada");
    }

    $stmtProd = $conn->prepare("
        SELECT p.nombre, p.descripcion, dv.cantidad, dv.precioUnitario AS precio, dv.subtotal
        FROM detalleventa dv
        INNER JOIN productos p ON p.productoID = dv.productoID
        WHERE dv.ventaID = :ventaID
        ORDER BY dv.detalleID ASC
    ");
    $stmtProd->execute([':ventaID' => $ventaID]);
    $productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    exit("Error en la base de datos: " . $e->getMessage());
}

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dinero($valor) {
    return number_format((float)$valor, 2, '.', '');
}

$tipoPagoTexto = strtoupper((string)$venta['tipoPago']);
if($venta['tipoPago'] === 'efectivo') {
    $tipoPagoTexto = 'EFECTIVO';
} elseif($venta['tipoPago'] === 'yape') {
    $tipoPagoTexto = 'YAPE';
} elseif($venta['tipoPago'] === 'plin') {
    $tipoPagoTexto = 'PLIN';
} elseif($venta['tipoPago'] === 'transferencia') {
    $tipoPagoTexto = 'TRANSFERENCIA';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket Venta #<?= $ventaID ?></title>
<link rel="stylesheet" href="assets/css/ticket.css?v=<?= filemtime(__DIR__ . '/assets/css/ticket.css') ?>">
</head>
<body>

<div class="ticket">
    <div class="center">
        <img src="assets/img/logo.png" alt="Logo Dulces Majaderias" class="logo">
        <h2>Dulces Majaderias</h2>
        <p><b>RUC:</b> 20615043428</p>
        <p><b>Direccion:</b> CLORINDA MALAGA DE PRADO</p>
        <p>Tel: (01) 123-4567</p>
    </div>

    <div class="line"></div>

    <p class="center"><b>TICKET #<?= $ventaID ?></b></p>
    <p>Fecha: <?= h($venta['fecha']) ?></p>
    <p>Cliente: <?= h($venta['cliente']) ?></p>
    <p>Cajero: <?= h($venta['vendedor']) ?></p>
    <p>Pago: <?= h($tipoPagoTexto) ?></p>

    <div class="line"></div>

    <?php foreach($productos as $prod): ?>
        <div class="item">
            <div class="item-name"><?= h($prod['nombre']) ?></div>
            <?php if(!empty($prod['descripcion'])): ?>
                <div class="item-desc"><?= h($prod['descripcion']) ?></div>
            <?php endif; ?>
            <div class="item-values">
                <span><?= (int)$prod['cantidad'] ?> x <?= dinero($prod['precio']) ?></span>
                <span>S/ <?= dinero($prod['subtotal']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="line"></div>

    <div class="totals">
        <div><span>TOTAL</span><b>S/ <?= dinero($venta['total']) ?></b></div>
        <div><span>PAGO</span><span>S/ <?= dinero($venta['pago']) ?></span></div>
        <div><span>VUELTO</span><span>S/ <?= dinero($venta['vuelto']) ?></span></div>
    </div>

    <div class="line"></div>

    <p class="center">Gracias por su compra</p>
    <p class="center">Vuelva pronto</p>
</div>

</body>
</html>
