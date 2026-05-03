let enviando = false;
const CATEGORIA_PRECIO_EDITABLE_NOMBRE = "prodeditable";
const CATEGORIA_PRECIO_EDITABLE_ID = 11;
const MIN_CARACTERES_BUSQUEDA = 2;
let busquedaTimer = null;
let busquedaSecuencia = 0;
let clienteTimer = null;
let clienteGeneral = null;

// =======================
// POS PRINCIPAL
// =======================
window.iniciarPOS = function() {

    const inputCodigo = document.getElementById("codigo");
    const tabla = document.getElementById("tabla-ventas");
    const inputPago = document.getElementById("pago");
    const btnGrabar = document.getElementById("btnGrabar");
    const fecha = document.getElementById("fecha");
    const mensajePago = document.getElementById("msmPago");

    if(!inputCodigo) return;
    inputCodigo.focus();
    iniciarClientePOS();

    // Buscar producto al presionar Enter
    inputCodigo.addEventListener("keydown", function(e){
        if(e.key === "Enter"){
            e.preventDefault();
            clearTimeout(busquedaTimer);
            const busqueda = inputCodigo.value.trim();
            if(busqueda !== ""){
                ocultarResultadosBusqueda();
                buscarProducto(busqueda);
            }
        }

        if(e.key === "Escape"){
            ocultarResultadosBusqueda();
        }
    });

    inputCodigo.addEventListener("input", function(){
        const busqueda = inputCodigo.value.trim();
        clearTimeout(busquedaTimer);

        if(busqueda.length < MIN_CARACTERES_BUSQUEDA){
            ocultarResultadosBusqueda();
            return;
        }

        busquedaTimer = setTimeout(() => {
            buscarProductosEnVivo(busqueda);
        }, 250);
    });

    if(!window.posBusquedaDocumentListener){
        document.addEventListener("click", function(e){
            if(!e.target.closest(".busqueda")){
                ocultarResultadosBusqueda();
            }
        });
        window.posBusquedaDocumentListener = true;
    }

    // Validación y cálculo de pago al presionar Enter
    if (inputPago) {
        inputPago.addEventListener("keydown", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                const pago = parseFloat(inputPago.value) || 0;
                const totalNum = parseFloat(document.getElementById("total").value) || 0;

                if (pago < totalNum) {
                    mensajePago.textContent = "El pago es menor al total";
                    setTimeout(() => {
                        mensajePago.textContent = "";
                        inputPago.value = ""; 
                        document.getElementById("vuelto").value = ""; 
                    }, 2000);
                    return;
                }

                mensajePago.textContent = "";
                calcularVuelto();
                formatearPago();
            }
        });
    }

    if(btnGrabar){
        btnGrabar.addEventListener("click", guardarVenta);
    }

    // Fecha actual
    if(fecha){
        const hoy = new Date();
        const formato =
            String(hoy.getDate()).padStart(2,"0") + "-" +
            String(hoy.getMonth()+1).padStart(2,"0") + "-" +
            hoy.getFullYear();
        fecha.value = formato;
    }

    const tipoPagoSelect = document.getElementById("tipoPago");
    if(tipoPagoSelect){
        tipoPagoSelect.addEventListener("change", function(){
            actualizarCamposPago();
        });
    }

    const estadoPagoSelect = document.getElementById("estadoPago");
    if(estadoPagoSelect){
        estadoPagoSelect.addEventListener("change", () => {
            document.querySelectorAll(".estado-detalle-pago").forEach(select => {
                const fila = select.closest("tr");
                const subtotal = parseFloat(fila?.querySelector(".subtotal")?.textContent) || 0;
                const input = fila?.querySelector(".monto-detalle-pagado");
                if(input){
                    input.value = estadoPagoSelect.value === "pagado" ? subtotal.toFixed(2) : "0.00";
                    actualizarEstadoPagoFila(fila);
                }
            });
            actualizarCamposPago();
            actualizarPagoMixto();
        });
        actualizarCamposPago();
    }

    document.querySelectorAll(".pago-mixto").forEach(input => {
        input.addEventListener("input", actualizarPagoMixto);
        input.addEventListener("blur", () => {
            input.value = formatearMonto(input.value);
            actualizarPagoMixto();
        });
    });
};

// =======================
// FUNCIONES POS
// =======================
function buscarProducto(busqueda){
    fetch("../controllers/buscar_producto.php?codigo=" + encodeURIComponent(busqueda))
    .then(res => res.json())
    .then(data => {
        if(data.error){
            ocultarResultadosBusqueda();
            alert("Producto no encontrado");
            return;
        }

        if(data.multiple && Array.isArray(data.productos)){
            mostrarResultadosBusqueda(data.productos);
            return;
        }

         // 🔹 Verificar stock
            if(data.stock <= 0){
                ocultarResultadosBusqueda();
                alert("No hay stock disponible para este producto");
                return; // detener ejecución
            }
    
        ocultarResultadosBusqueda();
        agregarProductoTabla(data);
        const inputCodigo = document.getElementById("codigo");
        inputCodigo.value = "";
        inputCodigo.focus();
    })
    .catch(err => {
        console.error("Error al buscar producto:", err);
        alert("Error al buscar producto");
    });
}

