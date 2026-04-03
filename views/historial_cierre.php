<?php
require_once "../config/conexion.php";

// Traemos los cierres ordenados por fecha descendente
$sql = "SELECT c.cierreID, c.fecha, c.tipopago, c.total_ventas, c.total_recibido, c.fisico, c.diferencia, c.observacion, u.usuario, c.estado
        FROM cierres c
        LEFT JOIN usuarios u ON c.usuarioID = u.usuarioID
        ORDER BY c.cierreID DESC";

$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Cierres</title>
    <link rel="stylesheet" href="../assets/css/pos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="container">

    <table class="tabla-ventas">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>TipoPago</th>
                <th>Total Ventas</th>
                <th>Total Recibido</th>
                <th>Fisico</th>
                <th>Diferencia</th>
                <th>Estado</th>
                <th>Observación</th>
                <th>Usuario</th>
                <th>Acción</th>
            </tr>
        </thead>

        <tbody>
            <?php while($row = $resultado->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
                <td><?= $row['cierreID'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= htmlspecialchars($row['tipopago']) ?></td>
                <td><?= number_format($row['total_ventas'],2) ?></td>
                <td><?= number_format($row['total_recibido'],2) ?></td>
                <td><?= number_format($row['fisico'],2) ?></td>
                <td><?= number_format($row['diferencia'],2) ?></td>
                <td><?= $row['estado'] == 1 ? 'Abierto' : 'Anulado' ?></td>
                <td><?= htmlspecialchars($row['observacion']) ?></td>
                <td><?= htmlspecialchars($row['usuario']) ?></td>
                <td class="acciones">
                    <i class="fa-solid fa-print imprimir-cierre" data-id="<?= $row['cierreID'] ?>" title="Imprimir"></i>
                    <i class="fa-solid fa-eye ver-cierre" data-id="<?= $row['cierreID'] ?>" title="Ver"></i>
                    <?php if($row['estado'] == 1): // Solo mostrar si está abierto ?>
                        <i class="fa-solid fa-trash eliminar-cierre" data-id="<?= $row['cierreID'] ?>" title="Eliminar"></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<script src="../assets/js/pos.js"></script>
</body>
</html>