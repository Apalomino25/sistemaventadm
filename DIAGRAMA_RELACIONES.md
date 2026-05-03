# 🗂️ Diagrama Entidad-Relación (ER) - Base de Datos

## Visualización Textual

```
┌─────────────────┐
│    USUARIOS     │
├─────────────────┤
│ usuarioID (PK)  │◄─────┐
│ usuario         │      │
│ contrasenia     │      │
│ rol             │      │
│ nombre          │      │
│ estado          │      │
└─────────────────┘      │
         ▲               │
         │               │
    (1:N)│          (1:N)│
         │               │
         │           ┌───┴──────────────────┐
         │           │                      │
┌─────────────────┐ ┌──────────────────────┐
│    CIERRES      │ │      VENTAS          │
├─────────────────┤ ├──────────────────────┤
│ cierreID (PK)   │ │ ventaID (PK)         │
│ fecha (UNIQUE)  │ │ fecha                │
│ estado          │ │ clienteID (FK) ──────┼─┐
│ total_pendiente │ │ usuarioID (FK) ──┐  │ │
│ total_pendientes│ │ total            │  │ │
│ usuario_cierre  │ │ pago             │  │ │
│                 │ │ vuelto           │  │ │
│                 │ │ tipoPago         │  │ │
│                 │ │ estadoPago       │  │ │
│                 │ │ fechaPago        │  │ │
│                 │ │ tipoComprobante  │  │ │
│                 │ │ estado           │  │ │
│                 │ │ token (UNIQUE)   │  │ │
│                 │ │ (1:N)            │  │ │
│                 │ │      │           │  │ │
└─────────────────┘ └──┬───┴─────────┬──┬──┘ │
                       │             │  │    │
                  (1:N)│         (1:N)  │    │
                       │             │  │    │
                ┌──────┴──────┐    │  │    │
                │             │    │  │    │
         ┌──────────────────┐ │    │  │    │
         │  DETALLEVENTA    │ │    │  │    │
         ├──────────────────┤ │    │  │    │
         │detalleID (PK)    │ │    │  │    │
         │ventaID (FK) ─────┼─┘    │  │    │
         │productoID (FK)──┬┘      │  │    │
         │cantidad         │       │  │    │
         │precioUnitario   │    ┌──┴──┘    │
         │subtotal         │    │          │
         └──────┬──────────┘    │          │
                │           (1:N)         │
                │               │      (1:N)
                │          ┌────┴─────────┤
                │          │              │
                │     ┌────────────┐ ┌─────────────┐
                │     │ PRODUCTOS  │ │  CLIENTES   │
                │     ├────────────┤ ├─────────────┤
                └────►│productoID  │ │clienteID(PK)│
                      │(PK)        │ │nombre       │
                      │codigo(UNIQ)│ │tipoDocumento│
                      │nombre      │ │numeroDocum. │
                      │descripcion │ │telefono     │
                      │precioCompra│ │direccion    │
                      │precioVenta │ │estado       │
                      │stock       │ └─────────────┘
                      │estado      │
                      │categoriaID │
                      │(FK)────────┼──────┐
                      │            │      │
                      │            │  (1:N)
                      └────┬───────┘      │
                           │             │
                      (1:N)│        ┌─────────────┐
                           └───────►│  CATEGORIA  │
                                    ├─────────────┤
                                    │categoriaID  │
                                    │(PK)         │
                                    │nombre(UNIQ) │
                                    │estado       │
                                    └─────────────┘
```

---

## Leyenda

```
PK   = Primary Key (Clave Primaria)
FK   = Foreign Key (Clave Foránea)
UNIQ = Campo UNIQUE (No se puede repetir)
(1:N) = Uno a Muchos (una categoría tiene muchos productos)
(1:1) = Uno a Uno
```

---

## Relaciones Detalladas

### 1. USUARIOS → VENTAS (1:N)
- 1 Usuario puede hacer N Ventas
- Campo: usuarioID en ventas
- Descripción: Quién registró la venta

### 2. USUARIOS → CIERRES (1:N)
- 1 Usuario puede hacer N Cierres
- Campo: usuario_cierre en cierres
- Descripción: Quién hizo el cierre diario

### 3. CLIENTES → VENTAS (1:N)
- 1 Cliente puede tener N Ventas
- Campo: clienteID en ventas
- Descripción: Quién compró

### 4. VENTAS → DETALLEVENTA (1:N)
- 1 Venta tiene N Líneas de detalle
- Campo: ventaID en detalleventa
- Descripción: Productos comprados

### 5. PRODUCTOS → DETALLEVENTA (1:N)
- 1 Producto puede estar en N Detalles de venta
- Campo: productoID en detalleventa
- Descripción: Cada venta registra qué productos se vendieron

### 6. CATEGORIA → PRODUCTOS (1:N)
- 1 Categoría tiene N Productos
- Campo: categoriaID en productos
- Descripción: Clasificación de productos

---

## Integridad Referencial

```
DELETE: ON DELETE RESTRICT
└─ No se puede eliminar un registro si tiene referencias

DELETE: ON DELETE CASCADE
└─ Si se elimina una venta, se eliminan sus detalles automáticamente

DELETE: ON DELETE SET NULL
└─ Si se elimina el usuario cierre, se pone NULL
```

