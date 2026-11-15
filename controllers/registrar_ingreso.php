<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

function redirigir(string $tipo, string $mensaje, string $tokenQr = ''): never
{
    $_SESSION['alerta'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
        'token_qr' => $tokenQr,
    ];
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('danger', 'Método no permitido.');
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    redirigir('danger', 'No hay conexión con la base de datos.');
}

$uidRfid = trim((string) ($_POST['uid_rfid'] ?? ''));
$idCasilla = filter_var($_POST['id_casilla'] ?? null, FILTER_VALIDATE_INT) ?: null;
$idTarifa = filter_var($_POST['id_tarifa'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
$idReserva = filter_var($_POST['id_reserva'] ?? null, FILTER_VALIDATE_INT) ?: null;

if (!preg_match('/^[A-Za-z0-9_-]{3,32}$/', $uidRfid)) {
    redirigir('danger', 'El código UID RFID debe tener entre 3 y 32 caracteres alfanuméricos.');
}

try {
    $stmtDup = $pdo->prepare("SELECT fecha_ingreso FROM registros WHERE uid_rfid = :uid AND estado = 'en_curso' LIMIT 1");
    $stmtDup->execute([':uid' => $uidRfid]);
    $activo = $stmtDup->fetch();

    if ($activo) {
        redirigir('danger', "La tarjeta '{$uidRfid}' ya tiene un ingreso activo desde {$activo['fecha_ingreso']}.");
    }

    if ($idCasilla !== null) {
        $stmtCas = $pdo->prepare('SELECT codigo, estado FROM casillas WHERE id_casilla = :id LIMIT 1');
        $stmtCas->execute([':id' => $idCasilla]);
        $casilla = $stmtCas->fetch();

        if (!$casilla || $casilla['estado'] !== 'libre') {
            redirigir('danger', 'La casilla seleccionada no está disponible.');
        }
    } else {
        $casillaLibre = $pdo->query("SELECT id_casilla FROM casillas WHERE estado = 'libre' ORDER BY piso ASC, codigo ASC LIMIT 1")->fetch();
        if (!$casillaLibre) {
            redirigir('danger', 'No hay casillas libres disponibles en este momento.');
        }
        $idCasilla = (int) $casillaLibre['id_casilla'];
    }

    $tokenQr = 'TCK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $pdo->beginTransaction();

    $stmtIns = $pdo->prepare(
        "INSERT INTO registros (id_reserva, uid_rfid, token_qr, id_casilla, id_tarifa, fecha_ingreso, estado)
         VALUES (:id_reserva, :uid_rfid, :token_qr, :id_casilla, :id_tarifa, NOW(), 'en_curso')"
    );
    $stmtIns->execute([
        ':id_reserva' => $idReserva,
        ':uid_rfid' => $uidRfid,
        ':token_qr' => $tokenQr,
        ':id_casilla' => $idCasilla,
        ':id_tarifa' => $idTarifa,
    ]);

    $stmtUpd = $pdo->prepare("UPDATE casillas SET estado = 'ocupado' WHERE id_casilla = :id");
    $stmtUpd->execute([':id' => $idCasilla]);

    $pdo->commit();
    redirigir('success', 'Ingreso registrado exitosamente.', $tokenQr);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirigir('danger', 'Error al registrar el ingreso en la base de datos.');
}
