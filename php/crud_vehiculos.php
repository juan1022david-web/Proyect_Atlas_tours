<?php
/* =========================================================
   API DE VEHICULOS - Atlas Tours
   GET  -> lista todos los vehículos
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

    $nombreArchivo = uniqid('vehiculo_') . '.' . $extension;
    $rutaDestino   = $carpetaImagenes . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        responder(['error' => 'No se pudo guardar la imagen en el servidor'], 500);
    }

    return $nombreArchivo;
}

switch ($metodo) {

    /* =====================================================
       LISTAR TODOS LOS VEHICULOS
    ====================================================== */
    case 'GET':
        $stmt = $pdo->query("SELECT * FROM vehiculos ORDER BY id_vehiculo DESC");
        $vehiculos = $stmt->fetchAll();

        foreach ($vehiculos as &$v) {
            $v['imagen'] = $rutaPublicaImagenes . $v['imagen'];
        }

        responder($vehiculos);
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
                INSERT INTO vehiculos (placa, marca, modelo, capacidad, descripcion, imagen, estado)
                VALUES (:placa, :marca, :modelo, :capacidad, :descripcion, :imagen, :estado)
            ");

            $stmt->execute([
                ':placa'       => trim($_POST['placa'] ?? ''),
                ':marca'       => trim($_POST['marca'] ?? ''),
                ':modelo'      => trim($_POST['modelo'] ?? ''),
                ':capacidad'   => (int) ($_POST['capacidad'] ?? 0),
                ':descripcion' => trim($_POST['descripcion'] ?? ''),
                ':imagen'      => $nombreImagen,
                ':estado'      => $_POST['estado'] ?? 'Activo',
            ]);

            responder(['mensaje' => 'Vehículo creado correctamente', 'id' => $pdo->lastInsertId()]);

        } elseif ($accion === 'editar') {

            $id = (int) ($_POST['id'] ?? 0);

            if (!$id) {
                responder(['error' => 'ID inválido'], 400);
            }

            $stmt = $pdo->prepare("SELECT imagen FROM vehiculos WHERE id_vehiculo = :id");
            $stmt->execute([':id' => $id]);
            $actual = $stmt->fetch();

            if (!$actual) {
                responder(['error' => 'Vehículo no encontrado'], 404);
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
                UPDATE vehiculos
                SET placa = :placa, marca = :marca, modelo = :modelo, capacidad = :capacidad,
                    descripcion = :descripcion, imagen = :imagen, estado = :estado
                WHERE id_vehiculo = :id
            ");

            $stmt->execute([
                ':placa'       => trim($_POST['placa'] ?? ''),
                ':marca'       => trim($_POST['marca'] ?? ''),
                ':modelo'      => trim($_POST['modelo'] ?? ''),
                ':capacidad'   => (int) ($_POST['capacidad'] ?? 0),
                ':descripcion' => trim($_POST['descripcion'] ?? ''),
                ':imagen'      => $nombreImagen,
                ':estado'      => $_POST['estado'] ?? 'Activo',
                ':id'          => $id,
            ]);

            responder(['mensaje' => 'Vehículo actualizado correctamente']);

        } elseif ($accion === 'eliminar') {

            $id = (int) ($_POST['id'] ?? 0);

            if (!$id) {
                responder(['error' => 'ID inválido'], 400);
            }

            $stmt = $pdo->prepare("SELECT imagen FROM vehiculos WHERE id_vehiculo = :id");
            $stmt->execute([':id' => $id]);
            $vehiculo = $stmt->fetch();

            if ($vehiculo) {
                $ruta = $carpetaImagenes . $vehiculo['imagen'];
                if (file_exists($ruta)) {
                    unlink($ruta);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE id_vehiculo = :id");
            $stmt->execute([':id' => $id]);

            responder(['mensaje' => 'Vehículo eliminado correctamente']);

        } else {
            responder(['error' => 'Acción no reconocida'], 400);
        }
        break;

    default:
        responder(['error' => 'Método no permitido'], 405);
} // php