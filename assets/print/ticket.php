<?php

include "../../config/conexion.php";

$ventaID = $_GET["id"];

// obtener datos de venta
$stmt = $conn->query("
SELECT v.*, c.nombre AS cliente, u.usuario AS vendedor
FROM ventas v
JOIN clientes c ON v.clienteID = c.clienteID
JOIN usuarios u ON v.usuarioID = u.usuarioID
WHERE v.ventaID = $ventaID
");

$venta = $stmt->fetch(PDO::FETCH_ASSOC);

// obtener productos vendidos
$detalle = $conn->query("
SELECT d.*, p.nombre
FROM detalleventa d
JOIN productos p ON d.productoID = p.productoID
WHERE d.ventaID = $ventaID
");

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>

body{
    font-family: monospace;
    width:280px;
}

table{
    width:100%;
}

td{
    font-size:12px;
}

</style>

</head>

<body onload="window.print()">

<center>
<h3>MI TIENDA</h3>
</center>

Fecha: <?php echo $venta["fecha"]; ?><br>
Cliente: <?php echo $venta["cliente"]; ?><br>
Vendedor: <?php echo $venta["vendedor"]; ?><br>

<hr>

<table>

<?php while($row = $detalle->fetch(PDO::FETCH_ASSOC)){ ?>

<tr>
<td colspan="2"><?php echo $row["nombre"]; ?></td>
</tr>

<tr>
<td>
<?php echo $row["cantidad"]; ?> x 
<?php echo number_format($row["precioUnitario"],2); ?>
</td>

<td align="right">
<?php echo number_format($row["subtotal"],2); ?>
</td>
</tr>

<?php } ?>

</table>

<hr>

<b>TOTAL: S/ <?php echo number_format($venta["total"],2); ?></b>

<br><br>

<center>
Gracias por su compra
</center>

</body>
</html>