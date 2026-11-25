<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

function obtenerPisosDisponibles($pdo)
{
    return $pdo->query("SELECT piso, COUNT(*) AS libres FROM casillas WHERE estado = 'libre' GROUP BY piso ORDER BY piso ASC")->fetchAll();
}

function obtenerCasillasLibres($pdo, $piso = 0)
{
    if ($piso > 0) {
        $stmt = $pdo->prepare("SELECT id_casilla, piso, codigo FROM casillas WHERE estado = 'libre' AND piso = ? ORDER BY codigo ASC");
        $stmt->execute([$piso]);
        return $stmt->fetchAll();
    }
    return $pdo->query("SELECT id_casilla, piso, codigo FROM casillas WHERE estado = 'libre' ORDER BY piso ASC, codigo ASC")->fetchAll();
}

function calcularMontoAlquiler($pdo, $inicio, $fin, $idTarifa = 1)
{
    $segundos = strtotime($fin) - strtotime($inicio);
    $horas = max(1, (int) ceil($segundos / 3600));
    $stmt = $pdo->prepare('SELECT costo_por_hora FROM tarifas WHERE id_tarifa = ? LIMIT 1');
    $stmt->execute([$idTarifa]);
    $precioHora = (float) ($stmt->fetchColumn() ?: 5.00);
    return ['horas' => $horas, 'monto' => round($horas * $precioHora, 2)];
}

function obtenerAlquileres($pdo, $idUsuario = 0)
{
    $sql = "SELECT r.id_reserva, r.codigo_reserva, r.nombre_cliente, r.placa_vehiculo,
                   r.fecha_hora_inicio, r.fecha_hora_fin, r.monto_total, r.pagado, r.estado,
                   c.piso, c.codigo AS casilla
            FROM reservas r
            INNER JOIN casillas c ON c.id_casilla = r.id_casilla ";
    if ($idUsuario > 0) {
        $stmt = $pdo->prepare($sql . 'WHERE r.id_usuario = ? ORDER BY r.fecha_hora_inicio DESC');
        $stmt->execute([$idUsuario]);
        return $stmt->fetchAll();
    }
    return $pdo->query($sql . 'ORDER BY r.estado ASC, r.fecha_hora_inicio DESC')->fetchAll();
}

