document.addEventListener("submit", function(e){
    const form = e.target.closest("#formInventario");
    if(!form) return;

    e.preventDefault();

    const btn = form.querySelector("button[type='submit']");
    const data = Object.fromEntries(new FormData(form).entries());

    if(btn){
        btn.disabled = true;
        btn.textContent = "Guardando...";
    }

    fetch("../controllers/guardar_producto_inventario.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(json => {
        if(json.ok){
            alert(json.message || "Producto registrado");
            if(typeof cargarPagina === "function"){
                cargarPagina("inventarios.php");
            } else {
                location.reload();
            }
            return;
        }

        alert("Error: " + json.error);
    })
    .catch(err => alert("Error de servidor: " + err))
    .finally(() => {
        if(btn){
            btn.disabled = false;
            btn.textContent = "Guardar ingreso";
        }
    });
});
