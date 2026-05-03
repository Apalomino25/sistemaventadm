<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');
$cierreRealizado = false;
$cierres = [];
$detalleGanancia = [];

try {
    asegurarColumnasPagos($conn);

    $stmtCierre = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE fecha = ? AND estado = 1");
    $stmtCierre->execute([$hoy]);
    $cierreRealizado = $stmtCierre->fetchColumn() > 0;

    if($cierreRealizado){
        $stmt = $conn->prepare("
            SELECT tipopago, total_ventas, total_pagado, total_pendiente, total_pendientes_cobrados, total_recibido,
                   total_vuelto, total_compra, total_ganancia, fisico, diferencia, observacion
            FROM cierres
            WHERE fecha = ? AND estado = 1
            ORDER BY tipopago
        ");
        $stmt->execute([$hoy]);
    } else {
        $stmt = $conn->prepare("
            SELECT
                vt.tipopago,
                vt.total_ventas,
                vt.total_pagado,
                vt.total_pendiente,
                vt.total_pendientes_cobrados,
                vt.total_recibido,
                vt.total_vuelto,
                COALESCE(gt.total_compra, 0) AS total_compra,
                COALESCE(gt.total_ganancia, 0) AS total_ganancia,
                vt.total_recibido AS fisico,
                0.00 AS diferencia,
                'Correcto' AS observacion
            FROM (
                SELECT
                    vp.tipoPago AS tipopago,
                    COALESCE(SUM(vp.monto), 0) AS total_ventas,
                    COALESCE(SUM(vp.monto), 0) AS total_pagado,
                    0 AS total_pendiente,
                    COALESCE(SUM(CASE WHEN DATE(v.fecha) < ? THEN vp.monto ELSE 0 END), 0) AS total_pendientes_cobrados,
                    COALESCE(SUM(vp.monto), 0) AS total_recibido,
                    0 AS total_vuelto
                FROM venta_pagos vp
                INNER JOIN ventas v ON v.ventaID = vp.ventaID
                WHERE DATE(vp.fechaPago) = ?
                  AND vp.estado = 1
                  AND v.estado = 1
                GROUP BY vp.tipoPago

                UNION ALL

                SELECT
                    'pendiente' AS tipopago,
                    0 AS total_ventas,
                    0 AS total_pagado,
                    COALESCE(SUM(d.subtotal), 0) AS total_pendiente,
                    0 AS total_pendientes_cobrados,
                    0 AS total_recibido,
                    0 AS total_vuelto
                FROM detalleventa d
                INNER JOIN ventas v ON v.ventaID = d.ventaID
                WHERE d.estadoPago = 'pendiente'
                  AND DATE(v.fecha) = ?
                  AND v.estado = 1
                HAVING total_pendiente > 0
            ) vt
            LEFT JOIN (
                SELECT
                    v.tipoPago AS tipopago,
                    COALESCE(SUM(d.cantidad * p.precioCompra), 0) AS total_compra,
                    COALESCE(SUM((d.precioUnitario - p.precioCompra) * d.cantidad), 0) AS total_ganancia
                FROM ventas v
                INNER JOIN detalleventa d ON d.ventaID = v.ventaID
                INNER JOIN productos p ON p.productoID = d.productoID
                WHERE DATE(v.fechaPago) = ?
                  AND v.estadoPago = 'pagado'
                  AND v.estado = 1
                GROUP BY v.tipoPago
            ) gt ON gt.tipopago = vt.tipopago
            ORDER BY vt.tipopago
        ");
        $stmt->execute([$hoy, $hoy, $hoy, $hoy]);
    }

    $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtGanancia = $conn->prepare("
        SELECT p.nombre, d.cantidad, p.precioCompra, d.precioUnitario AS precioVenta,
               d.subtotal AS totalVenta,
               (p.precioCompra * d.cantidad) AS totalCompra,
               (d.precioUnitario - p.precioCompra) * d.cantidad AS ganancia
        FROM detalleventa d
        INNER JOIN productos p ON d.productoID = p.productoID
        INNER JOIN ventas v ON d.ventaID = v.ventaID
        WHERE DATE(d.fechaPago) = ?
          AND d.estadoPago = 'pagado'
          AND v.estado = 1
        ORDER BY p.nombre
    ");
    $stmtGanancia->execute([$hoy]);
    $detalleGanancia = $stmtGanancia->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

$totales = [
    'total_ventas' => 0,
    'total_pagado' => 0,
    'total_pendiente' => 0,
    'total_pendientes_cobrados' => 0,
    'total_recibido' => 0,
    'total_vuelto' => 0,
    'total_compra' => 0,
    'total_ganancia' => 0,
    'fisico' => 0,
    'diferencia' => 0
];

foreach($cierres as $c){
    foreach($totales as $campo => $_){
        $totales[$campo] += floatval($c[$campo] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cierre de Caja</title>
<link rel="stylesheet" href="../assets/css/cierres.css?v=<?= filemtime(__DIR__ . '/../assets/css/cierres.css') ?>">
</head>
<body>

<div class="cierres-container">
    <h2>Cierre del Dia (<?= date('d-m-Y') ?>)</h2>

    <?php if($cierreRealizado): ?>
        <div class="cierre-aviso cerrado">El cierre de hoy ya fue realizado. Las ventas estan bloqueadas para este dia.</div>
    <?php elseif(empty($cierres)): ?>
        <div class="cierre-aviso">No hay ventas activas hoy para cerrar.</div>
    <?php else: ?>
        <div class="cierre-aviso pendiente">Revisa los montos fisicos antes de guardar. Al cerrar, ya no se podran registrar ventas hoy.</div>
    <?php endif; ?>

    <table id="tabla-cierres">
        <thead>
            <tr>
                <th>Tipo Pago</th>
                <th>Total Ventas</th>
                <th>Pagado</th>
                <th>Pendiente</th>
                <th>Boletas Pend. Cobradas</th>
                <th>Recibido</th>
                <th>Vuelto</th>
                <th>Compra</th>
                <th>Ganancia</th>
                <th>Fisico</th>
                <th>Observacion</th>
                <th>Diferencia</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($cierres as $c): ?>
            <tr>
                <td><?= htmlspecialchars(strtoupper($c['tipopago'])) ?></td>
                <td>S/ <?= number_format($c['total_ventas'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($c['total_pagado'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($c['total_pendiente'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($c['total_pendientes_cobrados'], 2, '.', '') ?></td>
                <td class="total-recibido">S/ <?= number_format($c['total_recibido'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($c['total_vuelto'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($c['total_compra'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($c['total_ganancia'], 2, '.', '') ?></td>
                <td>
                    <input
                        type="number"
                        step="0.01"
                        class="fisico"
                        data-tipopago="<?= htmlspecialchars($c['tipopago']) ?>"
                        data-total="<?= number_format($c['total_recibido'], 2, '.', '') ?>"
                        value="<?= number_format($c['fisico'], 2, '.', '') ?>"
                        <?= $cierreRealizado ? 'readonly' : '' ?>
                    >
                </td>
                <td class="obs"><?= htmlspecialchars($c['observacion']) ?></td>
                <td class="diferencia">S/ <?= number_format($c['diferencia'], 2, '.', '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th>S/ <?= number_format($totales['total_ventas'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_pagado'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_pendiente'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_pendientes_cobrados'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_recibido'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_vuelto'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_compra'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_ganancia'], 2, '.', '') ?></th>
                <th id="totalFisico">S/ <?= number_format($totales['fisico'], 2, '.', '') ?></th>
                <th></th>
                <th id="totalDiferencia">S/ <?= number_format($totales['diferencia'], 2, '.', '') ?></th>
            </tr>
        </tfoot>
    </table>

    <div class="cont-guardar-cierre">
        <button
            id="guardar-cierre"
            <?= ($cierreRealizado || empty($cierres)) ? 'disabled' : '' ?>
        >Guardar Cierre</button>
    </div>

    <h2>Detalle de Ganancia del Dia</h2>

    <table id="tabla-ganancia">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Compra</th>
                <th>Precio Venta</th>
                <th>Total Compra</th>
                <th>Total Venta</th>
                <th>Ganancia</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($detalleGanancia as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['nombre']) ?></td>
                <td><?= intval($d['cantidad']) ?></td>
                <td>S/ <?= number_format($d['precioCompra'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($d['precioVenta'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($d['totalCompra'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($d['totalVenta'], 2, '.', '') ?></td>
                <td>S/ <?= number_format($d['ganancia'], 2, '.', '') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="4" style="text-align:right">Totales:</th>
                <th>S/ <?= number_format($totales['total_compra'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_ventas'], 2, '.', '') ?></th>
                <th>S/ <?= number_format($totales['total_ganancia'], 2, '.', '') ?></th>
            </tr>
        </tbody>
    </table>
</div>

<script src="../assets/js/pos.js?v=<?= filemtime(__DIR__ . '/../assets/js/pos.js') ?>"></script>
</body>
</html>