function buscarProductosEnVivo(busqueda){
    const secuenciaActual = ++busquedaSecuencia;

    fetch("../controllers/buscar_producto.php?codigo=" + encodeURIComponent(busqueda))
    .then(res => res.json())
    .then(data => {
        if(secuenciaActual !== busquedaSecuencia){
            return;
        }

        const inputCodigo = document.getElementById("codigo");
        if(!inputCodigo || inputCodigo.value.trim() !== busqueda){
            return;
        }

        if(data.error){
            mostrarMensajeBusqueda("Sin resultados");
            return;
        }

        if(data.multiple && Array.isArray(data.productos)){
            mostrarResultadosBusqueda(data.productos);
            return;
        }

        mostrarResultadosBusqueda([data]);
    })
    .catch(err => {
        console.error("Error en busqueda en vivo:", err);
    });
}

function ocultarResultadosBusqueda(){
    const contenedor = document.getElementById("resultadosBusqueda");
    if(!contenedor) return;

    busquedaSecuencia++;
    contenedor.innerHTML = "";
    contenedor.classList.remove("activo");
}

function mostrarMensajeBusqueda(mensaje){
    const contenedor = document.getElementById("resultadosBusqueda");
    if(!contenedor) return;

    contenedor.innerHTML = "";

    const item = document.createElement("div");
    item.className = "resultado-producto sin-stock";
    item.textContent = mensaje;

    contenedor.appendChild(item);
    contenedor.classList.add("activo");
}

function mostrarResultadosBusqueda(productos){
    const contenedor = document.getElementById("resultadosBusqueda");
    if(!contenedor) return;

    contenedor.innerHTML = "";

    productos.forEach(prod => {
        const item = document.createElement("button");
        item.type = "button";
        item.className = "resultado-producto";

        if(Number(prod.stock) <= 0){
            item.classList.add("sin-stock");
        }

        const info = document.createElement("span");
        info.className = "resultado-info";

        const nombre = document.createElement("strong");
        nombre.textContent = prod.nombre || "";

        const descripcion = document.createElement("span");
        descripcion.textContent = prod.descripcion || "";

        const meta = document.createElement("span");
        meta.className = "resultado-meta";
        meta.textContent = `Cod: ${prod.codigo || "-"} | Stock: ${prod.stock} | Vence: ${prod.fechaVencimiento || "-"} | S/ ${formatearMonto(prod.precioVenta)}`;

        info.appendChild(nombre);
        info.appendChild(descripcion);
        info.appendChild(meta);

        const accion = document.createElement("span");
        accion.className = "resultado-accion";
        accion.textContent = Number(prod.stock) <= 0 ? "Sin stock" : "+";

        item.appendChild(info);
        item.appendChild(accion);

        item.addEventListener("click", function(){
            if(Number(prod.stock) <= 0){
                alert("No hay stock disponible para este producto");
                return;
            }

            agregarProductoTabla(prod);
            ocultarResultadosBusqueda();

            const inputCodigo = document.getElementById("codigo");
            inputCodigo.value = "";
            inputCodigo.focus();
        });

        contenedor.appendChild(item);
    });

    contenedor.classList.add("activo");
}

function productoPermiteEditarPrecio(prod){
    const categoriaNombre = String(prod.categoriaNombre || "").trim().toLowerCase();
    return categoriaNombre === CATEGORIA_PRECIO_EDITABLE_NOMBRE || Number(prod.categoriaID) === CATEGORIA_PRECIO_EDITABLE_ID;
}

function formatearMonto(valor){
    return (parseFloat(valor) || 0).toFixed(2);
}

function iniciarClientePOS(){
    const input = document.getElementById("clienteNombre");
    const idInput = document.getElementById("clienteID");
    const btnGeneral = document.getElementById("btnClienteGeneral");
    const btnNuevo = document.getElementById("btnNuevoCliente");
    const formNuevo = document.getElementById("formNuevoCliente");
    const btnCancelar = document.getElementById("btnCancelarCliente");

    if(!input || !idInput || input.dataset.iniciado === "1") return;
    input.dataset.iniciado = "1";
    clienteGeneral = {
        clienteID: idInput.value,
        nombre: input.value
    };

    input.addEventListener("input", () => {
        idInput.value = "";
        clearTimeout(clienteTimer);
        const q = input.value.trim();
        if(q.length < 2){
            ocultarResultadosClientes();
            return;
        }
        clienteTimer = setTimeout(() => buscarClientes(q), 250);
    });

    input.addEventListener("keydown", e => {
        if(e.key === "Escape"){
            ocultarResultadosClientes();
        }
    });

    if(btnGeneral){
        btnGeneral.addEventListener("click", usarClienteGeneral);
    }

    if(btnNuevo && formNuevo){
        btnNuevo.addEventListener("click", () => {
            formNuevo.classList.toggle("oculto");
            const nombre = formNuevo.querySelector("[name='nombre']");
            if(nombre){
                nombre.value = input.value.trim() && !idInput.value ? input.value.trim() : "";
                nombre.focus();
            }
        });
    }

    if(btnCancelar && formNuevo){
        btnCancelar.addEventListener("click", () => {
            formNuevo.reset();
            formNuevo.classList.add("oculto");
        });
    }
}

