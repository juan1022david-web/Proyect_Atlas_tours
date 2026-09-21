<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

function responder($data, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];

/* =========================================================
   GET
========================================================= */
if ($metodo === 'GET') {

    $accion = $_GET['accion'] ?? '';

    /* Tipos de documento */
    if ($accion === 'tipos_documento') {

        try {

            $stmt = $pdo->query("
                SELECT
                    id_tipo_documento,
                    tipo
                FROM tipo_documento
                ORDER BY tipo
            ");

            responder($stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (PDOException $e) {

            responder([
                'error' => 'Error al cargar tipos de documento',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    /* Listar usuarios */
    if ($accion === 'listar_usuarios') {

        try {

            $stmt = $pdo->query("
                SELECT
                    u.id_usuario,
                    u.nombres,
                    u.apellidos,
                    u.id_tipo_documento,
                    td.tipo AS tipo_documento,
                    u.numero_identificacion,
                    u.correo,
                    u.telefono,
                    u.creado_en
                FROM usuario u
                INNER JOIN tipo_documento td
                    ON td.id_tipo_documento = u.id_tipo_documento
                ORDER BY u.id_usuario DESC
            ");

            responder([
                'exito' => true,
                'usuarios' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

        } catch (PDOException $e) {

            responder([
                'exito' => false,
                'error' => 'Error al consultar usuarios',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    responder([
        'error' => 'Acción GET no válida'
    ], 400);
}