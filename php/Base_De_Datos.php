<?php
// ============================================================
//  Base_De_Datos.php  –  Único conector Atlas Tours
// ============================================================

// session_start() debe ir ANTES de cualquier salida.
session_start();

$host     = 'localhost';
$db_name  = 'atlas_tours';
$user     = 'root';
$password = '';

// No mostrar errores de PHP crudos al cliente (se registran en el log del servidor)
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $user,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('[Atlas Tours] Error de conexión: ' . $e->getMessage());
    header('Content-Type: application/json');
    die(json_encode(['exito' => false, 'mensaje' => 'No se pudo conectar con el servidor. Intenta más tarde.']));
}

// ============================================================
//  ACCIÓN: leer qué se necesita
//  Puede venir por GET (ej: ?accion=tipo_documento)
//  o por POST (formulario con campo "formulario")
// ============================================================
$accion     = $_GET['accion']       ?? '';
$formulario = $_POST['formulario']  ?? '';

/* ============================================================
   GET → tipos de documento (para el select de registro)
   URL: Base_De_Datos.php?accion=tipo_documento
   ============================================================ */
if ($accion === 'tipo_documento') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT id_tipo_documento, tipo FROM tipo_documento ORDER BY id_tipo_documento");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log('[Atlas Tours] tipo_documento: ' . $e->getMessage());
        echo json_encode(['exito' => false, 'mensaje' => 'No se pudieron cargar los tipos de documento.']);
    }
    exit;
}

/* ============================================================
   POST → formularios
   ============================================================ */