function seleccionarCliente(cliente){
    const input = document.getElementById("clienteNombre");
    const idInput = document.getElementById("clienteID");
    if(!input || !idInput) return;

    input.value = cliente.nombre || "";
    idInput.value = cliente.clienteID || "";
    ocultarResultadosClientes();
}

function usarClienteGeneral(){
    if(clienteGeneral){
        seleccionarCliente(clienteGeneral);
    }
}

function ocultarResultadosClientes(){
    const contenedor = document.getElementById("resultadosClientes");
    if(!contenedor) return;
    contenedor.innerHTML = "";
    contenedor.classList.remove("activo");
}

function buscarClientes(q){
    const contenedor = document.getElementById("resultadosClientes");
    if(!contenedor) return;

    fetch("../controllers/buscar_cliente.php?q=" + encodeURIComponent(q))
    .then(res => res.json())
    .then(data => {
        const clientes = data.clientes || [];
        contenedor.innerHTML = "";

        if(clientes.length === 0){
            const div = document.createElement("div");
            div.className = "resultado-cliente vacio";
            div.textContent = "Sin resultados. Puedes agregarlo con +";
            contenedor.appendChild(div);
            contenedor.classList.add("activo");
            return;
        }

        clientes.forEach(cliente => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "resultado-cliente";
            btn.innerHTML = `<strong>${cliente.nombre || ""}</strong><span>${cliente.tipoDocumento || ""} ${cliente.numeroDocumento || ""} ${cliente.telefono || ""}</span>`;
            btn.addEventListener("click", () => seleccionarCliente(cliente));
            contenedor.appendChild(btn);
        });

        contenedor.classList.add("activo");
    })
    .catch(err => console.error("Error al buscar clientes:", err));
}

function recalcularSubtotalFila(fila){
    const cantidad = parseInt(fila.querySelector(".cantidad").textContent) || 0;
    const precio = parseFloat(fila.querySelector(".precio").textContent) || 0;
    fila.querySelector(".subtotal").textContent = (cantidad * precio).toFixed(2);
    actualizarEstadoPagoFila(fila);
    actualizarPagoMixto();
}

function editarPrecioFila(fila){
    const precioCelda = fila.querySelector(".precio");
    if(precioCelda.dataset.precioEditable !== "1") return;

    let nuevoPrecio = prompt("Ingrese nuevo precio:", precioCelda.textContent);
    if(nuevoPrecio === null) return;

    nuevoPrecio = parseFloat(nuevoPrecio.replace(",", "."));
    if(isNaN(nuevoPrecio) || nuevoPrecio <= 0){
        alert("Precio invalido");
        return;
    }

    precioCelda.textContent = nuevoPrecio.toFixed(2);
    recalcularSubtotalFila(fila);
    calcularTotal();
}

function agregarProductoTabla(prod){
    const tabla = document.getElementById("tabla-ventas");
    const filas = tabla.querySelectorAll("tr");

    // Si ya existe → sumar cantidad
    for(let fila of filas){
        if(fila.children[0].textContent == prod.productoID){
            let cantidadCelda = fila.querySelector(".cantidad");
            let cantidad = parseInt(cantidadCelda.textContent) + 1;
            cantidadCelda.textContent = cantidad;

            recalcularSubtotalFila(fila);
            calcularTotal();
            return;
        }
    }

    let cantidad = 1;
    let precio = parseFloat(prod.precioVenta) || 0;
    let subtotal = cantidad * precio;
    const precioEditable = productoPermiteEditarPrecio(prod);

    const fila = document.createElement("tr");
    fila.innerHTML = `
        <td>${prod.productoID}</td>
        <td>${prod.nombre}</td>
        <td>${prod.descripcion}</td>
        <td>${prod.stock}</td>
        <td class="cantidad">${cantidad}</td>
        <td class="precio ${precioEditable ? "precio-editable" : ""}" data-precio-editable="${precioEditable ? "1" : "0"}" title="${precioEditable ? "Click para editar precio" : ""}">${formatearMonto(precio)}</td>
        <td class="subtotal">${subtotal.toFixed(2)}</td>
        <td><input type="number" step="0.01" min="0" class="monto-detalle-pagado" value="${subtotal.toFixed(2)}"></td>
        <td class="saldo-detalle">0.00</td>
        <td class="estado-detalle-pago">Pagado</td>
        <td class="acciones">
            <i class="fa-solid fa-pen editar"></i>
            ${precioEditable ? '<i class="fa-solid fa-dollar-sign editar-precio" title="Editar precio"></i>' : ''}
            <i class="fa-solid fa-trash eliminar-item"></i>
        </td>
    `;  

    tabla.appendChild(fila);

    fila.querySelector(".monto-detalle-pagado").addEventListener("input", () => {
        actualizarEstadoPagoFila(fila);
        sincronizarEstadoVentaPorDetalles();
        actualizarPagoMixto();
    });
    fila.querySelector(".monto-detalle-pagado").addEventListener("blur", e => {
        e.target.value = formatearMonto(e.target.value);
        actualizarEstadoPagoFila(fila);
        actualizarPagoMixto();
    });

    // EDITAR CANTIDAD
    fila.querySelector(".editar").addEventListener("click", function(){
        let nuevaCantidad = prompt("Ingrese nueva cantidad:", fila.querySelector(".cantidad").textContent);
        if(nuevaCantidad === null) return;
        nuevaCantidad = parseInt(nuevaCantidad);
        if(isNaN(nuevaCantidad) || nuevaCantidad <= 0){
            alert("Cantidad inválida");
            return;
        }
        fila.querySelector(".cantidad").textContent = nuevaCantidad;
        recalcularSubtotalFila(fila);
        calcularTotal();
    });

    const btnEditarPrecio = fila.querySelector(".editar-precio");
    if(btnEditarPrecio){
        btnEditarPrecio.addEventListener("click", () => editarPrecioFila(fila));
    }

    if(precioEditable){
        fila.querySelector(".precio").addEventListener("click", () => editarPrecioFila(fila));
    }

    // ELIMINAR PRODUCTO
    fila.querySelector(".eliminar-item").addEventListener("click", function(){
        fila.remove();
        calcularTotal();
    });

    calcularTotal();
}

