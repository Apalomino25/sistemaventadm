console.log("JS CARGADO");
document.addEventListener("DOMContentLoaded", () => {

    const fisicos = document.querySelectorAll('.fisico');
    const totalFisicoElem = document.getElementById('totalFisico');
    const btnGuardar = document.getElementById('guardar-cierre');

    // 🔹 Formatear número
    function formatearNumero(valor){
        return parseFloat(valor || 0).toFixed(2);
    }

    // 🔹 Limpiar texto de dinero (S/ 1,234.50 → 1234.50)

    function limpiarNumero(texto){
    return parseFloat(
        texto
            .replace(/\s/g, '')      // 🔥 elimina TODOS los espacios (incluye raros)
            .replace('S/','')
            .replace(/,/g,'')
    ) || 0;
    }



function actualizarObservaciones(){
    let sumaFisico = 0;
    let sumaDiferencia = 0; // 🔹 acumulador total de diferencias

    fisicos.forEach(input => {
        const fila = input.closest('tr');
        if(!fila) return;

        const totalPagadoElem = fila.querySelector('.total-pagado');
        const obs = fila.querySelector('.obs');
        const difTd = fila.querySelector('.diferencia'); // 🔹 columna diferencia

        if(!totalPagadoElem || !obs || !difTd) return;

        const totalPagado = limpiarNumero(totalPagadoElem.textContent);
        const valorFisico = parseFloat(input.value) || 0;

        const diferencia = valorFisico - totalPagado;

        // 🔹 Actualiza Observación
        if(Math.abs(diferencia) < 0.01){
            obs.textContent = "Correcto"; 
            obs.style.color = "green";
        } 
        else if(diferencia < 0){
            obs.textContent = `Faltante`; 
            obs.style.color = "red";
        } 
        else {
            obs.textContent = `Sobrante`; 
            obs.style.color = "orange";
        }

        // 🔹 Actualiza la columna Diferencia
        difTd.textContent = "S/ " + diferencia.toFixed(2);

        sumaFisico += valorFisico;
        sumaDiferencia += diferencia;
    });

    if(totalFisicoElem){
        totalFisicoElem.textContent = "S/ " + sumaFisico.toFixed(2);
    }

    // 🔹 Actualizar total Diferencia en <tfoot>
    const totalDifElem = document.getElementById('totalDiferencia');
    if(totalDifElem){
        totalDifElem.textContent = "S/ " + sumaDiferencia.toFixed(2);
    }
}

    // 🔹 Inicializar
    actualizarObservaciones();

    // 🔹 Eventos inputs
    fisicos.forEach(input => {

        input.addEventListener('input', () => {
            // solo números y un punto
            input.value = input.value
                .replace(/[^\d.]/g,'')
                .replace(/(\..*)\./g, '$1'); // evita múltiples puntos

            actualizarObservaciones();
        });

        input.addEventListener('blur', () => {
            input.value = formatearNumero(input.value);
            actualizarObservaciones();
        });

    });

    // 🔹 Guardar cierre
    if(btnGuardar){
        btnGuardar.addEventListener('click', () => {

            const cierresData = [];

            fisicos.forEach(input => {
                cierresData.push({ 
                    tipopago: input.dataset.tipopago, 
                    fisico: parseFloat(input.value) || 0
                });
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
    }

    // 🔹 Botones de acciones
    document.querySelectorAll('.imprimir-cierre').forEach(btn => {
        btn.addEventListener('click', () => {
            window.open(`imprimir_cierre.php?id=${btn.dataset.id}`, '_blank');
        });
    });

    document.querySelectorAll('.ver-cierre').forEach(btn => {
        btn.addEventListener('click', () => {
            window.location.href = `ver_cierre.php?id=${btn.dataset.id}`;
        });
    });

    document.querySelectorAll('.editar-cierre').forEach(btn => {
        btn.addEventListener('click', () => {
            window.location.href = `editar_cierre.php?id=${btn.dataset.id}`;
        });
    });

});