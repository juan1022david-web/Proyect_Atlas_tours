<?php
/* =========================================================
   API DE DESTINOS - Atlas Tours
   GET  -> lista todos los destinos
   POST -> crear / editar / eliminar (según campo "accion")

   NOVEDAD: ahora la imagen puede venir de dos formas:
     1) Archivo subido  -> $_FILES['imagen']
     2) URL de imagen   -> $_POST['imagen_url']
   Si llega imagen_url, tiene prioridad sobre el archivo.
========================================================= */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$metodo               = $_SERVER['REQUEST_METHOD'];
$carpetaImagenes       = __DIR__ . '/../assets/img/';
$rutaPublicaImagenes   = '../assets/img/';

function responder($data, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($data);
    exit;
}

function subirImagen($archivo, $carpetaImagenes) {
    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas)) {
        responder(['error' => 'Formato de imagen no permitido'], 400);
    }

    if ($archivo['size'] > 1.5 * 1024 * 1024) {
        responder(['error' => 'La imagen supera 1.5MB'], 400);
    }

    if (!is_dir($carpetaImagenes)) {
        mkdir($carpetaImagenes, 0755, true);
    }

    $nombreArchivo = uniqid('destino_') . '.' . $extension;
    $rutaDestino   = $carpetaImagenes . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        responder(['error' => 'No se pudo guardar la imagen en el servidor'], 500);
    }

    return $nombreArchivo;
}

/**
 * Valida que la URL de imagen sea una URL bien formada y con extensión de imagen.
 * NOTA: aquí solo se guarda el link, no se descarga el archivo al servidor.
 * Si prefieres descargarla y guardarla localmente, revisa la función
 * descargarImagenDesdeUrl() más abajo (comentada) como alternativa.
 */
function validarImagenUrl($url) {
    $url = trim($url);

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        responder(['error' => 'La URL de la imagen no es válida'], 400);
    }

    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $path = parse_url($url, PHP_URL_PATH);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas)) {
        responder(['error' => 'La URL debe apuntar a una imagen (jpg, jpeg, png, webp, gif)'], 400);
    }

    return $url;
}

/* ---------------------------------------------------------
   ALTERNATIVA (opcional): descargar la imagen de la URL y
   guardarla en el servidor, igual que si fuera subida.
   Descomenta y úsala en vez de validarImagenUrl() si prefieres
   tener copia local en lugar de depender del link externo.
---------------------------------------------------------
function descargarImagenDesdeUrl($url, $carpetaImagenes) {
    $url = validarImagenUrl($url);

    $contenido = @file_get_contents($url);
    if ($contenido === false) {
        responder(['error' => 'No se pudo descargar la imagen de la URL indicada'], 400);
    }

    if (strlen($contenido) > 1.5 * 1024 * 1024) {
        responder(['error' => 'La imagen supera 1.5MB'], 400);
    }

    $path = parse_url($url, PHP_URL_PATH);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (!is_dir($carpetaImagenes)) {
        mkdir($carpetaImagenes, 0755, true);
    }

    $nombreArchivo = uniqid('destino_') . '.' . $extension;
    $rutaDestino   = $carpetaImagenes . $nombreArchivo;

    file_put_contents($rutaDestino, $contenido);

    return $nombreArchivo;
}
--------------------------------------------------------- */

/**
 * Resuelve qué imagen usar según lo que llegó en la petición:
 * URL > archivo subido. Devuelve el valor que se guardará en la BD.
 */
function resolverImagen($carpetaImagenes) {
    if (!empty($_POST['imagen_url'])) {
        return validarImagenUrl($_POST['imagen_url']);
    }

    if (!empty($_FILES['imagen']['name'])) {
        return subirImagen($_FILES['imagen'], $carpetaImagenes);
    }

    return null;
}

