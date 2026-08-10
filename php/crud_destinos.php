<?php
/* =========================================================
   API DE DESTINOS - Atlas Tours
   GET  -> lista todos los destinos
   POST -> crear / editar / eliminar (según campo "accion")
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

switch ($metodo) {

    /* =====================================================
       LISTAR TODOS LOS DESTINOS
    ====================================================== */
    case 'GET':
        $stmt = $pdo->query("SELECT * FROM destinos ORDER BY id_destino DESC");
        $destinos = $stmt->fetchAll();

        foreach ($destinos as &$d) {
            $d['imagen'] = $rutaPublicaImagenes . $d['imagen'];
        }

        responder($destinos);
        break;

    /* =====================================================
       CREAR / EDITAR / ELIMINAR
    ====================================================== */
    case 'POST':
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'crear') {

            if (empty($_FILES['imagen']['name'])) {
                responder(['error' => 'Debes seleccionar una imagen'], 400);
            }

            $nombreImagen = subirImagen($_FILES['imagen'], $carpetaImagenes);

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

            // Solo se reemplaza la imagen si el usuario subió una nueva
            if (!empty($_FILES['imagen']['name'])) {
                $nombreImagen = subirImagen($_FILES['imagen'], $carpetaImagenes);

                $rutaVieja = $carpetaImagenes . $actual['imagen'];
                if (file_exists($rutaVieja)) {
                    unlink($rutaVieja);
                }
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

            if ($destino) {
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