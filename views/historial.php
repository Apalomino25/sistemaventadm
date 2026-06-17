<?php
require_once "../config/conexion.php";
require_once "../config/schema_helpers.php";

asegurarColumnasPagos($conn);

$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$estadoPagoFiltro = $_GET['estadoPago'] ?? '';
$tipoPagoFiltro = $_GET['tipoPago'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';
$clienteFiltro = (int)($_GET['clienteID'] ?? 0);

$clientes = $conn->query("
    SELECT clienteID, nombre, numeroDocumento
    FROM clientes
    ORDER BY nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$where = ["DATE(v.fecha) BETWEEN :fechaDesde AND :fechaHasta"];
$params = [
    ':fechaDesde' => $fechaDesde,
    ':fechaHasta' => $fechaHasta
];

if(in_array($estadoPagoFiltro, ['pagado', 'pendiente', 'parcial'], true)){
    $where[] = "v.estadoPago = :estadoPago";
    $params[':estadoPago'] = $estadoPagoFiltro;
}

if(in_array($tipoPagoFiltro, ['efectivo', 'yape', 'plin', 'transferencia'], true)){
    $where[] = "(
        v.tipoPago = :tipoPago
        OR EXISTS (
            SELECT 1
            FROM venta_pagos vpf
            WHERE vpf.ventaID = v.ventaID
              AND vpf.estado = 1
              AND vpf.tipoPago = :tipoPagoPago
        )
    )";
    $params[':tipoPago'] = $tipoPagoFiltro;
    $params[':tipoPagoPago'] = $tipoPagoFiltro;
}

if($estadoFiltro !== '' && in_array((int)$estadoFiltro, [0, 1], true)){
    $where[] = "v.estado = :estado";
    $params[':estado'] = (int)$estadoFiltro;
}

if($clienteFiltro > 0){
    $where[] = "v.clienteID = :clienteID";
    $params[':clienteID'] = $clienteFiltro;
}

$sql = "SELECT v.ventaID, c.nombre AS cliente, v.total, v.pago, v.vuelto, v.fecha,
               v.fechaPago, v.tipoPago, v.estado, v.estadoPago,
               COALESCE(vp.pago_efectivo, CASE WHEN v.tipoPago = 'efectivo' THEN GREATEST(v.pago - v.vuelto, 0) ELSE 0 END) AS pago_efectivo,
               COALESCE(vp.pago_yape, CASE WHEN v.tipoPago = 'yape' THEN v.pago ELSE 0 END) AS pago_yape,
               COALESCE(vp.pago_plin, CASE WHEN v.tipoPago = 'plin' THEN v.pago ELSE 0 END) AS pago_plin,
               COALESCE(vp.pago_transferencia, CASE WHEN v.tipoPago = 'transferencia' THEN v.pago ELSE 0 END) AS pago_transferencia,
               COALESCE(dp.saldo_pendiente, GREATEST(v.total - GREATEST(v.pago - v.vuelto, 0), 0)) AS saldo_pendiente
        FROM ventas v
        INNER JOIN clientes c ON v.clienteID = c.clienteID
        LEFT JOIN (
            SELECT ventaID,
                   SUM(CASE WHEN tipoPago = 'efectivo' THEN monto ELSE 0 END) AS pago_efectivo,
                   SUM(CASE WHEN tipoPago = 'yape' THEN monto ELSE 0 END) AS pago_yape,
                   SUM(CASE WHEN tipoPago = 'plin' THEN monto ELSE 0 END) AS pago_plin,
                   SUM(CASE WHEN tipoPago = 'transferencia' THEN monto ELSE 0 END) AS pago_transferencia
            FROM venta_pagos
            WHERE estado = 1
            GROUP BY ventaID
        ) vp ON vp.ventaID = v.ventaID
        LEFT JOIN (
            SELECT ventaID, SUM(saldoPendiente) AS saldo_pendiente
            FROM detalleventa
            GROUP BY ventaID
        ) dp ON dp.ventaID = v.ventaID
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.ventaID DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$resultado = $stmt;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Ventas</title>
    <link rel="stylesheet" href="../assets/css/pos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<div class="container">
    <h2>Historial de Ventas</h2>

    <form class="filtros-historial" data-page="historial.php">
        <label>
            Desde
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
        </label>
        <label>
            Hasta
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
        </label>
        <label>
            Estado pago
            <select name="estadoPago">
                <option value="">Todos</option>
                <option value="pagado" <?= $estadoPagoFiltro === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                <option value="pendiente" <?= $estadoPagoFiltro === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="parcial" <?= $estadoPagoFiltro === 'parcial' ? 'selected' : '' ?>>Parcial</option>
            </select>
        </label>
        <label>
            Tipo pago
            <select name="tipoPago">
                <option value="">Todos</option>
                <option value="efectivo" <?= $tipoPagoFiltro === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                <option value="yape" <?= $tipoPagoFiltro === 'yape' ? 'selected' : '' ?>>Yape</option>
                <option value="plin" <?= $tipoPagoFiltro === 'plin' ? 'selected' : '' ?>>Plin</option>
                <option value="transferencia" <?= $tipoPagoFiltro === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
            </select>
        </label>
        <label>
            Estado venta
            <select name="estado">
                <option value="">Todos</option>
                <option value="1" <?= $estadoFiltro === '1' ? 'selected' : '' ?>>Activo</option>
                <option value="0" <?= $estadoFiltro === '0' ? 'selected' : '' ?>>Anulado</option>
            </select>
        </label>
        <label>
            Cliente
            <select name="clienteID">
                <option value="0">Todos</option>
                <?php foreach($clientes as $cliente): ?>
                    <option value="<?= intval($cliente['clienteID']) ?>" <?= $clienteFiltro === intval($cliente['clienteID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cliente['nombre'] . ($cliente['numeroDocumento'] ? ' - ' . $cliente['numeroDocumento'] : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <table class="tabla-ventas">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Fecha Pago</th>
                <th>Cliente</th>
                <th>Pagado</th>
                <th>Vuelto</th>
                <th>Total</th>
                <th>Saldo</th>
                <th>TipoPago</th>
                <th>Efectivo</th>
                <th>Yape</th>
                <th>Plin</th>
                <th>Transferencia</th>
                <th>EstadoPago</th>
                <th>Pago saldo</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>

        <tbody>
            <?php while($row = $resultado->fetch(PDO::FETCH_ASSOC)): ?>
            <tr class="<?= ($row['estado'] == 0) ? 'inactivo' : '' ?>">
                <td><?= $row['ventaID'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['fechaPago'] ? $row['fechaPago'] : '-' ?></td>
                <td><?= htmlspecialchars($row['cliente']) ?></td>
                <td class="pago"><?= number_format($row['pago'],2) ?></td>
                <td class="vuelto"><?= number_format($row['vuelto'],2) ?></td>
                <td class="total"><?= number_format($row['total'],2) ?></td>
                <td><?= number_format($row['saldo_pendiente'],2) ?></td>
                <td class="tipoPago"><?= htmlspecialchars($row['tipoPago']) ?></td>
                <td><?= number_format($row['pago_efectivo'],2) ?></td>
                <td><?= number_format($row['pago_yape'],2) ?></td>
                <td><?= number_format($row['pago_plin'],2) ?></td>
                <td><?= number_format($row['pago_transferencia'],2) ?></td>
                <td class="estadoPago">
                    <?php if($row['estado'] == 1 && in_array($row['estadoPago'], ['pendiente', 'parcial'], true)): ?>
                        <select class="editar-estado-pago" data-id="<?= $row['ventaID'] ?>" data-estado-actual="<?= htmlspecialchars($row['estadoPago']) ?>">
                            <option value="<?= htmlspecialchars($row['estadoPago']) ?>" selected><?= ucfirst(htmlspecialchars($row['estadoPago'])) ?></option>
                            <option value="pagado">Pagado</option>
                        </select>
                    <?php else: ?>
                        <?= htmlspecialchars($row['estadoPago']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($row['estado'] == 1 && in_array($row['estadoPago'], ['pendiente', 'parcial'], true)): ?>
                        <select class="tipo-pago-saldo" data-id="<?= $row['ventaID'] ?>">
                            <option value="efectivo">Efectivo</option>
                            <option value="yape">Yape</option>
                            <option value="plin">Plin</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
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



<script src="../assets/js/pos.js"></script>
</body>
</html>
