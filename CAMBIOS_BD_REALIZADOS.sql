-- ═══════════════════════════════════════════════════════════════════════════════
-- CAMBIOS REALIZADOS A LA BASE DE DATOS SISTEMAVENTASDM
-- Análisis: Campos faltantes detectados y agregados
-- Fecha: Análisis del código vs BD
-- ═══════════════════════════════════════════════════════════════════════════════

-- ════════════════════════════════════════════════════════════════════════════════
-- 1. TABLA VENTAS - Campo faltante: fechaPago
-- ════════════════════════════════════════════════════════════════════════════════
-- Descripción: Se agregó el campo fechaPago para registrar cuándo se pagó una venta
-- Tipo: DATETIME NULL
-- Posición: Después de estadoPago
-- Usado en: guardar_venta.php, actualizar_estado_pago.php, obtener_historial.php

ALTER TABLE ventas ADD COLUMN fechaPago DATETIME NULL AFTER estadoPago;

-- ════════════════════════════════════════════════════════════════════════════════
-- 2. TABLA PRODUCTOS - Campo faltante: fechaVencimiento
-- ════════════════════════════════════════════════════════════════════════════════
-- Descripción: Se agregó para controlar productos con vencimiento (perecederos)
-- Tipo: DATE NULL
-- Posición: Después de stock
-- Usado en: guardar_producto_inventario.php

ALTER TABLE productos ADD COLUMN fechaVencimiento DATE NULL AFTER stock;

-- ════════════════════════════════════════════════════════════════════════════════
-- 3. TABLA CIERRES - 8 Campos faltantes
-- ════════════════════════════════════════════════════════════════════════════════

-- 3.1. Campo: total_pagado
-- Descripción: Total de ventas pagadas en este cierre
-- Tipo: DECIMAL(10,2) DEFAULT 0.00
-- Posición: Después de total_ventas
ALTER TABLE cierres ADD COLUMN total_pagado DECIMAL(10,2) DEFAULT 0.00 AFTER total_ventas;

-- 3.2. Campo: total_pendiente
-- Descripción: Total de ventas pendientes de pago en este cierre
-- Tipo: DECIMAL(10,2) DEFAULT 0.00
-- Posición: Después de total_pagado
ALTER TABLE cierres ADD COLUMN total_pendiente DECIMAL(10,2) DEFAULT 0.00 AFTER total_pagado;

-- 3.3. Campo: total_pendientes_cobrados
-- Descripción: Total de ventas pendientes de días anteriores cobradas hoy
-- Tipo: DECIMAL(10,2) DEFAULT 0.00
-- Posición: Después de total_pendiente
ALTER TABLE cierres ADD COLUMN total_pendientes_cobrados DECIMAL(10,2) DEFAULT 0.00 AFTER total_pendiente;

-- 3.4. Campo: total_vuelto
-- Descripción: Total de vuelto entregado en este cierre
-- Tipo: DECIMAL(10,2) DEFAULT 0.00
-- Posición: Después de total_recibido
ALTER TABLE cierres ADD COLUMN total_vuelto DECIMAL(10,2) DEFAULT 0.00 AFTER total_recibido;

-- 3.5. Campo: total_compra
-- Descripción: Total del costo de compra de productos vendidos
-- Tipo: DECIMAL(10,2) DEFAULT 0.00
-- Posición: Después de total_vuelto
ALTER TABLE cierres ADD COLUMN total_compra DECIMAL(10,2) DEFAULT 0.00 AFTER total_vuelto;

-- 3.6. Campo: total_ganancia
-- Descripción: Ganancia total (venta - compra) en este cierre
-- Tipo: DECIMAL(10,2) DEFAULT 0.00
-- Posición: Después de total_compra
ALTER TABLE cierres ADD COLUMN total_ganancia DECIMAL(10,2) DEFAULT 0.00 AFTER total_compra;

-- 3.7. Campo: estado
-- Descripción: Estado del cierre (1=Activo, 0=Inactivo/Anulado)
-- Tipo: INT DEFAULT 1
-- Posición: Después de creado_en
ALTER TABLE cierres ADD COLUMN estado INT DEFAULT 1 AFTER creado_en;

-- ════════════════════════════════════════════════════════════════════════════════
-- VERIFICACIÓN DE CAMBIOS
-- ════════════════════════════════════════════════════════════════════════════════

-- Verifica campos agregados en VENTAS:
-- SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='ventas' 
--        AND COLUMN_NAME IN ('fechaPago');

-- Verifica campos agregados en PRODUCTOS:
-- SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='productos' 
--        AND COLUMN_NAME IN ('fechaVencimiento');

-- Verifica campos agregados en CIERRES:
-- SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='cierres' 
--        AND COLUMN_NAME IN ('total_pagado', 'total_pendiente', 'total_pendientes_cobrados',
--                            'total_vuelto', 'total_compra', 'total_ganancia', 'estado');

-- ════════════════════════════════════════════════════════════════════════════════
-- IMPACTO EN EL CÓDIGO PHP
-- ════════════════════════════════════════════════════════════════════════════════
-- 
-- guardar_venta.php:
--   - Ahora guardará fechaPago al registrar venta pagada
--   - Evitará errores en la línea: UPDATE ventas SET fechaPago = NOW()
--
-- guardar_producto_inventario.php:
--   - Ahora registrará fechaVencimiento de productos
--   - Usado para control de stock con vencimiento
--
-- guardar_cierre.php:
--   - Insertará todos los totales de venta en cierres
--   - Registrará estado = 1 para cierre activo
--   - Usará total_pagado, total_pendiente, total_pendientes_cobrados,
--     total_vuelto, total_compra, total_ganancia
--
-- actualizar_estado_pago.php:
--   - Usará fechaPago al cambiar venta a pagado
--
-- obtener_historial.php:
--   - Podrá filtrar y mostrar fechaPago
--

-- ════════════════════════════════════════════════════════════════════════════════
-- RESUMEN DE CAMBIOS
-- ════════════════════════════════════════════════════════════════════════════════
-- 
-- Total de campos agregados: 10
-- 
-- Tabla VENTAS: +1 campo
--   ✓ fechaPago (DATETIME NULL)
--
-- Tabla PRODUCTOS: +1 campo
--   ✓ fechaVencimiento (DATE NULL)
--
-- Tabla CIERRES: +8 campos
--   ✓ total_pagado (DECIMAL)
--   ✓ total_pendiente (DECIMAL)
--   ✓ total_pendientes_cobrados (DECIMAL)
--   ✓ total_vuelto (DECIMAL)
--   ✓ total_compra (DECIMAL)
--   ✓ total_ganancia (DECIMAL)
--   ✓ estado (INT)
--
-- Tablas sin cambios (ya correctas):
--   ✓ CLIENTES - Todos los campos OK
--   ✓ USUARIOS - Todos los campos OK
--   ✓ DETALLEVENTA - Todos los campos OK
--   ✓ CATEGORIA - Todos los campos OK
--
-- ════════════════════════════════════════════════════════════════════════════════
