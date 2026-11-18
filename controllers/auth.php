<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

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

    $etiquetaRol = $user['rol'] === 'admin'
        ? 'Administrador (privilegios completos)'
        : ($user['rol'] === 'operador' ? 'Operador de portería' : 'Usuario (acceso limitado)');
    volverInicio('success', "Bienvenido, {$user['nombre']}. Ingresaste como {$etiquetaRol}.");
}

function crearCuenta(PDO $pdo, array $datos, string $rol): array
{
    $nombre = trim((string) ($datos['nombre'] ?? ''));
    $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
    $usuario = trim((string) ($datos['usuario'] ?? ''));
    $clave = (string) ($datos['password'] ?? '');

    if ($nombre === '' || strlen($usuario) < 3 || strlen($clave) < 4) {
        volverInicio('danger', 'Completa nombre, usuario (mín. 3 caracteres) y contraseña (mín. 4 caracteres).');
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        volverInicio('danger', 'Ingresa un correo electrónico válido.');
    }

    $existe = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1');
    $existe->execute([$usuario, $correo]);
    if ($existe->fetch()) {
        volverInicio('danger', 'Ese nombre de usuario o correo ya está registrado.');
    }

    $hash = password_hash($clave, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO usuarios (nombre, correo, usuario, password, rol) VALUES (?, ?, ?, ?, ?)')
        ->execute([$nombre, $correo, $usuario, $hash, $rol]);

    return [(int) $pdo->lastInsertId(), $nombre, $usuario];
}

if ($accion === 'registrar') {
    [$idNuevo, $nombre, $usuario] = crearCuenta($pdo, $_POST, 'usuario');

    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $idNuevo;
    $_SESSION['nombre_usuario'] = $nombre;
    $_SESSION['usuario_login'] = $usuario;
    $_SESSION['rol'] = 'usuario';

    volverInicio('success', "Cuenta de Usuario creada. Ya puedes alquilar y pagar puestos disponibles, {$nombre}.");
}

if ($accion === 'crear_admin') {
    if (($_SESSION['rol'] ?? 'usuario') !== 'admin') {
        volverInicio('danger', 'Solo un administrador puede crear cuentas administrativas.');
    }

    $rol = in_array($_POST['rol'] ?? '', ['admin', 'operador', 'usuario'], true) ? $_POST['rol'] : 'admin';
    [, , $usuario] = crearCuenta($pdo, $_POST, $rol);

    volverInicio('success', "Cuenta '{$usuario}' creada con rol {$rol}.");
}

if ($accion === 'cambiar_rol') {
    if (($_SESSION['rol'] ?? 'usuario') !== 'admin') {
        volverInicio('danger', 'Solo un administrador puede otorgar o cambiar roles.');
    }

    $idObjetivo = (int) ($_POST['id_usuario'] ?? 0);
    $nuevoRol = (string) ($_POST['rol'] ?? '');
    $nuevoActivo = isset($_POST['activo']) && (int) $_POST['activo'] === 0 ? 0 : 1;

    if ($idObjetivo <= 0 || !in_array($nuevoRol, ['admin', 'operador', 'usuario'], true)) {
        volverInicio('danger', 'Datos de usuario o rol no válidos.');
    }

    if ($idObjetivo === (int) ($_SESSION['id_usuario'] ?? 0) && ($nuevoRol !== 'admin' || $nuevoActivo === 0)) {
        volverInicio('danger', 'No puedes quitarte tu propio rol de administrador ni desactivar tu cuenta activa.');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET rol = ?, activo = ? WHERE id_usuario = ?');
    $stmt->execute([$nuevoRol, $nuevoActivo, $idObjetivo]);

    $estadoTxt = $nuevoActivo === 1 ? 'activo' : 'inactivo';
    volverInicio('success', "Permisos actualizados: rol '{$nuevoRol}' ({$estadoTxt}).");
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
