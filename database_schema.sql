-- ====================================
-- ESQUEMA COMPLETO - Sistema de Ventas
-- ====================================

-- 1. TABLA: USUARIOS
CREATE TABLE IF NOT EXISTS usuarios (
    usuarioID INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    contrasenia VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    estado INT DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABLA: CATEGORIA
CREATE TABLE IF NOT EXISTS categoria (
    categoriaID INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    estado INT DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. TABLA: PRODUCTOS
CREATE TABLE IF NOT EXISTS productos (
    productoID INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precioCompra DECIMAL(10, 2) NOT NULL,
    precioVenta DECIMAL(10, 2) NOT NULL,
    categoriaID INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    estado INT DEFAULT 1,
    fechaVencimiento DATE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoriaID) REFERENCES categoria(categoriaID) ON DELETE RESTRICT
);

-- 4. TABLA: CLIENTES
CREATE TABLE IF NOT EXISTS clientes (
    clienteID INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    tipoDocumento VARCHAR(20),
    numeroDocumento VARCHAR(20),
    telefono VARCHAR(20),
    direccion VARCHAR(255),
    estado INT DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. TABLA: VENTAS
CREATE TABLE IF NOT EXISTS ventas (
    ventaID INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    clienteID INT NOT NULL,
    usuarioID INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    tipoComprobante VARCHAR(50),
    estado INT DEFAULT 1,
    pago DECIMAL(10, 2) DEFAULT 0.00,
    vuelto DECIMAL(10, 2) DEFAULT 0.00,
    tipoPago VARCHAR(50),
    estadoPago VARCHAR(50) DEFAULT 'pagado',
    fechaPago DATETIME,
    token VARCHAR(100) UNIQUE,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (clienteID) REFERENCES clientes(clienteID) ON DELETE RESTRICT,
    FOREIGN KEY (usuarioID) REFERENCES usuarios(usuarioID) ON DELETE RESTRICT,
    INDEX idx_fecha (fecha),
    INDEX idx_estadoPago (estadoPago),
    INDEX idx_tipoPago (tipoPago)
);

-- 6. TABLA: DETALLE VENTA
CREATE TABLE IF NOT EXISTS detalleventa (
    detalleID INT PRIMARY KEY AUTO_INCREMENT,
    ventaID INT NOT NULL,
    productoID INT NOT NULL,
    cantidad INT NOT NULL,
    precioUnitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ventaID) REFERENCES ventas(ventaID) ON DELETE CASCADE,
    FOREIGN KEY (productoID) REFERENCES productos(productoID) ON DELETE RESTRICT,
    INDEX idx_ventaID (ventaID)
);

-- 7. TABLA: CIERRES
CREATE TABLE IF NOT EXISTS cierres (
    cierreID INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE UNIQUE NOT NULL,
    estado INT DEFAULT 1,
    total_pendiente DECIMAL(10, 2) DEFAULT 0.00,
    total_pendientes_cobrados DECIMAL(10, 2) DEFAULT 0.00,
    usuario_cierre INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_cierre) REFERENCES usuarios(usuarioID) ON DELETE SET NULL,
    INDEX idx_fecha (fecha)
);

-- ====================================
-- DATOS DE PRUEBA (OPCIONAL)
-- ====================================

-- Insertar usuario admin
INSERT IGNORE INTO usuarios (usuario, contrasenia, rol, nombre, estado) 
VALUES ('admin', 'admin123', 'administrador', 'Administrador', 1);

-- Insertar categoría general
INSERT IGNORE INTO categoria (nombre, estado) 
VALUES ('prodeditable', 1);

-- Insertar cliente general
INSERT IGNORE INTO clientes (nombre, tipoDocumento, numeroDocumento, telefono, direccion) 
VALUES ('Cliente general', 'DNI', '00000000', '', '');

-- ====================================
-- ÍNDICES Y OPTIMIZACIONES
-- ====================================

-- Índices para búsquedas frecuentes
CREATE INDEX IF NOT EXISTS idx_productos_codigo ON productos(codigo);
CREATE INDEX IF NOT EXISTS idx_productos_estado ON productos(estado);
CREATE INDEX IF NOT EXISTS idx_clientes_nombre ON clientes(nombre);
CREATE INDEX IF NOT EXISTS idx_clientes_documento ON clientes(numeroDocumento);
CREATE INDEX IF NOT EXISTS idx_ventas_cliente ON ventas(clienteID);
CREATE INDEX IF NOT EXISTS idx_ventas_usuario ON ventas(usuarioID);
