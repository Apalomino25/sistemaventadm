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
    LEFT JOIN usuarios u ON c.usuario_cierre = u.usuarioID
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

function dineroCierre($valor): string {
    return 'S/ ' . number_format((float)$valor, 2, '.', '');
}
?>

<div class="detalle-cierre-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;max-width:1120px;width:100%;max-height:90vh;overflow:auto;padding:20px;border-radius:8px;">
        <button type="button" onclick="this.closest('.detalle-cierre-modal').remove()" style="float:right;padding:6px 10px;cursor:pointer;">Cerrar</button>
        <h2>Cierre del <?= date('d-m-Y', strtotime($fecha)) ?></h2>
        <p><strong>Usuario:</strong> <?= htmlspecialchars($cierres[0]['usuario'] ?? '') ?></p>
        <table class="tabla-ventas" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Tipo Pago</th>
                    <th>Total Ventas</th>
                    <th>Pagado</th>
                    <th>Pendiente</th>
                    <th>Boletas Pend.</th>
                    <th>Recibido</th>
                    <th>Vuelto</th>
                    <th>Compra</th>
                    <th>Ganancia</th>
                    <th>Fisico</th>
                    <th>Diferencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cierres as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(strtoupper($c['tipopago'] ?? 'sin_tipo')) ?></td>
                    <td><?= dineroCierre($c['total_ventas'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['total_pagado'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['total_pendiente'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['total_pendientes_cobrados'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['total_recibido'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['total_vuelto'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['total_compra'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['total_ganancia'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['fisico'] ?? 0) ?></td>
                    <td><?= dineroCierre($c['diferencia'] ?? 0) ?></td>
                    <td><?= intval($c['estado'] ?? 1) === 1 ? 'Cerrado' : 'Anulado' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th><?= dineroCierre($totales['total_ventas']) ?></th>
                    <th><?= dineroCierre($totales['total_pagado']) ?></th>
                    <th><?= dineroCierre($totales['total_pendiente']) ?></th>
                    <th><?= dineroCierre($totales['total_pendientes_cobrados']) ?></th>
                    <th><?= dineroCierre($totales['total_recibido']) ?></th>
                    <th><?= dineroCierre($totales['total_vuelto']) ?></th>
                    <th><?= dineroCierre($totales['total_compra']) ?></th>
                    <th><?= dineroCierre($totales['total_ganancia']) ?></th>
                    <th><?= dineroCierre($totales['fisico']) ?></th>
                    <th><?= dineroCierre($totales['diferencia']) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
