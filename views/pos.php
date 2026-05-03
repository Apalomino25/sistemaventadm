<?php
    session_start();

    if(!isset($_SESSION['usuario'])){
        header("Location: login.php");
        exit;
    }

    require_once __DIR__ . "/../config/conexion.php";
    require_once __DIR__ . "/../config/schema_helpers.php";

    try {
    $conexion = $conn;

    $cliente = obtenerClienteGeneral($conexion);

    $stmtCierre = $conexion->prepare("SELECT COUNT(*) FROM cierres WHERE fecha = CURDATE() AND estado = 1");
    $stmtCierre->execute();
    $cierreRealizado = $stmtCierre->fetchColumn() > 0;

    } catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
    }

   ?>

    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../assets/css/pos.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/pos.css'); ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <title>POS</title>
    </head>
    <body>

    <div class="pos-container">

        <!-- DATOS VENTA -->

        <div class="datosVenta">

            <div class="datosVentaItem">
                <label>Cliente</label>
                <div class="cliente-campo">
                    <input type="text" id="clienteNombre" value="<?php echo htmlspecialchars($cliente['nombre']);?>" autocomplete="off">
                    <input type="hidden" id="clienteID" value="<?php echo intval($cliente['clienteID']); ?>">
                    <button type="button" id="btnClienteGeneral" title="Usar cliente general">General</button>
                    <button type="button" id="btnNuevoCliente" title="Agregar cliente">+</button>
                    <div id="resultadosClientes" class="resultados-clientes"></div>
                </div>
            </div>

            <form id="formNuevoCliente" class="form-nuevo-cliente oculto">
                <input type="text" name="nombre" placeholder="Nombre cliente" required>
                <select name="tipoDocumento">
                    <option value="DNI">DNI</option>
                    <option value="RUC">RUC</option>
                    <option value="CE">CE</option>
                    <option value="OTRO">OTRO</option>
                </select>
                <input type="text" name="numeroDocumento" placeholder="Documento">
                <input type="text" name="telefono" placeholder="Telefono">
                <input type="text" name="direccion" placeholder="Direccion">
                <button type="submit">Guardar cliente</button>
                <button type="button" id="btnCancelarCliente">Cancelar</button>
            </form>

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

        <?php if($cierreRealizado): ?>
            <div class="cierre-aviso-pos">El cierre del dia ya fue realizado. No se pueden registrar mas ventas hoy.</div>
        <?php endif; ?>

        <div class="busqueda">
            <label>Buscar Producto:</label>
            <div class="busqueda-campo">
                <input type="text" placeholder="Escanear o buscar producto..." id="codigo" class="input-buscar" autocomplete="off" <?= $cierreRealizado ? 'disabled' : '' ?>>
                <div id="resultadosBusqueda" class="resultados-busqueda"></div>
            </div>
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
                    <th>Estado Pago</th>
                    <th>Acciones</th>
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

            <div class="pagos-mixtos">
                <label>Pagos de esta venta</label>
                <div class="pagos-grid">
                    <div><span>Efectivo</span><input type="number" step="0.01" min="0" id="pagoEfectivo" class="pago-mixto" data-tipopago="efectivo" value="0.00"></div>
                    <div><span>Yape</span><input type="number" step="0.01" min="0" id="pagoYape" class="pago-mixto" data-tipopago="yape" value="0.00"></div>
                    <div><span>Plin</span><input type="number" step="0.01" min="0" id="pagoPlin" class="pago-mixto" data-tipopago="plin" value="0.00"></div>
                    <div><span>Transferencia</span><input type="number" step="0.01" min="0" id="pagoTransferencia" class="pago-mixto" data-tipopago="transferencia" value="0.00"></div>
                </div>
                <small>Si dejas productos pendientes, los pagos deben sumar solo los productos pagados.</small>
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
                <i class="fas fa-save cont-btn-icons <?= $cierreRealizado ? 'deshabilitado' : '' ?>" id="btnGrabar" data-title="Grabar"></i>
                
                <!-- <button type="button" class="cont-btn-item " id="btnGrabar">GRABAR</button> -->
            </div>

            

        </div>

        <!-- FIN CAMPOS CALCULADOS EN VENTA -->

    </div>

    <div id="contenedorHistorial"></div>
     
    <script src="../assets/js/pos.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/pos.js'); ?>"></script>
    <script src="../assets/js/historial.js"></script>
    

    </body>
    </html>
