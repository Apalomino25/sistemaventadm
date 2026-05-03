<?php
require "../config/conexion.php";
require_once "../config/schema_helpers.php";

asegurarColumnasPagos($conn);

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
    $sqlDetalle = "SELECT 
                        dv.detalleID,
                        p.nombre,
                        p.descripcion,
                        dv.cantidad,
                        dv.precioUnitario,
                        dv.subtotal,
                        dv.estadoPago,
                        dv.fechaPago
                   FROM detalleventa dv
                   INNER JOIN productos p 
                       ON dv.productoID = p.productoID
                   WHERE dv.ventaID = :ventaID";
    $stmtDetalle = $conn->prepare($sqlDetalle);
    $stmtDetalle->bindParam(':ventaID', $ventaID, PDO::PARAM_INT);
    $stmtDetalle->execute();
    $datos = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

    if (!$datos) {
        echo "<p>No hay productos en esta venta</p>";
        exit;
    }

    // 🔹 Generar modal con overlay y eliminar modal anterior (JS inline)
    echo "<script>
        const existente = document.querySelector('.detalle-venta-overlay');
        if(existente) existente.remove();
    </script>";

    echo "<div class='detalle-venta-overlay' style='
        position: fixed;
        top:0;
        left:0;
        width:100%;
        height:100%;
        background: rgba(0,0,0,0.5);
        z-index:9998;
    '></div>";

    echo "<div class='detalle-venta-modal' style='
        font-family: Arial, sans-serif;
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 700px;
        background: #f9f9f9;
        padding: 20px 25px;
        border-radius: 10px;
        box-shadow: 0 4px 25px rgba(0,0,0,0.3);
        z-index: 9999;
        overflow-y: auto;
        max-height: 80vh;
    '>
    <button onclick='this.parentElement.remove(); document.querySelector(\".detalle-venta-overlay\")?.remove();' 
        style='position:absolute; top:10px; right:10px; background:#ff4d4d; color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer; font-weight:bold;'>✖</button>

    <h2 style='text-align:center; color:#333; margin-top:0;'>🧾 Detalle de Venta #{$ventaID}</h2>
    <p><strong>Cliente:</strong> {$venta['cliente']}</p>
    <p><strong>Fecha:</strong> {$venta['fecha']}</p>
    <p><strong>Tipo de Pago:</strong> {$venta['tipoPago']}</p>

    <hr style='border:0; border-top:2px solid #eee; margin:15px 0;'>

    <table style='width:100%; border-collapse: collapse; text-align:center; margin-top:10px;'>
    <tr style='background:#007bff; color:white;'>
        <th style='padding:10px;'>Producto</th>
        <th style='padding:10px;'>Descripción</th>
        <th style='padding:10px;'>Cantidad</th>
        <th style='padding:10px;'>Precio</th>
        <th style='padding:10px;'>Subtotal</th>
        <th style='padding:10px;'>Estado</th>
        <th style='padding:10px;'>Accion</th>
    </tr>";

    $totalCalculado = 0;
    foreach ($datos as $row) {
        $totalCalculado += $row['subtotal'];
        echo "<tr style='border-bottom:1px solid #ddd;'>
                <td style='padding:8px;'>{$row['nombre']}</td>
                <td style='padding:8px;'>{$row['descripcion']}</td>
                <td style='padding:8px;'>{$row['cantidad']}</td>
                <td style='padding:8px;'>S/ " . number_format($row['precioUnitario'], 2) . "</td>
                <td style='padding:8px;'>S/ " . number_format($row['subtotal'], 2) . "</td>
                <td style='padding:8px;'>" . htmlspecialchars($row['estadoPago']) . "</td>
                <td style='padding:8px;'>";
        if($row['estadoPago'] === 'pendiente'){
            echo "<button type='button' class='marcar-detalle-pagado' data-id='{$row['detalleID']}' style='border:0;border-radius:6px;background:#FF1493;color:#fff;padding:6px 8px;cursor:pointer;'>Marcar pagado</button>";
        } else {
            echo htmlspecialchars($row['fechaPago'] ?? '');
        }
        echo "</td>
              </tr>";
    }

    echo "<tr style='font-weight:bold; background:#f2f2f2;'>
            <td colspan='6' style='text-align:right; padding:8px;'>Total:</td>
            <td style='padding:8px;'>S/ " . number_format($totalCalculado, 2) . "</td>
          </tr>";
    echo "<tr style='background:#f9f9f9;'>
            <td colspan='6' style='text-align:right; padding:8px;'>Pago:</td>
            <td style='padding:8px;'>S/ " . number_format($venta['pago'], 2) . "</td>
          </tr>";
    echo "<tr style='background:#f9f9f9;'>
            <td colspan='6' style='text-align:right; padding:8px;'>Vuelto:</td>
            <td style='padding:8px;'>S/ " . number_format($venta['vuelto'], 2) . "</td>
          </tr>";
    echo "</table>";

     echo "</div>"; // cierre del contenedor

} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
