<?php
require "../config/conexion.php";

if (!isset($_GET['id'])) {
    echo "<p>Venta no válida</p>";
    exit;
}

$ventaID = $_GET['id'];

try {
    // 🔹 Obtener datos de la venta
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

    // 🔹 Obtener detalle de productos
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

    // 🔹 Contenedor principal de la venta
    echo "<div class='detalle-venta'>";

    echo "<h2>🧾 Detalle de Venta #{$ventaID}</h2>";
    echo "<p><strong>Cliente:</strong> {$venta['cliente']}</p>";
    echo "<p><strong>Fecha:</strong> {$venta['fecha']}</p>";
    echo "<p><strong>Tipo de Pago:</strong> {$venta['tipoPago']}</p>";

    echo "<hr>";

    // 🔹 Tabla de productos
    echo "<table class='tabla-detalle'>";
    echo "<tr>
            <th>Producto</th>
            <th>Descripción</th>
            <th>Cantidad</th>
            <th>Precio</th>
            <th>Subtotal</th>
          </tr>";

    $totalCalculado = 0;
    foreach ($datos as $row) {
        $totalCalculado += $row['subtotal'];
        echo "<tr>
                <td>{$row['nombre']}</td>
                <td>{$row['descripcion']}</td>
                <td>{$row['cantidad']}</td>
                <td>S/ " . number_format($row['precioUnitario'], 2) . "</td>
                <td>S/ " . number_format($row['subtotal'], 2) . "</td>
              </tr>";
    }

    // 🔹 Totales
    echo "<tr>
            <td colspan='4' class='text-right'><strong>Total:</strong></td>
            <td><strong>S/ " . number_format($totalCalculado, 2) . "</strong></td>
          </tr>";
    echo "<tr>
            <td colspan='4' class='text-right'>Pago:</td>
            <td>S/ " . number_format($venta['pago'], 2) . "</td>
          </tr>";
    echo "<tr>
            <td colspan='4' class='text-right'>Vuelto:</td>
            <td>S/ " . number_format($venta['vuelto'], 2) . "</td>
          </tr>";
    echo "</table>";

    
    echo "</div>"; // cierre del contenedor detalle-venta

} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>