### Restricciones Aplicadas:

| Tabla | Campo | Referencia | Acción |
|-------|-------|-----------|--------|
| PRODUCTOS | categoriaID | CATEGORIA | RESTRICT |
| VENTAS | clienteID | CLIENTES | RESTRICT |
| VENTAS | usuarioID | USUARIOS | RESTRICT |
| DETALLEVENTA | ventaID | VENTAS | CASCADE |
| DETALLEVENTA | productoID | PRODUCTOS | RESTRICT |
| CIERRES | usuario_cierre | USUARIOS | SET NULL |

---

## Flujo de Datos Típico

### Escenario: Registrar una Venta

```
1. Usuario inicia sesión
   └─ SELECT * FROM usuarios WHERE usuario = ? AND estado = 1

2. Usuario selecciona cliente
   └─ SELECT * FROM clientes WHERE estado = 1

3. Usuario agrega productos al carrito
   └─ SELECT * FROM productos WHERE productoID = ? AND estado = 1

4. Usuario confirma la venta
   └─ INSERT INTO ventas (fecha, clienteID, usuarioID, total, ...)
   └─ Obtiene: ventaID

5. Por cada producto en la venta:
   └─ INSERT INTO detalleventa (ventaID, productoID, cantidad, precio, ...)
   └─ UPDATE productos SET stock = stock - cantidad WHERE productoID = ?

6. Si la venta se paga:
   └─ UPDATE ventas SET estadoPago = 'pagado', fechaPago = NOW()
```

---

## Índices Importantes

```
USUARIOS:
└─ PRIMARY KEY (usuarioID)
└─ UNIQUE (usuario)

CATEGORIA:
└─ PRIMARY KEY (categoriaID)
└─ UNIQUE (nombre)

PRODUCTOS:
└─ PRIMARY KEY (productoID)
└─ UNIQUE (codigo)
└─ INDEX (categoriaID)
└─ INDEX (estado)

CLIENTES:
└─ PRIMARY KEY (clienteID)
└─ INDEX (nombre)
└─ INDEX (numeroDocumento)

VENTAS:
└─ PRIMARY KEY (ventaID)
└─ UNIQUE (token) ← Evita ventas duplicadas
└─ INDEX (fecha)
└─ INDEX (estadoPago)
└─ INDEX (tipoPago)
└─ INDEX (clienteID)
└─ INDEX (usuarioID)

DETALLEVENTA:
└─ PRIMARY KEY (detalleID)
└─ INDEX (ventaID)

CIERRES:
└─ PRIMARY KEY (cierreID)
└─ UNIQUE (fecha)
└─ INDEX (fecha)
```

---

## Campos de Fecha (Timestamps)

```
Automáticos:
├─ usuarios.fecha_creacion (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
├─ categoria.fecha_creacion
├─ productos.fecha_creacion
├─ clientes.fecha_creacion
├─ ventas.fecha (DATETIME DEFAULT CURRENT_TIMESTAMP)
├─ ventas.fecha_actualizacion (AUTO UPDATE)
├─ detalleventa.fecha_creacion
└─ cierres.fecha_creacion

Manuales:
├─ ventas.fechaPago (Se llena cuando se paga una venta pendiente)
└─ cierres.fecha (Se establece cuando se hace el cierre)
```

---

## Consultas Típicas Complejas

### Venta con sus Detalles y Cliente

```sql
SELECT 
    v.ventaID,
    c.nombre AS cliente,
    v.fecha,
    v.total,
    u.nombre AS vendedor,
    p.nombre AS producto,
    d.cantidad,
    d.precioUnitario,
    d.subtotal
FROM ventas v
INNER JOIN clientes c ON v.clienteID = c.clienteID
INNER JOIN usuarios u ON v.usuarioID = u.usuarioID
INNER JOIN detalleventa d ON v.ventaID = d.ventaID
INNER JOIN productos p ON d.productoID = p.productoID
WHERE v.ventaID = ?
ORDER BY d.detalleID;
```

### Resumen de Ventas por Categoría

```sql
SELECT 
    cat.nombre AS categoria,
    COUNT(DISTINCT v.ventaID) AS num_ventas,
    SUM(d.cantidad) AS productos_vendidos,
    SUM(d.subtotal) AS ingresos
FROM categoria cat
INNER JOIN productos p ON cat.categoriaID = p.categoriaID
INNER JOIN detalleventa d ON p.productoID = d.productoID
INNER JOIN ventas v ON d.ventaID = v.ventaID
WHERE v.estadoPago = 'pagado'
GROUP BY cat.categoriaID, cat.nombre;
```

### Ganancias por Vendedor

```sql
SELECT 
    u.nombre AS vendedor,
    COUNT(DISTINCT v.ventaID) AS num_ventas,
    SUM(v.total) AS total_vendido,
    SUM((d.precioUnitario - p.precioCompra) * d.cantidad) AS ganancia
FROM usuarios u
INNER JOIN ventas v ON u.usuarioID = v.usuarioID
INNER JOIN detalleventa d ON v.ventaID = d.ventaID
INNER JOIN productos p ON d.productoID = p.productoID
WHERE v.estadoPago = 'pagado'
GROUP BY u.usuarioID, u.nombre;
```

