<?php
session_start();
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../config/schema_helpers.php";
require_once __DIR__ . "/../config/auth_helpers.php";

requerirAdministradorHtml();
asegurarColumnasInventario($conn);

$diasAlertaVencimiento = 30;

$categorias = $conn->query("
    SELECT categoriaID, nombre
    FROM categoria
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

$productos = $conn->query("
    SELECT p.productoID, p.codigo, p.nombre, p.descripcion, p.precioCompra,
           p.precioVenta, p.stock, p.fechaVencimiento, p.estado,
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

    if($fecha && $dias < 0){
        return ['clase' => 'vencido', 'texto' => 'Vencido'];
    }

    if($fecha && $dias <= $diasAlerta){
        return ['clase' => 'vence-pronto', 'texto' => 'Vence en ' . $dias . ' dias'];
    }

    if($stock < 5){
        return ['clase' => 'stock-bajo', 'texto' => 'Stock bajo'];
    }

    return ['clase' => 'ok', 'texto' => 'OK'];
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
            <p>Registra cada ingreso como un lote independiente cuando cambie codigo, costo, precio o vencimiento. El POS lista primero los lotes que vencen antes cuando buscas por nombre.</p>
        </div>
    </div>

    <?php if(!empty($alertasInventario)): ?>
    <div class="alertas-inventario">
        <h3>Alertas de inventario</h3>
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
    </div>
    <?php endif; ?>

    <form id="formInventario" class="inventario-form">
        <div class="campo">
            <label>Codigo de barra</label>
            <input type="text" name="codigo" autocomplete="off" required>
        </div>

        <div class="campo">
            <label>Nombre producto</label>
            <input type="text" name="nombre" list="productosExistentes" required>
            <datalist id="productosExistentes">
                <?php foreach($nombresProductos as $nombreProducto): ?>
                    <option value="<?= htmlspecialchars($nombreProducto) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="campo">
            <label>Descripcion</label>
            <input type="text" name="descripcion">
        </div>

        <div class="campo">
            <label>Categoria</label>
            <select name="categoriaID" required>
                <option value="">Seleccione</option>
                <?php foreach($categorias as $cat): ?>
                    <option value="<?= intval($cat['categoriaID']) ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label>Precio compra</label>
            <input type="number" name="precioCompra" min="0.01" step="0.01" required>
        </div>

        <div class="campo">
            <label>Precio venta</label>
            <input type="number" name="precioVenta" min="0.01" step="0.01" required>
        </div>

        <div class="campo">
            <label>Cantidad / Stock</label>
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
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="../assets/js/inventarios.js?v=<?= filemtime(__DIR__ . '/../assets/js/inventarios.js') ?>"></script>
</body>
</html>
