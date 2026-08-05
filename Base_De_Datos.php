<?php
// ============================================================
//  Base_De_Datos.php  –  Único conector Atlas Tours
// ============================================================
 
$host     = 'localhost';
$db_name  = 'atlas_tours';
$user     = 'root';
$password = '';
 
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $user,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
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
    $stmt = $pdo->query("SELECT id_tipo_documento, tipo FROM tipo_documento ORDER BY id_tipo_documento");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
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
        ':telefono'   => $telefono,
        ':contrasena' => password_hash($contrasena, PASSWORD_DEFAULT)
    ]);
 
    echo "<h2 style='font-family:sans-serif;color:green'>✅ Registro exitoso. <a href='empresa.html'>Iniciar sesión</a></h2>";
}
 
// --- 2. LOGIN ---
elseif ($formulario === 'login') {
 
    $correo     = trim($_POST['correo']    ?? '');
    $contrasena = $_POST['password']       ?? '';
 
    if (empty($correo) || empty($contrasena)) {
        echo "<p style='color:red'>❌ Ingresa tu correo y contraseña.</p>"; exit;
    }
 
    $stmt = $pdo->prepare("SELECT id_usuario, nombres, contrasena FROM usuario WHERE correo = :correo LIMIT 1");
    $stmt->execute([':correo' => $correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
        echo "<p style='color:red'>❌ Correo o contraseña incorrectos.</p>"; exit;
    }
 
    session_start();
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['nombre']     = $usuario['nombres'];
    header("Location: index.html");
    exit;
}
 
// --- 3. COTIZAR ---
elseif ($formulario === 'cotizar') {
 
    $servicio   = trim($_POST['servicio']  ?? '');
    $fecha_hora = trim($_POST['fecha']     ?? '');
    $origen     = trim($_POST['origen']    ?? '');
    $destino    = trim($_POST['destino']   ?? '');
    $n_personas = (int)($_POST['personas'] ?? 0);
    $detalles   = trim($_POST['detalles']  ?? '');
 
    if (empty($servicio) || empty($fecha_hora) || empty($origen) || empty($destino) || $n_personas < 1) {
        echo "<p style='color:red'>❌ Todos los campos obligatorios deben completarse.</p>"; exit;
    }
 
    $stmtTs = $pdo->prepare("SELECT id_tipo_servicio FROM tipo_servicio WHERE nombre_servicio = :nombre LIMIT 1");
    $stmtTs->execute([':nombre' => $servicio]);
    $fila = $stmtTs->fetch(PDO::FETCH_ASSOC);
 
    if ($fila) {
        $id_tipo_servicio = $fila['id_tipo_servicio'];
    } else {
        $pdo->prepare("INSERT INTO tipo_servicio (nombre_servicio) VALUES (:nombre)")->execute([':nombre' => $servicio]);
        $id_tipo_servicio = $pdo->lastInsertId();
    }
 
    $stmt = $pdo->prepare("INSERT INTO cotizar
        (id_usuario, id_tipo_servicio, fecha_hora, origen, destino, n_personas, detalles_opcionales)
        VALUES (NULL, :id_ts, :fecha, :origen, :destino, :personas, :detalles)");
    $stmt->execute([
        ':id_ts'    => $id_tipo_servicio,
        ':fecha'    => $fecha_hora,
        ':origen'   => $origen,
        ':destino'  => $destino,
        ':personas' => $n_personas,
        ':detalles' => $detalles ?: null
    ]);
 
    echo "<h2 style='font-family:sans-serif;color:green'>✅ Cotización enviada. Pronto te contactaremos. <a href='index.html'>Volver</a></h2>";
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
}
 
else {
    echo "<p style='color:red'>❌ Formulario no reconocido.</p>";
}