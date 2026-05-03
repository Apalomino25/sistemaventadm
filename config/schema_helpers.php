<?php
function columnaExiste(PDO $conn, string $tabla, string $columna): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tabla, $columna]);
    return (int)$stmt->fetchColumn() > 0;
}

function asegurarColumna(PDO $conn, string $tabla, string $columna, string $definicion): void {
    if(!columnaExiste($conn, $tabla, $columna)){
        $conn->exec("ALTER TABLE `$tabla` ADD COLUMN `$columna` $definicion");
    }
}

function asegurarColumnasPagos(PDO $conn): void {
    asegurarColumna($conn, 'ventas', 'fechaPago', 'DATETIME NULL AFTER estadoPago');
    asegurarColumna($conn, 'cierres', 'total_pendientes_cobrados', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_pendiente');

    $conn->exec("
        UPDATE ventas
        SET fechaPago = fecha
        WHERE estadoPago = 'pagado'
          AND fechaPago IS NULL
    ");
}

function asegurarColumnasInventario(PDO $conn): void {
    asegurarColumna($conn, 'productos', 'fechaVencimiento', 'DATE NULL AFTER stock');
}

function obtenerClienteGeneral(PDO $conn): array {
    $stmt = $conn->prepare("
        SELECT *
        FROM clientes
        WHERE LOWER(nombre) IN ('cliente general', 'general')
        ORDER BY clienteID ASC
        LIMIT 1
    ");
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if($cliente){
        return $cliente;
    }

    $insert = $conn->prepare("
        INSERT INTO clientes (nombre, tipoDocumento, numeroDocumento, telefono, direccion)
        VALUES ('Cliente general', 'DNI', '00000000', '', '')
    ");
    $insert->execute();

    $stmt = $conn->prepare("SELECT * FROM clientes WHERE clienteID = ? LIMIT 1");
    $stmt->execute([$conn->lastInsertId()]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
