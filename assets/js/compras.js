window.comprasEventosRegistrados = window.comprasEventosRegistrados || false;

function mostrarMensajeCompra(texto, clase = "nuevo"){
    const mensaje = document.getElementById("mensajeCompra");
    if(!mensaje) return;

    mensaje.className = `mensaje-compra ${clase}`;
    mensaje.textContent = texto;
}

function obtenerFormCompra(){
    return document.getElementById("formCompra");
}

function ocultarResultadosCompra(){
    const contenedor = document.getElementById("resultadosCompraProducto");
    if(!contenedor) return;

    contenedor.innerHTML = "";
    contenedor.classList.add("oculto");
}

function mostrarFormularioCompra(){
    obtenerFormCompra()?.classList.remove("oculto");
}

function ocultarFormularioCompra(){
    obtenerFormCompra()?.classList.add("oculto");
}

function formatearMontoCompra(valor){
    return (parseFloat(valor) || 0).toFixed(2);
}

function actualizarTotalCompra(){
    const form = obtenerFormCompra();
    const total = document.getElementById("compraSubtotal");
    if(!form || !total) return;

    const cantidad = parseInt(form.elements.cantidad?.value) || 0;
    const precioCompra = parseFloat(form.elements.precioCompra?.value) || 0;
    total.textContent = "S/ " + (cantidad * precioCompra).toFixed(2);
}

function buscarProductoParaCompra(valor){
    const q = String(valor || "").trim();
    if(!q){
        ocultarFormularioCompra();
        ocultarResultadosCompra();
        mostrarMensajeCompra("Selecciona un producto para registrar la compra.");
        return;
    }

    mostrarMensajeCompra("Buscando producto...");

    fetch("../controllers/buscar_producto_inventario.php?codigo=" + encodeURIComponent(q))
        .then(res => res.json())
        .then(json => {
            if(!json.ok){
                throw new Error(json.error || "No se pudo buscar el producto");
            }

            if(json.multiple && Array.isArray(json.productos)){
                ocultarFormularioCompra();
                mostrarResultadosCompra(json.productos);
                mostrarMensajeCompra("Selecciona un producto de la lista.", "existente");
                return;
            }

            ocultarResultadosCompra();

            if(json.existe && json.producto){
                seleccionarProductoCompra(json.producto);
                return;
            }

            ocultarFormularioCompra();
            mostrarMensajeCompra("Producto no encontrado. Registralo primero en Inventarios.", "existente");
        })
        .catch(err => {
            ocultarFormularioCompra();
            mostrarMensajeCompra("Error al buscar producto: " + err.message, "existente");
        });
}

function mostrarResultadosCompra(productos){
    const contenedor = document.getElementById("resultadosCompraProducto");
    if(!contenedor) return;

    contenedor.innerHTML = "";

    productos.forEach(producto => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "resultado-compra";

        const nombre = document.createElement("strong");
        nombre.textContent = producto.nombre || "";

        const detalle = document.createElement("span");
        detalle.textContent = `Codigo: ${producto.codigo || "-"} | Stock: ${producto.stock || 0} | Compra: S/ ${formatearMontoCompra(producto.precioCompra)} | Venta: S/ ${formatearMontoCompra(producto.precioVenta)}`;

        btn.appendChild(nombre);
        btn.appendChild(detalle);
        btn.addEventListener("click", () => {
            document.getElementById("busquedaCompraProducto").value = producto.codigo || producto.nombre || "";
            ocultarResultadosCompra();
            seleccionarProductoCompra(producto);
        });

        contenedor.appendChild(btn);
    });

    contenedor.classList.remove("oculto");
}

