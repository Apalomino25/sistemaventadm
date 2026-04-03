<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

$usuarioID = $_SESSION['usuarioID'] ?? 1; // ID del usuario según sesión

try {

        // 1️⃣ Verificar si ya hay un cierre activo para hoy
        $check = $conn->prepare("SELECT COUNT(*) FROM cierres WHERE DATE(fecha) = CURDATE() AND estado = 1");
        $check->execute();
        if($check->fetchColumn() > 0){
        echo json_encode(['ok'=>false, 'error'=>'Ya se realizó un cierre activo hoy.']);
        exit;
}

    // 2️⃣ Si no se envía data de cierres, obtenemos automáticamente los totales
    $data = json_decode(file_get_contents('php://input'), true);
    if(!isset($data['cierres']) || empty($data['cierres'])){

            $stmt = $conn->prepare("
            SELECT tipopago,
                SUM(IFNULL(total,0)) AS total_ventas,
                SUM(IFNULL(recibido,0)) AS total_recibido
            FROM ventas
            WHERE DATE(fecha) = CURDATE() AND estado = 1
            GROUP BY tipopago
            ");
$stmt->execute();
$cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);


        $stmt->execute();
        $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(empty($cierres)){
            echo json_encode(['ok'=>false, 'error'=>'No hay ventas activas hoy para cerrar.']);
            exit;
        }
    } else {
        $cierres = $data['cierres'];
    }

    // 3️⃣ Insertar cierres usando NOW() para fecha y creado_en
    $conn->beginTransaction();
    $insert = $conn->prepare("
        INSERT INTO cierres 
        (fecha, tipopago, total_ventas, total_recibido, fisico, diferencia, observacion, usuarioID)
        VALUES (NOW(), :tipopago, :total_ventas, :total_recibido, :fisico, :diferencia, :observacion, :usuarioID)
    ");

    foreach($cierres as $c){
        $total_ventas   = floatval($c['total_ventas'] ?? 0);
        $total_recibido = floatval($c['total_recibido'] ?? 0);
        $fisico         = floatval($c['fisico'] ?? 0);
        $diferencia     = $fisico - $total_recibido;
        $observacion    = ($diferencia != 0) ? "Diferencia de S/ ".number_format($diferencia,2) : '';

        $insert->execute([
            ':tipopago' => $c['tipopago'],
            ':total_ventas' => $total_ventas,
            ':total_recibido' => $total_recibido,
            ':fisico' => $fisico,
            ':diferencia' => $diferencia,
            ':observacion' => $observacion,
            ':usuarioID' => $usuarioID
        ]);
    }

    $conn->commit();
    echo json_encode(['ok'=>true, 'message'=>'Cierre guardado correctamente.']);

} catch(PDOException $e){
    $conn->rollBack();
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
?>