function calcularTotal(){
    const tabla = document.getElementById("tabla-ventas");
    const filas = tabla.querySelectorAll("tr");
    let total = 0;
    filas.forEach(fila => total += parseFloat(fila.children[6].textContent));
    document.getElementById("total").value = total.toFixed(2);

    const tipoPagoSelect = document.getElementById("tipoPago");
    const pagoInput = document.getElementById("pago");
    const vueltoInput = document.getElementById("vuelto");
    actualizarCamposPago();
    actualizarPagoMixto();
}

function obtenerTotalPagadoDetalle(){
    let total = 0;
    document.querySelectorAll("#tabla-ventas tr").forEach(fila => {
        total += parseFloat(fila.querySelector(".monto-detalle-pagado")?.value) || 0;
    });
    return total;
}

function actualizarEstadoPagoFila(fila){
    const subtotal = parseFloat(fila.querySelector(".subtotal")?.textContent) || 0;
    const inputPagado = fila.querySelector(".monto-detalle-pagado");
    const saldoCelda = fila.querySelector(".saldo-detalle");
    const estadoCelda = fila.querySelector(".estado-detalle-pago");
    let montoPagado = parseFloat(inputPagado?.value) || 0;

    if(montoPagado < 0) montoPagado = 0;
    if(montoPagado > subtotal) montoPagado = subtotal;

    if(inputPagado && String(inputPagado.value) !== String(montoPagado)){
        inputPagado.value = montoPagado.toFixed(2);
    }

    const saldo = Math.max(subtotal - montoPagado, 0);
    let estado = "pendiente";
    if(montoPagado >= subtotal - 0.01){
        estado = "pagado";
    } else if(montoPagado > 0){
        estado = "parcial";
    }

    if(saldoCelda) saldoCelda.textContent = saldo.toFixed(2);
    if(estadoCelda) estadoCelda.textContent = estado.charAt(0).toUpperCase() + estado.slice(1);
}

function obtenerPagosMixtos(){
    const pagos = [];
    document.querySelectorAll(".pago-mixto").forEach(input => {
        const monto = parseFloat(input.value) || 0;
        if(monto > 0){
            pagos.push({
                tipoPago: input.dataset.tipopago,
                monto
            });
        }
    });
    return pagos;
}

function sumarPagosMixtos(){
    return obtenerPagosMixtos().reduce((sum, pago) => sum + pago.monto, 0);
}

function actualizarPagoMixto(){
    const pagoInput = document.getElementById("pago");
    const vueltoInput = document.getElementById("vuelto");
    const totalPagadoDetalle = obtenerTotalPagadoDetalle();
    const totalPagos = sumarPagosMixtos();

    if(pagoInput){
        pagoInput.value = totalPagos > 0 ? totalPagos.toFixed(2) : pagoInput.value;
    }
    if(vueltoInput){
        vueltoInput.value = Math.max(totalPagos - totalPagadoDetalle, 0).toFixed(2);
    }
}

function sincronizarEstadoVentaPorDetalles(){
    const estados = Array.from(document.querySelectorAll(".estado-detalle-pago")).map(s => s.textContent.trim().toLowerCase());
    const estadoPagoSelect = document.getElementById("estadoPago");
    if(!estadoPagoSelect || estados.length === 0) return;
    estadoPagoSelect.value = estados.some(estado => estado === "pendiente" || estado === "parcial") ? "pendiente" : "pagado";
}

function actualizarCamposPago(){
    const tipoPagoSelect = document.getElementById("tipoPago");
    const estadoPagoSelect = document.getElementById("estadoPago");
    const pagoInput = document.getElementById("pago");
    const vueltoInput = document.getElementById("vuelto");
    const total = parseFloat(document.getElementById("total")?.value) || 0;

    if(!tipoPagoSelect || !estadoPagoSelect || !pagoInput || !vueltoInput) return;

    if(estadoPagoSelect.value === "pendiente"){
        pagoInput.value = "0.00";
        vueltoInput.value = "0.00";
        pagoInput.setAttribute("readonly", true);
        return;
    }

    if(tipoPagoSelect.value !== "efectivo"){
        pagoInput.value = total.toFixed(2);
        vueltoInput.value = "0.00";
        pagoInput.setAttribute("readonly", true);
    } else {
        pagoInput.removeAttribute("readonly");
        if(pagoInput.value === "0.00"){
            pagoInput.value = "";
        }
    }
}