function volverAlquileres($tipo, $mensaje, $ruta = '')
{
    $_SESSION['alerta'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    $permitidas = ['index.php', 'alquileres.php', 'usuario.php'];
    $elegida = $ruta !== '' ? $ruta : ($_POST['volver'] ?? $_GET['volver'] ?? 'usuario.php');
    $destino = (in_array($elegida, $permitidas, true) && file_exists(__DIR__ . '/../' . $elegida)) ? $elegida : 'index.php';
    header('Location: ../' . $destino);
    exit;
}

if (($_GET['entrar'] ?? '') === 'usuario') {
    $_SESSION['rol'] = 'usuario';
    $_SESSION['solo_lectura'] = true;
    if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'logica_alquileres.php') {
        volverAlquileres('success', 'Modo lectura activo: puedes ver pisos disponibles y registrarte para alquilar.');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}

if (!validarCsrf()) {
    volverAlquileres('danger', 'Sesión expirada o solicitud inválida. Recarga la página.');
}

if (!$pdo) {
    volverAlquileres('danger', 'No hay conexión con la base de datos.');
}

$accion = $_POST['accion'] ?? 'alquilar';

if ($accion === 'registrar_usuario') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = (string) ($_POST['password'] ?? '');

    if ($nombre === '' || strlen($usuario) < 3) {
        volverAlquileres('danger', 'Completa nombre y usuario (mín. 3 letras).');
    }

    if (strlen($clave) < 8 || !preg_match('/[A-Z]/', $clave) || !preg_match('/[a-z]/', $clave) || !preg_match('/[0-9]/', $clave)) {
        volverAlquileres('danger', 'La contraseña debe tener al menos 8 caracteres, incluyendo mayúscula, minúscula y número.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        volverAlquileres('danger', 'Ingresa un correo electrónico válido.');
    }

    $existe = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1');
    $existe->execute([$usuario, $correo]);
    if ($existe->fetch()) {
        volverAlquileres('danger', 'Ese usuario o correo electrónico ya está registrado.');
    }

    $hash = password_hash($clave, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO usuarios (nombre, correo, usuario, password, rol) VALUES (?, ?, ?, ?, 'usuario')")
        ->execute([$nombre, $correo, $usuario, $hash]);

    $_SESSION['id_usuario'] = (int) $pdo->lastInsertId();
    $_SESSION['nombre_usuario'] = $nombre;
    $_SESSION['rol'] = 'usuario';
    $_SESSION['solo_lectura'] = true;

    volverAlquileres('success', 'Registro exitoso. Ya puedes alquilar y pagar tu puesto.');
}

if ($accion === 'alquilar') {
    $idUsuario = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : 0;
    if ($idUsuario <= 0) {
        volverAlquileres('danger', 'Primero debes registrarte como usuario para alquilar un puesto.');
    }

    $nombre = trim($_SESSION['nombre_usuario'] ?? $_POST['nombre_cliente'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $placa = strtoupper(trim($_POST['placa_vehiculo'] ?? ''));
    $idCasilla = isset($_POST['id_casilla']) ? (int) $_POST['id_casilla'] : 0;
    $inicio = str_replace('T', ' ', trim($_POST['fecha_hora_inicio'] ?? ''));
    $fin = str_replace('T', ' ', trim($_POST['fecha_hora_fin'] ?? ''));
    $tInicio = strtotime($inicio);
    $tFin = strtotime($fin);

    if ($idCasilla <= 0 || !$tInicio || !$tFin || $tFin <= $tInicio) {
        volverAlquileres('danger', 'Elige un puesto libre y un rango de fechas y horas válido.');
    }

    $stmt = $pdo->prepare('SELECT piso, codigo, estado FROM casillas WHERE id_casilla = ? LIMIT 1');
    $stmt->execute([$idCasilla]);
    $casilla = $stmt->fetch();
    if (!$casilla || $casilla['estado'] !== 'libre') {
        volverAlquileres('danger', 'El puesto seleccionado ya no está disponible.');
    }

    $cobro = calcularMontoAlquiler($pdo, $inicio, $fin);
    $codigo = 'ALQ-' . strtoupper(bin2hex(random_bytes(2)));

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO reservas (id_usuario, id_casilla, nombre_cliente, telefono, placa_vehiculo, codigo_reserva, fecha_hora_inicio, fecha_hora_fin, monto_total, pagado, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'activa')"
        )->execute([$idUsuario, $idCasilla, $nombre, $telefono ?: null, $placa ?: null, $codigo, $inicio, $fin, $cobro['monto']]);
        $pdo->prepare("UPDATE casillas SET estado = 'reservado' WHERE id_casilla = ?")->execute([$idCasilla]);
        $pdo->commit();

        volverAlquileres('success', "Alquiler {$codigo} pagado ({$cobro['horas']}h = {$cobro['monto']} Bs) en Piso {$casilla['piso']} ({$casilla['codigo']}).");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        volverAlquileres('danger', 'Error al registrar y pagar el alquiler.');
    }
}

if ($accion === 'cancelar') {
    if (!in_array($_SESSION['rol'] ?? 'usuario', ['admin', 'operador'], true)) {
        volverAlquileres('danger', 'Solo un administrador puede cancelar o liberar alquileres.');
    }
    $idReserva = isset($_POST['id_reserva']) ? (int) $_POST['id_reserva'] : 0;
    $stmt = $pdo->prepare("SELECT id_casilla, codigo_reserva FROM reservas WHERE id_reserva = ? AND estado = 'activa' LIMIT 1");
    $stmt->execute([$idReserva]);
    $res = $stmt->fetch();
    if (!$res) {
        volverAlquileres('danger', 'Alquiler no encontrado.');
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id_reserva = ?")->execute([$idReserva]);
        $pdo->prepare("UPDATE casillas SET estado = 'libre' WHERE id_casilla = ?")->execute([(int) $res['id_casilla']]);
        $pdo->commit();
        volverAlquileres('success', "Alquiler {$res['codigo_reserva']} cancelado y puesto liberado.");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        volverAlquileres('danger', 'Error al cancelar el alquiler.');
    }
}

volverAlquileres('danger', 'Acción no válida.');
