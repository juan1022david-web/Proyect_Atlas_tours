<?php
session_start();
include("conexion.php");

// Verificar si el formulario fue enviado
if (isset($_POST['formulario'])) {

    // Verificar que el usuario haya iniciado sesión
    if (!isset($_SESSION['id_usuario'])) {
        echo "❌ Debes iniciar sesión para realizar una cotización.";
        exit();
    }

    // Obtener el ID del usuario
    $id_usuario = $_SESSION['id_usuario'];

    // Recibir los datos del formulario
    $id_tipo_servicio = $_POST['servicio'];

    // Convertir la fecha al formato DATETIME de MySQL
    $fecha_hora = $_POST['fecha'];
    $fecha_hora = str_replace("T", " ", $fecha_hora);
    $fecha_hora .= ":00";

    $origen = $_POST['origen'];
    $destino = $_POST['destino'];
    $personas = $_POST['personas'];
    $detalle = $_POST['detalles'];

    // ==========================
    // VALIDAR SI YA EXISTE ESA FECHA Y HORA
    // ==========================

    $consulta = mysqli_query($conexion,
        "SELECT id_cotizacion
         FROM cotizar
         WHERE fecha_hora = '$fecha_hora'"
    );

    if (!$consulta) {
        echo "❌ Error al consultar la base de datos: " . mysqli_error($conexion);
        exit();
    }

    if (mysqli_num_rows($consulta) > 0) {
        echo "❌ Ese horario ya está reservado. Por favor selecciona otro.";
        exit();
    }

    // ==========================
    // GUARDAR LA COTIZACIÓN
    // ==========================

    $guardar = mysqli_query($conexion,
        "INSERT INTO cotizar
        (
            id_usuario,
            id_tipo_servicio,
            fecha_hora,
            origen,
            destino,
            n_personas,
            detalles_opcionales
        )
        VALUES
        (
            '$id_usuario',
            '$id_tipo_servicio',
            '$fecha_hora',
            '$origen',
            '$destino',
            '$personas',
            '$detalle'
        )"
    );

    if ($guardar) {
        echo "✅ Cotización registrada correctamente.";
    } else {
        echo "❌ Error al guardar la cotización: " . mysqli_error($conexion);
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Atlas Tours – Cotizar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/img/icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair:ital,opsz,wght@0,5..1200,300..900;1,5..1200,300..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=menu" />

</head>

<body>
    <header>
        <div class="container">
            <nav>
                <ul>
                    <li><a href="index.html">Inicio</a></li>
                    <li><a href="index.html#servicios">Servicios</a></li>
                    <li><a href="index.html#contacto">Contacto</a></li>
                    <li><a href="destinos.html">Destinos</a></li>
                    <li><a href="vehiculos.html">Vehículos</a></li>
                    <li><a href="empresa.html"><i class="fa-solid fa-user"></i></a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="container-form form">
        <div class="container">
            <div class="content-text">
                <h1>APARTA TU CUPO</h1>
                <p>Cotiza ahora y asegura tu lugar de manera rápida y sencilla.</p>
            </div>
            <form id="formCotizar" method="POST">
                <input type="hidden" name="formulario" value="cotizar">

                <div class="row">
                    <div class="col">
                        <label for="servicio">Escoge tu servicio <span>*</span></label>
                        <select id="servicio" name="servicio" required>
                            <option value="">Selecciona una opción</option>
                            <option value="1">Hotelero</option>
                            <option value="2">Empresarial</option>
                            <option value="3">Turístico</option>
                            <option value="4">Persona Natural</option>
                            <option value="5">Otro</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="fecha">Fecha y hora <span>*</span></label>
                        <input type="datetime-local" id="fecha" name="fecha" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label for="origen">Origen <span>*</span></label>
                        <input type="text" id="origen" name="origen" required>
                    </div>
                    <div class="col">
                        <label for="destino">Destino <span>*</span></label>
                        <input type="text" id="destino" name="destino" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label for="personas">Número de personas <span>*</span></label>
                        <input type="number" id="personas" name="personas" min="1" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label for="detalles">Detalles adicionales</label>
                        <textarea id="detalles" name="detalles"
                            placeholder="Agrega algún comentario o detalle…"></textarea>
                    </div>
                </div>

                <div class="verificacion">
                    <input type="checkbox" id="politica" required>
                    <label for="politica">
                        Acepto y he leído la <a href="#" target="_blank">Política de Tratamiento de Datos Personales</a>
                    </label>
                </div>

                <button type="submit" class="btn" id="btnCotizar">Cotizar</button>
            </form>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>© 2025 AtlasTours | Todos los derechos reservados.</p>
            <div class="redes">
                <a href="https://www.instagram.com/" target="_blank" title="Instagram"><i
                        class="fab fa-instagram"></i></a>
                <a href="https://www.facebook.com/" target="_blank" title="Facebook"><i class="fab fa-facebook"></i></a>
                <a href="https://wa.me/message/EHEM3HUWAFZDL1" target="_blank" title="WhatsApp"><i
                        class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>

    <a href="https://wa.me/message/EHEM3HUWAFZDL1" class="whatsapp-float" target="_blank" title="WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Toast de notificación -->
    <div id="toast" class="toast"></div>

    <script src="assets/js/main.js"></script>
    <script>
        function mostrarToast(html, tipo) {
            const toast = document.getElementById('toast');
            toast.innerHTML = html;
            toast.className = 'toast ' + tipo;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 4000);
        }

        document.getElementById('formCotizar').addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnCotizar');
            btn.disabled = true;
            btn.textContent = 'Enviando...';

            fetch('Base_De_Datos.php', { method: 'POST', body: new FormData(this) })
                .then(res => res.text())
                .then(respuesta => {
                    if (respuesta.includes('✅')) {
                        mostrarToast('✅ ¡Cotización enviada! Pronto te contactaremos.', 'exito');
                        document.getElementById('formCotizar').reset();
                    } else {
                        mostrarToast(respuesta, 'error');
                    }
                    btn.disabled = false;
                    btn.textContent = 'Cotizar';
                })
                .catch(() => {
                    mostrarToast('❌ Error de conexión. Intenta de nuevo.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Cotizar';
                });
        });
    </script>
</body>

</html>