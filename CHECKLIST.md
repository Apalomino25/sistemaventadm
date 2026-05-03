# 📋 CHECKLIST - Configuración de Base de Datos

## Antes de Empezar

- [ ] MySQL está instalado en tu computadora
- [ ] MySQL está corriendo (servicio activo)
- [ ] Tienes acceso a PhpMyAdmin o MySQL Workbench
- [ ] Tu usuario MySQL está configurado (por defecto: root, sin contraseña en XAMPP)

---

## Paso 1: Crear la Base de Datos

- [ ] Abre PhpMyAdmin en http://localhost/phpmyadmin
- [ ] Inicia sesión con usuario: `root`
- [ ] Haz clic en la pestaña "SQL"
- [ ] Copia el contenido de `database_schema_simple.sql`
- [ ] Pégalo en el área de texto
- [ ] Haz clic en "Ejecutar"

**ó**

- [ ] Abre línea de comandos
- [ ] Ejecuta: `mysql -u root -p < database_schema_simple.sql`
- [ ] Presiona Enter (si no tiene contraseña, solo presiona Enter)

---

## Paso 2: Verificar Instalación

### En PhpMyAdmin:
- [ ] Actualiza la página (F5)
- [ ] En el panel izquierdo aparece `sistemaventasdm`
- [ ] Haz clic en `sistemaventasdm`
- [ ] Verifica que existan estas 7 tablas:
  - [ ] usuarios
  - [ ] categoria
  - [ ] productos
  - [ ] clientes
  - [ ] ventas
  - [ ] detalleventa
  - [ ] cierres

### Verificar datos iniciales:
- [ ] Haz clic en la tabla `usuarios`
- [ ] Deberías ver 1 usuario: admin
- [ ] Haz clic en la tabla `categoria`
- [ ] Deberías ver 1 categoría: prodeditable
- [ ] Haz clic en la tabla `clientes`
- [ ] Deberías ver 1 cliente: Cliente general

---

## Paso 3: Configurar Conexión PHP

- [ ] Abre el archivo: `config/conexion.php`
- [ ] Verifica estos valores:
  ```php
  $host = "localhost";           // ← Correcto
  $dbname = "sistemaventasdm";   // ← Correcto
  $user = "root";                // ← Usa tu usuario MySQL
  $password = "S0p0rt31994$";    // ← Usa tu contraseña (vacío en XAMPP)
  ```

- [ ] Si tu usuario es diferente, actualízalo
- [ ] Si no tiene contraseña, cambia a: `$password = "";`
- [ ] Guarda el archivo (Ctrl+S)

---

## Paso 4: Probar la Conexión

### Opción A: Desde el navegador
- [ ] Accede a tu aplicación: `http://localhost/sistemaventadm`
- [ ] Intenta iniciar sesión con:
  - Usuario: `admin`
  - Contraseña: `admin123`
- [ ] Si entras sin errores ✅ **¡Conexión OK!**

### Opción B: Crear archivo de prueba
- [ ] Crea un archivo `test_conexion.php` en la raíz:
  ```php
  <?php
  require_once "config/conexion.php";
  
  try {
      $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios");
      $stmt->execute();
      echo "✅ Conexión exitosa. Usuarios: " . $stmt->fetchColumn();
  } catch(PDOException $e){
      echo "❌ Error: " . $e->getMessage();
  }
  ```
- [ ] Abre en navegador: `http://localhost/sistemaventadm/test_conexion.php`
- [ ] Deberías ver: `✅ Conexión exitosa. Usuarios: 1`

---

## Paso 5: Estructura Resumida

✅ **7 Tablas Creadas:**

| Tabla | Función |
|-------|---------|
| **usuarios** | Almacena usuarios del sistema |
| **categoria** | Categorías de productos |
| **productos** | Inventario de productos |
| **clientes** | Datos de clientes |
| **ventas** | Registro de transacciones |
| **detalleventa** | Líneas de cada venta |
| **cierres** | Cierre diario de cajas |

---

## Paso 6: Campos Críticos (No pueden estar vacíos)

### Al crear un PRODUCTO:
```
- codigo: ABC123 (ÚNICO - código de barras)
- nombre: Producto X
- precioCompra: 10.00
- precioVenta: 15.00
- categoriaID: 1 (debe existir)
- stock: 50
```

### Al registrar una VENTA:
```
- clienteID: 1 o mayor
- usuarioID: 1 o mayor
- total: 100.00
- tipoPago: efectivo (o yape, plin, transferencia)
- estadoPago: pagado (o pendiente)
```

---

## 🆘 Troubleshooting

### ❌ "Error de conexión: SQLSTATE[HY000]"
- [ ] Verifica que MySQL está corriendo
- [ ] Abre Servicios y busca "MySQL"
- [ ] Haz clic en "Iniciar" si no está corriendo

### ❌ "Acceso denegado para usuario 'root'@'localhost'"
- [ ] Cambia `$user` y `$password` en `conexion.php`
- [ ] O usa: usuario = root, password = (vacío)

### ❌ "Base de datos 'sistemaventasdm' no existe"
- [ ] Ejecuta nuevamente `database_schema_simple.sql`
- [ ] Asegúrate de ejecutar TODO el script

### ❌ "Tabla 'usuarios' no existe"
- [ ] La base de datos existe pero las tablas no
- [ ] Ejecuta `database_schema_simple.sql` nuevamente

---

## ✅ Cuando TODO está listo:

- [x] Base de datos creada: `sistemaventasdm`
- [x] 7 tablas con estructura correcta
- [x] 3 registros iniciales (admin, categoría, cliente general)
- [x] Conexión PHP funcionando
- [x] Puedes iniciar sesión en la aplicación

**¡Tu sistema de ventas está listo!** 🎉

---

## Próximos Pasos Recomendados

1. [ ] Cambiar contraseña del admin a algo más seguro
2. [ ] Crear más categorías de productos
3. [ ] Cargar inventario de productos
4. [ ] Crear más usuarios según roles necesarios
5. [ ] Hacer backup regular de la BD

---

## 📞 Resumen de Archivos Creados

- ✅ **database_schema.sql** - Script detallado con comentarios
- ✅ **database_schema_simple.sql** - Script simple sin comentarios
- ✅ **DATABASE_SCHEMA.md** - Documentación completa de tablas y campos
- ✅ **INSTALACION_BD.md** - Guía paso a paso
- ✅ **CHECKLIST.md** - Este archivo (verificación rápida)

