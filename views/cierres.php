<?php
require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');

try {

    // --- Consulta cierres por tipo de pago ---
    $stmt = $conn->prepare("
        SELECT tipopago, 
               COALESCE(SUM(total),0) AS total_ventas, 
               COALESCE(SUM(CASE WHEN estadoPago = 'pagado' THEN total ELSE 0 END),0) AS total_pagado,
               COALESCE(SUM(CASE WHEN estadoPago = 'pendiente' THEN total ELSE 0 END),0) AS total_pendiente,
               COALESCE(SUM(pago),0) AS total_recibido,
               COALESCE(SUM(vuelto),0) AS total_vuelto
        FROM ventas
        WHERE DATE(fecha) = :hoy
        GROUP BY tipopago
    ");

    $stmt->execute([':hoy' => $hoy]);
    $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totales
    $suma_total = $suma_pagado = $suma_pendiente = $suma_recibido = $suma_vuelto = 0;
    foreach($cierres as $c){
        $suma_total += $c['total_ventas'];
        $suma_pagado += $c['total_pagado'];
        $suma_pendiente += $c['total_pendiente'];
        $suma_recibido += $c['total_recibido'];
        $suma_vuelto += $c['total_vuelto'];
    }

    // --- Consulta ganancia ---
    $stmtGanancia = $conn->prepare("
        SELECT p.nombre, d.cantidad, p.precioCompra, d.precioUnitario AS precioVenta,
               (d.precioUnitario - p.precioCompra) * d.cantidad AS ganancia
        FROM detalleventa d
        JOIN productos p ON d.productoID = p.productoID
        JOIN ventas v ON d.ventaID = v.ventaID
        WHERE DATE(v.fecha) = :hoy
          AND v.estado = 1
        ORDER BY p.nombre
    ");

    $stmtGanancia->execute([':hoy' => $hoy]);
    $detalleGanancia = $stmtGanancia->fetchAll(PDO::FETCH_ASSOC);

    $totalGanancia = 0;
    foreach($detalleGanancia as $d){
        $totalGanancia += $d['ganancia'];
    }

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
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

<h2>Cierre del Día (<?= date('d-m-Y') ?>)</h2>

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
    <th>Diferencia</th>
</tr>
</thead>

<tbody>
<?php foreach($cierres as $c): ?>
<tr>
    <td><?= htmlspecialchars(strtoupper($c['tipopago'])) ?></td>
    <td>S/ <?= number_format($c['total_ventas'],2,'.','') ?></td>
    <td class="total-pagado">S/ <?= number_format($c['total_pagado'],2,'.','') ?></td>
    <td>S/ <?= number_format($c['total_pendiente'],2,'.','') ?></td>
    <td>S/ <?= number_format($c['total_recibido'],2,'.','') ?></td>
    <td>S/ <?= number_format($c['total_vuelto'],2,'.','') ?></td>

    <td>
        <input 
            type="number"
            step="0.01"
            class="fisico"
            data-tipopago="<?= htmlspecialchars($c['tipopago']) ?>"
            data-total="<?= $c['total_pagado'] ?>"
            value="<?= $c['total_pagado'] ?>"
        >
    </td>

    <td class="obs">Correcto</td>
    <td class="diferencia">S/ 0.00</td>
</tr>
<?php endforeach; ?>
</tbody>

<tfoot>
<tr>
    <th>Total</th>
    <th>S/ <?= number_format($suma_total,2,'.','') ?></th>
    <th>S/ <?= number_format($suma_pagado,2,'.','') ?></th>
    <th>S/ <?= number_format($suma_pendiente,2,'.','') ?></th>
    <th>S/ <?= number_format($suma_recibido,2,'.','') ?></th>
    <th>S/ <?= number_format($suma_vuelto,2,'.','') ?></th>
    <th id="totalFisico"><?= $suma_pagado ?></th>
    <th></th>
    <th id="totalDiferencia">S/ 0.00</th>
</tr>
</tfoot>
</table>

<div class="cont-guardar-cierre">
    <button id="guardar-cierre">Guardar Cierre</button>
</div>

<br><br>

<h2>Detalle de Ganancia del Día</h2>

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
<?php 
$totalCompraGeneral = 0;
$totalVentaGeneral = 0;
?>

<?php foreach($detalleGanancia as $d): 
    $totalCompra = $d['cantidad'] * $d['precioCompra'];
    $totalVenta = $d['cantidad'] * $d['precioVenta'];
    $totalCompraGeneral += $totalCompra;
    $totalVentaGeneral += $totalVenta;
?>

<tr>
    <td><?= htmlspecialchars($d['nombre']) ?></td>
    <td><?= $d['cantidad'] ?></td>
    <td>S/ <?= number_format($d['precioCompra'],2,'.','') ?></td>
    <td>S/ <?= number_format($d['precioVenta'],2,'.','') ?></td>
    <td>S/ <?= number_format($totalCompra,2,'.','') ?></td>
    <td>S/ <?= number_format($totalVenta,2,'.','') ?></td>
    <td>S/ <?= number_format($d['ganancia'],2,'.','') ?></td>
</tr>

<?php endforeach; ?>

<tr>
    <th colspan="5" style="text-align:right">Total Compras:</th>
    <th colspan="2">S/ <?= number_format($totalCompraGeneral,2,'.','') ?></th>
</tr>

<tr>
    <th colspan="5" style="text-align:right">Total Ventas:</th>
    <th colspan="2">S/ <?= number_format($totalVentaGeneral,2,'.','') ?></th>
</tr>

<tr>
    <th colspan="5" style="text-align:right">Total Ganancia:</th>
    <th colspan="2">S/ <?= number_format($totalGanancia,2,'.','') ?></th>
</tr>

</tbody>
</table>

<!-- JS -->
<script src="../assets/js/cierres.js"></script>

<script>
console.log("INLINE JS FUNCIONA");

const inputsFisico = document.querySelectorAll('.fisico');
const totalFisico = document.getElementById('totalFisico');
const totalDiferencia = document.getElementById('totalDiferencia');

function formatearNumero(num){
    return parseFloat(num).toFixed(2);
}

inputsFisico.forEach(input => {
    input.addEventListener('input', () => {

        let suma = 0;
        let sumaDiferencia = 0;

        inputsFisico.forEach(i => {
            const totalRecibido = parseFloat(i.dataset.total) || 0;
            let fisico = parseFloat(i.value) || 0;
            suma += fisico;

            const obs = i.parentElement.nextElementSibling;
            const difTd = obs.nextElementSibling; // columna diferencia

            const diferencia = fisico - totalRecibido;
            difTd.textContent = 'S/ ' + formatearNumero(diferencia);
            sumaDiferencia += diferencia;

            if(fisico === totalRecibido){
                obs.textContent = 'Correcto';
                obs.style.color = 'green';
            } else if(fisico < totalRecibido){
                obs.textContent = 'Faltante';
                obs.style.color = 'red';
            } else {
                obs.textContent = 'Sobrante';
                obs.style.color = 'orange';
            }
        });

        totalFisico.textContent = formatearNumero(suma);
        totalDiferencia.textContent = 'S/ ' + formatearNumero(sumaDiferencia);
    });
});
</script>

</body>
</html>