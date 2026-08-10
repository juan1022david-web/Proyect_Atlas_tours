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
   FUNCIÓN AUXILIAR → guardar la imagen subida de un destino
   Devuelve ['ruta' => '...'] o ['error' => '...']
   ============================================================ */
function guardarImagenDestino(array $archivo): array {
    if (!isset($archivo['tmp_name'], $archivo['error'], $archivo['size'], $archivo['name']) ||
        $archivo['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No se pudo subir la imagen.'];
    }

    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas, true)) {
        return ['error' => 'Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.'];
    }

    if ($archivo['size'] > 2 * 1024 * 1024) {
        return ['error' => 'La imagen no debe superar 2MB.'];
    }

    if (@getimagesize($archivo['tmp_name']) === false) {
        return ['error' => 'El archivo no es una imagen válida.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $mimesPermitidos = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif'
    ];

    if (($mimesPermitidos[$extension] ?? '') !== $mime) {
        return ['error' => 'El tipo real de la imagen no coincide con su extensión.'];
    }

    $carpetaDestino = __DIR__ . '/assets/img/destinos/';
    if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0755, true) && !is_dir($carpetaDestino)) {
        return ['error' => 'No se pudo preparar la carpeta de imágenes.'];
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    $rutaCompleta  = $carpetaDestino . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return ['error' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return [
        'nombre' => $nombreArchivo,
        'ruta'   => 'assets/img/destinos/' . $nombreArchivo,
        'archivo'=> $rutaCompleta
    ];
}

/* ============================================================
   GET → tipos de documento (para el select de registro)
   URL: Base_De_Datos.php?accion=tipo_documento
   ============================================================ */
if ($accion === 'sesion_admin') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => (($_SESSION['rol'] ?? '') === 'admin'),
        'rol' => $_SESSION['rol'] ?? null,
        'mensaje' => (($_SESSION['rol'] ?? '') === 'admin') ? 'Sesión de administrador válida.' : 'No autorizado.'
    ]);
    exit;
}

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
   GET → listar destinos (para la tabla del CRUD)
   URL: Base_De_Datos.php?accion=listar_destinos
   ============================================================ */
