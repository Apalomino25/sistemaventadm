<?php
session_start();
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../config/schema_helpers.php";
require_once __DIR__ . "/../config/auth_helpers.php";

requerirAdministradorHtml();
asegurarColumnasInventario($conn);

// Alertas de vencimiento: 2 semanas (14 días) de anticipación
$diasAlertaVencimiento = 14;

$categorias = $conn->query("
    SELECT categoriaID, nombre
    FROM categoria
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

$categoriasGestion = $conn->query("
    SELECT c.categoriaID, c.nombre, COUNT(p.productoID) AS total_productos
    FROM categoria c
    LEFT JOIN productos p ON p.categoriaID = c.categoriaID
    GROUP BY c.categoriaID, c.nombre
    ORDER BY c.nombre
")->fetchAll(PDO::FETCH_ASSOC);

$productos = $conn->query("
    SELECT p.productoID, p.codigo, p.nombre, p.descripcion, p.precioCompra,
           p.precioVenta, p.stock, p.fechaVencimiento, p.estado, p.categoriaID,
           c.nombre AS categoria
    FROM productos p
    LEFT JOIN categoria c ON c.categoriaID = p.categoriaID
    ORDER BY
        CASE WHEN p.estado = 1 AND p.stock > 0 AND p.fechaVencimiento IS NOT NULL THEN 0 ELSE 1 END,
        p.fechaVencimiento ASC,
        p.productoID DESC
    LIMIT 80
")->fetchAll(PDO::FETCH_ASSOC);

$stmtAlertas = $conn->prepare("
    SELECT p.productoID, p.codigo, p.nombre, p.descripcion, p.stock, p.fechaVencimiento,
           DATEDIFF(p.fechaVencimiento, CURDATE()) AS dias_para_vencer
    FROM productos p
    WHERE p.estado = 1
      AND p.stock > 0
      AND (
          p.stock < 5
          OR (p.fechaVencimiento IS NOT NULL AND p.fechaVencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
      )
    ORDER BY
        CASE WHEN p.fechaVencimiento IS NULL THEN 1 ELSE 0 END,
        p.fechaVencimiento ASC,
        p.stock ASC,
        p.nombre ASC
");
$stmtAlertas->execute([$diasAlertaVencimiento]);
$alertasInventario = $stmtAlertas->fetchAll(PDO::FETCH_ASSOC);

$nombresProductos = $conn->query("
    SELECT DISTINCT nombre
    FROM productos
    WHERE estado = 1
    ORDER BY nombre
")->fetchAll(PDO::FETCH_COLUMN);

function estadoLoteInventario(array $producto, int $diasAlerta): array {
    $stock = intval($producto['stock'] ?? 0);
    $fecha = $producto['fechaVencimiento'] ?? null;
    $dias = null;

    if($fecha){
        $hoy = new DateTimeImmutable('today');
        $vence = new DateTimeImmutable($fecha);
        $dias = (int)$hoy->diff($vence)->format('%r%a');
    }

    // Vencido
    if($fecha && $dias < 0){
        return ['clase' => 'vencido', 'texto' => '⚠️ VENCIDO'];
    }

    // Vence próximamente (dentro de 2 semanas)
    if($fecha && $dias >= 0 && $dias <= $diasAlerta){
        return ['clase' => 'vence-pronto', 'texto' => "⏰ Vence en {$dias} días"];
    }

    // Stock bajo (menos de 5 unidades)
    if($stock < 5){
        return ['clase' => 'stock-bajo', 'texto' => '📦 Stock bajo (' . $stock . ')'];
    }

    return ['clase' => 'ok', 'texto' => '✓ OK'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventarios</title>
    <link rel="stylesheet" href="../assets/css/pos.css?v=<?= filemtime(__DIR__ . '/../assets/css/pos.css') ?>">
    <link rel="stylesheet" href="../assets/css/inventarios.css?v=<?= filemtime(__DIR__ . '/../assets/css/inventarios.css') ?>">
</head>
<body>
<div class="inventario-container">
    <div class="inventario-header">
        <div>
            <h2>Inventarios</h2>
            
        </div>
        <button type="button"
                class="alertas-toggle <?= !empty($alertasInventario) ? 'con-alertas' : 'sin-alertas' ?>"
                id="btnAlertasInventario"
                aria-expanded="false"
                aria-controls="panelAlertasInventario">
            <span class="alertas-toggle-icon" aria-hidden="true"></span>
            <span>Alertas</span>
            <b><?= count($alertasInventario) ?></b>
        </button>
    </div>

    <div class="inventario-tabs" role="tablist" aria-label="Opciones de inventario">
        <button type="button" class="inventario-tab activo" data-inventario-panel="productos">Productos</button>
        <button type="button" class="inventario-tab" data-inventario-panel="categorias">Categorias</button>
    </div>

    <section id="panelProductosInventario" class="inventario-panel activo">
        <div class="inventario-buscador">
            <label for="codigoBusquedaInventario">Buscar por codigo o nombre</label>
            <div class="inventario-buscador-linea">
                <input type="text" id="codigoBusquedaInventario" autocomplete="off" placeholder="Escanea el codigo o escribe el nombre">
                <button type="button" id="btnBuscarInventario">Buscar</button>
            </div>
        </div>

        <div id="mensajeInventario" class="mensaje-inventario nuevo">
            Ingresa un codigo o nombre y presiona Enter.
        </div>
        <div id="resultadosInventario" class="resultados-inventario oculto"></div>

        <form id="formInventario" class="inventario-form inventario-form-resultado oculto">
        <input type="hidden" name="productoID" id="productoID">
        <input type="hidden" name="modo" id="modoInventario" value="crear">
        <input type="hidden" name="codigo" id="codigoInventario">

        <div id="productoEncontrado" class="producto-encontrado oculto">
            <strong id="productoEncontradoNombre"></strong>
            <span id="productoEncontradoDetalle"></span>
            <span id="productoEncontradoStock"></span>
        </div>

        <div class="campo campo-crear">
            <label>Nombre producto</label>
            <input type="text" name="nombre" list="productosExistentes" required>
            <datalist id="productosExistentes">
                <?php foreach($nombresProductos as $nombreProducto): ?>
                    <option value="<?= htmlspecialchars($nombreProducto) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="campo campo-crear">
            <label>Descripcion</label>
            <input type="text" name="descripcion">
        </div>

        <div class="campo campo-crear">
            <label>Categoria</label>
            <select name="categoriaID" required>
                <option value="">Seleccione</option>
                <?php foreach($categorias as $cat): ?>
                    <option value="<?= intval($cat['categoriaID']) ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo campo-crear">
            <label>Precio compra</label>
            <input type="number" name="precioCompra" min="0.01" step="0.01" required>
        </div>

        <div class="campo campo-crear">
            <label>Precio venta</label>
            <input type="number" name="precioVenta" min="0.01" step="0.01" required>
        </div>

        <div class="campo">
            <label id="labelStock">Cantidad / Stock inicial</label>
            <input type="number" name="stock" min="1" step="1" required>
        </div>

        <div class="campo">
            <label>Fecha vencimiento</label>
            <input type="date" name="fechaVencimiento" required>
        </div>

        <div class="acciones-form">
            <button type="submit">Guardar ingreso</button>
            <button type="reset" class="secundario">Limpiar</button>
        </div>
        </form>

        <div class="alertas-inventario oculto" id="panelAlertasInventario">
        <div class="alertas-panel-header">
            <h3>Alertas de inventario</h3>
            <button type="button" id="btnCerrarAlertasInventario">Cerrar</button>
        </div>
        <?php if(!empty($alertasInventario)): ?>
        <div class="alertas-grid">
            <?php foreach($alertasInventario as $alerta): ?>
                <?php
                    $dias = $alerta['dias_para_vencer'];
                    $estaVencido = $dias !== null && intval($dias) < 0;
                    $vencePronto = $dias !== null && intval($dias) >= 0 && intval($dias) <= $diasAlertaVencimiento;
                    $stockBajo = intval($alerta['stock']) < 5;
                ?>
                <div class="alerta-card <?= $estaVencido ? 'critica' : ($vencePronto ? 'vence' : 'stock') ?>">
                    <strong><?= htmlspecialchars($alerta['nombre']) ?></strong>
                    <span>Codigo: <?= htmlspecialchars($alerta['codigo']) ?> | Stock: <?= intval($alerta['stock']) ?></span>
                    <span>Vence: <?= $alerta['fechaVencimiento'] ? date('d-m-Y', strtotime($alerta['fechaVencimiento'])) : '-' ?></span>
                    <b>
                        <?php if($estaVencido): ?>
                            Producto vencido
                        <?php elseif($vencePronto): ?>
                            Vence en <?= intval($dias) ?> dias
                        <?php endif; ?>
                        <?= ($vencePronto || $estaVencido) && $stockBajo ? ' / ' : '' ?>
                        <?= $stockBajo ? 'Stock menor a 5' : '' ?>
                    </b>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alertas-vacio">Sin productos por vencer ni stock bajo.</div>
        <?php endif; ?>
        </div>

        <h3>Productos y lotes para venta FEFO</h3>
        <table class="tabla-ventas">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Codigo</th>
                    <th>Producto</th>
                    <th>Categoria</th>
                    <th>Compra</th>
                    <th>Venta</th>
                    <th>Stock</th>
                    <th>Vencimiento</th>
                    <th>Alerta</th>
                    <th>Estado</th>
                    <th>Accion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($productos as $p): ?>
                <?php $estadoLote = estadoLoteInventario($p, $diasAlertaVencimiento); ?>
                <tr class="fila-<?= htmlspecialchars($estadoLote['clase']) ?>">
                    <td><?= intval($p['productoID']) ?></td>
                    <td><?= htmlspecialchars($p['codigo']) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($p['nombre']) ?></strong><br>
                        <span><?= htmlspecialchars($p['descripcion'] ?? '') ?></span>
                    </td>
                    <td><?= htmlspecialchars($p['categoria'] ?? '') ?></td>
                    <td>S/ <?= number_format($p['precioCompra'], 2) ?></td>
                    <td>S/ <?= number_format($p['precioVenta'], 2) ?></td>
                    <td><?= intval($p['stock']) ?></td>
                    <td><?= $p['fechaVencimiento'] ? date('d-m-Y', strtotime($p['fechaVencimiento'])) : '-' ?></td>
                    <td><span class="badge-alerta <?= htmlspecialchars($estadoLote['clase']) ?>"><?= htmlspecialchars($estadoLote['texto']) ?></span></td>
                    <td><?= intval($p['estado']) === 1 ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <button type="button"
                                class="btn-editar-stock"
                                data-producto-id="<?= intval($p['productoID']) ?>">
                            Stock / Vence
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section id="panelCategoriasInventario" class="inventario-panel oculto">
        <form id="formCategoriaInventario" class="categoria-form">
            <div class="campo">
                <label>Nueva categoria</label>
                <input type="text" name="nombre" autocomplete="off" maxlength="80" required placeholder="Nombre de categoria">
            </div>
            <button type="submit">Guardar categoria</button>
        </form>

        <div id="mensajeCategoriaInventario" class="mensaje-inventario nuevo">
            Crea categorias para ordenar los productos del inventario.
        </div>

        <h3>Categorias</h3>
        <table class="tabla-ventas tabla-categorias">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Categoria</th>
                    <th>Productos</th>
                    <th>Accion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($categoriasGestion as $cat): ?>
                <tr>
                    <td><?= intval($cat['categoriaID']) ?></td>
                    <td><?= htmlspecialchars($cat['nombre']) ?></td>
                    <td><?= intval($cat['total_productos']) ?></td>
                    <td>
                        <button type="button"
                                class="btn-eliminar-categoria"
                                data-categoria-id="<?= intval($cat['categoriaID']) ?>"
                                data-categoria-nombre="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= intval($cat['total_productos']) > 0 ? 'disabled title="No se puede eliminar si tiene productos"' : '' ?>>
                            Eliminar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<script src="../assets/js/inventarios.js?v=<?= filemtime(__DIR__ . '/../assets/js/inventarios.js') ?>"></script>
</body>
</html>