function calcularVuelto(){
    const total = parseFloat(document.getElementById("total").value) || 0;
    const pago = parseFloat(document.getElementById("pago").value) || 0;
    let vuelto = pago - total;
    if(vuelto < 0) vuelto = 0;
    document.getElementById("vuelto").value = vuelto.toFixed(2);
}

function formatearPago(){
    const inputPago = document.getElementById("pago");
    let valor = parseFloat(inputPago.value) || 0;
    inputPago.value = valor.toFixed(2);
}

function imprimirTicket(ventaID){
    const url = `../ticket.php?id=${encodeURIComponent(ventaID)}&_=${Date.now()}`;
    const iframe = document.createElement("iframe");
    iframe.style.display = "none";
    iframe.src = url;
    document.body.appendChild(iframe);

    iframe.onload = function() {
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        const texto = doc && doc.body ? doc.body.innerText.trim() : "";
        const tieneErrorPhp = texto.includes("Fatal error") ||
            texto.includes("Parse error") ||
            texto.includes("Warning") ||
            texto.startsWith("Error");

        if(tieneErrorPhp){
            alert("Error al generar ticket: " + texto.substring(0, 300));
            document.body.removeChild(iframe);
            return;
        }

        setTimeout(() => {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            setTimeout(() => document.body.removeChild(iframe), 1000);
        }, 300);
    };
}


function guardarVenta(){

    // 🚫 Evitar múltiples envíos
    if(enviando){
        console.log("Ya se está enviando la venta...");
        return;
    }

    const btn = document.getElementById("btnGrabar");
    if(btn && btn.classList.contains("deshabilitado")){
        alert("El cierre del dia ya fue realizado. No se pueden registrar mas ventas hoy.");
        return;
    }

    enviando = true;

    btn.style.pointerEvents = "none";
    btn.style.opacity = "0.5";

    const total = parseFloat(document.getElementById("total").value) || 0;
    let pago = parseFloat(document.getElementById("pago").value) || 0;
    let vuelto = parseFloat(document.getElementById("vuelto").value) || 0;
    const tipoPago = document.getElementById("tipoPago").value;
    const estadoPago = document.getElementById("estadoPago").value;
    const clienteID = parseInt(document.getElementById("clienteID")?.value) || 0;

    const filas = document.querySelectorAll("#tabla-ventas tr");
    let productos = [];
    const pagos = obtenerPagosMixtos();
    const totalPagadoDetalle = obtenerTotalPagadoDetalle();
    const totalPagosMixtos = sumarPagosMixtos();

    filas.forEach(fila => {
        productos.push({
            productoID: parseInt(fila.children[0].textContent),
            cantidad: parseInt(fila.children[4].textContent),
            precio: parseFloat(fila.children[5].textContent),
            subtotal: parseFloat(fila.children[6].textContent),
            montoPagado: parseFloat(fila.querySelector(".monto-detalle-pagado")?.value) || 0,
            saldoPendiente: parseFloat(fila.querySelector(".saldo-detalle")?.textContent) || 0,
            estadoPago: fila.querySelector(".estado-detalle-pago")?.textContent.trim().toLowerCase() || "pagado"
        });
    });

    // 🔴 Validaciones
    if(productos.length === 0){
        alert("No hay productos en la venta");
        resetBoton(btn);
        return;
    }

    if(pagos.length > 0){
        if(Math.abs(totalPagosMixtos - totalPagadoDetalle) > 0.01){
            alert("Los pagos ingresados deben sumar S/ " + totalPagadoDetalle.toFixed(2) + " segun los productos marcados como pagados.");
            resetBoton(btn);
            return;
        }
        pago = totalPagosMixtos;
        vuelto = 0;
    } else if(estadoPago === "pendiente"){
        document.getElementById("pago").value = "0.00";
        document.getElementById("vuelto").value = "0.00";
        pago = 0;
        vuelto = 0;
    } else if(tipoPago !== "efectivo"){
        document.getElementById("pago").value = total.toFixed(2);
        document.getElementById("vuelto").value = "0.00";
        pago = total;
        vuelto = 0;
    }

    if(estadoPago === "pagado" && pago < total){
        alert("El pago no puede ser menor al total");
        resetBoton(btn);
        return;
    }

    // 🟢 Token se genera en el servidor (más seguro)
    
    console.log("DATA QUE SE ENVÍA:", {
    total,
    pago,
    vuelto,
    tipoPago,
    estadoPago,
    clienteID,
    pagos,
    productos
});

    fetch("../controllers/guardar_venta.php",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({
            total,
            pago,
            vuelto,
            tipoPago,
            estadoPago,
            clienteID,
            pagos,
            productos
        })
    })
    .then(res => res.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Respuesta no JSON de guardar_venta.php:", text);
            throw new Error(text.trim() || "Respuesta invalida del servidor");
        }
    })
    .then(json => {

        if(json.ok){

            // 🧾 imprimir ticket
            imprimirTicket(json.ventaID);

            // 🧹 limpiar POS
            document.getElementById("tabla-ventas").innerHTML = "";
            document.getElementById("total").value = "0.00";
            document.getElementById("pago").value = "";
            document.getElementById("vuelto").value = "";
            document.getElementById("tipoPago").value = "efectivo";
            document.getElementById("estadoPago").value = "pagado";
            document.querySelectorAll(".pago-mixto").forEach(input => input.value = "0.00");
            usarClienteGeneral();
            actualizarCamposPago();
            document.getElementById("codigo").focus();

            const contenedorHistorial = document.getElementById("contenedorHistorial");
            if(contenedorHistorial && contenedorHistorial.innerHTML.trim() !== ""){
                cargarHistorialVentas();
            }

        } else {
            alert("Error al guardar venta: " + json.error);
        }

    })
    .catch(err => {
        console.error("Error:", err);
        alert("Error del servidor: " + err.message.substring(0, 300));
    })
    .finally(() => {
        resetBoton(btn);
    });
}







