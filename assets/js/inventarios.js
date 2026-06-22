var inventarioBusquedaTimer = null;
var inventarioBusquedaSecuencia = 0;
window.inventariosEventosRegistrados = window.inventariosEventosRegistrados || false;

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
            return json;
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

function recargarInventarios(panel = "productos"){
    sessionStorage.setItem("inventarioPanelActivo", panel);
    if(typeof cargarPagina === "function"){
        cargarPagina("inventarios.php");
    } else {
        location.reload();
    }
}

function mostrarMensajeCategoria(texto, clase = "nuevo"){
    const mensaje = document.getElementById("mensajeCategoriaInventario");
    if(mensaje){
        mensaje.className = `mensaje-inventario ${clase}`;
        mensaje.textContent = texto;
    }
}

function activarPanelInventario(panel){
    const panelActivo = ["productos", "kardex", "categorias"].includes(panel) ? panel : "productos";
    sessionStorage.setItem("inventarioPanelActivo", panelActivo);

    document.querySelectorAll(".inventario-tab").forEach(btn => {
        btn.classList.toggle("activo", btn.dataset.inventarioPanel === panelActivo);
    });

    const paneles = {
        productos: document.getElementById("panelProductosInventario"),
        kardex: document.getElementById("panelKardexInventario"),
        categorias: document.getElementById("panelCategoriasInventario")
    };

    Object.entries(paneles).forEach(([nombre, elemento]) => {
        if(!elemento) return;
        const activo = nombre === panelActivo;
        elemento.classList.toggle("oculto", !activo);
        elemento.classList.toggle("activo", activo);
    });

    if(panelActivo !== "productos"){
        alternarAlertasInventario(false);
    }
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

function ocultarResultadosInventario(){
    const contenedor = document.getElementById("resultadosInventario");
    if(!contenedor) return;
    contenedor.innerHTML = "";
    contenedor.classList.add("oculto");
}

function alternarAlertasInventario(forzarVisible = null){
    const panel = document.getElementById("panelAlertasInventario");
    const boton = document.getElementById("btnAlertasInventario");
    if(!panel || !boton) return;

    const visible = forzarVisible === null ? panel.classList.contains("oculto") : Boolean(forzarVisible);
    panel.classList.toggle("oculto", !visible);
    boton.classList.toggle("activo", visible);
    boton.setAttribute("aria-expanded", visible ? "true" : "false");
}

function mostrarResultadosInventario(productos){
    const contenedor = document.getElementById("resultadosInventario");
    if(!contenedor) return;

    contenedor.innerHTML = "";

    productos.forEach(producto => {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "resultado-inventario";

        const nombre = document.createElement("strong");
        nombre.textContent = producto.nombre || "";

        const detalle = document.createElement("span");
        detalle.textContent = `Codigo: ${producto.codigo || "-"} | Stock: ${producto.stock || 0} | Vence: ${producto.fechaVencimiento || "-"}`;

        boton.appendChild(nombre);
        boton.appendChild(detalle);
        boton.addEventListener("click", () => {
            const buscador = document.getElementById("codigoBusquedaInventario");
            if(buscador){
                buscador.value = producto.codigo || producto.nombre || "";
            }
            ocultarResultadosInventario();
            cargarProductoExistente(producto);
        });

        contenedor.appendChild(boton);
    });

    contenedor.classList.remove("oculto");
}

function mostrarMensajeKardex(texto, clase = "nuevo"){
    const mensaje = document.getElementById("mensajeKardex");
    if(!mensaje) return;

    mensaje.className = `mensaje-inventario ${clase}`;
    mensaje.textContent = texto;
}

function ocultarResultadosKardex(){
    const contenedor = document.getElementById("resultadosKardexProducto");
    if(!contenedor) return;

    contenedor.innerHTML = "";
    contenedor.classList.add("oculto");
}

function mostrarResultadosKardex(productos){
    const contenedor = document.getElementById("resultadosKardexProducto");
    if(!contenedor) return;

    contenedor.innerHTML = "";

    productos.forEach(producto => {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "resultado-inventario";

        const nombre = document.createElement("strong");
        nombre.textContent = producto.nombre || "";

        const detalle = document.createElement("span");
        detalle.textContent = `Codigo: ${producto.codigo || "-"} | Stock: ${producto.stock || 0} | Vence: ${producto.fechaVencimiento || "-"}`;

        boton.appendChild(nombre);
        boton.appendChild(detalle);
        boton.addEventListener("click", () => {
            const buscador = document.getElementById("codigoBusquedaKardex");
            if(buscador){
                buscador.value = producto.codigo || producto.nombre || "";
            }
            ocultarResultadosKardex();
            cargarKardexProducto(producto.productoID);
        });

        contenedor.appendChild(boton);
    });

    contenedor.classList.remove("oculto");
}

function formatearFechaKardex(valor){
    if(!valor) return "-";

    const partes = String(valor).split(" ");
    const fecha = partes[0] || "";
    const hora = partes[1] ? partes[1].slice(0, 5) : "";
    const f = fecha.split("-");
    if(f.length !== 3) return valor;

    return `${f[2]}-${f[1]}-${f[0]}${hora ? " " + hora : ""}`;
}

function textoConceptoKardex(valor){
    return String(valor || "-").replace(/_/g, " ");
}

function moneyKardex(valor){
    if(valor === null || valor === undefined || valor === "") return "-";
    return "S/ " + (parseFloat(valor) || 0).toFixed(2);
}

function limpiarKardex(texto = "Sin producto seleccionado."){
    document.getElementById("resumenKardex")?.classList.add("oculto");
    const tabla = document.getElementById("tablaKardex");
    if(tabla){
        tabla.innerHTML = `<tr><td colspan="10">${texto}</td></tr>`;
    }
}

function renderKardex(data){
    const producto = data.producto || {};
    const resumen = data.resumen || {};
    const movimientos = Array.isArray(data.movimientos) ? data.movimientos : [];
    const resumenEl = document.getElementById("resumenKardex");
    if(!resumenEl) return;

    resumenEl.classList.remove("oculto");
    document.getElementById("kardexProductoNombre").textContent = producto.nombre || "-";
    document.getElementById("kardexProductoDetalle").textContent = `Codigo: ${producto.codigo || "-"} | Categoria: ${producto.categoria || "-"} | Vence: ${producto.fechaVencimiento || "-"}`;
    document.getElementById("kardexTotalEntradas").textContent = resumen.totalEntradas ?? 0;
    document.getElementById("kardexTotalSalidas").textContent = resumen.totalSalidas ?? 0;
    document.getElementById("kardexSaldo").textContent = resumen.saldoKardex ?? 0;
    document.getElementById("kardexStockActual").textContent = resumen.stockActual ?? 0;

    const tabla = document.getElementById("tablaKardex");
    if(!tabla) return;

    tabla.innerHTML = "";

    if(movimientos.length === 0){
        tabla.innerHTML = "<tr><td colspan=\"10\">Sin movimientos registrados.</td></tr>";
        return;
    }

    movimientos.forEach(mov => {
        const tr = document.createElement("tr");
        const referencia = mov.referenciaTipo
            ? `${mov.referenciaTipo}${mov.referenciaID ? " #" + mov.referenciaID : ""}`
            : "-";

        [
            formatearFechaKardex(mov.fecha),
            textoConceptoKardex(mov.concepto),
            referencia,
            mov.cantidadEntrada || 0,
            mov.cantidadSalida || 0,
            mov.saldoAnterior || 0,
            mov.saldoNuevo || 0,
            moneyKardex(mov.costoUnitario),
            moneyKardex(mov.precioUnitario),
            mov.observacion || "-"
        ].forEach(valor => {
            const td = document.createElement("td");
            td.textContent = valor;
            tr.appendChild(td);
        });

        tabla.appendChild(tr);
    });

    const coincide = resumen.coincideStock !== false;
    mostrarMensajeKardex(
        coincide
            ? "Kardex cargado correctamente."
            : "Kardex cargado. El saldo no coincide con el stock actual.",
        coincide ? "existente" : "nuevo"
    );
}

function cargarKardexProducto(productoID){
    const id = parseInt(productoID) || 0;
    if(id <= 0){
        mostrarMensajeKardex("Seleccione un producto.", "nuevo");
        return;
    }

    mostrarMensajeKardex("Cargando kardex...", "nuevo");

    fetch(`../controllers/obtener_kardex_producto.php?productoID=${encodeURIComponent(id)}`)
        .then(res => res.json())
        .then(json => {
            if(!json.ok){
                throw new Error(json.error || "No se pudo cargar el kardex");
            }
            renderKardex(json);
        })
        .catch(err => {
            limpiarKardex("No se pudo cargar el kardex.");
            mostrarMensajeKardex("Error: " + err.message, "nuevo");
        });
}

function buscarCodigoKardex(){
    const buscador = document.getElementById("codigoBusquedaKardex");
    const codigo = buscador ? buscador.value.trim() : "";
    if(!codigo){
        ocultarResultadosKardex();
        limpiarKardex();
        mostrarMensajeKardex("Selecciona un producto para ver sus entradas, salidas y saldo.", "nuevo");
        return;
    }

    mostrarMensajeKardex("Buscando producto...", "nuevo");
    buscarProductoInventario({codigo})
        .then(respuesta => {
            if(respuesta.multiple && Array.isArray(respuesta.productos)){
                limpiarKardex("Selecciona un producto de la lista.");
                mostrarResultadosKardex(respuesta.productos);
                mostrarMensajeKardex("Se encontraron varios productos.", "existente");
                return;
            }

            ocultarResultadosKardex();

            if(respuesta.existe && respuesta.producto){
                cargarKardexProducto(respuesta.producto.productoID);
            } else {
                limpiarKardex("Producto no encontrado.");
                mostrarMensajeKardex("Producto no encontrado.", "nuevo");
            }
        })
        .catch(err => {
            limpiarKardex("No se pudo buscar el producto.");
            mostrarMensajeKardex("Error al buscar producto: " + err.message, "nuevo");
        });
}

function limpiarCamposCrear(form){
    ["nombre", "descripcion", "categoriaID", "precioCompra", "precioVenta"].forEach(nombre => {
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
    form.elements.fechaVencimiento.value = producto.fechaVencimiento || "";
    form.elements.precioVenta.value = Number(producto.precioVenta || 0).toFixed(2);

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
        btn.textContent = "Actualizar stock, vencimiento y precio";
    }

    form.elements.stock.value = producto.stock || 0;
    form.elements.stock.focus();
    mostrarMensajeInventario("Producto encontrado. Edita el stock, la fecha de vencimiento o el precio de venta y guarda los cambios.", "existente");
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
    form.elements.fechaVencimiento.value = "";

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
    ocultarResultadosInventario();
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
    mostrarMensajeInventario("Ingresa un codigo o nombre y presiona Enter.", "nuevo");
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
        .then(respuesta => {
            if(secuenciaActual !== inventarioBusquedaSecuencia) return;

            if(respuesta.multiple && Array.isArray(respuesta.productos)){
                ocultarFormularioInventario();
                mostrarResultadosInventario(respuesta.productos);
                mostrarMensajeInventario("Se encontraron varios productos. Selecciona uno de la lista.", "existente");
                return;
            }

            ocultarResultadosInventario();

            if(respuesta.existe && respuesta.producto){
                cargarProductoExistente(respuesta.producto);
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

function registrarEventosInventario(){
if(window.inventariosEventosRegistrados) return;
window.inventariosEventosRegistrados = true;

document.addEventListener("input", function(e){
    if(e.target.id !== "codigoBusquedaInventario") return;

    clearTimeout(inventarioBusquedaTimer);
    inventarioBusquedaSecuencia++;
    ocultarResultadosInventario();

    if(e.target.value.trim() === ""){
        limpiarInventario();
        return;
    }

    ocultarFormularioInventario();
    mostrarMensajeInventario("Termina de escribir y presiona Enter o Buscar.", "nuevo");
});

document.addEventListener("input", function(e){
    if(e.target.id !== "codigoBusquedaKardex") return;

    ocultarResultadosKardex();
    limpiarKardex("Presiona Enter o Buscar para consultar el kardex.");
    mostrarMensajeKardex("Presiona Enter o Buscar para consultar el kardex.", "nuevo");
});

document.addEventListener("keydown", function(e){
    if(e.target.id !== "codigoBusquedaInventario" || e.key !== "Enter") return;
    e.preventDefault();
    clearTimeout(inventarioBusquedaTimer);
    buscarCodigoInventario();
});

document.addEventListener("keydown", function(e){
    if(e.target.id !== "codigoBusquedaKardex" || e.key !== "Enter") return;
    e.preventDefault();
    buscarCodigoKardex();
});

document.addEventListener("click", function(e){
    const tab = e.target.closest(".inventario-tab");
    if(tab){
        activarPanelInventario(tab.dataset.inventarioPanel || "productos");
        return;
    }

    if(e.target.closest("#btnAlertasInventario")){
        activarPanelInventario("productos");
        alternarAlertasInventario();
        return;
    }

    if(e.target.closest("#btnCerrarAlertasInventario")){
        alternarAlertasInventario(false);
        return;
    }

    if(e.target.id === "btnBuscarInventario"){
        buscarCodigoInventario();
        return;
    }

    if(e.target.id === "btnBuscarKardex"){
        buscarCodigoKardex();
        return;
    }

    const btnEliminarCategoria = e.target.closest(".btn-eliminar-categoria");
    if(btnEliminarCategoria){
        eliminarCategoriaInventario(btnEliminarCategoria);
        return;
    }

    const btnKardex = e.target.closest(".btn-ver-kardex");
    if(btnKardex){
        activarPanelInventario("kardex");
        const buscador = document.getElementById("codigoBusquedaKardex");
        const codigo = btnKardex.closest("tr")?.children[1]?.textContent.trim() || "";
        if(buscador) buscador.value = codigo;
        ocultarResultadosKardex();
        cargarKardexProducto(btnKardex.dataset.productoId);
        document.getElementById("panelKardexInventario")?.scrollIntoView({behavior: "smooth", block: "start"});
        return;
    }

    const btnStock = e.target.closest(".btn-editar-stock");
    if(!btnStock) return;

    mostrarMensajeInventario("Cargando producto...", "nuevo");
    buscarProductoInventario({productoID: btnStock.dataset.productoId})
        .then(respuesta => {
            if(respuesta.existe && respuesta.producto){
                const buscador = document.getElementById("codigoBusquedaInventario");
                if(buscador) buscador.value = respuesta.producto.codigo || "";
                ocultarResultadosInventario();
                cargarProductoExistente(respuesta.producto);
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
    const formCategoria = e.target.closest("#formCategoriaInventario");
    if(formCategoria){
        e.preventDefault();
        guardarCategoriaInventario(formCategoria);
        return;
    }

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
            recargarInventarios();
            return;
        }

        alert("Error: " + json.error);
    })
    .catch(err => alert("Error de servidor: " + err))
    .finally(() => {
        if(btn){
            btn.disabled = false;
            btn.textContent = data.modo === "actualizar_stock" ? "Actualizar stock, vencimiento y precio" : "Guardar producto";
        }
    });
});
}

function inicializarInventarios(){
    setCamposCrearVisibles(false);
    ocultarFormularioInventario();
    activarPanelInventario(sessionStorage.getItem("inventarioPanelActivo") || "productos");
}

window.inicializarInventarios = inicializarInventarios;
registrarEventosInventario();

if(document.readyState === "loading"){
    document.addEventListener("DOMContentLoaded", inicializarInventarios);
} else {
    inicializarInventarios();
}

function guardarCategoriaInventario(form){
    const btn = form.querySelector("button[type='submit']");
    const nombre = String(form.elements.nombre.value || "").trim();

    if(!nombre){
        mostrarMensajeCategoria("Ingrese el nombre de la categoria.", "nuevo");
        form.elements.nombre.focus();
        return;
    }

    if(btn){
        btn.disabled = true;
        btn.textContent = "Guardando...";
    }

    fetch("../controllers/guardar_categoria_inventario.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({accion: "crear", nombre})
    })
    .then(res => res.json())
    .then(json => {
        if(json.ok){
            alert(json.message || "Categoria guardada");
            recargarInventarios("categorias");
            return;
        }

        mostrarMensajeCategoria(json.error || "No se pudo guardar la categoria.", "existente");
    })
    .catch(err => mostrarMensajeCategoria("Error de servidor: " + err, "existente"))
    .finally(() => {
        if(btn){
            btn.disabled = false;
            btn.textContent = "Guardar categoria";
        }
    });
}

function eliminarCategoriaInventario(btn){
    const categoriaID = parseInt(btn.dataset.categoriaId) || 0;
    const nombre = btn.dataset.categoriaNombre || "esta categoria";

    if(categoriaID <= 0) return;
    if(!confirm(`Eliminar categoria "${nombre}"?`)) return;

    btn.disabled = true;
    btn.textContent = "Eliminando...";

    fetch("../controllers/guardar_categoria_inventario.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({accion: "eliminar", categoriaID})
    })
    .then(res => res.json())
    .then(json => {
        if(json.ok){
            alert(json.message || "Categoria eliminada");
            recargarInventarios("categorias");
            return;
        }

        alert("Error: " + json.error);
    })
    .catch(err => alert("Error de servidor: " + err))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = "Eliminar";
    });
}
