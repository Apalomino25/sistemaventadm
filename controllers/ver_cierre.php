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

$stmt = $conn->prepare("
    SELECT c.*, u.usuario
    FROM cierres c
    LEFT JOIN usuarios u ON c.usuarioID = u.usuarioID
    WHERE c.fecha = ?
    ORDER BY c.tipopago
");
$stmt->execute([$fecha]);
$cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(!$cierres){
    exit("Cierre no encontrado");
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

<div class="detalle-cierre-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;max-width:1000px;width:100%;max-height:90vh;overflow:auto;padding:20px;border-radius:8px;">
        <button type="button" onclick="this.closest('.detalle-cierre-modal').remove()" style="float:right;padding:6px 10px;cursor:pointer;">Cerrar</button>
        <h2>Cierre del <?= date('d-m-Y', strtotime($fecha)) ?></h2>
        <table class="tabla-ventas" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Tipo Pago</th>
                    <th>Total</th>
                    <th>Pagado</th>
                    <th>Pendiente</th>
                    <th>Boletas Pend. Cobradas</th>
                    <th>Recibido</th>
                    <th>Fisico</th>
                    <th>Diferencia</th>
                    <th>Ganancia</th>
                    <th>Obs.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cierres as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(strtoupper($c['tipopago'])) ?></td>
                    <td>S/ <?= number_format($c['total_ventas'], 2) ?></td>
                    <td>S/ <?= number_format($c['total_pagado'], 2) ?></td>
                    <td>S/ <?= number_format($c['total_pendiente'], 2) ?></td>
                    <td>S/ <?= number_format($c['total_pendientes_cobrados'], 2) ?></td>
                    <td>S/ <?= number_format($c['total_recibido'], 2) ?></td>
                    <td>S/ <?= number_format($c['fisico'], 2) ?></td>
                    <td>S/ <?= number_format($c['diferencia'], 2) ?></td>
                    <td>S/ <?= number_format($c['total_ganancia'], 2) ?></td>
                    <td><?= htmlspecialchars($c['observacion']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th>S/ <?= number_format($totales['total_ventas'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_pagado'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_pendiente'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_pendientes_cobrados'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_recibido'], 2) ?></th>
                    <th>S/ <?= number_format($totales['fisico'], 2) ?></th>
                    <th>S/ <?= number_format($totales['diferencia'], 2) ?></th>
                    <th>S/ <?= number_format($totales['total_ganancia'], 2) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
