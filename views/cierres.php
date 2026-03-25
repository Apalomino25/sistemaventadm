<?php
require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Lima');
$hoy = date('Y-m-d');

try {
    // Traer totales por tipo de pago
    $stmt = $conn->prepare("
        SELECT tipopago, 
               SUM(total) AS total_ventas, 
               SUM(pago) AS total_recibido,
               SUM(vuelto) AS total_vuelto
        FROM ventas
        WHERE DATE(fecha) = :hoy
        GROUP BY tipopago
    ");
    $stmt->execute([':hoy' => $hoy]);
    $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Calcular sumas totales
$suma_total = 0;
$suma_recibido = 0;
$suma_vuelto = 0; 

foreach($cierres as $c){
    $suma_total += $c['total_ventas'];
    $suma_recibido += $c['total_recibido'];
    $suma_vuelto += $c['total_vuelto'];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cierre de Caja</title>
    <link rel="stylesheet" href="../assets/css/cierres.css">
</head>

<body>

<h2>Cierre del Día (<?php echo date('d-m-Y'); ?>)</h2>

<div class="cierres-container">
    <table id="tabla-cierres">
        <thead>
            <tr>
                <th>Tipo de Pago</th>
                <th>Total Ventas</th>
                <th>Total Recibido</th>
                <th>Total Vuelto</th>
                <th>Total Pendientes Pago</th>
                <th>Físico</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($cierres as $c): ?>
            <tr>
                <td><?php echo strtoupper($c['tipopago']); ?></td>
                <td>S/ <?php echo number_format($c['total_ventas'],2); ?></td>
                <td class="total-recibido"><?php echo $c['total_recibido']; ?></td>
                <td>
                    <input type="number" class="fisico" data-tipopago="<?php echo $c['tipopago']; ?>" step="0.01" min="0" value="<?php echo $c['total_recibido']; ?>">
                </td>
                <td><span class="obs" style="color:green">Correcto</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th>S/ <?php echo number_format($suma_total,2); ?></th>
                <th class="suma-recibido"><?php echo number_format($suma_recibido,2); ?></th>
                <th id="totalFisico">S/ <?php echo number_format($suma_recibido,2); ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <button id="guardar-cierre">Guardar Cierre</button>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const fisicos = document.querySelectorAll('.fisico');
    const totalFisicoElem = document.getElementById('totalFisico');
    const btnGuardar = document.getElementById('guardar-cierre');

    if (!fisicos.length || !totalFisicoElem || !btnGuardar) return;

    function formatearNumero(valor){
        return parseFloat(valor || 0).toFixed(2);
    }

    function actualizarObservaciones(){
        let sumaFisico = 0;

        fisicos.forEach(input => {
            const fila = input.closest('tr');
            const totalRecibido = parseFloat(fila.querySelector('.total-recibido').textContent) || 0;
            let valorFisico = parseFloat(input.value) || 0;

            input.value = formatearNumero(valorFisico);

            const obs = fila.querySelector('.obs');
            if(valorFisico === totalRecibido){
                obs.textContent = "Correcto"; obs.style.color = "green";
            } else if(valorFisico < totalRecibido){
                obs.textContent = "Faltante"; obs.style.color = "red";
            } else {
                obs.textContent = "Sobrante"; obs.style.color = "orange";
            }

            sumaFisico += valorFisico;
        });

        totalFisicoElem.textContent = "S/ " + formatearNumero(sumaFisico);
    }

    // Inicializar total y observaciones al cargar
    actualizarObservaciones();

    fisicos.forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^\d.]/g,'');
            actualizarObservaciones();
        });
        input.addEventListener('blur', actualizarObservaciones);
    });

    // Guardar cierre
    btnGuardar.addEventListener('click', () => {
        const cierresData = [];
        fisicos.forEach(input => {
            const fisico = parseFloat(input.value) || 0;
            cierresData.push({ tipopago: input.dataset.tipopago, fisico });
        });

        fetch('../controllers/guardar_cierre.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({cierres: cierresData})
        })
        .then(res => res.json())
        .then(res => {
            if(res.ok){
                alert('Cierre guardado correctamente');
                location.reload();
            } else {
                alert('Error: ' + res.error);
            }
        })
        .catch(err => alert('Error de servidor: ' + err));
    });
});
</script>

</body>
</html>