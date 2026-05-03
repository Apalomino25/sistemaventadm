<?php
require_once "../config/conexion.php";
require_once "../config/schema_helpers.php";

asegurarColumnasPagos($conn);

$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';
$estadoPagoFiltro = $_GET['estadoPago'] ?? '';
$tipoPagoFiltro = $_GET['tipoPago'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';

$where = [];
$params = [];

if($fechaDesde !== '' && $fechaHasta !== ''){
    $where[] = "DATE(v.fecha) BETWEEN :fechaDesde AND :fechaHasta";
    $params[':fechaDesde'] = $fechaDesde;
    $params[':fechaHasta'] = $fechaHasta;
}

if(in_array($estadoPagoFiltro, ['pagado', 'pendiente', 'parcial'], true)){
    $where[] = "v.estadoPago = :estadoPago";
    $params[':estadoPago'] = $estadoPagoFiltro;
}

if(in_array($tipoPagoFiltro, ['efectivo', 'yape', 'plin', 'transferencia'], true)){
    $where[] = "v.tipoPago = :tipoPago";
    $params[':tipoPago'] = $tipoPagoFiltro;
}

if($estadoFiltro !== '' && in_array((int)$estadoFiltro, [0, 1], true)){
    $where[] = "v.estado = :estado";
    $params[':estado'] = (int)$estadoFiltro;
}

$sql = "SELECT v.ventaID, c.nombre AS cliente, v.total, v.pago, v.vuelto, v.fecha,
               v.fechaPago, v.tipoPago, v.estado, v.estadoPago
        FROM ventas v
        INNER JOIN clientes c ON v.clienteID = c.clienteID";

if(!empty($where)){
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY v.ventaID DESC LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(count($data) === 0){
    echo "No hay ventas";
    exit;
}

echo "<table class='tabla-ventas'>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Fecha Pago</th>
                <th>Cliente</th>
                <th>Total</th>
                <th>Pagado</th>
                <th>Vuelto</th>
                <th>Tipo Pago</th>
                <th>Estado Pago</th>
                <th>Pago saldo</th>
                <th>Estado</th>
                <th>Accion</th>
            </tr>
        </thead>
        <tbody>";

foreach($data as $row){
    echo "<tr class='".((int)$row['estado'] === 0 ? "inactivo" : "")."'>
            <td>".intval($row['ventaID'])."</td>
            <td>".htmlspecialchars($row['fecha'])."</td>
            <td>".htmlspecialchars($row['fechaPago'] ?: '-')."</td>
            <td>".htmlspecialchars($row['cliente'])."</td>
            <td>".number_format((float)$row['total'], 2)."</td>
            <td>".number_format((float)$row['pago'], 2)."</td>
            <td>".number_format((float)$row['vuelto'], 2)."</td>
            <td>".htmlspecialchars($row['tipoPago'])."</td>
            <td>";

    if((int)$row['estado'] === 1 && in_array($row['estadoPago'], ['pendiente', 'parcial'], true)){
        $estadoActual = htmlspecialchars($row['estadoPago']);
        $estadoTexto = ucfirst($estadoActual);
        echo "<select class='editar-estado-pago' data-id='".intval($row['ventaID'])."' data-estado-actual='{$estadoActual}'>
                <option value='{$estadoActual}' selected>{$estadoTexto}</option>
                <option value='pagado'>Pagado</option>
              </select>";
    } else {
        echo htmlspecialchars($row['estadoPago']);
    }

    echo "</td>
            <td>";

    if((int)$row['estado'] === 1 && in_array($row['estadoPago'], ['pendiente', 'parcial'], true)){
        echo "<select class='tipo-pago-saldo' data-id='".intval($row['ventaID'])."'>
                <option value='efectivo'>Efectivo</option>
                <option value='yape'>Yape</option>
                <option value='plin'>Plin</option>
                <option value='transferencia'>Transferencia</option>
              </select>";
    } else {
        echo "-";
    }

    echo "</td>
            <td>".((int)$row['estado'] === 1 ? "Activo" : "Anulado")."</td>
            <td>
                <i class='fa-solid fa-print imprimir' data-id='".intval($row['ventaID'])."'></i>
                <i class='fa-solid fa-eye ver' data-id='".intval($row['ventaID'])."'></i>";

    if((int)$row['estado'] === 1){
        echo "<i class='fa-solid fa-trash eliminar' data-id='".intval($row['ventaID'])."'></i>";
    }

    echo "</td></tr>";
}

echo "</tbody></table>";
?>
