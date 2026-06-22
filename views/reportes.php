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

$stmtResumen = $conn->prepare("
    SELECT COUNT(*) AS total_ventas,
           COALESCE(SUM(total), 0) AS total_vendido,
           COALESCE(AVG(total), 0) AS ticket_promedio
    FROM ventas
    WHERE estado = 1
      AND DATE(fecha) BETWEEN :desde AND :hasta
");
$stmtResumen->execute($paramsFecha);
$resumenPeriodo = $stmtResumen->fetch(PDO::FETCH_ASSOC) ?: [
    'total_ventas' => 0,
    'total_vendido' => 0,
    'ticket_promedio' => 0
];

$stmtResumenProductos = $conn->prepare("
    SELECT COALESCE(SUM(d.cantidad), 0) AS productos_vendidos
    FROM detalleventa d
    INNER JOIN ventas v ON v.ventaID = d.ventaID
    WHERE v.estado = 1
      AND DATE(v.fecha) BETWEEN :desde AND :hasta
");
$stmtResumenProductos->execute($paramsFecha);
$productosVendidosPeriodo = (int)$stmtResumenProductos->fetchColumn();

$stmtVentasDia = $conn->prepare("
    SELECT DATE(fecha) AS fecha,
           COUNT(ventaID) AS ventas,
           COALESCE(SUM(total), 0) AS total_vendido
    FROM ventas
    WHERE estado = 1
      AND DATE(fecha) BETWEEN :desde AND :hasta
    GROUP BY DATE(fecha)
    ORDER BY fecha ASC
");
$stmtVentasDia->execute($paramsFecha);
$ventasPorDia = $stmtVentasDia->fetchAll(PDO::FETCH_ASSOC);

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

function numeroReporte($valor): string {
    return number_format((float)$valor, 0);
}

function porcentajeReporte($valor, $maximo): float {
    $valor = (float)$valor;
    $maximo = (float)$maximo;
    if($maximo <= 0){
        return 0;
    }

    return max(4, min(100, ($valor / $maximo) * 100));
}

function fechaCortaReporte($fecha): string {
    if(!$fecha){
        return '-';
    }

    $timestamp = strtotime((string)$fecha);
    if(!$timestamp){
        return (string)$fecha;
    }

    return date('d/m', $timestamp);
}

$maxProductosCantidad = 0;
foreach($productosMasVendidos as $row){
    $maxProductosCantidad = max($maxProductosCantidad, (float)$row['cantidad_vendida']);
}

$maxVendedoresTotal = 0;
foreach($vendedores as $row){
    $maxVendedoresTotal = max($maxVendedoresTotal, (float)$row['total_vendido']);
}

$maxClientesTotal = 0;
foreach($clientesTop as $row){
    $maxClientesTotal = max($maxClientesTotal, (float)$row['total_comprado']);
}

$maxVentasDia = 0;
foreach($ventasPorDia as $row){
    $maxVentasDia = max($maxVentasDia, (float)$row['total_vendido']);
}

$maxUltimasCompras = 0;
foreach($ultimasCompras as $row){
    $maxUltimasCompras = max($maxUltimasCompras, (float)$row['total']);
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
    <div class="reportes-encabezado">
        <div>
            <h2>Reportes</h2>
            <p><?= htmlspecialchars(date('d/m/Y', strtotime($fechaDesde))) ?> - <?= htmlspecialchars(date('d/m/Y', strtotime($fechaHasta))) ?></p>
        </div>
    </div>

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

    <section class="dashboard-general">
        <div class="dashboard-titulo">
            <div>
                <h3>Dashboard general</h3>
                <span>Resumen del periodo filtrado</span>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-item">
                <span>Ventas</span>
                <strong><?= numeroReporte($resumenPeriodo['total_ventas']) ?></strong>
                <small>operaciones registradas</small>
            </div>
            <div class="kpi-item">
                <span>Total vendido</span>
                <strong><?= moneyReporte($resumenPeriodo['total_vendido']) ?></strong>
                <small>ingresos del periodo</small>
            </div>
            <div class="kpi-item">
                <span>Ticket promedio</span>
                <strong><?= moneyReporte($resumenPeriodo['ticket_promedio']) ?></strong>
                <small>promedio por venta</small>
            </div>
            <div class="kpi-item">
                <span>Productos</span>
                <strong><?= numeroReporte($productosVendidosPeriodo) ?></strong>
                <small>unidades vendidas</small>
            </div>
        </div>

        <?php if(empty($ventasPorDia)): ?>
            <p class="reporte-vacio">No hay ventas para graficar en este periodo.</p>
        <?php else: ?>
            <div class="grafico-columnas" aria-label="Ventas por dia">
                <?php foreach($ventasPorDia as $row): ?>
                    <div class="columna-dia">
                        <div class="columna-barra">
                            <span style="height: <?= porcentajeReporte($row['total_vendido'], $maxVentasDia) ?>%"></span>
                        </div>
                        <strong><?= moneyReporte($row['total_vendido']) ?></strong>
                        <small><?= fechaCortaReporte($row['fecha']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="reportes-grid">
        <section class="reporte-panel">
            <div class="dashboard-titulo">
                <div>
                    <h3>Productos mas vendidos</h3>
                    <span>Ranking por unidades vendidas</span>
                </div>
            </div>

            <?php if(empty($productosMasVendidos)): ?>
                <p class="reporte-vacio">No hay productos vendidos en este periodo.</p>
            <?php else: ?>
                <div class="grafico-barras">
                    <?php foreach($productosMasVendidos as $row): ?>
                        <div class="barra-reporte">
                            <div class="barra-info">
                                <strong><?= htmlspecialchars($row['nombre']) ?></strong>
                                <span><?= intval($row['cantidad_vendida']) ?> und. | <?= moneyReporte($row['total_vendido']) ?></span>
                            </div>
                            <div class="barra-pista">
                                <span style="width: <?= porcentajeReporte($row['cantidad_vendida'], $maxProductosCantidad) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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

        <section class="reporte-panel">
            <div class="dashboard-titulo">
                <div>
                    <h3>Vendedores que mas venden</h3>
                    <span>Comparativo por total vendido</span>
                </div>
            </div>

            <?php if(empty($vendedores)): ?>
                <p class="reporte-vacio">No hay ventas por vendedor en este periodo.</p>
            <?php else: ?>
                <div class="grafico-barras">
                    <?php foreach($vendedores as $row): ?>
                        <div class="barra-reporte">
                            <div class="barra-info">
                                <strong><?= htmlspecialchars($row['nombre'] ?: $row['usuario']) ?></strong>
                                <span><?= intval($row['ventas']) ?> ventas | <?= moneyReporte($row['total_vendido']) ?></span>
                            </div>
                            <div class="barra-pista vendedor">
                                <span style="width: <?= porcentajeReporte($row['total_vendido'], $maxVendedoresTotal) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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

        <section class="reporte-panel">
            <div class="dashboard-titulo">
                <div>
                    <h3>Clientes que mas compran</h3>
                    <span>Clientes con mayor consumo</span>
                </div>
            </div>

            <?php if(empty($clientesTop)): ?>
                <p class="reporte-vacio">No hay clientes con compras en este periodo.</p>
            <?php else: ?>
                <div class="grafico-barras">
                    <?php foreach($clientesTop as $row): ?>
                        <div class="barra-reporte">
                            <div class="barra-info">
                                <strong><?= htmlspecialchars($row['nombre']) ?></strong>
                                <span><?= intval($row['compras']) ?> compras | <?= moneyReporte($row['total_comprado']) ?></span>
                            </div>
                            <div class="barra-pista cliente">
                                <span style="width: <?= porcentajeReporte($row['total_comprado'], $maxClientesTotal) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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

        <section class="reporte-panel">
            <div class="dashboard-titulo">
                <div>
                    <h3>Ultimas compras del cliente</h3>
                    <span>Detalle grafico del cliente seleccionado</span>
                </div>
            </div>

            <?php if($clienteID <= 0): ?>
                <p class="reporte-vacio">Selecciona un cliente para ver sus ultimas compras.</p>
            <?php else: ?>
                <?php if(empty($ultimasCompras)): ?>
                    <p class="reporte-vacio">El cliente seleccionado no tiene compras en este periodo.</p>
                <?php else: ?>
                    <div class="grafico-barras">
                        <?php foreach($ultimasCompras as $row): ?>
                            <div class="barra-reporte">
                                <div class="barra-info">
                                    <strong>Venta #<?= intval($row['ventaID']) ?></strong>
                                    <span><?= htmlspecialchars($row['tipoPago'] . ' / ' . $row['estadoPago']) ?> | <?= moneyReporte($row['total']) ?></span>
                                </div>
                                <div class="barra-pista compra">
                                    <span style="width: <?= porcentajeReporte($row['total'], $maxUltimasCompras) ?>%"></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

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
