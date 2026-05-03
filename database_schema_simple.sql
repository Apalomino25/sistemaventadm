-- Crear base de datos
CREATE DATABASE IF NOT EXISTS sistemaventasdm DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistemaventasdm;

-- TABLA: USUARIOS
CREATE TABLE usuarios (
    usuarioID INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    contrasenia VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    estado INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: CATEGORIA
CREATE TABLE categoria (
    categoriaID INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    estado INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: PRODUCTOS
CREATE TABLE productos (
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
    FOREIGN KEY (categoriaID) REFERENCES categoria(categoriaID) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: CLIENTES
CREATE TABLE clientes (
    clienteID INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    tipoDocumento VARCHAR(20),
    numeroDocumento VARCHAR(20),
    telefono VARCHAR(20),
    direccion VARCHAR(255),
    estado INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: VENTAS
CREATE TABLE ventas (
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
    FOREIGN KEY (clienteID) REFERENCES clientes(clienteID) ON DELETE RESTRICT,
    FOREIGN KEY (usuarioID) REFERENCES usuarios(usuarioID) ON DELETE RESTRICT,
    INDEX idx_fecha (fecha),
    INDEX idx_estadoPago (estadoPago),
    INDEX idx_tipoPago (tipoPago),
    INDEX idx_clienteID (clienteID),
    INDEX idx_usuarioID (usuarioID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: DETALLEVENTA
CREATE TABLE detalleventa (
    detalleID INT PRIMARY KEY AUTO_INCREMENT,
    ventaID INT NOT NULL,
    productoID INT NOT NULL,
    cantidad INT NOT NULL,
    precioUnitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (ventaID) REFERENCES ventas(ventaID) ON DELETE CASCADE,
    FOREIGN KEY (productoID) REFERENCES productos(productoID) ON DELETE RESTRICT,
    INDEX idx_ventaID (ventaID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: CIERRES
CREATE TABLE cierres (
    cierreID INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE UNIQUE NOT NULL,
    estado INT DEFAULT 1,
    total_pendiente DECIMAL(10, 2) DEFAULT 0.00,
    total_pendientes_cobrados DECIMAL(10, 2) DEFAULT 0.00,
    usuario_cierre INT,
    FOREIGN KEY (usuario_cierre) REFERENCES usuarios(usuarioID) ON DELETE SET NULL,
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- INSERTAR DATOS INICIALES
INSERT IGNORE INTO usuarios (usuario, contrasenia, rol, nombre, estado) 
VALUES ('admin', 'admin123', 'administrador', 'Administrador', 1);

INSERT IGNORE INTO categoria (nombre, estado) 
VALUES ('prodeditable', 1);

INSERT IGNORE INTO clientes (nombre, tipoDocumento, numeroDocumento, telefono, direccion) 
VALUES ('Cliente general', 'DNI', '00000000', '', '');
