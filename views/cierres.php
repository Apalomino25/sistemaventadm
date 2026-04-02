<?php
require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');

try {
    $stmt = $conn->prepare("
        SELECT tipopago, 
               SUM(total) AS total_ventas, 
               SUM(CASE WHEN estadoPago = 'pagado' THEN total ELSE 0 END) AS total_pagado,
               SUM(CASE WHEN estadoPago = 'pendiente' THEN total ELSE 0 END) AS total_pendiente,
               SUM(pago) AS total_recibido,
               SUM(vuelto) AS total_vuelto
        FROM ventas
        WHERE DATE(fecha) = :hoy
          AND estado = 1   -- solo ventas activas
        GROUP BY tipopago
    ");

    $stmt->execute([':hoy' => $hoy]);
    $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Totales generales
$suma_total = 0;
$suma_pagado = 0;
$suma_pendiente = 0;
$suma_recibido = 0;
$suma_vuelto = 0;

foreach($cierres as $c){
    $suma_total += $c['total_ventas'];
    $suma_pagado += $c['total_pagado'];
    $suma_pendiente += $c['total_pendiente'];
    $suma_recibido += $c['total_recibido'];
    $suma_vuelto += $c['total_vuelto'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cierre de Caja</title>
<link rel="stylesheet" href="../assets/css/cierres.css">
</head>

<body>

<h2>Cierre del Día (<?php echo date('d-m-Y'); ?>)</h2>

<table id="tabla-cierres">
<thead>
<tr>
<th>Tipo de Pago</th>
<th>Total Ventas</th>
<th>Total Pagado</th>
<th>Total Pendiente</th>
<th>Total Recibido</th>
<th>Total Vuelto</th>
<th>Físico</th>
<th>Observación</th>
</tr>
</thead>

<tbody>
<?php foreach($cierres as $c): ?>
<tr>
<td><?php echo strtoupper($c['tipopago']); ?></td>
<td>S/ <?php echo number_format($c['total_ventas'],2); ?></td>
<td class="total-pagado">S/ <?php echo number_format($c['total_pagado'],2); ?></td>
<td>S/ <?php echo number_format($c['total_pendiente'],2); ?></td>
<td>S/ <?php echo number_format($c['total_recibido'],2); ?></td>
<td>S/ <?php echo number_format($c['total_vuelto'],2); ?></td>
<td>
    <input type="number"
           class="fisico"
           data-tipopago="<?php echo $c['tipopago']; ?>"
           step="0.01"
           min="0"
           value="<?php echo $c['total_pagado']; ?>">
</td>
<td><span class="obs">Correcto</span></td>
</tr>
<?php endforeach; ?>
</tbody>

<tfoot>
<tr>
<th>Total</th>
<th>S/ <?php echo number_format($suma_total,2); ?></th>
<th>S/ <?php echo number_format($suma_pagado,2); ?></th>
<th>S/ <?php echo number_format($suma_pendiente,2); ?></th>
<th>S/ <?php echo number_format($suma_recibido,2); ?></th>
<th>S/ <?php echo number_format($suma_vuelto,2); ?></th>
<th id="totalFisico">S/ <?php echo number_format($suma_pagado,2); ?></th>
<th></th>
</tr>
</tfoot>
</table>

<button id="guardar-cierre">Guardar Cierre</button>

<script src="../assets/js/cierres.js"></script>

</body>
</html>