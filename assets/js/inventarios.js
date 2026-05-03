let inventarioBusquedaTimer = null;
let inventarioBusquedaSecuencia = 0;

function urlInventarioBusqueda(params){
    const query = new URLSearchParams(params).toString();
    return `../controllers/buscar_producto_inventario.php?${query}`;
}

function buscarProductoInventario(params){
    return fetch(urlInventarioBusqueda(params))
        .then(res => res.json())
        .then(json => {
            if(!json.ok){
                throw new Error(json.error || "No se pudo buscar el producto");
            }
            return json.existe ? json.producto : null;
        });
}

function mostrarMensajeInventario(texto, clase = "nuevo"){
    const mensaje = document.getElementById("mensajeInventario");
    if(mensaje){
        mensaje.className = `mensaje-inventario ${clase}`;
        mensaje.textContent = texto;
    }
}

function obtenerFormInventario(){
    return document.getElementById("formInventario");
}

function setCamposCrearVisibles(visible){
    document.querySelectorAll("#formInventario .campo-crear").forEach(campo => {
        campo.classList.toggle("oculto", !visible);
        campo.querySelectorAll("input, select").forEach(input => {
            input.disabled = !visible;
        });
    });
}

function mostrarFormularioInventario(){
    const form = obtenerFormInventario();
    if(form){
        form.classList.remove("oculto");
    }
}

function ocultarFormularioInventario(){
    const form = obtenerFormInventario();
    if(form){
        form.classList.add("oculto");
    }
}

function limpiarCamposCrear(form){
    ["nombre", "descripcion", "categoriaID", "precioCompra", "precioVenta", "fechaVencimiento"].forEach(nombre => {
        const campo = form.elements[nombre];
        if(campo){
            campo.value = "";
        }
    });
}

function cargarProductoExistente(producto){
    const form = obtenerFormInventario();
    if(!form) return;

    mostrarFormularioInventario();
    setCamposCrearVisibles(false);
    limpiarCamposCrear(form);

    form.elements.productoID.value = producto.productoID || "";
    form.elements.modo.value = "actualizar_stock";
    form.elements.codigo.value = producto.codigo || "";
    form.elements.stock.value = "";

    const ficha = document.getElementById("productoEncontrado");
    const nombre = document.getElementById("productoEncontradoNombre");
    const detalle = document.getElementById("productoEncontradoDetalle");
    const stock = document.getElementById("productoEncontradoStock");

    if(ficha) ficha.classList.remove("oculto");
    if(nombre) nombre.textContent = producto.nombre || "";
    if(detalle){
        detalle.textContent = `Codigo: ${producto.codigo || "-"} | Precio venta: S/ ${Number(producto.precioVenta || 0).toFixed(2)} | Vence: ${producto.fechaVencimiento || "-"}`;
    }
    if(stock) stock.textContent = `Stock actual: ${producto.stock || 0}`;

    const labelStock = document.getElementById("labelStock");
    if(labelStock){
        labelStock.textContent = "Nuevo stock";
    }

    const btn = form.querySelector("button[type='submit']");
    if(btn){
        btn.textContent = "Actualizar stock";
    }

    form.elements.stock.value = producto.stock || 0;
    form.elements.stock.focus();
    mostrarMensajeInventario("Producto encontrado. Edita el stock y guarda los cambios.", "existente");
}

function prepararProductoNuevo(codigo){
    const form = obtenerFormInventario();
    if(!form) return;

    mostrarFormularioInventario();
    setCamposCrearVisibles(true);
    limpiarCamposCrear(form);

    form.elements.productoID.value = "";
    form.elements.modo.value = "crear";
    form.elements.codigo.value = codigo;
    form.elements.stock.value = "";

    const ficha = document.getElementById("productoEncontrado");
    if(ficha) ficha.classList.add("oculto");

    const labelStock = document.getElementById("labelStock");
    if(labelStock){
        labelStock.textContent = "Cantidad / Stock inicial";
    }

    const btn = form.querySelector("button[type='submit']");
    if(btn){
        btn.textContent = "Guardar producto";
    }

    form.elements.nombre.focus();
    mostrarMensajeInventario("Producto no encontrado. Completa los datos para agregarlo.", "nuevo");
}