switch ($metodo) {

    /* =====================================================
       LISTAR TODOS LOS DESTINOS
    ====================================================== */
    case 'GET':
        $stmt = $pdo->query("SELECT * FROM destinos ORDER BY id_destino DESC");
        $destinos = $stmt->fetchAll();

        foreach ($destinos as &$d) {
            // Si ya es una URL completa (http/https), la dejamos tal cual.
            // Si es un archivo local, le anteponemos la ruta pública.
            if (!preg_match('/^https?:\/\//i', $d['imagen'])) {
                $d['imagen'] = $rutaPublicaImagenes . $d['imagen'];
            }
        }

        responder($destinos);
        break;

    /* =====================================================
       CREAR / EDITAR / ELIMINAR
    ====================================================== */
    case 'POST':
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'crear') {

            $nombreImagen = resolverImagen($carpetaImagenes);

            if (!$nombreImagen) {
                responder(['error' => 'Debes seleccionar una imagen o indicar una URL'], 400);
            }

            $stmt = $pdo->prepare("
                INSERT INTO destinos (nombre, descripcion, imagen, telefono, estado)
                VALUES (:nombre, :descripcion, :imagen, :telefono, :estado)
            ");

            $stmt->execute([
                ':nombre'      => trim($_POST['nombre'] ?? ''),
                ':descripcion' => trim($_POST['descripcion'] ?? ''),
                ':imagen'      => $nombreImagen,
                ':telefono'    => trim($_POST['telefono'] ?? ''),
                ':estado'      => $_POST['estado'] ?? 'Activo',
            ]);

            responder(['mensaje' => 'Destino creado correctamente', 'id' => $pdo->lastInsertId()]);

        } elseif ($accion === 'editar') {

            $id = (int) ($_POST['id'] ?? 0);

            if (!$id) {
                responder(['error' => 'ID inválido'], 400);
            }

            $stmt = $pdo->prepare("SELECT imagen FROM destinos WHERE id_destino = :id");
            $stmt->execute([':id' => $id]);
            $actual = $stmt->fetch();

            if (!$actual) {
                responder(['error' => 'Destino no encontrado'], 404);
            }

            $nombreImagen = $actual['imagen'];
            $imagenNueva  = resolverImagen($carpetaImagenes);

            // Solo se reemplaza si el usuario mandó una URL nueva o subió un archivo nuevo
            if ($imagenNueva) {
                // Si la imagen anterior era un archivo local (no URL), lo borramos
                if (!preg_match('/^https?:\/\//i', $actual['imagen'])) {
                    $rutaVieja = $carpetaImagenes . $actual['imagen'];
                    if (file_exists($rutaVieja)) {
                        unlink($rutaVieja);
                    }
                }
                $nombreImagen = $imagenNueva;
            }

            $stmt = $pdo->prepare("
                UPDATE destinos
                SET nombre = :nombre, descripcion = :descripcion, imagen = :imagen,
                    telefono = :telefono, estado = :estado
                WHERE id_destino = :id
            ");

            $stmt->execute([
                ':nombre'      => trim($_POST['nombre'] ?? ''),
                ':descripcion' => trim($_POST['descripcion'] ?? ''),
                ':imagen'      => $nombreImagen,
                ':telefono'    => trim($_POST['telefono'] ?? ''),
                ':estado'      => $_POST['estado'] ?? 'Activo',
                ':id'          => $id,
            ]);

            responder(['mensaje' => 'Destino actualizado correctamente']);

        } elseif ($accion === 'eliminar') {

            $id = (int) ($_POST['id'] ?? 0);

            if (!$id) {
                responder(['error' => 'ID inválido'], 400);
            }

            $stmt = $pdo->prepare("SELECT imagen FROM destinos WHERE id_destino = :id");
            $stmt->execute([':id' => $id]);
            $destino = $stmt->fetch();

            // Solo borramos el archivo físico si NO es una URL externa
            if ($destino && !preg_match('/^https?:\/\//i', $destino['imagen'])) {
                $ruta = $carpetaImagenes . $destino['imagen'];
                if (file_exists($ruta)) {
                    unlink($ruta);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM destinos WHERE id_destino = :id");
            $stmt->execute([':id' => $id]);

            responder(['mensaje' => 'Destino eliminado correctamente']);

        } else {
            responder(['error' => 'Acción no reconocida'], 400);
        }
        break;

    default:
        responder(['error' => 'Método no permitido'], 405);
}