<?php
require "../config/conexion.php";

if (!isset($_GET['id'])) {
    echo "<p>Venta no válida</p>";
    exit;
}

$ventaID = $_GET['id'];

try {

    // 🔹 1. Obtener datos de la venta
    $sqlVenta = "SELECT 
                    v.fecha, 
                    v.tipoPago, 
                    v.total,
                    v.pago,
                    v.vuelto,
                    c.nombre AS cliente
                 FROM ventas v
                 INNER JOIN clientes c 
                    ON v.clienteID = c.clienteID
                 WHERE v.ventaID = :ventaID";

    $stmtVenta = $conn->prepare($sqlVenta);
    $stmtVenta->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmtVenta->execute();

    $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        echo "<p>No se encontró la venta</p>";
        exit;
    }

    // 🔹 2. Obtener detalle de productos
    $sql = "SELECT 
                p.nombre,
                p.descripcion,
                dv.cantidad,
                dv.precioUnitario,
                dv.subtotal
            FROM detalleventa dv
            INNER JOIN productos p 
                ON p.productoID = dv.productoID
            WHERE dv.ventaID = :ventaID";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmt->execute();

    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$datos) {
        echo "<p>No hay productos en esta venta</p>";
        exit;
    }

    // 🔹 3. Mostrar cabecera
    echo "<div style='font-family:Arial;'>";
    echo "<h2>🧾 Detalle de Venta #{$ventaID}</h2>";

    echo "<p><strong>Cliente:</strong> {$venta['cliente']}</p>";
    echo "<p><strong>Fecha:</strong> {$venta['fecha']}</p>";
    echo "<p><strong>Tipo de Pago:</strong> {$venta['tipoPago']}</p>";

    echo "<hr>";

    // 🔹 4. Tabla de productos
    echo "<table style='width:100%; border-collapse:collapse; text-align:center;'>";

    echo "<tr style='background:#f2f2f2'>
            <th style='padding:8px; border:1px solid #ccc;'>Producto</th>
            <th style='padding:8px; border:1px solid #ccc;'>Descripción</th>
            <th style='padding:8px; border:1px solid #ccc;'>Cantidad</th>
            <th style='padding:8px; border:1px solid #ccc;'>Precio</th>
            <th style='padding:8px; border:1px solid #ccc;'>Subtotal</th>
          </tr>";

    $totalCalculado = 0;

    foreach ($datos as $row) {
        $totalCalculado += $row['subtotal'];

        echo "<tr>
                <td style='padding:6px; border:1px solid #ccc;'>{$row['nombre']}</td>
                <td style='padding:6px; border:1px solid #ccc;'>{$row['descripcion']}</td>
                <td style='padding:6px; border:1px solid #ccc;'>{$row['cantidad']}</td>
                <td style='padding:6px; border:1px solid #ccc;'>S/ " . number_format($row['precioUnitario'], 2) . "</td>
                <td style='padding:6px; border:1px solid #ccc;'>S/ " . number_format($row['subtotal'], 2) . "</td>
              </tr>";
    }

    // 🔹 5. Totales
    echo "<tr>
            <td colspan='4' style='text-align:right; padding:8px; border:1px solid #ccc;'><strong>Total:</strong></td>
            <td style='padding:8px; border:1px solid #ccc;'><strong>S/ " . number_format($totalCalculado, 2) . "</strong></td>
          </tr>";

    echo "<tr>
            <td colspan='4' style='text-align:right; padding:8px; border:1px solid #ccc;'>Pago:</td>
            <td style='padding:8px; border:1px solid #ccc;'>S/ " . number_format($venta['pago'], 2) . "</td>
          </tr>";

    echo "<tr>
            <td colspan='4' style='text-align:right; padding:8px; border:1px solid #ccc;'>Vuelto:</td>
            <td style='padding:8px; border:1px solid #ccc;'>S/ " . number_format($venta['vuelto'], 2) . "</td>
          </tr>";

    echo "</table>";

    echo "<br>";

    // 🔹 6. Botón imprimir
    echo "<button onclick='window.print()' 
            style='padding:10px 15px; background:#28a745; color:#fff; border:none; border-radius:5px; cursor:pointer;'>
            🖨️ Imprimir
          </button>";

    echo "</div>";

} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>