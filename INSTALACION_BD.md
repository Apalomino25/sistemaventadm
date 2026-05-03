# 🚀 GUÍA RÁPIDA: Configurar Base de Datos

## Paso 1️⃣: Abre tu cliente MySQL

Elige una opción:

### Opción A: PhpMyAdmin (Recomendado para principiantes)
1. Abre `http://localhost/phpmyadmin`
2. Inicia sesión con usuario `root` (sin contraseña en XAMPP)

### Opción B: MySQL Workbench
1. Abre MySQL Workbench
2. Conecta con tu servidor local

### Opción C: Línea de comandos
1. Abre CMD/PowerShell
2. Escribe: `mysql -u root -p`
3. Ingresa tu contraseña

---

## Paso 2️⃣: Copia y Ejecuta el Script

### Si usas PhpMyAdmin:
1. Haz clic en "SQL" en la pestaña superior
2. Copia todo el contenido de `database_schema_simple.sql`
3. Pégalo en el área de texto
4. Haz clic en "Ejecutar" (botón azul abajo)

### Si usas MySQL Workbench:
1. Abre archivo → `database_schema_simple.sql`
2. Presiona `Ctrl+Shift+Enter` para ejecutar
3. O haz clic en el rayo ⚡ para ejecutar

### Si usas línea de comandos:
```bash
mysql -u root -p < "C:\ruta\a\database_schema_simple.sql"
```

---

## Paso 3️⃣: Verifica la Instalación

En PhpMyAdmin o MySQL Workbench, deberías ver:

```
Base de datos: sistemaventasdm
├── usuarios
├── categoria  
├── productos
├── clientes
├── ventas
├── detalleventa
└── cierres
```

Si aparecen todas las 7 tablas ✅ **¡Listo!**

---

## ✅ Datos de Acceso Predeterminados

```
Usuario: admin
Contraseña: admin123
Base de datos: sistemaventasdm
Host: localhost
Puerto: 3306
```

---

## 🆘 ¿Algo salió mal?

### Error: "Access denied for user 'root'@'localhost'"
- **Solución:** Cambia `root` en `conexion.php` por tu usuario MySQL
- O ejecuta sin contraseña: `mysql -u root < script.sql`

### Error: "Database 'sistemaventasdm' already exists"
- **Solución:** Es normal si ya existe. El script usa `CREATE TABLE IF NOT EXISTS`
- Puedes ignorar este error

### Error: "Table 'usuarios' doesn't exist"
- **Solución:** Vuelve a ejecutar el script SQL completo

### "Connection refused"
- **Solución:** Asegúrate que MySQL está corriendo:
  - En Windows: Abre Servicios y busca MySQL
  - En XAMPP: Haz clic en "Start" para Apache y MySQL

---

## 📝 Campos Obligatorios por Tabla

### usuarios
- usuario (UNIQUE)
- contrasenia
- rol
- nombre

### categoria
- nombre (UNIQUE)

### productos
- codigo (UNIQUE) ← Código de barras
- nombre
- categoriaID ← Debe existir en tabla categoria
- precioCompra
- precioVenta
- stock

### clientes
- nombre

### ventas
- clienteID ← Debe existir en tabla clientes
- usuarioID ← Debe existir en tabla usuarios
- total
- fecha (automática)

### detalleventa
- ventaID ← Debe existir en tabla ventas
- productoID ← Debe existir en tabla productos
- cantidad
- precioUnitario
- subtotal

### cierres
- fecha (UNIQUE)

---

## 🔍 Verificar Datos Iniciales

Ejecuta estas consultas para confirmar:

```sql
SELECT * FROM usuarios;
SELECT * FROM categoria;
SELECT * FROM clientes;
```

Deberías ver:
- 1 usuario (admin)
- 1 categoría (prodeditable)
- 1 cliente (Cliente general)

---

## 💾 Hacer Backup

### Desde PhpMyAdmin:
1. Selecciona la BD `sistemaventasdm`
2. Pestaña "Exportar"
3. Formato: SQL
4. Haz clic en "Ir"

### Desde línea de comandos:
```bash
mysqldump -u root -p sistemaventasdm > backup.sql
```

---

## 🔐 Nota Importante

⚠️ **Cambia la contraseña de admin en producción:**
```sql
UPDATE usuarios 
SET contrasenia = 'nueva_contraseña_segura' 
WHERE usuario = 'admin';
```

---

## 📞 Soporte

Si tienes errores, verifica:
1. ✅ MySQL está corriendo
2. ✅ Usuario y contraseña son correctos
3. ✅ El script SQL fue ejecutado completamente
4. ✅ El archivo `conexion.php` tiene datos correctos

