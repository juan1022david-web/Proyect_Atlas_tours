<?php

/* =========================================================
   API DE VEHÍCULOS - ATLAS TOURS

   GET  -> listar vehículos
   POST -> crear
   POST -> editar
   POST -> eliminar
========================================================= */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';


/* =========================================================
   CONFIGURACIÓN
========================================================= */

$metodo = $_SERVER['REQUEST_METHOD'];

$carpetaImagenes =
    __DIR__ . '/../assets/img/';

$rutaPublicaImagenes =
    '../assets/img/';


/* =========================================================
   FUNCIÓN DE RESPUESTA
========================================================= */

function responder($data, $codigo = 200)
{
    http_response_code($codigo);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* =========================================================
   SUBIR IMAGEN
========================================================= */

function subirImagen(
    $archivo,
    $carpetaImagenes
) {

    $extensionesPermitidas = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif'
    ];


    if (
        !isset($archivo['error']) ||
        $archivo['error'] !== UPLOAD_ERR_OK
    ) {

        responder(
            [
                'error' =>
                    'Error al subir la imagen'
            ],
            400
        );

    }


    $extension =
        strtolower(
            pathinfo(
                $archivo['name'],
                PATHINFO_EXTENSION
            )
        );


    if (
        !in_array(
            $extension,
            $extensionesPermitidas,
            true
        )
    ) {

        responder(
            [
                'error' =>
                    'Formato de imagen no permitido'
            ],
            400
        );

    }


    if (
        $archivo['size'] >
        1.5 * 1024 * 1024
    ) {

        responder(
            [
                'error' =>
                    'La imagen supera 1.5 MB'
            ],
            400
        );

    }


    if (
        !is_dir($carpetaImagenes)
    ) {

        if (
            !mkdir(
                $carpetaImagenes,
                0755,
                true
            )
        ) {

            responder(
                [
                    'error' =>
                        'No se pudo crear la carpeta de imágenes'
                ],
                500
            );

        }

    }


    $nombreArchivo =
        uniqid(
            'vehiculo_',
            true
        )
        . '.'
        . $extension;


    $rutaDestino =
        $carpetaImagenes .
        $nombreArchivo;


    if (
        !move_uploaded_file(
            $archivo['tmp_name'],
            $rutaDestino
        )
    ) {

        responder(
            [
                'error' =>
                    'No se pudo guardar la imagen'
            ],
            500
        );

    }


    return $nombreArchivo;
}


/* =========================================================
   MÉTODO GET
========================================================= */

if ($metodo === 'GET') {

    try {

        $stmt =
            $pdo->query(
                "SELECT
                    id_vehiculo,
                    placa,
                    marca,
                    modelo,
                    capacidad,
                    descripcion,
                    imagen,
                    estado
                 FROM vehiculos
                 ORDER BY id_vehiculo DESC"
            );


        $vehiculos =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $vehiculos as &$vehiculo
        ) {

            if (
                !empty(
                    $vehiculo['imagen']
                )
            ) {

                $vehiculo['imagen'] =
                    $rutaPublicaImagenes .
                    $vehiculo['imagen'];

            } else {

                $vehiculo['imagen'] = '';

            }

        }


        responder(
            $vehiculos
        );


    } catch (PDOException $e) {

        responder(
            [
                'error' =>
                    'Error al consultar los vehículos',
                'detalle' =>
                    $e->getMessage()
            ],
            500
        );

    }

}


/* =========================================================
   MÉTODO POST
========================================================= */