function seleccionarProductoCompra(producto){
    const form = obtenerFormCompra();
    if(!form) return;

    ["cantidad", "precioCompra", "precioVenta", "fechaVencimiento", "proveedor", "comprobante", "observacion"].forEach(nombre => {
        if(form.elements[nombre]){
            form.elements[nombre].value = "";
        }
    });

    form.elements.productoID.value = producto.productoID || "";
    form.elements.precioCompra.value = formatearMontoCompra(producto.precioCompra);
    form.elements.precioVenta.value = formatearMontoCompra(producto.precioVenta);
    form.elements.fechaVencimiento.value = producto.fechaVencimiento || "";
    form.elements.cantidad.value = "";

    const nombre = document.getElementById("productoCompraNombre");
    const detalle = document.getElementById("productoCompraDetalle");
    const stock = document.getElementById("productoCompraStock");

    if(nombre) nombre.textContent = producto.nombre || "";
    if(detalle){
        detalle.textContent = `Codigo: ${producto.codigo || "-"} | Vence: ${producto.fechaVencimiento || "-"} | Venta actual: S/ ${formatearMontoCompra(producto.precioVenta)}`;
    }
    if(stock) stock.textContent = `Stock actual: ${producto.stock || 0}`;

    mostrarFormularioCompra();
    actualizarTotalCompra();
    mostrarMensajeCompra("Producto seleccionado. Ingresa la cantidad comprada.", "existente");
    form.elements.cantidad.focus();
}

function limpiarCompra(){
    const form = obtenerFormCompra();
    if(form){
        form.reset();
        form.elements.productoID.value = "";
    }
    const buscador = document.getElementById("busquedaCompraProducto");
    if(buscador){
        buscador.value = "";
    }
    ocultarResultadosCompra();
    ocultarFormularioCompra();
    actualizarTotalCompra();
    mostrarMensajeCompra("Selecciona un producto para registrar la compra.");
}

function guardarCompra(form){
    const btn = form.querySelector("button[type='submit']");
    const data = Object.fromEntries(new FormData(form).entries());

    if(!data.productoID){
        mostrarMensajeCompra("Selecciona un producto.", "existente");
        return;
    }

    if(btn){
        btn.disabled = true;
        btn.textContent = "Registrando...";
    }

    fetch("../controllers/guardar_compra.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(data)
    })
        .then(res => res.json())
        .then(json => {
            if(json.ok){
                alert(json.message || "Compra registrada");
                if(typeof cargarPagina === "function"){
                    cargarPagina("compras.php");
                } else {
                    location.reload();
                }
                return;
            }

            mostrarMensajeCompra(json.error || "No se pudo registrar la compra.", "existente");
        })
        .catch(err => mostrarMensajeCompra("Error de servidor: " + err, "existente"))
        .finally(() => {
            if(btn){
                btn.disabled = false;
                btn.textContent = "Registrar compra";
            }
        });
}

function registrarEventosCompras(){
    if(window.comprasEventosRegistrados) return;
    window.comprasEventosRegistrados = true;

    document.addEventListener("keydown", function(e){
        if(e.target.id !== "busquedaCompraProducto" || e.key !== "Enter") return;
        e.preventDefault();
        buscarProductoParaCompra(e.target.value);
    });

    document.addEventListener("click", function(e){
        if(e.target.closest("#btnBuscarCompraProducto")){
            buscarProductoParaCompra(document.getElementById("busquedaCompraProducto")?.value || "");
        }
    });

    document.addEventListener("input", function(e){
        if(e.target.id === "busquedaCompraProducto"){
            ocultarResultadosCompra();
            ocultarFormularioCompra();
            mostrarMensajeCompra("Presiona Enter o Buscar para seleccionar producto.");
            return;
        }

        if(e.target.closest("#formCompra")){
            actualizarTotalCompra();
        }
    });

    document.addEventListener("reset", function(e){
        if(!e.target.closest("#formCompra")) return;
        setTimeout(limpiarCompra, 0);
    });

    document.addEventListener("submit", function(e){
        const form = e.target.closest("#formCompra");
        if(!form) return;

        e.preventDefault();
        guardarCompra(form);
    });
}

function inicializarCompras(){
    ocultarResultadosCompra();
    ocultarFormularioCompra();
    actualizarTotalCompra();
    const buscador = document.getElementById("busquedaCompraProducto");
    if(buscador){
        buscador.focus();
    }
}

window.inicializarCompras = inicializarCompras;
registrarEventosCompras();

if(document.readyState === "loading"){
    document.addEventListener("DOMContentLoaded", inicializarCompras);
} else {
    inicializarCompras();
}
