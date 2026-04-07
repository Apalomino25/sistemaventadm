<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

function errorJSON($msg){
    echo json_encode(['ok'=>false,'error'=>$msg]);
    exit;
}

require_once __DIR__ . '/../config/conexion.php';
$usuarioID = $_SESSION['usuarioID'] ?? 1;

try {
    // 1️⃣ Verificar si ya existe un cierre activo hoy
    $check = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE DATE(fecha) = CURDATE() AND estado = 1");
    $check->execute();
    if($check->fetchColumn() > 0) errorJSON('Ya se realizó un cierre activo hoy.');

    // 2️⃣ Obtener los datos de la tabla ventas si no vienen por POST
    $data = json_decode(file_get_contents('php://input'), true);

    if(!isset($data['cierres']) || empty($data['cierres'])){
        // Traer los totales desde la tabla ventas
        $stmt = $conn->prepare("
            SELECT tipoPago AS tipopago,
                   COALESCE(SUM(total),0) AS total_ventas,
                   COALESCE(SUM(CASE WHEN estadoPago='pagado' THEN total ELSE 0 END),0) AS total_pagado,
                   COALESCE(SUM(CASE WHEN estadoPago!='pagado' THEN total ELSE 0 END),0) AS total_pendiente,
                   COALESCE(SUM(pago),0) AS total_recibido
            FROM ventas
            WHERE DATE(fecha) = CURDATE() AND estado = 1
            GROUP BY tipoPago
        ");
        $stmt->execute();
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(empty($ventas)) errorJSON('No hay ventas activas hoy para cerrar.');

        // Calcular vuelto dinámicamente
        $cierres = [];
        foreach($ventas as $v){
            $vuelto = max(0, $v['total_recibido'] - $v['total_ventas']); // pago - total
            $cierres[] = [
                'tipopago'       => $v['tipopago'],
                'total_ventas'   => $v['total_ventas'],
                'total_pagado'   => $v['total_pagado'],
                'total_pendiente'=> $v['total_pendiente'],
                'total_recibido' => $v['total_recibido'],
                'total_vuelto'   => $vuelto,
                'fisico'         => $v['total_recibido'] // por defecto igual al recibido
            ];
        }

    } else {
        $cierres = $data['cierres'];
    }

    // 3️⃣ Insertar cierres en la tabla cierres
    $conn->beginTransaction();
    $insert = $conn->prepare("
        INSERT INTO cierres
        (fecha, tipopago, total_ventas, total_pagado, total_pendiente, total_recibido, total_vuelto, fisico, diferencia, observacion, usuarioID, estado)
        VALUES
        (NOW(), :tipopago, :total_ventas, :total_pagado, :total_pendiente, :total_recibido, :total_vuelto, :fisico, :diferencia, :observacion, :usuarioID, :estado)
    ");

    foreach($cierres as $c){
        $tipopago        = $c['tipopago'] ?? null;
        $total_ventas    = floatval($c['total_ventas'] ?? 0);
        $total_pagado    = floatval($c['total_pagado'] ?? 0);
        $total_pendiente = floatval($c['total_pendiente'] ?? 0);
        $total_recibido  = floatval($c['total_recibido'] ?? 0);
        $total_vuelto    = floatval($c['total_vuelto'] ?? 0);

        if(empty($tipopago)) throw new Exception('Tipo de pago no definido.');

        // 🔹 Tomar valor de Físico si viene del POST, si no igual a total_recibido
        $fisico = floatval($c['fisico'] ?? $total_recibido);

        // 🔹 Diferencia y observación
        $diferencia  = $fisico - $total_recibido;
        $observacion = ($diferencia != 0) ? "Diferencia de S/ ".number_format($diferencia,2) : 'Correcto';

        // 🔹 Insertar en la base
        $insert->execute([
            ':tipopago'        => $tipopago,
            ':total_ventas'    => $total_ventas,
            ':total_pagado'    => $total_pagado,
            ':total_pendiente' => $total_pendiente,
            ':total_recibido'  => $total_recibido,
            ':total_vuelto'    => $total_vuelto,
            ':fisico'          => $fisico,
            ':diferencia'      => $diferencia,
            ':observacion'     => $observacion,
            ':usuarioID'       => $usuarioID,
            ':estado'          => 1
        ]);
    }

    $conn->commit();
    echo json_encode(['ok'=>true,'message'=>'Cierre guardado correctamente.']);

} catch(Exception $e){
    if($conn->inTransaction()) $conn->rollBack();
    errorJSON('Error: '.$e->getMessage());
}
?>