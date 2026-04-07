<?php
require_once "../config/conexion.php";

// Traemos los cierres ordenados por fecha descendente
$sql = "SELECT c.cierreID, c.fecha, c.tipopago, c.total_ventas, c.total_pagado, c.total_pendiente, 
               c.total_recibido, c.total_vuelto, c.fisico, c.diferencia, c.observacion, u.usuario, c.estado
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
    <style>
        .tabla-ventas th, .tabla-ventas td {
            text-align: center;
            padding: 5px 10px;
        }
        .acciones i {
            cursor: pointer;
            margin: 0 3px;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Historial de Cierres</h2>

    <table class="tabla-ventas">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Tipo Pago</th>
                <th>Total Ventas</th>
                <th>Total Pagado</th>
                <th>Total Pendiente</th>
                <th>Total Recibido</th>
                <th>Total Vuelto</th>
                <th>Físico</th>
                <th>Diferencia</th>
                <th>Observación</th>
                <th>Usuario</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>

        <tbody>
            <?php while($row = $resultado->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
                <td><?= $row['cierreID'] ?></td>
                <td><?= date("d-m-Y H:i", strtotime($row['fecha'])) ?></td>
                <td><?= htmlspecialchars(strtoupper($row['tipopago'])) ?></td>
                <td><?= number_format($row['total_ventas'],2) ?></td>
                <td><?= number_format($row['total_pagado'],2) ?></td>
                <td><?= number_format($row['total_pendiente'],2) ?></td>
                <td><?= number_format($row['total_recibido'],2) ?></td>
                <td><?= number_format($row['total_vuelto'],2) ?></td>
                <td><?= number_format($row['fisico'],2) ?></td>
                <td><?= number_format($row['diferencia'],2) ?></td>
                <td><?= htmlspecialchars($row['observacion']) ?></td>
                <td><?= htmlspecialchars($row['usuario']) ?></td>
                <td><?= $row['estado'] == 1 ? 'Abierto' : 'Anulado' ?></td>
                <td class="acciones">
                    <i class="fa-solid fa-print imprimir-cierre" data-id="<?= $row['cierreID'] ?>" title="Imprimir"></i>
                    <i class="fa-solid fa-eye ver-cierre" data-id="<?= $row['cierreID'] ?>" title="Ver"></i>
                    <?php if($row['estado'] == 1): ?>
                        <i class="fa-solid fa-trash eliminar-cierre" data-id="<?= $row['cierreID'] ?>" title="Eliminar"></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<script src="../assets/js/pos.js"></script>
<script>
    // Opcional: manejo de botones imprimir, ver y eliminar
    document.querySelectorAll('.imprimir-cierre').forEach(btn => {
        btn.addEventListener('click', () => {
            window.open(`imprimir_cierre.php?id=${btn.dataset.id}`, '_blank');
        });
    });

    document.querySelectorAll('.ver-cierre').forEach(btn => {
        btn.addEventListener('click', () => {
            window.location.href = `ver_cierre.php?id=${btn.dataset.id}`;
        });
    });

    document.querySelectorAll('.eliminar-cierre').forEach(btn => {
        btn.addEventListener('click', () => {
            if(confirm('¿Desea eliminar este cierre?')){
                fetch(`eliminar_cierre.php?id=${btn.dataset.id}`, { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if(data.ok) location.reload();
                        else alert(data.error);
                    })
                    .catch(err => alert('Error: '+err));
            }
        });
    });
</script>

</body>
</html>