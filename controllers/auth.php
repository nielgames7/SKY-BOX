<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

const CLAVE_REGISTRO_ADMIN = 'SKYBOX-ADMIN';

function volverInicio($tipo, $mensaje)
{
    $_SESSION['alerta'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    header('Location: ../index.php');
    exit;
}

if (($_GET['accion'] ?? '') === 'logout' || ($_POST['accion'] ?? '') === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    volverInicio('success', 'Sesión cerrada. Estás navegando en modo usuario (solo lectura).');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../index.php');
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    volverInicio('danger', 'No hay conexión con la base de datos.');
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'login') {
    $identificador = trim($_POST['identificador'] ?? '');
    $clave = (string) ($_POST['password'] ?? '');

    if ($identificador === '' || $clave === '') {
        volverInicio('danger', 'Ingresa tu usuario/correo y contraseña.');
    }

    $stmt = $pdo->prepare(
        'SELECT id_usuario, nombre, usuario, password, rol, activo FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1'
    );
    $stmt->execute([$identificador, strtolower($identificador)]);
    $user = $stmt->fetch();

    if (!$user || !(bool) $user['activo'] || !password_verify($clave, $user['password'])) {
        volverInicio('danger', 'Credenciales incorrectas o cuenta inactiva.');
    }

    session_regenerate_id(true);
    $_SESSION['id_usuario'] = (int) $user['id_usuario'];
    $_SESSION['nombre_usuario'] = $user['nombre'];
    $_SESSION['usuario_login'] = $user['usuario'];
    $_SESSION['rol'] = $user['rol'];

    $etiquetaRol = in_array($user['rol'], ['admin', 'operador'], true) ? 'Administrador (privilegios completos)' : 'Usuario (acceso limitado)';
    volverInicio('success', "Bienvenido, {$user['nombre']}. Ingresaste como {$etiquetaRol}.");
}

if ($accion === 'registrar') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = (string) ($_POST['password'] ?? '');
    $codigoAdmin = trim($_POST['codigo_admin'] ?? '');

    if ($nombre === '' || strlen($usuario) < 3 || strlen($clave) < 4) {
        volverInicio('danger', 'Completa nombre, usuario (mín. 3 caracteres) y contraseña (mín. 4 caracteres).');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        volverInicio('danger', 'Ingresa un correo electrónico válido.');
    }

    $rolAsignado = 'usuario';
    if ($codigoAdmin !== '') {
        if ($codigoAdmin !== CLAVE_REGISTRO_ADMIN) {
            volverInicio('danger', 'La clave de autorización para administrador no es correcta.');
        }
        $rolAsignado = 'admin';
    }

    $existe = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1');
    $existe->execute([$usuario, $correo]);
    if ($existe->fetch()) {
        volverInicio('danger', 'Ese nombre de usuario o correo ya está registrado.');
    }

    $hash = password_hash($clave, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO usuarios (nombre, correo, usuario, password, rol) VALUES (?, ?, ?, ?, ?)')
        ->execute([$nombre, $correo, $usuario, $hash, $rolAsignado]);

    session_regenerate_id(true);
    $_SESSION['id_usuario'] = (int) $pdo->lastInsertId();
    $_SESSION['nombre_usuario'] = $nombre;
    $_SESSION['usuario_login'] = $usuario;
    $_SESSION['rol'] = $rolAsignado;

    $msg = $rolAsignado === 'admin'
        ? "Cuenta de Administrador creada. Tienes privilegios completos, {$nombre}."
        : "Cuenta de Usuario creada. Ya puedes alquilar y pagar puestos disponibles, {$nombre}.";
    volverInicio('success', $msg);
}

if ($accion === 'crear_admin') {
    if (($_SESSION['rol'] ?? 'usuario') !== 'admin') {
        volverInicio('danger', 'Solo un administrador puede crear cuentas administrativas.');
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = (string) ($_POST['password'] ?? '');
    $rol = in_array($_POST['rol'] ?? '', ['admin', 'operador', 'usuario'], true) ? $_POST['rol'] : 'admin';

    if ($nombre === '' || strlen($usuario) < 3 || strlen($clave) < 4 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        volverInicio('danger', 'Verifica nombre, correo válido, usuario y contraseña (mín. 4 caracteres).');
    }

    $existe = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1');
    $existe->execute([$usuario, $correo]);
    if ($existe->fetch()) {
        volverInicio('danger', 'Ese usuario o correo ya existe.');
    }

    $hash = password_hash($clave, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO usuarios (nombre, correo, usuario, password, rol) VALUES (?, ?, ?, ?, ?)')
        ->execute([$nombre, $correo, $usuario, $hash, $rol]);

    volverInicio('success', "Cuenta '{$usuario}' creada con rol {$rol}.");
}

if ($accion === 'actualizar_tarifa') {
    if (!in_array($_SESSION['rol'] ?? 'usuario', ['admin', 'operador'], true)) {
        volverInicio('danger', 'No tienes permiso para modificar tarifas.');
    }

    $idTarifa = isset($_POST['id_tarifa']) ? (int) $_POST['id_tarifa'] : 0;
    $costoHora = (float) ($_POST['costo_por_hora'] ?? 0);
    $costoFraccion = (float) ($_POST['costo_fraccion'] ?? 0);
    $tolerancia = isset($_POST['minutos_tolerancia']) ? (int) $_POST['minutos_tolerancia'] : 15;

    if ($idTarifa <= 0 || $costoHora <= 0 || $costoFraccion < 0 || $tolerancia < 0) {
        volverInicio('danger', 'Valores de tarifa no válidos.');
    }

    $pdo->prepare('UPDATE tarifas SET costo_por_hora = ?, costo_fraccion = ?, minutos_tolerancia = ? WHERE id_tarifa = ?')
        ->execute([$costoHora, $costoFraccion, $tolerancia, $idTarifa]);

    volverInicio('success', 'Tarifa actualizada correctamente.');
}

volverInicio('danger', 'Acción no reconocida.');
