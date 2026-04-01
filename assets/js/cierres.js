document.addEventListener("DOMContentLoaded", () => {
    const fisicos = document.querySelectorAll('.fisico');
    const totalFisicoElem = document.getElementById('totalFisico');
    const btnGuardar = document.getElementById('guardar-cierre');

    function formatearNumero(valor){
        return parseFloat(valor || 0).toFixed(2);
    }

    function actualizarObservaciones(){
        let sumaFisico = 0;

        fisicos.forEach(input => {
            const fila = input.closest('tr');

            // ✅ USAR TOTAL PAGADO
            const totalPagado = parseFloat(
                fila.querySelector('.total-pagado').textContent
            ) || 0;

            let valorFisico = parseFloat(input.value) || 0;

            input.value = formatearNumero(valorFisico);

            const obs = fila.querySelector('.obs');

            if(valorFisico === totalPagado){
                obs.textContent = "Correcto"; 
                obs.style.color = "green";
            } 
            else if(valorFisico < totalPagado){
                obs.textContent = "Faltante"; 
                obs.style.color = "red";
            } 
            else {
                obs.textContent = "Sobrante"; 
                obs.style.color = "orange";
            }

            // 🔥 BONUS: mostrar diferencia
            let diferencia = valorFisico - totalPagado;
            if(diferencia !== 0){
                obs.textContent += " (" + diferencia.toFixed(2) + ")";
            }

            sumaFisico += valorFisico;
        });

        totalFisicoElem.textContent = "S/ " + formatearNumero(sumaFisico);
    }

    // Inicializar
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

            cierresData.push({ 
                tipopago: input.dataset.tipopago, 
                fisico 
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
});