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
    asegurarColumna($conn, 'detalleventa', 'montoPagado', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER estadoPago');
    asegurarColumna($conn, 'detalleventa', 'saldoPendiente', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER montoPagado');
    asegurarColumna($conn, 'detalleventa', 'fechaPago', 'DATETIME NULL AFTER saldoPendiente');

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
        SET estadoPago = CASE
            WHEN COALESCE(pago, 0) >= COALESCE(total, 0) THEN 'pagado'
            WHEN COALESCE(pago, 0) > 0 THEN 'parcial'
            ELSE 'pendiente'
        END
        WHERE estadoPago IS NULL
           OR estadoPago = ''
    ");

    $conn->exec("
        UPDATE ventas
        SET fechaPago = fecha
        WHERE estadoPago = 'pagado'
          AND fechaPago IS NULL
    ");

    $conn->exec("
        UPDATE detalleventa d
        INNER JOIN ventas v ON v.ventaID = d.ventaID
        SET d.estadoPago = COALESCE(NULLIF(v.estadoPago, ''), 'pendiente'),
            d.montoPagado = CASE WHEN COALESCE(NULLIF(v.estadoPago, ''), 'pendiente') = 'pagado' THEN d.subtotal ELSE 0 END,
            d.saldoPendiente = CASE WHEN COALESCE(NULLIF(v.estadoPago, ''), 'pendiente') = 'pagado' THEN 0 ELSE d.subtotal END,
            d.fechaPago = v.fechaPago
        WHERE d.fechaPago IS NULL
    ");

    $conn->exec("
        UPDATE detalleventa d
        INNER JOIN ventas v ON v.ventaID = d.ventaID
        SET d.montoPagado = CASE WHEN COALESCE(NULLIF(v.estadoPago, ''), 'pendiente') = 'pagado' THEN d.subtotal ELSE 0 END,
            d.saldoPendiente = CASE WHEN COALESCE(NULLIF(v.estadoPago, ''), 'pendiente') = 'pagado' THEN 0 ELSE d.subtotal END
        WHERE d.montoPagado = 0
          AND d.saldoPendiente = 0
    ");

    $conn->exec("
        INSERT INTO venta_pagos (ventaID, tipoPago, monto, fechaPago, estado)
        SELECT
            v.ventaID,
            v.tipoPago,
            CASE
                WHEN LOWER(COALESCE(v.tipoPago, '')) = 'efectivo'
                    THEN GREATEST(COALESCE(v.pago, 0) - COALESCE(v.vuelto, 0), 0)
                ELSE COALESCE(v.pago, 0)
            END,
            COALESCE(v.fechaPago, v.fecha),
            1
        FROM ventas v
        LEFT JOIN venta_pagos vp ON vp.ventaID = v.ventaID
        WHERE vp.pagoID IS NULL
          AND v.estadoPago = 'pagado'
          AND v.pago > 0
    ");

    $conn->exec("
        UPDATE venta_pagos vp
        INNER JOIN ventas v ON v.ventaID = vp.ventaID
        INNER JOIN (
            SELECT ventaID, COUNT(*) AS total_pagos
            FROM venta_pagos
            WHERE estado = 1
            GROUP BY ventaID
        ) conteo ON conteo.ventaID = vp.ventaID
        SET vp.monto = GREATEST(COALESCE(v.pago, 0) - COALESCE(v.vuelto, 0), 0)
        WHERE vp.estado = 1
          AND conteo.total_pagos = 1
          AND LOWER(COALESCE(v.tipoPago, '')) = 'efectivo'
          AND COALESCE(v.vuelto, 0) > 0
          AND ABS(vp.monto - COALESCE(v.pago, 0)) < 0.01
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
