
// Imprimir venta
function imprimirVenta(ventaID){
    const url = `http://localhost/sistemaventasDM/ticket.php?id=${ventaID}`;
    let iframe = document.createElement("iframe");
    iframe.style.display = "none";
    iframe.src = url;
    document.body.appendChild(iframe);

    iframe.onload = function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => document.body.removeChild(iframe), 1000);
    };
}

// Ver detalles de venta
function verDetalle(ventaID){
    fetch(`../controllers/ver_detalle.php?id=${ventaID}`)
        .then(res => res.text())
        .then(html => {
            // Mostrar en un modal o alert simple
            // Puedes personalizar tu modal en tu CSS/HTML
            let modal = document.createElement("div");
            modal.classList.add("modal-detalle");
            modal.innerHTML = `
                <div class="modal-content">
                    <span class="cerrar">&times;</span>
                    ${html}
                </div>
            `;
            document.body.appendChild(modal);

            modal.querySelector(".cerrar").addEventListener("click", () => {
                document.body.removeChild(modal);
            });
        })
        .catch(err => console.error("Error al ver detalle:", err));
}

// Anular venta (cambiar estado a inactivo)
function anularVenta(ventaID){
    if(!confirm("¿Seguro que deseas anular esta venta?")) return;

    fetch("../controllers/anular_venta.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({ventaID})
    })
    .then(res => res.json())
    .then(json => {
        if(json.ok){
            alert("Venta anulada correctamente");
            // Recargar historial
            cargarHistorialVentas();
        } else {
            alert("Error al anular venta: " + json.error);
        }
    })
    .catch(err => {
        console.error("Error al anular:", err);
        alert("Error del servidor");
    });
}



document.addEventListener("click", (e) => {

    // IMPRIMIR
    if (e.target.classList.contains("imprimir")) {
        const id = e.target.dataset.id;
        imprimirVenta(id);
    }

    // VER DETALLE
    if (e.target.classList.contains("ver")) {
        const id = e.target.dataset.id;
        verDetalle(id);
    }

    // ELIMINAR
    if (e.target.classList.contains("eliminar")) {
        const id = e.target.dataset.id;
        anularVenta(id);
    }

});