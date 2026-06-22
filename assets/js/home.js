const dropdowns = document.querySelectorAll(".dropdown");

dropdowns.forEach(dropdown => {
    const button = dropdown.querySelector(".menu-btn");
    if(!button) return;

    button.addEventListener("click", e => {
        e.stopPropagation();
        dropdowns.forEach(item => {
            if(item !== dropdown) item.classList.remove("active");
        });

        if(!dropdown.querySelector(".dropdown-content")){
            dropdown.classList.remove("active");
            return;
        }

        dropdown.classList.toggle("active");
    });
});

const userBtn = document.getElementById("userBtn");
const userMenu = document.getElementById("userMenu");

if(userBtn){
    userBtn.addEventListener("click", e => {
        e.stopPropagation();
        userMenu?.classList.toggle("active");
    });
}

document.addEventListener("click", () => {
    userMenu?.classList.remove("active");
    dropdowns.forEach(dropdown => dropdown.classList.remove("active"));
});

function cargarPagina(pagina){
    const separador = pagina.includes("?") ? "&" : "?";

    fetch(`${pagina}${separador}_=${Date.now()}`)
        .then(res => res.text())
        .then(data => {
            const contenido = document.getElementById("contenido");
            contenido.innerHTML = data;

            if(typeof iniciarPOS === "function"){
                iniciarPOS();
            }

            if(pagina.includes("inventarios.php") && typeof inicializarInventarios === "function"){
                inicializarInventarios();
            }

            if(pagina.includes("compras.php") && typeof inicializarCompras === "function"){
                inicializarCompras();
            }

            if(pagina.includes("cierres.php") && typeof initCierres === "function"){
                initCierres();
            }

            if(typeof aplicarEtiquetasTablasResponsivas === "function"){
                aplicarEtiquetasTablasResponsivas(contenido);
            }
        })
        .catch(err => console.error("Error cargando pagina:", err));
}

document.addEventListener("click", e => {
    if(e.target && e.target.id === "btnHistoricoVentas"){
        cargarPagina("historial.php");
    }
});

window.addEventListener("DOMContentLoaded", () => {
    cargarPagina("pos.php");
});