if ($metodo === 'POST') {

    $accion =
        $_POST['accion'] ?? '';


    /* =====================================================
       CREAR
    ====================================================== */

    if ($accion === 'crear') {

        $placa =
            trim(
                $_POST['placa'] ?? ''
            );

        $marca =
            trim(
                $_POST['marca'] ?? ''
            );

        $modelo =
            trim(
                $_POST['modelo'] ?? ''
            );

        $capacidad =
            (int)
            ($_POST['capacidad'] ?? 0);

        $descripcion =
            trim(
                $_POST['descripcion'] ?? ''
            );

        $estado =
            $_POST['estado'] ??
            'Activo';


        if (
            $placa === '' ||
            $marca === '' ||
            $modelo === ''
        ) {

            responder(
                [
                    'error' =>
                        'Placa, marca y modelo son obligatorios'
                ],
                400
            );

        }


        if ($capacidad <= 0) {

            responder(
                [
                    'error' =>
                        'La capacidad debe ser mayor que 0'
                ],
                400
            );

        }


        if (
            empty(
                $_FILES['imagen']['name']
            )
        ) {

            responder(
                [
                    'error' =>
                        'Debes seleccionar una imagen'
                ],
                400
            );

        }


        /* Verificar placa */

        $stmt =
            $pdo->prepare(
                "SELECT id_vehiculo
                 FROM vehiculos
                 WHERE placa = :placa"
            );


        $stmt->execute(
            [
                ':placa' => $placa
            ]
        );


        if ($stmt->fetch()) {

            responder(
                [
                    'error' =>
                        'Ya existe un vehículo con esa placa'
                ],
                400
            );

        }


        $nombreImagen =
            subirImagen(
                $_FILES['imagen'],
                $carpetaImagenes
            );


        try {

            $stmt =
                $pdo->prepare(
                    "INSERT INTO vehiculos
                    (
                        placa,
                        marca,
                        modelo,
                        capacidad,
                        descripcion,
                        imagen,
                        estado
                    )
                    VALUES
                    (
                        :placa,
                        :marca,
                        :modelo,
                        :capacidad,
                        :descripcion,
                        :imagen,
                        :estado
                    )"
                );


            $stmt->execute(
                [
                    ':placa' =>
                        $placa,

                    ':marca' =>
                        $marca,

                    ':modelo' =>
                        $modelo,

                    ':capacidad' =>
                        $capacidad,

                    ':descripcion' =>
                        $descripcion,

                    ':imagen' =>
                        $nombreImagen,

                    ':estado' =>
                        $estado
                ]
            );


            responder(
                [
                    'mensaje' =>
                        'Vehículo creado correctamente',

                    'id' =>
                        $pdo->lastInsertId()
                ]
            );


        } catch (PDOException $e) {

            $rutaImagen =
                $carpetaImagenes .
                $nombreImagen;

            if (
                file_exists(
                    $rutaImagen
                )
            ) {

                unlink(
                    $rutaImagen
                );

            }


            responder(
                [
                    'error' =>
                        'No se pudo crear el vehículo',

                    'detalle' =>
                        $e->getMessage()
                ],
                500
            );

        }

    }


    /* =====================================================
       EDITAR
    ====================================================== */

    elseif ($accion === 'editar') {

        $id =
            (int)
            ($_POST['id'] ?? 0);


        if (!$id) {

            responder(
                [
                    'error' =>
                        'ID inválido'
                ],
                400
            );

        }


        $stmt =
            $pdo->prepare(
                "SELECT *
                 FROM vehiculos
                 WHERE id_vehiculo = :id"
            );


        $stmt->execute(
            [
                ':id' => $id
            ]
        );


        $actual =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$actual) {

            responder(
                [
                    'error' =>
                        'Vehículo no encontrado'
                ],
                404
            );

        }


        $placa =
            trim(
                $_POST['placa'] ?? ''
            );

        $marca =
            trim(
                $_POST['marca'] ?? ''
            );

        $modelo =
            trim(
                $_POST['modelo'] ?? ''
            );

        $capacidad =
            (int)
            ($_POST['capacidad'] ?? 0);

        $descripcion =
            trim(
                $_POST['descripcion'] ?? ''
            );

        $estado =
            $_POST['estado'] ??
            'Activo';


        if (
            $placa === '' ||
            $marca === '' ||
            $modelo === ''
        ) {

            responder(
                [
                    'error' =>
                        'Placa, marca y modelo son obligatorios'
                ],
                400
            );

        }


        if ($capacidad <= 0) {

            responder(
                [
                    'error' =>
                        'La capacidad debe ser mayor que 0'
                ],
                400
            );

        }


        /* Verificar placa */

        $stmt =
            $pdo->prepare(
                "SELECT id_vehiculo
                 FROM vehiculos
                 WHERE placa = :placa
                 AND id_vehiculo != :id"
            );


        $stmt->execute(
            [
                ':placa' =>
                    $placa,

                ':id' =>
                    $id
            ]
        );


        if ($stmt->fetch()) {

            responder(
                [
                    'error' =>
                        'Otro vehículo ya utiliza esa placa'
                ],
                400
            );

        }


        $nombreImagen =
            $actual['imagen'];


        $nuevaImagen = false;


        if (
            !empty(
                $_FILES['imagen']['name']
            )
        ) {

            $nombreImagen =
                subirImagen(
                    $_FILES['imagen'],
                    $carpetaImagenes
                );

            $nuevaImagen = true;

        }


        try {

            $stmt =
                $pdo->prepare(
                    "UPDATE vehiculos
                     SET
                        placa = :placa,
                        marca = :marca,
                        modelo = :modelo,
                        capacidad = :capacidad,
                        descripcion = :descripcion,
                        imagen = :imagen,
                        estado = :estado
                     WHERE id_vehiculo = :id"
                );


            $stmt->execute(
                [
                    ':placa' =>
                        $placa,

                    ':marca' =>
                        $marca,

                    ':modelo' =>
                        $modelo,

                    ':capacidad' =>
                        $capacidad,

                    ':descripcion' =>
                        $descripcion,

                    ':imagen' =>
                        $nombreImagen,

                    ':estado' =>
                        $estado,

                    ':id' =>
                        $id
                ]
            );


            /* Borrar imagen anterior */

            if (
                $nuevaImagen &&
                !empty($actual['imagen'])
            ) {

                $rutaVieja =
                    $carpetaImagenes .
                    $actual['imagen'];


                if (
                    file_exists(
                        $rutaVieja
                    )
                ) {

                    unlink(
                        $rutaVieja
                    );

                }

            }


            responder(
                [
                    'mensaje' =>
                        'Vehículo actualizado correctamente'
                ]
            );


        } catch (PDOException $e) {

            responder(
                [
                    'error' =>
                        'No se pudo actualizar el vehículo',

                    'detalle' =>
                        $e->getMessage()
                ],
                500
            );

        }

    }


    /* =====================================================
       ELIMINAR
    ====================================================== */

    elseif ($accion === 'eliminar') {

        $id =
            (int)
            ($_POST['id'] ?? 0);


        if (!$id) {

            responder(
                [
                    'error' =>
                        'ID inválido'
                ],
                400
            );

        }


        $stmt =
            $pdo->prepare(
                "SELECT imagen
                 FROM vehiculos
                 WHERE id_vehiculo = :id"
            );


        $stmt->execute(
            [
                ':id' => $id
            ]
        );


        $vehiculo =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$vehiculo) {

            responder(
                [
                    'error' =>
                        'Vehículo no encontrado'
                ],
                404
            );

        }


        try {

            $stmt =
                $pdo->prepare(
                    "DELETE FROM vehiculos
                     WHERE id_vehiculo = :id"
                );


            $stmt->execute(
                [
                    ':id' => $id
                ]
            );


            /* Borrar imagen */

            if (
                !empty(
                    $vehiculo['imagen']
                )
            ) {

                $ruta =
                    $carpetaImagenes .
                    $vehiculo['imagen'];


                if (
                    file_exists(
                        $ruta
                    )
                ) {

                    unlink(
                        $ruta
                    );

                }

            }


            responder(
                [
                    'mensaje' =>
                        'Vehículo eliminado correctamente'
                ]
            );


        } catch (PDOException $e) {

            responder(
                [
                    'error' =>
                        'No se pudo eliminar el vehículo',

                    'detalle' =>
                        $e->getMessage()
                ],
                500
            );

        }

    }


    else {

        responder(
            [
                'error' =>
                    'Acción no reconocida'
            ],
            400
        );

    }

}


/* =========================================================
   MÉTODO NO PERMITIDO
========================================================= */

responder(
    [
        'error' =>
            'Método no permitido'
    ],
    405
);  