function resetBoton(btn){
    enviando = false;
    btn.style.pointerEvents = "auto";
    btn.style.opacity = "1";
}

// =======================
// HISTORIAL
// =======================
function cargarHistorialVentas() {
    const contenedor = document.getElementById("contenedorHistorial");

    fetch(`../controllers/obtener_historial.php?_=${Date.now()}`)
        .then(res => res.text())
        .then(data => {
            contenedor.innerHTML = data;
            if (typeof iniciarHistorial === "function") {
                iniciarHistorial();
            }
        })
        .catch(err => console.error("Error:", err));
}


/* acciones en el historial */ 

document.addEventListener("click", function(e) {
    if (e.target.id === "btnHistoricoVentas") {
        cargarHistorialVentas();
    }
     
    /* ver detalle */
    
if (e.target.classList.contains("ver")) {
    const id = e.target.dataset.id;

    fetch(`../controllers/ver_detalle.php?id=${id}`)
        .then(res => res.text())
        .then(html => {
            // Eliminar cualquier detalle anterior
            const anterior = document.querySelector('.detalle-venta-modal');
            if(anterior) anterior.remove();

            // Insertar nuevo detalle
            const modal = document.createElement('div');
            modal.innerHTML = html; // ya contiene estilos inline y botón de cerrar
            document.body.appendChild(modal);
        })
        .catch(err => console.error("Error al ver detalle:", err));
}

    /* fin ver detalle */ 



    if (e.target.classList.contains("imprimir")) {
        const id = e.target.dataset.id;
        imprimirTicket(id);
    }

    if (e.target.classList.contains("eliminar")) {
        const id = e.target.dataset.id;
        if(confirm("¿Seguro que deseas anular esta venta?")){
            fetch("../controllers/anular_venta.php", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({ventaID: id})
            })
            .then(res => res.json())
            .then(data => {
                if(data.ok){
                    alert("Venta anulada correctamente");
                    cargarHistorialVentas();
                } else {
                    alert("Error: " + data.error);
                }
            });
        }
    }

    if(e.target.classList.contains("marcar-detalle-pagado")){
        const detalleID = e.target.dataset.id;
        const saldo = parseFloat(e.target.dataset.saldo) || 0;
        const montoTexto = prompt("Monto que cancela ahora:", saldo.toFixed(2));
        if(montoTexto === null) return;
        const monto = parseFloat(montoTexto.replace(",", "."));
        if(isNaN(monto) || monto <= 0 || monto > saldo + 0.01){
            alert("Monto invalido");
            return;
        }

        const tipoPago = prompt("Tipo de pago para este producto: efectivo, yape, plin o transferencia", "efectivo");
        if(tipoPago === null) return;

        const tipo = tipoPago.trim().toLowerCase();
        if(!["efectivo", "yape", "plin", "transferencia"].includes(tipo)){
            alert("Tipo de pago invalido");
            return;
        }

        fetch("../controllers/actualizar_detalle_pago.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({detalleID, tipoPago: tipo, monto})
        })
        .then(res => res.json())
        .then(data => {
            if(data.ok){
                alert(data.message || "Producto pagado");
                const modal = document.querySelector(".detalle-venta-modal");
                const ventaTitulo = modal ? modal.querySelector("h2")?.textContent || "" : "";
                const match = ventaTitulo.match(/#(\d+)/);
                if(match){
                    fetch(`../controllers/ver_detalle.php?id=${match[1]}`)
                        .then(res => res.text())
                        .then(html => {
                            document.querySelector(".detalle-venta-modal")?.remove();
                            document.querySelector(".detalle-venta-overlay")?.remove();
                            const cont = document.createElement("div");
                            cont.innerHTML = html;
                            document.body.appendChild(cont);
                        });
                }
            } else {
                alert("Error: " + data.error);
            }
        })
        .catch(err => alert("Error de servidor: " + err));
    }
});


// =======================
// CIERRES DE CAJA
// =======================


