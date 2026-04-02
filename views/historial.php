<?php
require_once "../config/conexion.php";

// Traemos estado para mostrar activo/inactivo
$sql = "SELECT v.ventaID, c.nombre AS cliente, v.total, v.pago, v.vuelto, v.fecha,v.tipoPago, v.estado
        FROM 
         ventas v
        INNER JOIN 
         clientes c ON v.clienteID = c.clienteID
        WHERE
          DATE(v.fecha) = CURDATE()
        ORDER BY 
         v.fecha DESC;";

$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Ventas</title>
    <link rel="stylesheet" href="../assets/css/historial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<div class="container">

    <table class="tabla-ventas">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Pagado</th>
                <th>Vuelto</th>
                <th>Total</th>
                <th>TipoPago</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>

        <tbody>
            <?php while($row = $resultado->fetch(PDO::FETCH_ASSOC)): ?>
            <tr class="<?= ($row['estado'] == 0) ? 'inactivo' : '' ?>">
                <td><?= $row['ventaID'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= htmlspecialchars($row['cliente']) ?></td>
                <td class="pago"><?= number_format($row['pago'],2) ?></td>
                <td class="vuelto"><?= number_format($row['vuelto'],2) ?></td>
                <td class="total"><?= number_format($row['total'],2) ?></td>
                <td class="tipoPago"><?= ($row['tipoPago']) ?></td>
                <td><?= ($row['estado'] == 1) ? 'Activo' : 'Anulado' ?></td>
                <td class="acciones">
                    <i class="fa-solid fa-print imprimir" data-id="<?= $row['ventaID'] ?>"></i>
                    <i class="fa-solid fa-eye ver" data-id="<?= $row['ventaID'] ?>"></i>
                    <?php if($row['estado'] == 1): ?>
                        <i class="fa-solid fa-trash eliminar" data-id="<?= $row['ventaID'] ?>"></i>
                    <?php endif; ?>
                </td>
                </tr>
                <?php endwhile; ?>
        </tbody>
    </table>

</div>



<!-- <script src="../assets/js/historial.js"></script> -->
</body>
</html>