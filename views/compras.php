<?php
session_start();

require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../config/schema_helpers.php";
require_once __DIR__ . "/../config/auth_helpers.php";

requerirAdministradorHtml();
asegurarColumnasInventario($conn);
asegurarTablasKardex($conn);
inicializarKardexDesdeStock($conn, intval($_SESSION['usuarioID'] ?? 0) ?: null);

$comprasRecientes = $conn->query("
    SELECT c.compraID, c.fecha, c.proveedor, c.comprobante, c.total,
           p.codigo, p.nombre AS producto, dc.cantidad, dc.precioCompra,
           dc.precioVenta, dc.fechaVencimiento
    FROM compras c
    INNER JOIN detallecompra dc ON dc.compraID = c.compraID
    INNER JOIN productos p ON p.productoID = dc.productoID
    WHERE c.estado = 1
    ORDER BY c.compraID DESC
    LIMIT 40
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compras</title>
    <link rel="stylesheet" href="../assets/css/pos.css?v=<?= filemtime(__DIR__ . '/../assets/css/pos.css') ?>">
    <link rel="stylesheet" href="../assets/css/compras.css?v=<?= filemtime(__DIR__ . '/../assets/css/compras.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="compras-container">
    <div class="compras-header">
        <h2>Compras</h2>
    </div>

    <section class="compras-busqueda">
        <label for="busquedaCompraProducto">Producto</label>
        <div class="compras-busqueda-linea">
            <input type="text" id="busquedaCompraProducto" autocomplete="off" placeholder="Codigo o nombre">
            <button type="button" id="btnBuscarCompraProducto">
                <i class="fa-solid fa-magnifying-glass"></i>
                Buscar
            </button>
        </div>
        <div id="resultadosCompraProducto" class="resultados-compra oculto"></div>
    </section>

    <div id="mensajeCompra" class="mensaje-compra nuevo">Selecciona un producto para registrar la compra.</div>

    <form id="formCompra" class="compra-form oculto">
        <input type="hidden" name="productoID" id="compraProductoID">

        <div class="producto-compra" id="productoCompraSeleccionado">
            <strong id="productoCompraNombre"></strong>
            <span id="productoCompraDetalle"></span>
            <span id="productoCompraStock"></span>
        </div>

        <div class="campo">
            <label>Cantidad</label>
            <input type="number" name="cantidad" min="1" step="1" required>
        </div>

        <div class="campo">
            <label>Precio compra</label>
            <input type="number" name="precioCompra" min="0.01" step="0.01" required>
        </div>

        <div class="campo">
            <label>Precio venta</label>
            <input type="number" name="precioVenta" min="0.01" step="0.01" required>
        </div>

        <div class="campo">
            <label>Fecha vencimiento</label>
            <input type="date" name="fechaVencimiento">
        </div>

        <div class="campo">
            <label>Proveedor</label>
            <input type="text" name="proveedor" maxlength="150">
        </div>

        <div class="campo">
            <label>Comprobante</label>
            <input type="text" name="comprobante" maxlength="80">
        </div>

        <div class="campo campo-observacion">
            <label>Observacion</label>
            <input type="text" name="observacion" maxlength="255">
        </div>

        <div class="compra-total">
            <span>Total compra</span>
            <strong id="compraSubtotal">S/ 0.00</strong>
        </div>

        <div class="acciones-form">
            <button type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Registrar compra
            </button>
            <button type="reset" class="secundario">Limpiar</button>
        </div>
    </form>

    <h3>Ultimas compras</h3>
    <table class="tabla-ventas">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Compra</th>
                <th>Venta</th>
                <th>Total</th>
                <th>Vencimiento</th>
                <th>Proveedor</th>
                <th>Comprobante</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($comprasRecientes)): ?>
            <tr>
                <td colspan="10">Sin compras registradas.</td>
            </tr>
            <?php endif; ?>
            <?php foreach($comprasRecientes as $compra): ?>
            <tr>
                <td>#<?= intval($compra['compraID']) ?></td>
                <td><?= date('d-m-Y H:i', strtotime($compra['fecha'])) ?></td>
                <td>
                    <strong><?= htmlspecialchars($compra['producto']) ?></strong><br>
                    <span><?= htmlspecialchars($compra['codigo']) ?></span>
                </td>
                <td><?= intval($compra['cantidad']) ?></td>
                <td>S/ <?= number_format((float)$compra['precioCompra'], 2) ?></td>
                <td>S/ <?= number_format((float)$compra['precioVenta'], 2) ?></td>
                <td>S/ <?= number_format((float)$compra['total'], 2) ?></td>
                <td><?= $compra['fechaVencimiento'] ? date('d-m-Y', strtotime($compra['fechaVencimiento'])) : '-' ?></td>
                <td><?= htmlspecialchars($compra['proveedor'] ?? '-') ?></td>
                <td><?= htmlspecialchars($compra['comprobante'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="../assets/js/compras.js?v=<?= filemtime(__DIR__ . '/../assets/js/compras.js') ?>"></script>
</body>
</html>
