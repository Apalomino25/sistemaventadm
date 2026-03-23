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

    // Buscar producto al presionar Enter
    inputCodigo.addEventListener("keypress", function(e){
        if(e.key === "Enter"){
            const codigo = inputCodigo.value;
            buscarProducto(codigo);
            inputCodigo.value = "";
        }
    });

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
            const tipo = this.value;
            const pagoInput = document.getElementById("pago");
            const vueltoInput = document.getElementById("vuelto");
            const total = parseFloat(document.getElementById("total").value) || 0;

            if(tipo !== "efectivo"){
                pagoInput.value = total.toFixed(2);
                vueltoInput.value = "0.00";
                pagoInput.setAttribute("readonly", true);
            } else {
                pagoInput.removeAttribute("readonly");
                pagoInput.value = "";
                vueltoInput.value = "";
            }
        });
    }
};

// =======================
// FUNCIONES POS
// =======================
function buscarProducto(codigo){
    fetch("../controllers/buscar_producto.php?codigo="+codigo)
    .then(res => res.json())
    .then(data => {
        if(data.error){
            alert("Producto no encontrado");
            return;
        }
        agregarProductoTabla(data);
        document.getElementById("codigo").focus();
    });
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

            let precio = parseFloat(fila.querySelector(".precio").textContent);
            fila.querySelector(".subtotal").textContent = (cantidad * precio).toFixed(2);
            calcularTotal();
            return;
        }
    }

    let cantidad = 1;
    let subtotal = cantidad * prod.precioVenta;

    const fila = document.createElement("tr");
    fila.innerHTML = `
        <td>${prod.productoID}</td>
        <td>${prod.nombre}</td>
        <td>${prod.descripcion}</td>
        <td>${prod.stock}</td>
        <td class="cantidad">${cantidad}</td>
        <td class="precio">${prod.precioVenta}</td>
        <td class="subtotal">${subtotal.toFixed(2)}</td>
        <td class="acciones">
            <i class="fa-solid fa-pen editar"></i>
            <i class="fa-solid fa-trash eliminar-item"></i>
        </td>
    `;  

    tabla.appendChild(fila);

    // EDITAR CANTIDAD
    fila.querySelector(".editar").addEventListener("click", function(){
        let nuevaCantidad = prompt("Ingrese nueva cantidad:", fila.querySelector(".cantidad").textContent);
        if(nuevaCantidad === null) return;
        nuevaCantidad = parseInt(nuevaCantidad);
        if(isNaN(nuevaCantidad) || nuevaCantidad <= 0){
            alert("Cantidad inválida");
            return;
        }
        let precio = parseFloat(fila.querySelector(".precio").textContent);
        fila.querySelector(".cantidad").textContent = nuevaCantidad;
        fila.querySelector(".subtotal").textContent = (nuevaCantidad * precio).toFixed(2);
        calcularTotal();
    });

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

function guardarVenta(){
    const total = parseFloat(document.getElementById("total").value) || 0;
    const pago = parseFloat(document.getElementById("pago").value) || 0;
    const vuelto = parseFloat(document.getElementById("vuelto").value) || 0;
    const tipoPago = document.getElementById("tipoPago").value;
    const estadoPago = document.getElementById("estadoPago").value;
    const filas = document.querySelectorAll("#tabla-ventas tr");  /// productos
    let productos = [];

    filas.forEach(fila => {
        productos.push({
            productoID: parseInt(fila.children[0].textContent),
            cantidad: parseInt(fila.children[4].textContent),
            precio: parseFloat(fila.children[5].textContent),
            subtotal: parseFloat(fila.children[6].textContent)
        });
    });

    if(productos.length === 0){
        alert("No hay productos en la venta");
        return;
    }

    if(tipoPago !== "efectivo"){
        document.getElementById("pago").value = total.toFixed(2);
        document.getElementById("vuelto").value = "0.00";
    }

    if(pago < total){
        alert("El pago no puede ser menor al total");
        return;
    }
    
    // enviando datos a controlador en php

    fetch("../controllers/guardar_venta.php",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({total, pago, vuelto, tipoPago,estadoPago,productos})
    })
    

    .then(res => res.json())
    .then(json => {
        if(json.ok){
            const url = `http://localhost/sistemaventasDM/ticket.php?id=${json.ventaID}`;
            let iframe = document.createElement("iframe");
            iframe.style.display = "none";
            iframe.src = url;
            document.body.appendChild(iframe);

            iframe.onload = function() {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                setTimeout(() => document.body.removeChild(iframe), 1000);
            };

            // Limpiar POS
            document.getElementById("tabla-ventas").innerHTML = "";
            document.getElementById("total").value = "0.00";
            document.getElementById("pago").value = "";
            document.getElementById("vuelto").value = "";
            document.getElementById("codigo").focus();
        } else {
            alert("Error al guardar venta: " + json.error);
        }
    })
    .catch(err => {
        console.error("Error:", err);
        alert("Error del servidor");
    });
}

// =======================
// HISTORIAL
// =======================
function cargarHistorialVentas() {
    const contenedor = document.getElementById("contenedorHistorial");

    fetch("../controllers/obtener_historial.php")
        .then(res => res.text())
        .then(data => {
            contenedor.innerHTML = data;
            if (typeof iniciarHistorial === "function") {
                iniciarHistorial();
            }
        })
        .catch(err => console.error("Error:", err));
}

document.addEventListener("click", function(e) {
    if (e.target.id === "btnHistoricoVentas") {
        cargarHistorialVentas();
    }

    if (e.target.classList.contains("imprimir")) {
        const id = e.target.dataset.id;
        const url = `http://localhost/sistemaventasDM/ticket.php?id=${id}`;
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

    function actualizarObservaciones() {
        inputsFisico.forEach(input => {
            const totalRecibido = parseFloat(
                input.parentElement.previousElementSibling.textContent.replace(/[^\d.-]/g,'')
            ) || 0;

            let fisico = parseFloat(input.value.replace(/[^\d.-]/g,'')) || 0;

            if(input.value.trim() !== '') input.value = formatearNumero(fisico);

            const obs = input.parentElement.nextElementSibling.querySelector('.obs');
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

// =======================
// INICIALIZACIÓN AUTOMÁTICA
// =======================
document.addEventListener("DOMContentLoaded", iniciarPOS);