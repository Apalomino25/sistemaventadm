<?php
function columnaExiste(PDO $conn, string $tabla, string $columna): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tabla, $columna]);
    return (int)$stmt->fetchColumn() > 0;
}

function tablaExiste(PDO $conn, string $tabla): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$tabla]);
    return (int)$stmt->fetchColumn() > 0;
}

function asegurarColumna(PDO $conn, string $tabla, string $columna, string $definicion): void {
    if(!columnaExiste($conn, $tabla, $columna)){
        $conn->exec("ALTER TABLE `$tabla` ADD COLUMN `$columna` $definicion");
    }
}

function asegurarColumnasPagos(PDO $conn): void {
    asegurarColumna($conn, 'ventas', 'fechaPago', 'DATETIME NULL AFTER estadoPago');
    asegurarColumna($conn, 'cierres', 'total_pendientes_cobrados', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_pendiente');
    asegurarColumna($conn, 'detalleventa', 'estadoPago', "VARCHAR(20) NOT NULL DEFAULT 'pagado' AFTER subtotal");
    asegurarColumna($conn, 'detalleventa', 'fechaPago', 'DATETIME NULL AFTER estadoPago');

    if(!tablaExiste($conn, 'venta_pagos')){
        $conn->exec("
            CREATE TABLE venta_pagos (
                pagoID INT AUTO_INCREMENT PRIMARY KEY,
                ventaID INT NOT NULL,
                tipoPago VARCHAR(30) NOT NULL,
                monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                fechaPago DATETIME NOT NULL,
                estado TINYINT NOT NULL DEFAULT 1,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_venta_pagos_venta (ventaID),
                INDEX idx_venta_pagos_fecha (fechaPago),
                INDEX idx_venta_pagos_tipo (tipoPago)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    $conn->exec("
        UPDATE ventas
        SET fechaPago = fecha
        WHERE estadoPago = 'pagado'
          AND fechaPago IS NULL
    ");

    $conn->exec("
        UPDATE detalleventa d
        INNER JOIN ventas v ON v.ventaID = d.ventaID
        SET d.estadoPago = v.estadoPago,
            d.fechaPago = v.fechaPago
        WHERE d.fechaPago IS NULL
    ");

    $conn->exec("
        INSERT INTO venta_pagos (ventaID, tipoPago, monto, fechaPago, estado)
        SELECT v.ventaID, v.tipoPago, v.pago, COALESCE(v.fechaPago, v.fecha), 1
        FROM ventas v
        LEFT JOIN venta_pagos vp ON vp.ventaID = v.ventaID
        WHERE vp.pagoID IS NULL
          AND v.estadoPago = 'pagado'
          AND v.pago > 0
    ");
}

function asegurarColumnasInventario(PDO $conn): void {
    asegurarColumna($conn, 'productos', 'fechaVencimiento', 'DATE NULL AFTER stock');
}

function obtenerClienteGeneral(PDO $conn): array {
    $stmt = $conn->prepare("
        SELECT *
        FROM clientes
        WHERE LOWER(nombre) IN ('cliente general', 'general')
        ORDER BY clienteID ASC
        LIMIT 1
    ");
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if($cliente){
        return $cliente;
    }

    $insert = $conn->prepare("
        INSERT INTO clientes (nombre, tipoDocumento, numeroDocumento, telefono, direccion)
        VALUES ('Cliente general', 'DNI', '00000000', '', '')
    ");
    $insert->execute();

    $stmt = $conn->prepare("SELECT * FROM clientes WHERE clienteID = ? LIMIT 1");
    $stmt->execute([$conn->lastInsertId()]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