if ($accion === 'listar_destinos') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT id_destino, nombre, descripcion, imagen, telefono, estado, fecha_creacion
                              FROM destino ORDER BY id_destino DESC");
        echo json_encode(['exito' => true, 'destinos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('[Atlas Tours] listar_destinos: ' . $e->getMessage());
        echo json_encode(['exito' => false, 'mensaje' => 'No se pudieron cargar los destinos.']);
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
                'redirect' => 'cruds/destinos.html'
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

// --- 5. CREAR DESTINO ---
elseif ($formulario === 'destino_crear') {

    header('Content-Type: application/json; charset=utf-8');

    if (($_SESSION['rol'] ?? '') !== 'admin') {
        echo json_encode(['exito' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $telefono    = trim($_POST['telefono'] ?? '');
    $estado      = $_POST['estado'] ?? 'Activo';

    if ($nombre === '' || $descripcion === '' || empty($_FILES['imagen']['tmp_name'] ?? '')) {
        echo json_encode(['exito' => false, 'mensaje' => 'Nombre, descripción e imagen son obligatorios.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($nombre) > 100) {
        echo json_encode(['exito' => false, 'mensaje' => 'El nombre no puede superar 100 caracteres.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
        echo json_encode(['exito' => false, 'mensaje' => 'Estado inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resultadoImagen = guardarImagenDestino($_FILES['imagen']);
    if (isset($resultadoImagen['error'])) {
        echo json_encode(['exito' => false, 'mensaje' => $resultadoImagen['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO destino
            (nombre, descripcion, imagen, telefono, estado, fecha_creacion)
            VALUES (:nombre, :descripcion, :imagen, :telefono, :estado, :fecha)");
        $stmt->execute([
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion,
            ':imagen'      => $resultadoImagen['ruta'],
            ':telefono'    => $telefono !== '' ? $telefono : null,
            ':estado'      => $estado,
            ':fecha'       => date('Y-m-d')
        ]);

        echo json_encode([
            'exito' => true,
            'mensaje' => 'Destino creado correctamente.',
            'id' => (int)$pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        @unlink($resultadoImagen['archivo']);
        error_log('[Atlas Tours] destino_crear: ' . $e->getMessage());
        echo json_encode(['exito' => false, 'mensaje' => 'No se pudo crear el destino.'], JSON_UNESCAPED_UNICODE);
    }
}

// --- 6. EDITAR DESTINO ---
elseif ($formulario === 'destino_editar') {

    header('Content-Type: application/json; charset=utf-8');

    if (($_SESSION['rol'] ?? '') !== 'admin') {
        echo json_encode(['exito' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id          = filter_input(INPUT_POST, 'id_destino', FILTER_VALIDATE_INT);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $telefono    = trim($_POST['telefono'] ?? '');
    $estado      = $_POST['estado'] ?? 'Activo';

    if (!$id || $nombre === '' || $descripcion === '') {
        echo json_encode(['exito' => false, 'mensaje' => 'Faltan datos obligatorios.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($nombre) > 100 || !in_array($estado, ['Activo', 'Inactivo'], true)) {
        echo json_encode(['exito' => false, 'mensaje' => 'Los datos enviados no son válidos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $buscar = $pdo->prepare("SELECT imagen FROM destino WHERE id_destino = :id LIMIT 1");
        $buscar->execute([':id' => $id]);
        $actual = $buscar->fetch(PDO::FETCH_ASSOC);

        if (!$actual) {
            echo json_encode(['exito' => false, 'mensaje' => 'Destino no encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nuevaImagen = null;
        if (!empty($_FILES['imagen']['tmp_name'] ?? '')) {
            $resultadoImagen = guardarImagenDestino($_FILES['imagen']);
            if (isset($resultadoImagen['error'])) {
                echo json_encode(['exito' => false, 'mensaje' => $resultadoImagen['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $nuevaImagen = $resultadoImagen;
        }

        if ($nuevaImagen) {
            $stmt = $pdo->prepare("UPDATE destino SET
                nombre = :nombre, descripcion = :descripcion, imagen = :imagen,
                telefono = :telefono, estado = :estado
                WHERE id_destino = :id");
            $stmt->execute([
                ':nombre'      => $nombre,
                ':descripcion' => $descripcion,
                ':imagen'      => $nuevaImagen['ruta'],
                ':telefono'    => $telefono !== '' ? $telefono : null,
                ':estado'      => $estado,
                ':id'          => $id
            ]);

            if (!empty($actual['imagen'])) {
                $rutaVieja = __DIR__ . '/' . ltrim($actual['imagen'], '/');
                if (is_file($rutaVieja) && realpath($rutaVieja) !== realpath($nuevaImagen['archivo'])) {
                    @unlink($rutaVieja);
                }
            }
        } else {
            $stmt = $pdo->prepare("UPDATE destino SET
                nombre = :nombre, descripcion = :descripcion,
                telefono = :telefono, estado = :estado
                WHERE id_destino = :id");
            $stmt->execute([
                ':nombre'      => $nombre,
                ':descripcion' => $descripcion,
                ':telefono'    => $telefono !== '' ? $telefono : null,
                ':estado'      => $estado,
                ':id'          => $id
            ]);
        }

        echo json_encode(['exito' => true, 'mensaje' => 'Destino actualizado correctamente.'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        if (!empty($nuevaImagen['archivo'])) {
            @unlink($nuevaImagen['archivo']);
        }
        error_log('[Atlas Tours] destino_editar: ' . $e->getMessage());
        echo json_encode(['exito' => false, 'mensaje' => 'No se pudo actualizar el destino.'], JSON_UNESCAPED_UNICODE);
    }
}

// --- 7. ELIMINAR DESTINO ---
elseif ($formulario === 'destino_eliminar') {

    header('Content-Type: application/json; charset=utf-8');

    if (($_SESSION['rol'] ?? '') !== 'admin') {
        echo json_encode(['exito' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = filter_input(INPUT_POST, 'id_destino', FILTER_VALIDATE_INT);

    if (!$id) {
        echo json_encode(['exito' => false, 'mensaje' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $buscar = $pdo->prepare("SELECT imagen FROM destino WHERE id_destino = :id LIMIT 1");
        $buscar->execute([':id' => $id]);
        $destino = $buscar->fetch(PDO::FETCH_ASSOC);

        if (!$destino) {
            echo json_encode(['exito' => false, 'mensaje' => 'Destino no encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM destino WHERE id_destino = :id");
        $stmt->execute([':id' => $id]);

        if (!empty($destino['imagen'])) {
            $rutaImagen = __DIR__ . '/' . ltrim($destino['imagen'], '/');
            if (is_file($rutaImagen)) {
                @unlink($rutaImagen);
            }
        }

        echo json_encode(['exito' => true, 'mensaje' => 'Destino eliminado correctamente.'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log('[Atlas Tours] destino_eliminar: ' . $e->getMessage());
        echo json_encode(['exito' => false, 'mensaje' => 'No se pudo eliminar el destino.'], JSON_UNESCAPED_UNICODE);
    }
}

else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['exito' => false, 'mensaje' => 'Formulario o acción no reconocidos.'], JSON_UNESCAPED_UNICODE);
}