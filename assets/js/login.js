const togglePassword = document.getElementById("togglePassword");
const password = document.getElementById("password");

togglePassword.addEventListener("click", function(){
    const type = password.getAttribute("type") === "password" ? "text" : "password";
    password.setAttribute("type", type);
});

// LOGIN
document.getElementById("login-box").addEventListener("submit", function(e){
    e.preventDefault(); // evita recarga

    let datos = new FormData(this);

    fetch("../controllers/LoginController.php", {
        method: "POST",
        body: datos
    })
    .then(res => res.text())
    .then(text => {
        try {
            const data = JSON.parse(text);

            const mensaje = document.getElementById("errorLogin");


            if (data.status === "ok") {
                
                mensaje.classList.remove("error");
                mensaje.classList.add("ok")
                mensaje.innerText = "Bienvenido, redirigiendo ...";
                
                setTimeout(function() {
                window.location.href = "../views/home.php";
                }, 2000);
                
            } else {
                mensaje.classList.remove("ok");
                mensaje.classList.add("error");
                mensaje.innerText = data.mensaje || "Usuario o contraseña incorrectos";
                setTimeout(() => {
                    mensaje.innerText = ""
                }, 2000);
            }

        } catch (e) {
            console.error("NO ES JSON:", text);
            document.getElementById("errorLogin").innerText = "Error del servidor";
        }
    })
    .catch(err => {
        console.error("ERROR FETCH:", err);
        document.getElementById("errorLogin").innerText = "Error de conexión";
    });
});