// --- 1. REGISTRO ---
if ($formulario === 'registro') {

    $tipo_id        = $_POST['tipo_id']        ?? null;
    $identificacion = trim($_POST['identificacion'] ?? '');
    $nombres        = trim($_POST['nombre']     ?? '');
    $apellidos      = trim($_POST['apellido']   ?? '');
    $correo         = trim($_POST['correo']     ?? '');
    $telefono       = trim($_POST['telefono']   ?? '');
    $contrasena     = $_POST['password']        ?? '';

    if (empty($tipo_id) || empty($identificacion) || empty($nombres) ||
        empty($apellidos) || empty($correo) || empty($contrasena)) {
        echo "<p style='color:red'>❌ Todos los campos obligatorios deben completarse.</p>";
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo "<p style='color:red'>❌ Correo inválido.</p>"; exit;
    }

    if (strlen($contrasena) < 6) {
        echo "<p style='color:red'>❌ La contraseña debe tener al menos 6 caracteres.</p>"; exit;
    }

    try {
        $check = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo OR numero_identificacion = :id LIMIT 1");
        $check->execute([':correo' => $correo, ':id' => $identificacion]);
        if ($check->fetch()) {
            echo "<p style='color:red'>❌ El correo o identificación ya están registrados.</p>"; exit;
        }

        $stmt = $pdo->prepare("INSERT INTO usuario
            (id_tipo_documento, numero_identificacion, nombres, apellidos, correo, telefono, contrasena)
            VALUES (:tipo_id, :id, :nombres, :apellidos, :correo, :telefono, :contrasena)");
        $stmt->execute([
            ':tipo_id'    => $tipo_id,
            ':id'         => $identificacion,
            ':nombres'    => $nombres,
            ':apellidos'  => $apellidos,
            ':correo'     => $correo,
            ':telefono'   => $telefono ?: null,
            ':contrasena' => password_hash($contrasena, PASSWORD_DEFAULT)
        ]);

        echo "<h2 style='font-family:sans-serif;color:green'>✅ Registro exitoso. <a href='empresa.html'>Iniciar sesión</a></h2>";
    } catch (PDOException $e) {
        error_log('[Atlas Tours] registro: ' . $e->getMessage());
        echo "<p style='color:red'>❌ No se pudo completar el registro. Intenta más tarde.</p>";
    }
}

// --- 2. LOGIN ---
elseif ($formulario === 'login') {

    header('Content-Type: application/json');

    $correo     = trim($_POST['correo']    ?? '');
    $contrasena = $_POST['password']       ?? '';

    if (empty($correo) || empty($contrasena)) {
        echo json_encode(['exito' => false, 'mensaje' => 'Ingresa tu correo y contraseña.']);
        exit;
    }

    try {
        // 1) ¿Es un administrador?
        // La tabla admin usa "contrasena" (sin eñe) e "id_admin", igual que aquí.
        $stmtAdmin = $pdo->prepare("SELECT id_admin, correo, contrasena FROM admin WHERE correo = :correo LIMIT 1");
        $stmtAdmin->execute([':correo' => $correo]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        // Solo se acepta si la contraseña guardada es un hash válido de password_hash().
        // No se admite comparación en texto plano.
        if ($admin && password_verify($contrasena, $admin['contrasena'])) {
            session_regenerate_id(true); // evita session fixation
            $_SESSION['id_admin'] = $admin['id_admin'];
            $_SESSION['correo']   = $admin['correo'];
            $_SESSION['rol']      = 'admin';

            echo json_encode([
                'exito'    => true,
                'mensaje'  => '¡Bienvenido, administrador!',
                'redirect' => 'cruds/Destinos.html'
            ]);
            exit;
        }

        // 2) ¿Es un usuario registrado?
        $stmt = $pdo->prepare("SELECT id_usuario, nombres, contrasena FROM usuario WHERE correo = :correo LIMIT 1");
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
            session_regenerate_id(true);
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre']     = $usuario['nombres'];
            $_SESSION['rol']        = 'usuario';

            echo json_encode([
                'exito'    => true,
                'mensaje'  => '¡Bienvenido, ' . $usuario['nombres'] . '!',
                'redirect' => 'index.html'
            ]);
            exit;
        }

        // 3) Ninguno coincidió
        echo json_encode(['exito' => false, 'mensaje' => 'Correo o contraseña incorrectos.']);
        exit;

    } catch (PDOException $e) {
        error_log('[Atlas Tours] login: ' . $e->getMessage());
        echo json_encode(['exito' => false, 'mensaje' => 'No se pudo iniciar sesión. Intenta más tarde.']);
        exit;
    }
}

// --- 3. COTIZAR ---
elseif ($formulario === 'cotizar') {

    header('Content-Type: application/json');

    // Requiere sesión iniciada (igual que hacía la versión vieja de cotizar.php)
    if (!isset($_SESSION['id_usuario'])) {
        echo json_encode(['exito' => false, 'mensaje' => 'Debes iniciar sesión para realizar una cotización.']);
        exit;
    }

    $servicio_input = trim($_POST['servicio']  ?? ''); // puede venir el ID (1-5) o el nombre
    $fecha_hora     = trim($_POST['fecha']     ?? '');
    $origen         = trim($_POST['origen']    ?? '');
    $destino        = trim($_POST['destino']   ?? '');
    $n_personas     = filter_input(INPUT_POST, 'personas', FILTER_VALIDATE_INT);
    $detalles       = trim($_POST['detalles']  ?? '');

    if (empty($servicio_input) || empty($fecha_hora) || empty($origen) || empty($destino) ||
        $n_personas === false || $n_personas === null || $n_personas < 1) {
        echo json_encode(['exito' => false, 'mensaje' => 'Todos los campos obligatorios deben completarse.']);
        exit;
    }

    if ($n_personas > 500) {
        echo json_encode(['exito' => false, 'mensaje' => 'El número de personas parece demasiado alto. Contáctanos directamente para grupos grandes.']);
        exit;
    }

    // El <input type="datetime-local"> envía "AAAA-MM-DDTHH:MM"; MySQL espera espacio, no "T"
    $fecha_mysql = str_replace('T', ' ', $fecha_hora);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $fecha_mysql)) {
        $fecha_mysql .= ':00';
    }
    if (!DateTime::createFromFormat('Y-m-d H:i:s', $fecha_mysql)) {
        echo json_encode(['exito' => false, 'mensaje' => 'Fecha inválida.']);
        exit;
    }

    try {
        // El <select> del formulario ya envía el ID (1-5), pero se admite también el nombre
        if (ctype_digit($servicio_input)) {
            $stmtTs = $pdo->prepare("SELECT id_tipo_servicio FROM tipo_servicio WHERE id_tipo_servicio = :id LIMIT 1");
            $stmtTs->execute([':id' => $servicio_input]);
        } else {
            $stmtTs = $pdo->prepare("SELECT id_tipo_servicio FROM tipo_servicio WHERE nombre_servicio = :nombre LIMIT 1");
            $stmtTs->execute([':nombre' => $servicio_input]);
        }
        $fila = $stmtTs->fetch(PDO::FETCH_ASSOC);

        if ($fila) {
            $id_tipo_servicio = $fila['id_tipo_servicio'];
        } elseif (!ctype_digit($servicio_input)) {
            $pdo->prepare("INSERT INTO tipo_servicio (nombre_servicio) VALUES (:nombre)")->execute([':nombre' => $servicio_input]);
            $id_tipo_servicio = $pdo->lastInsertId();
        } else {
            echo json_encode(['exito' => false, 'mensaje' => 'Servicio inválido.']);
            exit;
        }

        // Evita reservar dos cotizaciones en la misma fecha/hora exacta
        $checkFecha = $pdo->prepare("SELECT id_cotizacion FROM cotizar WHERE fecha_hora = :fecha LIMIT 1");
        $checkFecha->execute([':fecha' => $fecha_mysql]);
        if ($checkFecha->fetch()) {
            echo json_encode(['exito' => false, 'mensaje' => 'Ese horario ya está reservado. Por favor selecciona otro.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO cotizar
            (id_usuario, id_tipo_servicio, fecha_hora, origen, destino, n_personas, detalles_opcionales)
            VALUES (:id_usuario, :id_ts, :fecha, :origen, :destino, :personas, :detalles)");
        $stmt->execute([
            ':id_usuario' => $_SESSION['id_usuario'],
            ':id_ts'      => $id_tipo_servicio,
            ':fecha'      => $fecha_mysql,
            ':origen'     => $origen,
            ':destino'    => $destino,
            ':personas'   => $n_personas,
            ':detalles'   => $detalles ?: null
        ]);

        echo json_encode(['exito' => true, 'mensaje' => 'Cotización registrada correctamente. Pronto te contactaremos.']);
    } catch (PDOException $e) {
        error_log('[Atlas Tours] cotizar: ' . $e->getMessage());
        echo json_encode(['exito' => false, 'mensaje' => 'No se pudo enviar la cotización. Intenta más tarde.']);
    }
}

// --- 4. CONTACTO ---
elseif ($formulario === 'contacto') {

    $primer_nombre    = trim($_POST['primer_nombre']    ?? '');
    $segundo_nombre   = trim($_POST['segundo_nombre']   ?? '');
    $primer_apellido  = trim($_POST['primer_apellido']  ?? '');
    $segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
    $correo           = trim($_POST['correo']           ?? '');
    $telefono         = trim($_POST['telefono']         ?? '');
    $asunto           = trim($_POST['asunto']           ?? '');
    $mensaje          = trim($_POST['mensaje']          ?? '');

    if (empty($primer_nombre) || empty($primer_apellido) ||
        empty($correo) || empty($telefono) || empty($asunto) || empty($mensaje)) {
        echo "<p style='color:red'>❌ Todos los campos obligatorios deben completarse.</p>"; exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo "<p style='color:red'>❌ Correo inválido.</p>"; exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO mensaje
            (primer_nombre, segundo_nombre, apellidos, correo, telefono, asunto, mensaje)
            VALUES (:pnombre, :snombre, :apellidos, :correo, :telefono, :asunto, :mensaje)");
        $stmt->execute([
            ':pnombre'   => $primer_nombre,
            ':snombre'   => $segundo_nombre ?: null,
            ':apellidos' => trim("$primer_apellido $segundo_apellido"),
            ':correo'    => $correo,
            ':telefono'  => $telefono,
            ':asunto'    => $asunto,
            ':mensaje'   => $mensaje
        ]);

        echo "<h2 style='font-family:sans-serif;color:green'>✅ Mensaje enviado. Te responderemos pronto. <a href='index.html'>Volver</a></h2>";
    } catch (PDOException $e) {
        error_log('[Atlas Tours] contacto: ' . $e->getMessage());
        echo "<p style='color:red'>❌ No se pudo enviar el mensaje. Intenta más tarde.</p>";
    }
}

else {
    echo "<p style='color:red'>❌ Formulario no reconocido.</p>";
}