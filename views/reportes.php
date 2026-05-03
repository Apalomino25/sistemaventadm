<?php
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../config/schema_helpers.php";

$clienteGeneral = obtenerClienteGeneral($conn);
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$clienteID = intval($_GET['clienteID'] ?? 0);

$paramsFecha = [
    ':desde' => $fechaDesde,
    ':hasta' => $fechaHasta
];

$stmtProductos = $conn->prepare("
    SELECT p.productoID, p.codigo, p.nombre, p.descripcion,
           SUM(d.cantidad) AS cantidad_vendida,
           SUM(d.subtotal) AS total_vendido
    FROM detalleventa d
    INNER JOIN ventas v ON v.ventaID = d.ventaID
    INNER JOIN productos p ON p.productoID = d.productoID
    WHERE v.estado = 1
      AND DATE(v.fecha) BETWEEN :desde AND :hasta
    GROUP BY p.productoID, p.codigo, p.nombre, p.descripcion
    ORDER BY cantidad_vendida DESC, total_vendido DESC
    LIMIT 10
");
$stmtProductos->execute($paramsFecha);
$productosMasVendidos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

$stmtVendedores = $conn->prepare("
    SELECT u.usuarioID, u.nombre, u.usuario,
           COUNT(v.ventaID) AS ventas,
           SUM(v.total) AS total_vendido
    FROM ventas v
    INNER JOIN usuarios u ON u.usuarioID = v.usuarioID
    WHERE v.estado = 1
      AND DATE(v.fecha) BETWEEN :desde AND :hasta
    GROUP BY u.usuarioID, u.nombre, u.usuario
    ORDER BY total_vendido DESC, ventas DESC
    LIMIT 10
");
$stmtVendedores->execute($paramsFecha);
$vendedores = $stmtVendedores->fetchAll(PDO::FETCH_ASSOC);

$stmtClientes = $conn->prepare("
    SELECT c.clienteID, c.nombre, c.numeroDocumento, c.telefono,
           COUNT(v.ventaID) AS compras,
           SUM(v.total) AS total_comprado,
           MAX(v.fecha) AS ultima_compra
    FROM ventas v
    INNER JOIN clientes c ON c.clienteID = v.clienteID
    WHERE v.estado = 1
      AND DATE(v.fecha) BETWEEN :desde AND :hasta
    GROUP BY c.clienteID, c.nombre, c.numeroDocumento, c.telefono
    ORDER BY total_comprado DESC, compras DESC
    LIMIT 10
");
$stmtClientes->execute($paramsFecha);
$clientesTop = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

$clientes = $conn->query("
    SELECT clienteID, nombre, numeroDocumento
    FROM clientes
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

$ultimasCompras = [];
if($clienteID > 0){
    $stmtUltimas = $conn->prepare("
        SELECT v.ventaID, v.fecha, v.total, v.tipoPago, v.estadoPago,
               GROUP_CONCAT(CONCAT(p.nombre, ' x', d.cantidad) ORDER BY d.detalleID SEPARATOR ', ') AS productos
        FROM ventas v
        INNER JOIN detalleventa d ON d.ventaID = v.ventaID
        INNER JOIN productos p ON p.productoID = d.productoID
        WHERE v.estado = 1
          AND v.clienteID = :clienteID
          AND DATE(v.fecha) BETWEEN :desde AND :hasta
        GROUP BY v.ventaID, v.fecha, v.total, v.tipoPago, v.estadoPago
        ORDER BY v.fecha DESC
        LIMIT 20
    ");
    $stmtUltimas->execute([
        ':clienteID' => $clienteID,
        ':desde' => $fechaDesde,
        ':hasta' => $fechaHasta
    ]);
    $ultimasCompras = $stmtUltimas->fetchAll(PDO::FETCH_ASSOC);
}

function moneyReporte($valor): string {
    return 'S/ ' . number_format((float)$valor, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes</title>
    <link rel="stylesheet" href="../assets/css/pos.css?v=<?= filemtime(__DIR__ . '/../assets/css/pos.css') ?>">
    <link rel="stylesheet" href="../assets/css/reportes.css?v=<?= filemtime(__DIR__ . '/../assets/css/reportes.css') ?>">
</head>
<body>
<div class="reportes-container">
    <h2>Reportes</h2>

    <form class="filtros-historial" data-page="reportes.php">
        <label>
            Desde
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
        </label>
        <label>
            Hasta
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
        </label>
        <label>
            Cliente para ultimas compras
            <select name="clienteID">
                <option value="0">Seleccione cliente</option>
                <?php foreach($clientes as $cliente): ?>
                    <option value="<?= intval($cliente['clienteID']) ?>" <?= $clienteID === intval($cliente['clienteID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cliente['nombre'] . ($cliente['numeroDocumento'] ? ' - ' . $cliente['numeroDocumento'] : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Ver reportes</button>
    </form>

    <div class="reportes-grid">
        <section>
            <h3>Productos mas vendidos</h3>
            <table class="tabla-ventas">
                <thead><tr><th>Producto</th><th>Codigo</th><th>Cantidad</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach($productosMasVendidos as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre']) ?><br><span><?= htmlspecialchars($row['descripcion'] ?? '') ?></span></td>
                        <td><?= htmlspecialchars($row['codigo']) ?></td>
                        <td><?= intval($row['cantidad_vendida']) ?></td>
                        <td><?= moneyReporte($row['total_vendido']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section>
            <h3>Vendedores que mas venden</h3>
            <table class="tabla-ventas">
                <thead><tr><th>Vendedor</th><th>Ventas</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach($vendedores as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre'] ?: $row['usuario']) ?></td>
                        <td><?= intval($row['ventas']) ?></td>
                        <td><?= moneyReporte($row['total_vendido']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section>
            <h3>Clientes que mas compran</h3>
            <table class="tabla-ventas">
                <thead><tr><th>Cliente</th><th>Compras</th><th>Total</th><th>Ultima</th></tr></thead>
                <tbody>
                    <?php foreach($clientesTop as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre']) ?><br><span><?= htmlspecialchars($row['numeroDocumento'] ?? '') ?></span></td>
                        <td><?= intval($row['compras']) ?></td>
                        <td><?= moneyReporte($row['total_comprado']) ?></td>
                        <td><?= htmlspecialchars($row['ultima_compra']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section>
            <h3>Ultimas compras del cliente</h3>
            <?php if($clienteID <= 0): ?>
                <p class="reporte-vacio">Selecciona un cliente para ver sus ultimas compras.</p>
            <?php else: ?>
            <table class="tabla-ventas">
                <thead><tr><th>Venta</th><th>Fecha</th><th>Productos</th><th>Pago</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach($ultimasCompras as $row): ?>
                    <tr>
                        <td>#<?= intval($row['ventaID']) ?></td>
                        <td><?= htmlspecialchars($row['fecha']) ?></td>
                        <td><?= htmlspecialchars($row['productos']) ?></td>
                        <td><?= htmlspecialchars($row['tipoPago'] . ' / ' . $row['estadoPago']) ?></td>
                        <td><?= moneyReporte($row['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>
</div>
</body>
</html>
