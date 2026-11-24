<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/registrar_salida.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function jsonIoT(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function registrarMarcaIot(string $tipo, string $detalle): void
{
    $dirQr = __DIR__ . '/../assets/qr';
    if (is_dir($dirQr) || @mkdir($dirQr, 0775, true)) {
        @file_put_contents(
            $dirQr . '/ultimo_iot.json',
            json_encode(['tipo' => $tipo, 'detalle' => $detalle, 'ts' => microtime(true)], JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    jsonIoT(500, ['ok' => false, 'mensaje' => 'Sin conexión con la base de datos skybox_db.']);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    try {
        $casillas = $pdo->query('SELECT id_casilla, piso, codigo, estado FROM casillas ORDER BY piso ASC, codigo ASC')->fetchAll();
        $activos = $pdo->query("SELECT id_casilla, uid_rfid FROM registros WHERE estado = 'en_curso'")->fetchAll();

        $ocupantes = [];
        foreach ($activos as $a) {
            if ($a['id_casilla'] !== null) {
                $ocupantes[(int) $a['id_casilla']] = (string) $a['uid_rfid'];
            }
        }

        $reservas = [];
        foreach ($pdo->query("SELECT id_casilla, codigo_reserva FROM reservas WHERE estado = 'activa'") as $r) {
            $reservas[(int) $r['id_casilla']] = (string) $r['codigo_reserva'];
        }

        $conteo = ['libre' => 0, 'ocupado' => 0, 'reservado' => 0];
        foreach ($casillas as &$cas) {
            $est = (string) $cas['estado'];
            if (isset($conteo[$est])) {
                $conteo[$est]++;
            }
            $idCas = (int) $cas['id_casilla'];
            $cas['id_casilla'] = $idCas;
            $cas['piso'] = (int) $cas['piso'];
            $cas['uid_rfid'] = $ocupantes[$idCas] ?? null;
            $cas['codigo_reserva'] = $reservas[$idCas] ?? null;
        }
        unset($cas);

        $conteo['total'] = count($casillas);
        $archivoPago = __DIR__ . '/../assets/qr/ultimo_pago.json';
        $ultimoPago = is_file($archivoPago) ? (json_decode((string) file_get_contents($archivoPago), true) ?: ['evento' => '']) : ['evento' => ''];
        $firmaReg = (string) $pdo->query("SELECT CONCAT(COUNT(*), ':', COALESCE(MAX(id_registro), 0), ':', COALESCE(SUM(estado = 'en_curso'), 0), ':', COALESCE(SUM(estado = 'pagado'), 0)) FROM registros")->fetchColumn();

        jsonIoT(200, [
            'ok' => true,
            'conteo' => $conteo,
            'activos' => count($activos),
            'firma_registros' => $firmaReg,
            'ultimo_pago' => $ultimoPago,
            'casillas' => $casillas,
        ]);
    } catch (Throwable $e) {
        jsonIoT(500, ['ok' => false, 'mensaje' => 'Error al consultar el estado del parqueo.']);
    }
}

if ($metodo !== 'POST') {
    jsonIoT(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$raw = file_get_contents('php://input');
$jsonBody = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
$entrada = is_array($jsonBody) ? $jsonBody : $_POST;
$evento = trim((string) ($entrada['evento'] ?? ''));

if ($evento === 'sensor') {
    $codigo = strtoupper(trim((string) ($entrada['codigo'] ?? '')));
    $ocupado = filter_var($entrada['ocupado'] ?? null, FILTER_VALIDATE_INT);

    if (!preg_match('/^P\d{2}-\d{2}$/', $codigo) || !in_array($ocupado, [0, 1], true)) {
        jsonIoT(400, ['ok' => false, 'mensaje' => 'Parámetros inválidos (se requiere codigo P0X-0X y ocupado 0 o 1).']);
    }

    try {
        $stmt = $pdo->prepare('SELECT id_casilla, estado FROM casillas WHERE codigo = ? LIMIT 1');
        $stmt->execute([$codigo]);
        $casilla = $stmt->fetch();
        if (!$casilla) {
            jsonIoT(404, ['ok' => false, 'mensaje' => "La casilla {$codigo} no existe."]);
        }

        $idCasilla = (int) $casilla['id_casilla'];
        $estadoActual = (string) $casilla['estado'];
        $nuevoEstado = $ocupado === 1
            ? 'ocupado'
            : ($estadoActual === 'ocupado' ? 'libre' : $estadoActual);

        if ($nuevoEstado !== $estadoActual) {
            $pdo->prepare('UPDATE casillas SET estado = ? WHERE id_casilla = ?')
                ->execute([$nuevoEstado, $idCasilla]);

            if ($nuevoEstado === 'ocupado') {
                // Si hay un vehículo ingresado con tarjeta sin puesto asignado, vincularlo al sensor detectado
                $sinPuesto = $pdo->query("SELECT id_registro FROM registros WHERE estado = 'en_curso' AND id_casilla IS NULL ORDER BY id_registro DESC LIMIT 1")->fetchColumn();
                if ($sinPuesto) {
                    $pdo->prepare('UPDATE registros SET id_casilla = ? WHERE id_registro = ? AND id_casilla IS NULL')
                        ->execute([$idCasilla, (int) $sinPuesto]);
                }
            }
            registrarMarcaIot('sensor', "{$codigo}:{$nuevoEstado}");
        }

        jsonIoT(200, [
            'ok' => true,
            'evento' => 'sensor',
            'codigo' => $codigo,
            'estado' => $nuevoEstado,
            'mensaje' => "Sensor {$codigo} -> {$nuevoEstado}",
        ]);
    } catch (Throwable $e) {
        jsonIoT(500, ['ok' => false, 'mensaje' => 'Error al actualizar estado del sensor.']);
    }
}

if ($evento === 'rfid') {
    $uidRfid = strtoupper(trim((string) ($entrada['uid_rfid'] ?? '')));
    if (!preg_match('/^[A-Za-z0-9_-]{3,32}$/', $uidRfid)) {
        jsonIoT(400, ['ok' => false, 'mensaje' => 'UID RFID no válido.']);
    }

    try {
        $stmtAct = $pdo->prepare("SELECT id_registro, token_qr, fecha_ingreso FROM registros WHERE uid_rfid = ? AND estado = 'en_curso' ORDER BY id_registro DESC LIMIT 1");
        $stmtAct->execute([$uidRfid]);
        $activo = $stmtAct->fetch();

        if ($activo) {
            // Segunda pasada de tarjeta: procesar salida, cobro y QR en pantalla exterior
            $salida = procesarSalida($pdo, [
                'id_registro' => (int) $activo['id_registro'],
                'metodo_pago' => 'qr_transferencia',
            ]);
            registrarMarcaIot('rfid_salida', $uidRfid);

            jsonIoT(200, [
                'ok' => true,
                'evento' => 'rfid',
                'accion' => 'salida',
                'uid_rfid' => $uidRfid,
                'ticket' => $salida['token_qr'],
                'total_bs' => $salida['total_pagado'],
                'casilla' => $salida['casilla'] ?: 'Maqueta',
                'mensaje' => "Salida RFID {$uidRfid} | Total: {$salida['total_pagado']} Bs | QR generado en pantalla.",
            ]);
        }

        // Primera pasada de tarjeta: registrar ingreso (sin ocupar casilla hasta que el auto pase al sensor FC-51)
        $tokenQr = 'TCK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmtIns = $pdo->prepare(
            "INSERT INTO registros (id_reserva, uid_rfid, token_qr, id_casilla, id_tarifa, fecha_ingreso, estado)
             VALUES (NULL, :uid_rfid, :token_qr, NULL, 1, NOW(), 'en_curso')"
        );
        $stmtIns->execute([
            ':uid_rfid' => $uidRfid,
            ':token_qr' => $tokenQr,
        ]);
        registrarMarcaIot('rfid_ingreso', $uidRfid);

        jsonIoT(200, [
            'ok' => true,
            'evento' => 'rfid',
            'accion' => 'ingreso',
            'uid_rfid' => $uidRfid,
            'ticket' => $tokenQr,
            'mensaje' => "Ingreso RFID {$uidRfid} registrado (Ticket {$tokenQr}). Estaciona el vehículo en un puesto libre.",
        ]);
    } catch (Throwable $e) {
        jsonIoT(500, ['ok' => false, 'mensaje' => $e->getMessage()]);
    }
}

jsonIoT(400, ['ok' => false, 'mensaje' => 'Tipo de evento IoT no reconocido (use sensor o rfid).']);
