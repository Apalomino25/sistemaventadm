<?php
require_once "../config/conexion.php";

$sql = "SELECT v.ventaID, c.nombre AS cliente, v.total, v.pago, v.vuelto, v.fecha, v.estado
        FROM ventas v
        INNER JOIN clientes c ON v.clienteID = c.clienteID
        ORDER BY v.fecha DESC
        LIMIT 50";

$resultado = $conn->query($sql);

$data = $resultado->fetchAll(PDO::FETCH_ASSOC);

if (count($data) > 0) {

    echo "<table class='tabla-ventas'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Pagado</th>
                    <th>Vuelto</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($data as $row) {

        echo "<tr class='".($row['estado']==0 ? "inactivo" : "")."'>
                <td>{$row['ventaID']}</td>
                <td>{$row['cliente']}</td>
                <td>{$row['total']}</td>
                <td>{$row['pago']}</td>
                <td>{$row['vuelto']}</td>
                <td>{$row['fecha']}</td>
                <td>".($row['estado']==1 ? "Activo" : "Anulado")."</td>
                <td>
                    <i class='fa-solid fa-print imprimir' data-id='{$row['ventaID']}'></i>
                    <i class='fa-solid fa-eye ver' data-id='{$row['ventaID']}'></i>";

        if($row['estado']==1){
            echo "<i class='fa-solid fa-trash eliminar' data-id='{$row['ventaID']}'></i>";
        }

        echo "</td></tr>";
    }

    echo "</tbody></table>";

} else {
    echo "No hay ventas";
}