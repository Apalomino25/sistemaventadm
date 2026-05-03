# 🎯 INICIO RÁPIDO - Configurar Base de Datos

## 👀 ¿Por dónde empiezo?

Elige tu nivel:

### 🚀 **Quiero empezar YA (5 minutos)**
→ Ve a: **INSTALACION_BD.md → Sección "Paso 1"**

### 📚 **Quiero entender qué hay (10 minutos)**  
→ Lee: **README_BD.md** (este archivo tiene todo resumido)

### 🔍 **Quiero ver la estructura en detalle (20 minutos)**
→ Abre: **DIAGRAMA_RELACIONES.md** (visual + explicación)

### 📋 **Quiero un checklist paso a paso**
→ Usa: **CHECKLIST.md** (marca cada paso mientras avanzas)

---

## ⚡ Instalación en 3 Clics

### 1. Abre PhpMyAdmin
```
URL: http://localhost/phpmyadmin
Usuario: root
Contraseña: (dejar vacío en XAMPP)
```

### 2. Copia y Pega
```
Archivo: database_schema_simple.sql
→ Selecciona TODO el contenido
→ Pégalo en la pestaña "SQL" de PhpMyAdmin
→ Haz clic en "Ejecutar"
```

### 3. ¡Listo!
```
En el panel izquierdo verás:
sistemaventasdm
  ├─ usuarios
  ├─ categoria
  ├─ productos
  ├─ clientes
  ├─ ventas
  ├─ detalleventa
  └─ cierres
```

---

## 📂 Estructura de Archivos de Documentación

```
c:\sistemaventadm\
│
├── database_schema.sql ..................... Script SQL (con comentarios)
├── database_schema_simple.sql ............. Script SQL (sin comentarios)
│
├── README_BD.md ............................ 📌 LÉEME PRIMERO
├── INSTALACION_BD.md ....................... Guía paso a paso
├── CHECKLIST.md ............................ Verificación interactiva
├── DIAGRAMA_RELACIONES.md .................. Explicación visual
│
└── CONFIG IMPORTANTE:
    ├── config/conexion.php ................. ⚠️ Verifica los datos
    └── config/schema_helpers.php ........... Define helpers de BD
```

---

## ✅ Checklist Ultra Rápido

- [ ] Abre PhpMyAdmin (http://localhost/phpmyadmin)
- [ ] Copia `database_schema_simple.sql` al completo
- [ ] Pégalo en la pestaña SQL
- [ ] Haz clic en Ejecutar
- [ ] Recarga la página (F5)
- [ ] Verifica que existan 7 tablas
- [ ] Abre `conexion.php` y verifica datos de conexión
- [ ] Prueba login con: admin / admin123

**Si todo funciona: ✅ YA ESTÁ LISTO**

---

## 🔑 Credenciales Iniciales

```
Usuario:     admin
Contraseña:  admin123
BD:          sistemaventasdm
Host:        localhost
```

---

## 📱 Las 7 Tablas en 30 segundos

| Tabla | Qué almacena |
|-------|-------------|
| **usuarios** | Cuentas del sistema (admin, vendedores) |
| **categoria** | Tipos de productos (ropa, electrónica, etc.) |
| **productos** | Inventario (código, precio, stock) |
| **clientes** | Clientes que compran |
| **ventas** | Cada compra registrada |
| **detalleventa** | Qué productos se vendieron en cada venta |
| **cierres** | Cierre diario de cajas |

---

## 🛠️ Cambios Necesarios en tu Código

✅ **NINGUNO** - Tu código ya está optimizado

El archivo `schema_helpers.php` hace:
- Agregar columnas si no existen ✔️
- Buscar cliente general ✔️
- Llenar datos por defecto ✔️

**Ahora ya todo existe** en la BD, así que es más rápido.

---

## ❓ Preguntas Frecuentes

### P: ¿Ejecuto el archivo con comentarios o sin comentarios?
**R:** Sin comentarios (`database_schema_simple.sql`) - es más fácil

### P: ¿Qué pasa si ya tengo una BD?
**R:** El script usa `CREATE TABLE IF NOT EXISTS` - no borra nada existente

### P: ¿Puedo usar línea de comandos en vez de PhpMyAdmin?
**R:** Sí: `mysql -u root -p sistemaventadm < database_schema_simple.sql`

### P: ¿Dónde cambio la contraseña de admin?
**R:** En PhpMyAdmin → tabla usuarios → cambias el campo "contrasenia"

### P: ¿Por qué hay un "Cliente general"?
**R:** Para registrar ventas sin cliente específico (es requisito del código)

### P: ¿Puedo eliminar datos que vienen por defecto?
**R:** No elimines "Cliente general" ni "prodeditable" - los usa tu código

---

## 📚 Documentos Disponibles

| Archivo | Para qué | Tiempo |
|---------|----------|--------|
| **README_BD.md** | Resumen ejecutivo | 5 min |
| **INSTALACION_BD.md** | Pasos detallados | 10 min |
| **CHECKLIST.md** | Verificación paso a paso | 15 min |
| **DIAGRAMA_RELACIONES.md** | Estructura visual | 20 min |
| **DATABASE_SCHEMA.md** | Documentación técnica | 30 min |

---

## 🚨 Si Algo Falla

### Error: "Database already exists"
✓ Normal, ignora. El script es seguro.

### Error: "Access denied"
```
Verifica en conexion.php:
$user = "root";        ← Tu usuario MySQL
$password = "";        ← Tu contraseña (vacío en XAMPP)
$dbname = "sistemaventasdm";
```

### Error: "Table doesn't exist"
```
Ejecuta nuevamente database_schema_simple.sql
Todo el contenido, no solo una parte.
```

### No se ve la BD creada
```
Presiona F5 para recargar PhpMyAdmin
O haz clic en "Actualizar" en el panel
```

---

## 🎯 Próximos Pasos

1. ✅ **Instala la BD** (ahora)
2. ✅ **Prueba el login** (admin/admin123)
3. ⏳ **Carga productos** (después)
4. ⏳ **Crea más usuarios** (después)
5. ⏳ **Haz backups** (regularmente)

---

## 💾 Hacer Backup Rápido

### En PhpMyAdmin:
1. Haz clic en `sistemaventasdm`
2. Pestaña "Exportar"
3. Haz clic en "Ir"

### En Línea de Comandos:
```
mysqldump -u root -p sistemaventasdm > mi_backup.sql
```

---

## 📞 Resumen

✅ Tienes **2 scripts SQL** listos para ejecutar
✅ Tienes **5 documentos** explicativos
✅ Tienes **7 tablas** correctamente diseñadas
✅ Tienes **datos iniciales** precargados

**Solo falta: Ejecutar el script en tu MySQL**

---

## 🎓 Ahora que tienes la BD

Tu código puede:
- ✅ Registrar usuarios
- ✅ Guardar productos con vencimiento
- ✅ Registrar ventas (pagadas o pendientes)
- ✅ Hacer cierre diario
- ✅ Consultar historial
- ✅ Anular ventas

**Sin errores de "tabla no existe"** 🎉

---

## 🚀 ¡EMPIZA AQUÍ!

1. Abre PhpMyAdmin
2. Copia `database_schema_simple.sql`
3. Ejecuta el script
4. ¡Listo! Ya puedes usar tu sistema

**Tiempo total: 5 minutos** ⏱️

---

**Para dudas detalladas: Abre README_BD.md**  
**Para procedimientos paso a paso: Abre INSTALACION_BD.md**  
**Para verificar todo: Abre CHECKLIST.md**  
**Para entender la estructura: Abre DIAGRAMA_RELACIONES.md**

