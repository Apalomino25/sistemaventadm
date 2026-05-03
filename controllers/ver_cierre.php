<?php
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../config/schema_helpers.php";

asegurarColumnasPagos($conn);

$id = trim($_GET['id'] ?? '');

if($id === ''){
    exit("No se especifico cierre");
}

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

// Obtener info del cierre
$stmtCierre = $conn->prepare("
    SELECT c.*, u.usuario
    FROM cierres c
    LEFT JOIN usuarios u ON c.usuario_cierre = u.usuarioID
    WHERE c.fecha = ?
");
$stmtCierre->execute([$fecha]);
$cierre = $stmtCierre->fetch(PDO::FETCH_ASSOC);

if(!$cierre){
    exit("Cierre no encontrado");
}

// Obtener datos de ventas por tipo de pago
$stmtVentas = $conn->prepare("
    SELECT 
        COALESCE(v.tipoPago, 'sin_tipo') as tipopago,
        COUNT(v.ventaID) as cantidad_ventas,
        SUM(v.total) as total_ventas,
        SUM(CASE WHEN v.estadoPago = 'pagado' THEN v.pago ELSE 0 END) as total_pagado,
        SUM(CASE WHEN v.estadoPago = 'pendiente' THEN v.total ELSE 0 END) as total_pendiente,
        SUM(v.vuelto) as total_vuelto,
        SUM(CASE WHEN v.estadoPago = 'pendiente' THEN 0 ELSE v.pago END) as total_recibido
    FROM ventas v
    WHERE DATE(v.fecha) = ? AND v.estado = 1
    GROUP BY v.tipoPago
");
$stmtVentas->execute([$fecha]);
$cierres = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

if(!$cierres){
    $cierres = [];
}

$totales = [
    'total_ventas' => 0,
    'total_pagado' => 0,
    'total_pendiente' => 0,
    'total_vuelto' => 0,
    'total_recibido' => 0,
    'cantidad_ventas' => 0
];

foreach($cierres as $c){
    $totales['total_ventas'] += floatval($c['total_ventas'] ?? 0);
    $totales['total_pagado'] += floatval($c['total_pagado'] ?? 0);
    $totales['total_pendiente'] += floatval($c['total_pendiente'] ?? 0);
    $totales['total_vuelto'] += floatval($c['total_vuelto'] ?? 0);
    $totales['total_recibido'] += floatval($c['total_recibido'] ?? 0);
    $totales['cantidad_ventas'] += intval($c['cantidad_ventas'] ?? 0);
}

foreach($cierres as $c){
    foreach($totales as $campo => $_){
        $totales[$campo] += floatval($c[$campo] ?? 0);
    }
}
?>

<div class="detalle-cierre-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;max-width:1000px;width:100%;max-height:90vh;overflow:auto;padding:20px;border-radius:8px;">
        <button type="button" onclick="this.closest('.detalle-cierre-modal').remove()" style="float:right;padding:6px 10px;cursor:pointer;">Cerrar</button>
        <h2>Cierre del <?= date('d-m-Y', strtotime($fecha)) ?></h2>
        <table class="tabla-ventas" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Tipo Pago</th>
                    <th>Cant. Ventas</th>
                    <th>Total Venta</th>
                    <th>Pagado</th>
                    <th>Pendiente</th>
                    <th>Recibido</th>
                    <th>Vuelto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cierres as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(strtoupper($c['tipopago'] ?? 'sin_tipo')) ?></td>
                    <td><?= intval($c['cantidad_ventas'] ?? 0) ?></td>
                    <td>S/ <?= number_format(floatval($c['total_ventas'] ?? 0), 2) ?></td>
                    <td>S/ <?= number_format(floatval($c['total_pagado'] ?? 0), 2) ?></td>
                    <td>S/ <?= number_format(floatval($c['total_pendiente'] ?? 0), 2) ?></td>
                    <td>S/ <?= number_format(floatval($c['total_recibido'] ?? 0), 2) ?></td>
                    <td>S/ <?= number_format(floatval($c['total_vuelto'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th><?= intval($totales['cantidad_ventas']) ?></th>
                    <th>S/ <?= number_format($totales['total_ventas'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_pagado'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_pendiente'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_recibido'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_vuelto'], 2) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
