<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/schema_helpers.php';

function responder($payload) {
    if(ob_get_length() !== false){
        ob_clean();
    }

    echo json_encode($payload);
    exit;
}

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');
$usuarioID = $_SESSION['usuarioID'] ?? 1;
$data = json_decode(file_get_contents('php://input'), true);
$fisicos = [];

if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
    responder([
        'ok' => false,
        'error' => 'Metodo no permitido.'
    ]);
}

if(!is_array($data) || empty($data['cierres']) || !is_array($data['cierres'])){
    responder([
        'ok' => false,
        'error' => 'No llegaron datos de cierre.'
    ]);
}

foreach($data['cierres'] as $cierre){
    $tipo = strtolower(trim($cierre['tipopago'] ?? ''));
    if($tipo !== ''){
        $fisicos[$tipo] = floatval($cierre['fisico'] ?? 0);
    }
}

try {
    asegurarColumnasPagos($conn);

    $check = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE fecha = ? AND estado = 1");
    $check->execute([$hoy]);

    if($check->fetchColumn() > 0){
        responder([
            'ok' => false,
            'error' => 'Ya existe un cierre activo para hoy.'
        ]);
    }

    $stmt = $conn->prepare("
        SELECT
            vt.tipopago,
            vt.total_ventas,
            vt.total_pagado,
            vt.total_pendiente,
            vt.total_pendientes_cobrados,
            vt.total_recibido,
            vt.total_vuelto,
            COALESCE(gt.total_compra, 0) AS total_compra,
            COALESCE(gt.total_ganancia, 0) AS total_ganancia
        FROM (
            SELECT
                vp.tipoPago AS tipopago,
                COALESCE(SUM(vp.monto), 0) AS total_ventas,
                COALESCE(SUM(vp.monto), 0) AS total_pagado,
                0 AS total_pendiente,
                COALESCE(SUM(CASE WHEN DATE(v.fecha) < ? THEN vp.monto ELSE 0 END), 0) AS total_pendientes_cobrados,
                COALESCE(SUM(vp.monto), 0) AS total_recibido,
                0 AS total_vuelto
            FROM venta_pagos vp
            INNER JOIN ventas v ON v.ventaID = vp.ventaID
            WHERE DATE(vp.fechaPago) = ?
              AND vp.estado = 1
              AND v.estado = 1
            GROUP BY vp.tipoPago

            UNION ALL

            SELECT
                'pendiente' AS tipopago,
                0 AS total_ventas,
                0 AS total_pagado,
                COALESCE(SUM(d.saldoPendiente), 0) AS total_pendiente,
                0 AS total_pendientes_cobrados,
                0 AS total_recibido,
                0 AS total_vuelto
            FROM detalleventa d
            INNER JOIN ventas v ON v.ventaID = d.ventaID
            WHERE d.estadoPago IN ('pendiente', 'parcial')
              AND DATE(v.fecha) = ?
              AND v.estado = 1
            HAVING total_pendiente > 0
        ) vt
        LEFT JOIN (
            SELECT
                v.tipoPago AS tipopago,
                COALESCE(SUM(CASE WHEN d.subtotal > 0 THEN (d.montoPagado / d.subtotal) * (d.cantidad * p.precioCompra) ELSE 0 END), 0) AS total_compra,
                COALESCE(SUM(CASE WHEN d.subtotal > 0 THEN (d.montoPagado / d.subtotal) * ((d.precioUnitario - p.precioCompra) * d.cantidad) ELSE 0 END), 0) AS total_ganancia
            FROM ventas v
            INNER JOIN detalleventa d ON d.ventaID = v.ventaID
            INNER JOIN productos p ON p.productoID = d.productoID
            WHERE DATE(d.fechaPago) = ?
              AND d.estadoPago = 'pagado'
              AND v.estado = 1
            GROUP BY v.tipoPago
        ) gt ON gt.tipopago = vt.tipopago
        ORDER BY vt.tipopago
    ");
    $stmt->execute([$hoy, $hoy, $hoy, $hoy]);
    $resumenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($resumenes)){
        responder([
            'ok' => false,
            'error' => 'No hay ventas activas hoy para cerrar.'
        ]);
    }

    $conn->beginTransaction();

    $insert = $conn->prepare("
        INSERT INTO cierres
        (fecha, tipopago, total_ventas, total_pagado, total_pendiente, total_pendientes_cobrados, total_recibido,
         total_vuelto, total_compra, total_ganancia, fisico, observacion, usuarioID, estado)
        VALUES
        (:fecha, :tipopago, :total_ventas, :total_pagado, :total_pendiente, :total_pendientes_cobrados, :total_recibido,
         :total_vuelto, :total_compra, :total_ganancia, :fisico, :observacion, :usuarioID, 1)
    ");

    foreach($resumenes as $resumen){
        $tipopago = strtolower(trim($resumen['tipopago']));
        $totalRecibido = floatval($resumen['total_recibido']);
        $fisico = array_key_exists($tipopago, $fisicos) ? $fisicos[$tipopago] : $totalRecibido;
        $diferencia = $fisico - $totalRecibido;

        if(abs($diferencia) < 0.01){
            $observacion = 'Correcto';
        } elseif($diferencia < 0){
            $observacion = 'Faltante de S/ ' . number_format(abs($diferencia), 2, '.', '');
        } else {
            $observacion = 'Sobrante de S/ ' . number_format($diferencia, 2, '.', '');
        }

        $insert->execute([
            ':fecha' => $hoy,
            ':tipopago' => $tipopago,
            ':total_ventas' => floatval($resumen['total_ventas']),
            ':total_pagado' => floatval($resumen['total_pagado']),
            ':total_pendiente' => floatval($resumen['total_pendiente']),
            ':total_pendientes_cobrados' => floatval($resumen['total_pendientes_cobrados']),
            ':total_recibido' => $totalRecibido,
            ':total_vuelto' => floatval($resumen['total_vuelto']),
            ':total_compra' => floatval($resumen['total_compra']),
            ':total_ganancia' => floatval($resumen['total_ganancia']),
            ':fisico' => $fisico,
            ':observacion' => $observacion,
            ':usuarioID' => $usuarioID
        ]);
    }

    $conn->commit();

    responder([
        'ok' => true,
        'message' => 'Cierre guardado correctamente. Desde ahora no se permiten ventas para hoy.'
    ]);

} catch(Throwable $e){
    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }

    responder([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>
