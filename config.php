<?php
/* ============================================================
   config.php — Conexión PDO para los módulos CRUD
   (destinos, vehiculos, etc.)

   Colocar en la raíz del proyecto, un nivel arriba de la
   carpeta donde viven los archivos crud_*.php
   ============================================================ */

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
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Usa prepared statements reales del driver (no emulados) — más seguro y con tipado correcto
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log('[Atlas Tours] Error de conexión: ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'No se pudo conectar con el servidor. Intenta más tarde.']));
}