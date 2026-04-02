<?php
if(!isset($_GET['id'])) { exit("No se especificó ID de venta"); }
$ventaID = intval($_GET['id']);

require_once __DIR__.'/config/conexion.php';

try {
    // DATOS DE LA VENTA
    $stmtVenta = $conn->prepare("
        SELECT v.fecha, v.total, v.tipopago, v.pago, v.vuelto, u.nombre AS vendedor
        FROM ventas v
        INNER JOIN usuarios u ON v.usuarioID = u.usuarioID
        WHERE v.ventaID = :ventaID
    ");
    $stmtVenta->execute([':ventaID' => $ventaID]);
    $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

    if(!$venta){ exit("Venta no encontrada"); }

    // PRODUCTOS
    $stmtProd = $conn->prepare("
        SELECT p.nombre,p.descripcion, dv.cantidad, dv.precioUnitario AS precio, dv.subtotal
        FROM detalleventa dv
        JOIN 
        productos p ON p.productoID = dv.productoID
        WHERE dv.ventaID = :ventaID
    ");
    $stmtProd->execute([':ventaID' => $ventaID]);
    $productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    exit("Error en la base de datos: ".$e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket Venta #<?php echo $ventaID; ?></title>
<link rel="stylesheet" href="assets/css/ticket.css">
</head>
<body onload="window.print()">

<div class="center">
    <img src="assets/img/logo.png" alt="Logo Dulces Majaderias" class="logo">
    <h2>Dulces Majaderías</h2>
    <p><b>RUC:</b> 20615043428</p>
    <p><b>Dirección:</b> CLORINDA MALAGA DE PRADO</p>
    <p>Tel: (01) 123-4567</p>
</div>

<h3 class="center">
    TICKET #<?php echo $ventaID; ?>
</h3>

<p class="center">
    Fecha: <?php echo $venta['fecha']; ?>
</p>

<!-- VENDEDOR -->
<p class="center">
    Cajero: <?php echo htmlspecialchars($venta['vendedor']); ?>
</p>

<p class="center">
    Tipo de Pago: 
    <?php 
        if($venta['tipopago'] == 'efectivo') echo " EFECTIVO";
        elseif($venta['tipopago'] == 'yape') echo " YAPE";
        elseif($venta['tipopago'] == 'plin') echo " PLIN";
        elseif($venta['tipopago'] == 'transferencia') echo " TRANSFERENCIA";
        else echo strtoupper($venta['tipopago']);
    ?>
</p>

<!-- PAGO Y VUELTO -->
<p class="center">
    Pago: S/ <?php echo number_format($venta['pago'], 2); ?>
</p>

<p class="center">
    Vuelto: S/ <?php echo number_format($venta['vuelto'], 2); ?>
</p>

<table>
<thead>
<tr>
<th>Producto</th>
<th>Descripcion</th>
<th class="center">Cant</th>
<th class="right">P.Unit</th>
<th class="right">Subtotal</th>
</tr>
</thead>

<tbody>
<?php foreach($productos as $prod): ?>
<tr>
<td><?php echo htmlspecialchars($prod['nombre']); ?></td>
<td><?php echo htmlspecialchars($prod['descripcion']); ?></td>
<td class="center"><?php echo $prod['cantidad']; ?></td>
<td class="right"><?php echo number_format($prod['precio'],2); ?></td>
<td class="right"><?php echo number_format($prod['subtotal'],2); ?></td>
</tr>
<?php endforeach; ?>
</tbody>

<tfoot>
<tr>
<td colspan="3" class="right"><b>TOTAL</b></td>
<td class="right"><b><?php echo number_format($venta['total'],2); ?></b></td>
</tr>
</tfoot>
</table>

<p class="center">¡Gracias por su compra!</p>
<p class="center">Vuelva pronto :)</p>

</body>
</html>