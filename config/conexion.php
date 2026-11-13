<?php
/**
 * Conexión a la base de datos MySQL/MariaDB usando PDO.
 * Issue #3 - [HU-01] Desarrollo del script de conexion en PHP con PDO.
 */

declare(strict_types=1);

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('SKYBOX_DB_HOST') ?: 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('SKYBOX_DB_NAME') ?: 'skybox_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('SKYBOX_DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('SKYBOX_DB_PASS') !== false ? (string) getenv('SKYBOX_DB_PASS') : '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

/**
 * Crea y retorna una instancia PDO configurada para db_skybox.
 *
 * @throws PDOException Si ocurre un error al conectar con la base de datos.
 */
function obtenerConexion(): PDO
{
    static $conexion = null;

    if ($conexion instanceof PDO) {
        return $conexion;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $conexion = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        return $conexion;
    } catch (PDOException $e) {
        error_log('[SKY-BOX] Error de conexión PDO: ' . $e->getMessage());
        throw $e;
    }
}

// Instancias globales $pdo y $conexion disponibles al incluir este archivo.
try {
    $pdo = obtenerConexion();
} catch (PDOException $e) {
    $pdo = null;
}
$conexion = $pdo;
