// home.js

// Manejo de dropdowns del menú
const dropdowns = document.querySelectorAll(".dropdown");
dropdowns.forEach(dropdown => {
    const button = dropdown.querySelector(".menu-btn");
    button.addEventListener("click", (e) => {
        e.stopPropagation();
        // Cerrar otros dropdowns
        dropdowns.forEach(d => {
            if (d !== dropdown) d.classList.remove("active");
        });
        dropdown.classList.toggle("active");
    });
});

// Manejo del menú de usuario
const userBtn = document.getElementById("userBtn");
const userMenu = document.getElementById("userMenu");

if(userBtn){
    userBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        userMenu.classList.toggle("active");
    });
}

// Cerrar menus si se hace click fuera
document.addEventListener("click", () => {
    if(userMenu) userMenu.classList.remove("active");
    dropdowns.forEach(d => d.classList.remove("active"));
});

// Función para cargar páginas vía fetch


function cargarPagina(pagina){
    const separador = pagina.includes("?") ? "&" : "?";
    fetch(`${pagina}${separador}_=${Date.now()}`)
        .then(res => res.text())
        .then(data => {
            document.getElementById("contenido").innerHTML = data;

            // Inicializar POS si existe
            if(typeof iniciarPOS === "function") iniciarPOS();

            // Inicializar cierres si se cargó cierres.php
           if(pagina.includes("cierres.php") && typeof initCierres === "function"){
            initCierres(); // Inicializa la lógica de cierres
}
        })
        .catch(err => console.error("Error cargando página:", err));
}





// Evento click para botones históricos (opcional)
document.addEventListener("click", function(e){
    if(e.target && e.target.id === "btnHistoricoVentas"){
        cargarPagina("historial.php");
    }
});

// Cargar POS al inicio
window.addEventListener("DOMContentLoaded", () => {
    cargarPagina('pos.php');
});
