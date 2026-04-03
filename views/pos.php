     
    
    <?php
    session_start();

    if(!isset($_SESSION['usuario'])){
        header("Location: login.php");
        exit;
    }

    $conexion = new mysqli("localhost","root","","sistemaventasdm");
    $clienteDefault = $conexion->query("SELECT * FROM clientes WHERE clienteID = 1 LIMIT 1");
    $cliente = $clienteDefault->fetch_assoc();
    ?>

    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../assets/css/pos.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <title>POS</title>
    </head>
    <body>

    <div class="pos-container">

        <!-- DATOS VENTA -->

        <div class="datosVenta">

            <div class="datosVentaItem">
                <label>Cliente</label>
                <input type="text" id="clienteNombre" value="<?php echo $cliente['nombre'];?>" readonly>
                <input type="hidden" id="clienteID" value="<?php echo $cliente['clienteID']; ?>">
            </div>

            <div class="datosVentaItem">
                <label>Fecha</label>
                <input type="text" id="fecha" readonly>
            </div>

            <div class="datosVentaItem">
                <label>Vendedor</label>
                <input type="text" id="vendedor" value="<?php echo $_SESSION['usuario']; ?>" readonly>
            </div>

        </div>

        <!-- FIN DATOS DE VENTA -->

        <!-- BUSQUEDA -->

        <div class="busqueda">
            <label>Buscar Producto:</label>
            <input type="text" placeholder="Escanear producto..." id="codigo" class="input-buscar">
        </div>

        <!-- FIN BUSQUEDA -->

        <!-- TABLA DE PRODUCTOS -->

        <table class="tabla-ventas">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Descripcion</th>
                    <th>Stock</th>
                    <th>Cantidad</th>
                    <th>Precio Venta</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody id="tabla-ventas">
            </tbody>
        </table>

        <!-- FIN TABLA DE PRODUCTOS -->

        <!--  CAMPOS CALCULADOS EN VENTA -->

        <div class="campos">

            <div class="campoItem primero">
                <label class="txt-campos">TOTAL S/.</label>
                <input type="number" id="total" readonly>
            </div>

            <div class="campoItem">
                <label class="txt-campos">TIPO PAGO</label>
                <select id="tipoPago">
                    <option value="efectivo" selected>Efectivo</option>
                    <option value="yape">Yape</option>
                    <option value="plin">Plin</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </div>

            <div class="campoItem">
                <label class="txt-campos"> estadoPago </label>
                <select id="estadoPago">
                    <option value="pagado" selected>pagado</option>
                    <option value="pendiente">pendiente</option>
                </select>
            </div>

            <div class="campoItem">
                <label class="txt-campos">PAGO S/.</label>
                <input type="text" inputmode="numeric"  placeholder="Paga el cliente..." id="pago">
                <div id="msmPago"></div>
            </div>

             <div class="campoItem">
                <label class="txt-campos">VUELTO S/.</label>
                <input type="text"  readonly id="vuelto" placeholder="vuelto al cliente">
                <div id="mensaje"></div>
            </div>

            <div class="cont-btn">
                <i class="fas fa-coins cont-btn-icons" id="btnHistoricoVentas" data-title="Hist. de Ventas"></i>
                <i class="fas fa-save cont-btn-icons" id="btnGrabar" data-title="Grabar"></i>
                
                <!-- <button type="button" class="cont-btn-item " id="btnGrabar">GRABAR</button> -->
            </div>

            

        </div>

        <!-- FIN CAMPOS CALCULADOS EN VENTA -->

    </div>

    <div id="contenedorHistorial"></div>
     
    <script src="../assets/js/pos.js"></script>
    <script src="../assets/js/historial.js"></script>
    

    </body>
    </html>
