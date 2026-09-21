<?php

/* =========================================================
   API DE USUARIOS - ATLAS TOURS

   GET  -> listar usuarios (o tipos de documento con ?accion=tipos_documento)
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
   MÉTODO GET
========================================================= */

if ($metodo === 'GET') {

    $accion = $_GET['accion'] ?? '';


    /* =====================================================
       TIPOS DE DOCUMENTO (para el select del formulario)
    ====================================================== */

    if ($accion === 'tipos_documento') {

        try {

            $stmt =
                $pdo->query(
                    "SELECT id_tipo_documento, tipo
                     FROM tipo_documento
                     ORDER BY tipo"
                );


            responder(
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
            );


        } catch (PDOException $e) {

            responder(
                [
                    'error' =>
                        'Error al consultar los tipos de documento',

                    'detalle' =>
                        $e->getMessage()
                ],
                500
            );

        }

    }


    /* =====================================================
       LISTAR USUARIOS
    ====================================================== */

    try {

        $stmt =
            $pdo->query(
                "SELECT
                    u.id_usuario,
                    u.nombres,
                    u.apellidos,
                    u.id_tipo_documento,
                    t.tipo AS tipo_documento,
                    u.numero_identificacion,
                    u.correo,
                    u.telefono,
                    u.creado_en
                 FROM usuario u
                 LEFT JOIN tipo_documento t
                    ON t.id_tipo_documento = u.id_tipo_documento
                 ORDER BY u.id_usuario DESC"
            );


        responder(
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
        );


    } catch (PDOException $e) {

        responder(
            [
                'error' =>
                    'Error al consultar los usuarios',

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

        $nombres =
            trim(
                $_POST['nombres'] ?? ''
            );

        $apellidos =
            trim(
                $_POST['apellidos'] ?? ''
            );

        $id_tipo_documento =
            (int)
            ($_POST['id_tipo_documento'] ?? 0);

        $numero_identificacion =
            trim(
                $_POST['numero_identificacion'] ?? ''
            );

        $correo =
            trim(
                $_POST['correo'] ?? ''
            );

        $telefono =
            trim(
                $_POST['telefono'] ?? ''
            );

        $contrasena =
            $_POST['contrasena'] ?? '';


        if (
            $nombres === '' ||
            $apellidos === '' ||
            $numero_identificacion === '' ||
            $correo === '' ||
            $contrasena === ''
        ) {

            responder(
                [
                    'error' =>
                        'Nombres, apellidos, identificación, correo y contraseña son obligatorios'
                ],
                400
            );

        }


        if (!$id_tipo_documento) {

            responder(
                [
                    'error' =>
                        'Selecciona un tipo de documento'
                ],
                400
            );

        }


        if (
            !filter_var(
                $correo,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            responder(
                [
                    'error' =>
                        'El correo no es válido'
                ],
                400
            );

        }


        /* Verificar correo */

        $stmt =
            $pdo->prepare(
                "SELECT id_usuario
                 FROM usuario
                 WHERE correo = :correo"
            );


        $stmt->execute(
            [
                ':correo' => $correo
            ]
        );


        if ($stmt->fetch()) {

            responder(
                [
                    'error' =>
                        'Ya existe un usuario con ese correo'
                ],
                400
            );

        }


        $hash =
            password_hash(
                $contrasena,
                PASSWORD_BCRYPT
            );


        try {

            $stmt =
                $pdo->prepare(
                    "INSERT INTO usuario
                    (
                        nombres,
                        apellidos,
                        id_tipo_documento,
                        numero_identificacion,
                        correo,
                        telefono,
                        contrasena,
                        creado_en
                    )
                    VALUES
                    (
                        :nombres,
                        :apellidos,
                        :id_tipo_documento,
                        :numero_identificacion,
                        :correo,
                        :telefono,
                        :contrasena,
                        NOW()
                    )"
                );


            $stmt->execute(
                [
                    ':nombres' =>
                        $nombres,

                    ':apellidos' =>
                        $apellidos,

                    ':id_tipo_documento' =>
                        $id_tipo_documento,

                    ':numero_identificacion' =>
                        $numero_identificacion,

                    ':correo' =>
                        $correo,

                    ':telefono' =>
                        $telefono,

                    ':contrasena' =>
                        $hash
                ]
            );


            responder(
                [
                    'mensaje' =>
                        'Usuario creado correctamente',

                    'id' =>
                        $pdo->lastInsertId()
                ]
            );


        } catch (PDOException $e) {

            responder(
                [
                    'error' =>
                        'No se pudo crear el usuario',

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
                 FROM usuario
                 WHERE id_usuario = :id"
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
                        'Usuario no encontrado'
                ],
                404
            );

        }


        $nombres =
            trim(
                $_POST['nombres'] ?? ''
            );

        $apellidos =
            trim(
                $_POST['apellidos'] ?? ''
            );

        $id_tipo_documento =
            (int)
            ($_POST['id_tipo_documento'] ?? 0);

        $numero_identificacion =
            trim(
                $_POST['numero_identificacion'] ?? ''
            );

        $correo =
            trim(
                $_POST['correo'] ?? ''
            );

        $telefono =
            trim(
                $_POST['telefono'] ?? ''
            );

        $contrasena =
            $_POST['contrasena'] ?? ''; // vacío = no cambiar


        if (
            $nombres === '' ||
            $apellidos === '' ||
            $numero_identificacion === '' ||
            $correo === ''
        ) {

            responder(
                [
                    'error' =>
                        'Nombres, apellidos, identificación y correo son obligatorios'
                ],
                400
            );

        }


        if (!$id_tipo_documento) {

            responder(
                [
                    'error' =>
                        'Selecciona un tipo de documento'
                ],
                400
            );

        }


        if (
            !filter_var(
                $correo,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            responder(
                [
                    'error' =>
                        'El correo no es válido'
                ],
                400
            );

        }


        /* Verificar correo */

        $stmt =
            $pdo->prepare(
                "SELECT id_usuario
                 FROM usuario
                 WHERE correo = :correo
                 AND id_usuario != :id"
            );


        $stmt->execute(
            [
                ':correo' =>
                    $correo,

                ':id' =>
                    $id
            ]
        );


        if ($stmt->fetch()) {

            responder(
                [
                    'error' =>
                        'Otro usuario ya utiliza ese correo'
                ],
                400
            );

        }


        try {

            if ($contrasena !== '') {

                $stmt =
                    $pdo->prepare(
                        "UPDATE usuario
                         SET
                            nombres = :nombres,
                            apellidos = :apellidos,
                            id_tipo_documento = :id_tipo_documento,
                            numero_identificacion = :numero_identificacion,
                            correo = :correo,
                            telefono = :telefono,
                            contrasena = :contrasena
                         WHERE id_usuario = :id"
                    );


                $stmt->execute(
                    [
                        ':nombres' =>
                            $nombres,

                        ':apellidos' =>
                            $apellidos,

                        ':id_tipo_documento' =>
                            $id_tipo_documento,

                        ':numero_identificacion' =>
                            $numero_identificacion,

                        ':correo' =>
                            $correo,

                        ':telefono' =>
                            $telefono,

                        ':contrasena' =>
                            password_hash(
                                $contrasena,
                                PASSWORD_BCRYPT
                            ),

                        ':id' =>
                            $id
                    ]
                );

            } else {

                $stmt =
                    $pdo->prepare(
                        "UPDATE usuario
                         SET
                            nombres = :nombres,
                            apellidos = :apellidos,
                            id_tipo_documento = :id_tipo_documento,
                            numero_identificacion = :numero_identificacion,
                            correo = :correo,
                            telefono = :telefono
                         WHERE id_usuario = :id"
                    );


                $stmt->execute(
                    [
                        ':nombres' =>
                            $nombres,

                        ':apellidos' =>
                            $apellidos,

                        ':id_tipo_documento' =>
                            $id_tipo_documento,

                        ':numero_identificacion' =>
                            $numero_identificacion,

                        ':correo' =>
                            $correo,

                        ':telefono' =>
                            $telefono,

                        ':id' =>
                            $id
                    ]
                );

            }


            responder(
                [
                    'mensaje' =>
                        'Usuario actualizado correctamente'
                ]
            );


        } catch (PDOException $e) {

            responder(
                [
                    'error' =>
                        'No se pudo actualizar el usuario',

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
                "SELECT id_usuario
                 FROM usuario
                 WHERE id_usuario = :id"
            );


        $stmt->execute(
            [
                ':id' => $id
            ]
        );


        if (!$stmt->fetch()) {

            responder(
                [
                    'error' =>
                        'Usuario no encontrado'
                ],
                404
            );

        }


        try {

            $stmt =
                $pdo->prepare(
                    "DELETE FROM usuario
                     WHERE id_usuario = :id"
                );


            $stmt->execute(
                [
                    ':id' => $id
                ]
            );


            responder(
                [
                    'mensaje' =>
                        'Usuario eliminado correctamente'
                ]
            );


        } catch (PDOException $e) {

            responder(
                [
                    'error' =>
                        'No se pudo eliminar el usuario',

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