function limpiarInventario(){
    const form = obtenerFormInventario();
    const buscador = document.getElementById("codigoBusquedaInventario");
    if(form){
        form.reset();
        form.elements.productoID.value = "";
        form.elements.modo.value = "crear";
        form.elements.codigo.value = "";
    }
    if(buscador){
        buscador.value = "";
        buscador.focus();
    }
    const ficha = document.getElementById("productoEncontrado");
    if(ficha) ficha.classList.add("oculto");
    setCamposCrearVisibles(false);
    ocultarFormularioInventario();
    mostrarMensajeInventario("Ingresa un codigo para buscar el producto.", "nuevo");
}

function buscarCodigoInventario(){
    const buscador = document.getElementById("codigoBusquedaInventario");
    const codigo = buscador ? buscador.value.trim() : "";
    if(!codigo){
        limpiarInventario();
        return;
    }

    const secuenciaActual = ++inventarioBusquedaSecuencia;
    mostrarMensajeInventario("Buscando producto...", "nuevo");

    buscarProductoInventario({codigo})
        .then(producto => {
            if(secuenciaActual !== inventarioBusquedaSecuencia) return;
            if(producto){
                cargarProductoExistente(producto);
            } else {
                prepararProductoNuevo(codigo);
            }
        })
        .catch(err => {
            if(secuenciaActual !== inventarioBusquedaSecuencia) return;
            ocultarFormularioInventario();
            mostrarMensajeInventario("Error al buscar producto: " + err.message, "nuevo");
        });
}

document.addEventListener("input", function(e){
    if(e.target.id !== "codigoBusquedaInventario") return;

    clearTimeout(inventarioBusquedaTimer);
    inventarioBusquedaTimer = setTimeout(buscarCodigoInventario, 350);
});

document.addEventListener("keydown", function(e){
    if(e.target.id !== "codigoBusquedaInventario" || e.key !== "Enter") return;
    e.preventDefault();
    clearTimeout(inventarioBusquedaTimer);
    buscarCodigoInventario();
});

document.addEventListener("click", function(e){
    if(e.target.id === "btnBuscarInventario"){
        buscarCodigoInventario();
        return;
    }

    const btnStock = e.target.closest(".btn-editar-stock");
    if(!btnStock) return;

    mostrarMensajeInventario("Cargando producto...", "nuevo");
    buscarProductoInventario({productoID: btnStock.dataset.productoId})
        .then(producto => {
            if(producto){
                const buscador = document.getElementById("codigoBusquedaInventario");
                if(buscador) buscador.value = producto.codigo || "";
                cargarProductoExistente(producto);
                document.querySelector(".inventario-buscador")?.scrollIntoView({behavior: "smooth", block: "start"});
            }
        })
        .catch(err => {
            mostrarMensajeInventario("Error al cargar producto: " + err.message, "nuevo");
        });
});

document.addEventListener("reset", function(e){
    const form = e.target.closest("#formInventario");
    if(!form) return;

    setTimeout(limpiarInventario, 0);
});

document.addEventListener("submit", function(e){
    const form = e.target.closest("#formInventario");
    if(!form) return;

    e.preventDefault();

    const btn = form.querySelector("button[type='submit']");
    const data = Object.fromEntries(new FormData(form).entries());
    data.modo = form.elements.modo.value;
    data.codigo = form.elements.codigo.value;

    if(btn){
        btn.disabled = true;
            btn.textContent = data.modo === "actualizar_stock" ? "Actualizando..." : "Guardando...";
    }

    fetch("../controllers/guardar_producto_inventario.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(json => {
        if(json.ok){
            alert(json.message || "Inventario actualizado");
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
            btn.textContent = data.modo === "actualizar_stock" ? "Actualizar stock" : "Guardar producto";
        }
    });
});

document.addEventListener("DOMContentLoaded", function(){
    setCamposCrearVisibles(false);
    ocultarFormularioInventario();
});
