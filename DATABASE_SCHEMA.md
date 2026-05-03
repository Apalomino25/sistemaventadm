# 📊 Documentación de Base de Datos - Sistema de Ventas

## Estructura de Tablas

### 1. **USUARIOS**
Almacena los datos de los usuarios del sistema.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| usuarioID | INT | Identificador único (PK, AUTO_INCREMENT) |
| usuario | VARCHAR(50) | Nombre de usuario (UNIQUE) |
| contrasenia | VARCHAR(255) | Contraseña encriptada |
| rol | VARCHAR(50) | Rol del usuario (administrador, vendedor, etc.) |
| nombre | VARCHAR(100) | Nombre completo |
| estado | INT | 1=Activo, 0=Inactivo |

---

### 2. **CATEGORIA**
Categorías de productos.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| categoriaID | INT | Identificador único (PK, AUTO_INCREMENT) |
| nombre | VARCHAR(100) | Nombre de la categoría (UNIQUE) |
| estado | INT | 1=Activo, 0=Inactivo |

---

### 3. **PRODUCTOS**
Catálogo de productos/artículos en inventario.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| productoID | INT | Identificador único (PK, AUTO_INCREMENT) |
| codigo | VARCHAR(50) | Código de barras (UNIQUE) |
| nombre | VARCHAR(150) | Nombre del producto |
| descripcion | TEXT | Descripción del producto |
| precioCompra | DECIMAL(10,2) | Precio de costo |
| precioVenta | DECIMAL(10,2) | Precio de venta |
| categoriaID | INT | FK a categoria (requerido) |
| stock | INT | Cantidad disponible |
| estado | INT | 1=Activo, 0=Inactivo |
| fechaVencimiento | DATE | Fecha de vencimiento (opcional) |

---

### 4. **CLIENTES**
Datos de clientes.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| clienteID | INT | Identificador único (PK, AUTO_INCREMENT) |
| nombre | VARCHAR(150) | Nombre del cliente |
| tipoDocumento | VARCHAR(20) | DNI, RUC, Pasaporte, etc. |
| numeroDocumento | VARCHAR(20) | Número del documento |
| telefono | VARCHAR(20) | Teléfono de contacto |
| direccion | VARCHAR(255) | Dirección |
| estado | INT | 1=Activo, 0=Inactivo |

**Nota:** Existe un cliente especial "Cliente general" para ventas sin cliente específico.

---

### 5. **VENTAS**
Registro de cada transacción de venta.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| ventaID | INT | Identificador único (PK, AUTO_INCREMENT) |
| fecha | DATETIME | Fecha y hora de la venta |
| clienteID | INT | FK a clientes (requerido) |
| usuarioID | INT | FK a usuarios (vendedor) |
| total | DECIMAL(10,2) | Total de la venta |
| tipoComprobante | VARCHAR(50) | TICKET, FACTURA, etc. |
| estado | INT | 1=Activa, 0=Anulada |
| pago | DECIMAL(10,2) | Monto pagado |
| vuelto | DECIMAL(10,2) | Cambio entregado |
| tipoPago | VARCHAR(50) | efectivo, yape, plin, transferencia |
| estadoPago | VARCHAR(50) | 'pagado' o 'pendiente' |
| fechaPago | DATETIME | Fecha cuando se realizó el pago |
| token | VARCHAR(100) | Token único para evitar duplicados (UNIQUE) |

**Índices:** fecha, estadoPago, tipoPago, clienteID, usuarioID

---

### 6. **DETALLEVENTA**
Detalle línea por línea de cada venta (muchos productos por venta).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| detalleID | INT | Identificador único (PK, AUTO_INCREMENT) |
| ventaID | INT | FK a ventas (requerido) |
| productoID | INT | FK a productos (requerido) |
| cantidad | INT | Cantidad vendida |
| precioUnitario | DECIMAL(10,2) | Precio por unidad |
| subtotal | DECIMAL(10,2) | cantidad × precioUnitario |

**Relación:** Una venta (1) tiene muchos detalles (N)

---

### 7. **CIERRES**
Cierre diario de cajas.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| cierreID | INT | Identificador único (PK, AUTO_INCREMENT) |
| fecha | DATE | Fecha del cierre (UNIQUE) |
| estado | INT | 1=Activo, 0=Inactivo |
| total_pendiente | DECIMAL(10,2) | Total de ventas pendientes del día |
| total_pendientes_cobrados | DECIMAL(10,2) | Pendientes de días anteriores cobrados hoy |
| usuario_cierre | INT | FK a usuarios (quien realizó el cierre) |

---

## 🔧 Instalación

### Opción 1: Usando MySQL Workbench o PhpMyAdmin
1. Copia el contenido del archivo `database_schema.sql`
2. Abre tu cliente MySQL
3. Crea una base de datos: `CREATE DATABASE sistemaventasdm;`
4. Selecciona la BD: `USE sistemaventasdm;`
5. Ejecuta el script SQL

### Opción 2: Desde terminal/cmd
```bash
mysql -u root -p sistemaventasdm < database_schema.sql
```

### Opción 3: Desde PHP (automático)
Si quieres que la BD se cree automáticamente, crea un archivo `setup.php`:

```php
<?php
require_once 'config/conexion.php';

$sql = file_get_contents('database_schema.sql');
$statements = explode(';', $sql);

foreach($statements as $statement){
    $statement = trim($statement);
    if(!empty($statement)){
        try {
            $conn->exec($statement);
            echo "✓ Ejecutado\n";
        } catch(PDOException $e){
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "Base de datos configurada correctamente.";
?>
```

---

## 🔑 Relaciones Principales

```
USUARIOS (1) ──────→ (N) VENTAS
                         ↓
                    DETALLEVENTA
                         ↓
                    PRODUCTOS
                         ↓
                    CATEGORIA

CLIENTES (1) ──────→ (N) VENTAS

USUARIOS (1) ──────→ (N) CIERRES
```

---

## ⚠️ Campos Críticos que NO Pueden Estar Vacíos

- **productos.codigo** - Debe ser UNIQUE (código de barras)
- **productos.categoriaID** - Relación obligatoria
- **ventas.clienteID** - Siempre debe haber un cliente (mínimo "Cliente general")
- **ventas.usuarioID** - Debe registrar quién hizo la venta
- **ventas.total** - Debe ser mayor a 0
- **detalleventa.ventaID** - Relación obligatoria con venta

---

## 🚨 Errores Comunes y Soluciones

### Error: "Violación de clave foránea"
**Causa:** Intentas insertar un producto con categoriaID que no existe
**Solución:** Primero crea la categoría, luego el producto

### Error: "Columna no existe"
**Causa:** La tabla no tiene el campo esperado
**Solución:** Ejecuta el archivo `database_schema.sql` para crear la estructura correcta

### Error: "Duplicado de campo UNIQUE"
**Causa:** Intentas insertar un código de producto o token duplicado
**Solución:** Cambia el valor a uno único

---

## 📝 Datos de Prueba Predeterminados

El script crea automáticamente:
- **Usuario:** admin / admin123
- **Cliente especial:** "Cliente general" con DNI 00000000
- **Categoría:** "prodeditable" (para productos con precio editable)

---

## 🔄 Cambios Dinámicos en el Código

El archivo `schema_helpers.php` contiene funciones que agregan campos automáticamente si no existen:

```php
asegurarColumnasPagos();      // Agrega fechaPago, total_pendientes_cobrados
asegurarColumnasInventario(); // Agrega fechaVencimiento
```

Esto significa que tu código es resiliente a cambios de esquema.

