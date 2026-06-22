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

function indiceExiste(PDO $conn, string $tabla, string $indice): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$tabla, $indice]);
    return (int)$stmt->fetchColumn() > 0;
}

function eliminarIndiceUnicoFechaCierres(PDO $conn): void {
    $stmt = $conn->prepare("
        SELECT INDEX_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cierres'
          AND NON_UNIQUE = 0
          AND INDEX_NAME <> 'PRIMARY'
        GROUP BY INDEX_NAME
        HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) = 'fecha'
    ");
    $stmt->execute();

    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $indice){
        $conn->exec("ALTER TABLE cierres DROP INDEX `$indice`");
    }
}

function asegurarColumna(PDO $conn, string $tabla, string $columna, string $definicion): void {
    if(!columnaExiste($conn, $tabla, $columna)){
        $conn->exec("ALTER TABLE `$tabla` ADD COLUMN `$columna` $definicion");
    }
}

function asegurarColumnasPagos(PDO $conn): void {
    asegurarColumna($conn, 'ventas', 'fechaPago', 'DATETIME NULL AFTER estadoPago');
    asegurarColumna($conn, 'detalleventa', 'estadoPago', "VARCHAR(20) NOT NULL DEFAULT 'pagado' AFTER subtotal");
    asegurarColumna($conn, 'detalleventa', 'montoPagado', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER estadoPago');
    asegurarColumna($conn, 'detalleventa', 'saldoPendiente', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER montoPagado');
    asegurarColumna($conn, 'detalleventa', 'fechaPago', 'DATETIME NULL AFTER saldoPendiente');

    if(tablaExiste($conn, 'cierres')){
        eliminarIndiceUnicoFechaCierres($conn);
        asegurarColumna($conn, 'cierres', 'tipopago', "VARCHAR(50) NULL AFTER fecha");
        asegurarColumna($conn, 'cierres', 'total_ventas', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER tipopago');
        asegurarColumna($conn, 'cierres', 'total_pagado', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_ventas');
        asegurarColumna($conn, 'cierres', 'total_pendiente', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_pagado');
        asegurarColumna($conn, 'cierres', 'total_pendientes_cobrados', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_pendiente');
        asegurarColumna($conn, 'cierres', 'total_recibido', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_pendientes_cobrados');
        asegurarColumna($conn, 'cierres', 'total_vuelto', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_recibido');
        asegurarColumna($conn, 'cierres', 'total_compra', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_vuelto');
        asegurarColumna($conn, 'cierres', 'total_ganancia', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_compra');
        asegurarColumna($conn, 'cierres', 'fisico', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_ganancia');
        asegurarColumna($conn, 'cierres', 'diferencia', 'DECIMAL(10,2) GENERATED ALWAYS AS (fisico - total_recibido) STORED AFTER fisico');
        asegurarColumna($conn, 'cierres', 'observacion', 'VARCHAR(255) NULL AFTER diferencia');
        asegurarColumna($conn, 'cierres', 'usuarioID', 'INT NULL AFTER observacion');
        asegurarColumna($conn, 'cierres', 'usuario_cierre', 'INT NULL AFTER usuarioID');
        asegurarColumna($conn, 'cierres', 'creado_en', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER usuario_cierre');
        asegurarColumna($conn, 'cierres', 'estado', 'INT NOT NULL DEFAULT 1 AFTER creado_en');

        $conn->exec("
            UPDATE cierres
            SET usuario_cierre = usuarioID
            WHERE usuario_cierre IS NULL
              AND usuarioID IS NOT NULL
        ");

        if(!indiceExiste($conn, 'cierres', 'idx_cierres_fecha')){
            $conn->exec("ALTER TABLE cierres ADD INDEX idx_cierres_fecha (fecha)");
        }
        if(!indiceExiste($conn, 'cierres', 'idx_cierres_usuarioID')){
            $conn->exec("ALTER TABLE cierres ADD INDEX idx_cierres_usuarioID (usuarioID)");
        }
        if(!indiceExiste($conn, 'cierres', 'idx_cierres_usuario_cierre')){
            $conn->exec("ALTER TABLE cierres ADD INDEX idx_cierres_usuario_cierre (usuario_cierre)");
        }
    }

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

function asegurarTablasKardex(PDO $conn): void {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS compras (
            compraID INT AUTO_INCREMENT PRIMARY KEY,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            proveedor VARCHAR(150) NULL,
            comprobante VARCHAR(80) NULL,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            observacion VARCHAR(255) NULL,
            usuarioID INT NULL,
            estado TINYINT NOT NULL DEFAULT 1,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_compras_fecha (fecha),
            INDEX idx_compras_usuarioID (usuarioID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS detallecompra (
            detalleCompraID INT AUTO_INCREMENT PRIMARY KEY,
            compraID INT NOT NULL,
            productoID INT NOT NULL,
            cantidad INT NOT NULL,
            precioCompra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            precioVenta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            fechaVencimiento DATE NULL,
            INDEX idx_detallecompra_compraID (compraID),
            INDEX idx_detallecompra_productoID (productoID),
            CONSTRAINT fk_detallecompra_compra
                FOREIGN KEY (compraID) REFERENCES compras(compraID)
                ON DELETE CASCADE,
            CONSTRAINT fk_detallecompra_producto
                FOREIGN KEY (productoID) REFERENCES productos(productoID)
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS kardex_movimientos (
            kardexID INT AUTO_INCREMENT PRIMARY KEY,
            productoID INT NOT NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            tipoMovimiento VARCHAR(20) NOT NULL,
            concepto VARCHAR(50) NOT NULL,
            referenciaTipo VARCHAR(30) NULL,
            referenciaID INT NULL,
            cantidadEntrada INT NOT NULL DEFAULT 0,
            cantidadSalida INT NOT NULL DEFAULT 0,
            saldoAnterior INT NOT NULL DEFAULT 0,
            saldoNuevo INT NOT NULL DEFAULT 0,
            costoUnitario DECIMAL(10,2) NULL,
            precioUnitario DECIMAL(10,2) NULL,
            totalMovimiento DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            observacion VARCHAR(255) NULL,
            usuarioID INT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kardex_producto_fecha (productoID, fecha),
            INDEX idx_kardex_referencia (referenciaTipo, referenciaID),
            INDEX idx_kardex_concepto (concepto),
            CONSTRAINT fk_kardex_producto
                FOREIGN KEY (productoID) REFERENCES productos(productoID)
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function inicializarKardexDesdeStock(PDO $conn, ?int $usuarioID = null): void {
    asegurarTablasKardex($conn);

    $stmt = $conn->prepare("
        INSERT INTO kardex_movimientos
        (productoID, fecha, tipoMovimiento, concepto, referenciaTipo, referenciaID,
         cantidadEntrada, cantidadSalida, saldoAnterior, saldoNuevo, costoUnitario,
         precioUnitario, totalMovimiento, observacion, usuarioID)
        SELECT
            p.productoID,
            NOW(),
            'entrada',
            'saldo_inicial',
            'producto',
            p.productoID,
            p.stock,
            0,
            0,
            p.stock,
            p.precioCompra,
            p.precioVenta,
            p.stock * COALESCE(p.precioCompra, 0),
            'Saldo inicial migrado desde inventario',
            ?
        FROM productos p
        WHERE p.stock > 0
          AND NOT EXISTS (
              SELECT 1
              FROM kardex_movimientos km
              WHERE km.productoID = p.productoID
              LIMIT 1
          )
    ");
    $stmt->execute([$usuarioID]);
}

function registrarMovimientoKardex(
    PDO $conn,
    int $productoID,
    string $tipoMovimiento,
    string $concepto,
    int $cantidadEntrada,
    int $cantidadSalida,
    int $saldoAnterior,
    int $saldoNuevo,
    ?string $referenciaTipo = null,
    ?int $referenciaID = null,
    ?float $costoUnitario = null,
    ?float $precioUnitario = null,
    ?string $observacion = null,
    ?int $usuarioID = null
): int {
    if(!tablaExiste($conn, 'kardex_movimientos')){
        asegurarTablasKardex($conn);
    }

    $totalMovimiento = 0;
    if($cantidadEntrada > 0){
        $totalMovimiento = round($cantidadEntrada * (float)($costoUnitario ?? 0), 2);
    } elseif($cantidadSalida > 0){
        $valorUnitario = $precioUnitario ?? $costoUnitario ?? 0;
        $totalMovimiento = round($cantidadSalida * (float)$valorUnitario, 2);
    }

    $stmt = $conn->prepare("
        INSERT INTO kardex_movimientos
        (productoID, tipoMovimiento, concepto, referenciaTipo, referenciaID,
         cantidadEntrada, cantidadSalida, saldoAnterior, saldoNuevo, costoUnitario,
         precioUnitario, totalMovimiento, observacion, usuarioID)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $productoID,
        $tipoMovimiento,
        $concepto,
        $referenciaTipo,
        $referenciaID,
        $cantidadEntrada,
        $cantidadSalida,
        $saldoAnterior,
        $saldoNuevo,
        $costoUnitario,
        $precioUnitario,
        $totalMovimiento,
        $observacion,
        $usuarioID
    ]);

    return (int)$conn->lastInsertId();
}

function aplicarMovimientoStock(
    PDO $conn,
    int $productoID,
    string $tipoMovimiento,
    string $concepto,
    int $cantidad,
    ?string $referenciaTipo = null,
    ?int $referenciaID = null,
    ?float $costoUnitario = null,
    ?float $precioUnitario = null,
    ?string $observacion = null,
    ?int $usuarioID = null
): array {
    if($cantidad <= 0){
        throw new Exception('La cantidad del movimiento debe ser mayor a cero.');
    }

    if(!in_array($tipoMovimiento, ['entrada', 'salida'], true)){
        throw new Exception('Tipo de movimiento kardex invalido.');
    }

    if(!tablaExiste($conn, 'kardex_movimientos')){
        asegurarTablasKardex($conn);
    }

    $stmt = $conn->prepare("
        SELECT stock
        FROM productos
        WHERE productoID = ?
        FOR UPDATE
    ");
    $stmt->execute([$productoID]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$producto){
        throw new Exception('Producto no encontrado para movimiento kardex.');
    }

    $saldoAnterior = (int)$producto['stock'];
    $cantidadEntrada = $tipoMovimiento === 'entrada' ? $cantidad : 0;
    $cantidadSalida = $tipoMovimiento === 'salida' ? $cantidad : 0;
    $saldoNuevo = $tipoMovimiento === 'entrada'
        ? $saldoAnterior + $cantidad
        : $saldoAnterior - $cantidad;

    if($saldoNuevo < 0){
        throw new Exception('Stock insuficiente para registrar salida de kardex.');
    }

    $stmtUpdate = $conn->prepare("
        UPDATE productos
        SET stock = ?
        WHERE productoID = ?
    ");
    $stmtUpdate->execute([$saldoNuevo, $productoID]);

    $kardexID = registrarMovimientoKardex(
        $conn,
        $productoID,
        $tipoMovimiento,
        $concepto,
        $cantidadEntrada,
        $cantidadSalida,
        $saldoAnterior,
        $saldoNuevo,
        $referenciaTipo,
        $referenciaID,
        $costoUnitario,
        $precioUnitario,
        $observacion,
        $usuarioID
    );

    return [
        'kardexID' => $kardexID,
        'saldoAnterior' => $saldoAnterior,
        'saldoNuevo' => $saldoNuevo
    ];
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