window.initCierres = function() {
    const btnGuardar = document.getElementById('guardar-cierre');
    if (!btnGuardar) return;

    const inputsFisico = document.querySelectorAll('.fisico');

    btnGuardar.style.display = 'block';

    // Crear contenedor de observaciones si no existe
    inputsFisico.forEach(input => {
        let obs = input.parentElement.nextElementSibling.querySelector('.obs');
        if(!obs){
            obs = document.createElement('span');
            obs.className = 'obs';
            obs.style.marginLeft = '10px';
            obs.style.fontWeight = 'bold';
            input.parentElement.nextElementSibling.appendChild(obs);
        }
    });

    function formatearNumero(valor){
        return parseFloat(valor || 0).toFixed(2);
    }
 


    /* Funcion de comparaciones */

    function actualizarObservaciones() {
    inputsFisico.forEach(input => {

        const totalRecibido = parseFloat(input.dataset.total) || 0;

        // quitar cualquier carácter no numérico y parsear
        let fisico = parseFloat(input.value.replace(/[^\d.-]/g,'')) || 0;

        // actualizar valor formateado
        if(input.value.trim() !== '') input.value = formatearNumero(fisico);

        // acceder al <td> de observación directamente
        const obs = input.parentElement.nextElementSibling;

        if(fisico === totalRecibido){
            obs.textContent = 'Correcto';
            obs.style.color = 'green';
        } else if(fisico < totalRecibido){
            obs.textContent = 'Faltante';
            obs.style.color = 'red';
        } else {
            obs.textContent = 'Sobrante';
            obs.style.color = 'orange';
        }
    });
}


    /* fin funcion de comparaciones */



    inputsFisico.forEach(input => {
        input.addEventListener('blur', actualizarObservaciones);
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^\d.]/g,'');
        });
    });

    // Guardar cierre
    btnGuardar.addEventListener('click', () => {
        const cierresData = [];
        inputsFisico.forEach(input => {
            const fisico = parseFloat(input.value.replace(/[^\d.-]/g,'')) || 0;
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

};









window.initCierres = function() {
    const btnGuardar = document.getElementById('guardar-cierre');
    if(!btnGuardar) return;

    const inputsFisico = document.querySelectorAll('.fisico');

    function formatearNumero(valor){
        return (parseFloat(valor) || 0).toFixed(2);
    }

    function actualizarObservaciones(){
        let sumaFisico = 0;
        let sumaDiferencia = 0;

        inputsFisico.forEach(input => {
            const totalRecibido = parseFloat(input.dataset.total) || 0;
            const fisico = parseFloat(String(input.value).replace(/[^\d.-]/g,'')) || 0;
            const diferencia = fisico - totalRecibido;
            const fila = input.closest('tr');
            const obs = fila ? fila.querySelector('.obs') : null;
            const difTd = fila ? fila.querySelector('.diferencia') : null;

            sumaFisico += fisico;
            sumaDiferencia += diferencia;

            if(difTd){
                difTd.textContent = 'S/ ' + formatearNumero(diferencia);
            }

            if(obs){
                if(Math.abs(diferencia) < 0.01){
                    obs.textContent = 'Correcto';
                    obs.style.color = 'green';
                } else if(diferencia < 0){
                    obs.textContent = 'Faltante';
                    obs.style.color = 'red';
                } else {
                    obs.textContent = 'Sobrante';
                    obs.style.color = 'orange';
                }
            }
        });

        const totalFisico = document.getElementById('totalFisico');
        const totalDiferencia = document.getElementById('totalDiferencia');

        if(totalFisico){
            totalFisico.textContent = 'S/ ' + formatearNumero(sumaFisico);
        }

        if(totalDiferencia){
            totalDiferencia.textContent = 'S/ ' + formatearNumero(sumaDiferencia);
        }
    }

    inputsFisico.forEach(input => {
        if(input.hasAttribute('readonly')) return;

        input.addEventListener('input', () => {
            input.value = input.value
                .replace(/[^\d.]/g,'')
                .replace(/(\..*)\./g, '$1');
            actualizarObservaciones();
        });

        input.addEventListener('blur', () => {
            input.value = formatearNumero(input.value);
            actualizarObservaciones();
        });
    });

    actualizarObservaciones();

    btnGuardar.addEventListener('click', () => {
        if(btnGuardar.disabled) return;

        if(!confirm('¿Guardar el cierre diario? Luego no se podrán registrar más ventas hoy.')){
            return;
        }

        const cierresData = [];
        inputsFisico.forEach(input => {
            const fisico = parseFloat(String(input.value).replace(/[^\d.-]/g,'')) || 0;
            cierresData.push({
                tipopago: input.dataset.tipopago,
                fisico
            });
        });

        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Guardando...';

        fetch('../controllers/guardar_cierre.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({cierres: cierresData})
        })
        .then(res => res.json())
        .then(res => {
            if(res.ok){
                alert(res.message || 'Cierre guardado correctamente');
                if(typeof cargarPagina === 'function'){
                    cargarPagina('historial_cierre.php');
                } else {
                    location.reload();
                }
            } else {
                alert('Error: ' + res.error);
                btnGuardar.disabled = false;
                btnGuardar.textContent = 'Guardar Cierre';
            }
        })
        .catch(err => {
            alert('Error de servidor: ' + err);
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar Cierre';
        });
    });
};

//  JS PARA HISTORUAL DE CIERRES */ 


document.addEventListener("click", function(e) {

    /* Ver detalle de cierre */
    if (e.target.classList.contains("ver-cierre")) {
        const id = e.target.dataset.id;

        fetch(`../controllers/ver_cierre.php?id=${id}`)
            .then(res => res.text())
            .then(html => {
                // Eliminar cualquier modal anterior
                const anterior = document.querySelector('.detalle-cierre-modal');
                if (anterior) anterior.remove();

                // Insertar nuevo modal
                const modal = document.createElement('div');
                modal.innerHTML = html; // Contiene estilos inline y botón de cerrar
                document.body.appendChild(modal.firstElementChild || modal);
            })
            .catch(err => console.error("Error al ver detalle del cierre:", err));
    }

    /* Imprimir cierre */
    
    if (e.target.classList.contains("imprimir-cierre")) {
        const id = e.target.dataset.id;
        const url = `../ticket_cierre.php?id=${encodeURIComponent(id)}`;
        const iframe = document.createElement("iframe");
        iframe.style.display = "none";
        iframe.src = url;
        document.body.appendChild(iframe);

        iframe.onload = function() {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            setTimeout(() => document.body.removeChild(iframe), 1000);
        };
    }

    /* Anular cierre */
    if (e.target.classList.contains("eliminar-cierre")) {
        const id = e.target.dataset.id;
        if (confirm("¿Seguro que deseas anular este cierre?")) {
            fetch("../controllers/anular_cierre.php", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({cierreID: id})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success){
                    alert("Cierre anulado correctamente");
                    if(typeof cargarPagina === 'function'){
                        cargarPagina('historial_cierre.php');
                    } else {
                        location.reload();
                    }
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(err => console.error("Error al anular cierre:", err));
        }
    }

    /* Editar cierre */
    if (e.target.classList.contains("editar-cierre")) {
        const id = e.target.dataset.id;
        // Aquí puedes abrir un modal o redirigir a la página de edición
        window.location.href = `editar_cierre.php?id=${id}`;
    }

});


/* FIN */ 


// =======================
// INICIALIZACIÓN AUTOMÁTICA
// =======================

document.addEventListener("DOMContentLoaded", () => {
    iniciarPOS();
    initCierres();
});

document.addEventListener("submit", function(e) {
    const formCliente = e.target.closest("#formNuevoCliente");
    if(formCliente){
        e.preventDefault();
        const btn = formCliente.querySelector("button[type='submit']");
        const data = Object.fromEntries(new FormData(formCliente).entries());

        if(btn){
            btn.disabled = true;
            btn.textContent = "Guardando...";
        }

        fetch("../controllers/guardar_cliente.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(json => {
            if(json.ok){
                seleccionarCliente(json.cliente);
                formCliente.reset();
                formCliente.classList.add("oculto");
                alert(json.message || "Cliente guardado");
            } else {
                alert("Error: " + json.error);
            }
        })
        .catch(err => alert("Error de servidor: " + err))
        .finally(() => {
            if(btn){
                btn.disabled = false;
                btn.textContent = "Guardar cliente";
            }
        });
        return;
    }

    const form = e.target.closest(".filtros-historial");
    if(!form) return;

    e.preventDefault();
    const pagina = form.dataset.page || "historial.php";
    const params = new URLSearchParams(new FormData(form)).toString();
    const destino = params ? `${pagina}?${params}` : pagina;

    if(typeof cargarPagina === "function"){
        cargarPagina(destino);
        return;
    }

    fetch(`${destino}${destino.includes("?") ? "&" : "?"}_=${Date.now()}`)
        .then(res => res.text())
        .then(html => {
            const container = form.closest(".container") || document.body;
            container.outerHTML = html;
        })
        .catch(err => alert("Error al filtrar: " + err));
});

document.addEventListener("change", function(e) {
    if(!e.target.classList.contains("editar-estado-pago")) return;

    const select = e.target;
    const ventaID = select.dataset.id;
    const estadoPago = select.value;
    const estadoAnterior = select.dataset.estadoActual || "pendiente";

    if(estadoPago !== "pagado"){
        return;
    }

    if(!confirm("Marcar esta boleta como pagada hoy? Entrara en el cierre de hoy.")){
        select.value = estadoAnterior;
        return;
    }

    const fila = select.closest("tr");
    const tipoPago = (fila?.querySelector(".tipo-pago-saldo")?.value || "").trim().toLowerCase();
    if(!["efectivo", "yape", "plin", "transferencia"].includes(tipoPago)){
        alert("Selecciona el tipo de pago del saldo");
        select.value = estadoAnterior;
        return;
    }

    select.disabled = true;

    fetch("../controllers/actualizar_estado_pago.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({ventaID, estadoPago, tipoPago})
    })
    .then(res => res.json())
    .then(data => {
        if(data.ok){
            alert(data.message || "Estado de pago actualizado");
            if(typeof cargarPagina === "function"){
                cargarPagina("historial.php");
            } else {
                cargarHistorialVentas();
            }
        } else {
            alert("Error: " + data.error);
            select.disabled = false;
            select.value = estadoAnterior;
        }
    })
    .catch(err => {
        alert("Error de servidor: " + err);
        select.disabled = false;
        select.value = estadoAnterior;
    });
});
