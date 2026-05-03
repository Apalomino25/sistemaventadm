<?php
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../config/schema_helpers.php";

asegurarColumnasPagos($conn);

$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';

$where = [];
$params = [];

if($fechaDesde !== '' && $fechaHasta !== ''){
    $where[] = "c.fecha BETWEEN :fechaDesde AND :fechaHasta";
    $params[':fechaDesde'] = $fechaDesde;
    $params[':fechaHasta'] = $fechaHasta;
}

if($estadoFiltro !== '' && in_array((int)$estadoFiltro, [0, 1], true)){
    $where[] = "c.estado = :estado";
    $params[':estado'] = (int)$estadoFiltro;
}

$sql = "
    SELECT
        c.fecha,
        MIN(c.cierreID) AS cierreID,
        COUNT(*) AS formas_pago,
        SUM(c.total_ventas) AS total_ventas,
        SUM(c.total_pagado) AS total_pagado,
        SUM(c.total_pendiente) AS total_pendiente,
        SUM(c.total_pendientes_cobrados) AS total_pendientes_cobrados,
        SUM(c.total_recibido) AS total_recibido,
        SUM(c.total_vuelto) AS total_vuelto,
        SUM(c.total_compra) AS total_compra,
        SUM(c.total_ganancia) AS total_ganancia,
        SUM(c.fisico) AS fisico,
        SUM(c.diferencia) AS diferencia,
        MAX(c.creado_en) AS creado_en,
        MAX(u.usuario) AS usuario,
        MIN(c.estado) AS estado
    FROM cierres c
    LEFT JOIN usuarios u ON c.usuarioID = u.usuarioID
";

if(!empty($where)){
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= "
    GROUP BY c.fecha
    ORDER BY c.fecha DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$resultado = $stmt;
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
            padding: 6px 10px;
        }
        .acciones i {
            cursor: pointer;
            margin: 0 4px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Historial de Cierres Diarios</h2>

    <form class="filtros-historial" data-page="historial_cierre.php">
        <label>
            Desde
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
        </label>
        <label>
            Hasta
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
        </label>
        <label>
            Estado
            <select name="estado">
                <option value="">Todos</option>
                <option value="1" <?= $estadoFiltro === '1' ? 'selected' : '' ?>>Cerrado</option>
                <option value="0" <?= $estadoFiltro === '0' ? 'selected' : '' ?>>Anulado</option>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <table class="tabla-ventas">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Formas Pago</th>
                <th>Total Ventas</th>
                <th>Pagado</th>
                <th>Pendiente</th>
                <th>Boletas Pend. Cobradas</th>
                <th>Recibido</th>
                <th>Fisico</th>
                <th>Diferencia</th>
                <th>Ganancia</th>
                <th>Usuario</th>
                <th>Estado</th>
                <th>Accion</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $resultado->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
                <td><?= date("d-m-Y", strtotime($row['fecha'])) ?></td>
                <td><?= intval($row['formas_pago']) ?></td>
                <td>S/ <?= number_format($row['total_ventas'], 2) ?></td>
                <td>S/ <?= number_format($row['total_pagado'], 2) ?></td>
                <td>S/ <?= number_format($row['total_pendiente'], 2) ?></td>
                <td>S/ <?= number_format($row['total_pendientes_cobrados'], 2) ?></td>
                <td>S/ <?= number_format($row['total_recibido'], 2) ?></td>
                <td>S/ <?= number_format($row['fisico'], 2) ?></td>
                <td>S/ <?= number_format($row['diferencia'], 2) ?></td>
                <td>S/ <?= number_format($row['total_ganancia'], 2) ?></td>
                <td><?= htmlspecialchars($row['usuario'] ?? '') ?></td>
                <td><?= intval($row['estado']) === 1 ? 'Cerrado' : 'Anulado' ?></td>
                <td class="acciones">
                    <i class="fa-solid fa-print imprimir-cierre" data-id="<?= $row['fecha'] ?>" title="Imprimir"></i>
                    <i class="fa-solid fa-eye ver-cierre" data-id="<?= $row['fecha'] ?>" title="Ver"></i>
                    <?php if(intval($row['estado']) === 1): ?>
                        <i class="fa-solid fa-trash eliminar-cierre" data-id="<?= $row['fecha'] ?>" title="Anular"></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script src="../assets/js/pos.js?v=<?= filemtime(__DIR__ . '/../assets/js/pos.js') ?>"></script>
</body>
</html>
