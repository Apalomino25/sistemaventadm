CREATE DATABASE IF NOT EXISTS sistemaventasdm
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sistemaventasdm;

CREATE TABLE IF NOT EXISTS usuarios (
    usuarioID INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    contrasenia VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categoria (
    categoriaID INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(150) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
    productoID INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(150) NULL,
    precioCompra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    precioVenta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    categoriaID INT NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    stock INT NOT NULL DEFAULT 0,
    fechaVencimiento DATE NULL,
    INDEX idx_productos_categoriaID (categoriaID),
    CONSTRAINT fk_productos_categoria
        FOREIGN KEY (categoriaID) REFERENCES categoria(categoriaID)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
    clienteID INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    tipoDocumento VARCHAR(50) NULL,
    numeroDocumento VARCHAR(50) NULL,
    telefono VARCHAR(20) NULL,
    direccion VARCHAR(150) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ventas (
    ventaID INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    clienteID INT NOT NULL,
    usuarioID INT NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tipoComprobante VARCHAR(50) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    pago DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    vuelto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tipoPago VARCHAR(50) NOT NULL DEFAULT 'efectivo',
    estadoPago VARCHAR(50) NOT NULL DEFAULT 'pagado',
    fechaPago DATETIME NULL,
    token VARCHAR(100) NULL UNIQUE,
    INDEX idx_ventas_fecha (fecha),
    INDEX idx_ventas_estadoPago (estadoPago),
    INDEX idx_ventas_tipoPago (tipoPago),
    INDEX idx_ventas_clienteID (clienteID),
    INDEX idx_ventas_usuarioID (usuarioID),
    CONSTRAINT fk_ventas_cliente
        FOREIGN KEY (clienteID) REFERENCES clientes(clienteID)
        ON DELETE RESTRICT,
    CONSTRAINT fk_ventas_usuario
        FOREIGN KEY (usuarioID) REFERENCES usuarios(usuarioID)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalleventa (
    detalleID INT PRIMARY KEY AUTO_INCREMENT,
    ventaID INT NOT NULL,
    productoID INT NOT NULL,
    cantidad INT NOT NULL,
    precioUnitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estadoPago VARCHAR(20) NOT NULL DEFAULT 'pagado',
    montoPagado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    saldoPendiente DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fechaPago DATETIME NULL,
    INDEX idx_detalleventa_ventaID (ventaID),
    INDEX idx_detalleventa_productoID (productoID),
    CONSTRAINT fk_detalleventa_venta
        FOREIGN KEY (ventaID) REFERENCES ventas(ventaID)
        ON DELETE CASCADE,
    CONSTRAINT fk_detalleventa_producto
        FOREIGN KEY (productoID) REFERENCES productos(productoID)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venta_pagos (
    pagoID INT PRIMARY KEY AUTO_INCREMENT,
    ventaID INT NOT NULL,
    tipoPago VARCHAR(30) NOT NULL,
    monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fechaPago DATETIME NOT NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_venta_pagos_venta (ventaID),
    INDEX idx_venta_pagos_fecha (fechaPago),
    INDEX idx_venta_pagos_tipo (tipoPago),
    CONSTRAINT fk_venta_pagos_venta
        FOREIGN KEY (ventaID) REFERENCES ventas(ventaID)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compras (
    compraID INT PRIMARY KEY AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detallecompra (
    detalleCompraID INT PRIMARY KEY AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kardex_movimientos (
    kardexID INT PRIMARY KEY AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cierres (
    cierreID INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE NOT NULL,
    tipopago VARCHAR(50) NOT NULL,
    total_ventas DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_pagado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_pendiente DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_pendientes_cobrados DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_recibido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_vuelto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_ganancia DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fisico DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    diferencia DECIMAL(10,2) GENERATED ALWAYS AS (fisico - total_recibido) STORED,
    observacion VARCHAR(255) NULL,
    usuarioID INT NULL,
    usuario_cierre INT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado INT NOT NULL DEFAULT 1,
    INDEX idx_cierres_fecha (fecha),
    INDEX idx_cierres_usuarioID (usuarioID),
    INDEX idx_cierres_usuario_cierre (usuario_cierre),
    CONSTRAINT fk_cierres_usuario
        FOREIGN KEY (usuarioID) REFERENCES usuarios(usuarioID)
        ON DELETE SET NULL,
    CONSTRAINT fk_cierres_usuario_cierre
        FOREIGN KEY (usuario_cierre) REFERENCES usuarios(usuarioID)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO usuarios (usuarioID, nombre, usuario, contrasenia, rol, estado)
VALUES (1, 'Administrador', 'admin', 'admin123', 'administrador', 1);

INSERT IGNORE INTO categoria (categoriaID, nombre, descripcion, estado)
VALUES (1, 'prodeditable', 'Productos con precio editable en POS', 1);

INSERT IGNORE INTO clientes (clienteID, nombre, tipoDocumento, numeroDocumento, telefono, direccion, estado)
VALUES (1, 'Cliente general', 'DNI', '00000000', '', '', 1);
