<?php
/**
 * =====================================================================
 * PROYECTO: Sistema Web Inteligente de Estacionamiento - SkyBox
 * SPRINT 1 - HU-01: Registro y Gestión de Ingresos de Vehículos
 * ARCHIVO: config/conexion.php
 * RESPONSABLE: Jhoel (@jhoelito19) / Módulo de Conexión PDO
 * =====================================================================
 */

$host    = 'localhost';
$dbname  = 'skybox_db';
$usuario = 'root';
$clave   = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conexion = new PDO($dsn, $usuario, $clave, $opciones);
    $pdo = $conexion; // Alias por compatibilidad
} catch (PDOException $e) {
    die("<h3>Error al conectar con la base de datos de SkyBox:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}
