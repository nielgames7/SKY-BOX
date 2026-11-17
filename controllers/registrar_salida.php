<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

function responderSalida($tipo, $mensaje, $datos = [])
{
    $esJson = ($_REQUEST['formato'] ?? '') === 'json'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($esJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($tipo === 'success' ? 200 : 400);
        echo json_encode([
            'ok' => $tipo === 'success',
            'mensaje' => $mensaje,
            'datos' => $datos,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['alerta'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
        'token_qr' => $datos['token_qr'] ?? '',
        'salida' => $datos,
    ];
    header('Location: ../index.php');
    exit;
}

function calcularLiquidacion($registro)
{
    $entrada = new DateTimeImmutable($registro['fecha_ingreso']);
    $salida = new DateTimeImmutable('now');
    $minutos = max(0, (int) ceil(($salida->getTimestamp() - $entrada->getTimestamp()) / 60));

    $horaBs = (float) ($registro['costo_por_hora'] ?? 5.0);
    $fraccionBs = (float) ($registro['costo_fraccion'] ?? 3.0);
    $tolerancia = (int) ($registro['minutos_tolerancia'] ?? 15);

    if ($minutos <= 60) {
        $totalBs = $horaBs;
    } else {
        $excedente = $minutos - 60;
        $totalBs = $horaBs + (intdiv($excedente, 60) * $horaBs);
        $resto = $excedente % 60;
        if ($resto > $tolerancia) {
            $totalBs += ($fraccionBs > 0 && $resto <= 30) ? $fraccionBs : $horaBs;
        }
    }

    $h = intdiv($minutos, 60);
    $m = $minutos % 60;

    return [
        'minutos' => $minutos,
        'tiempo' => ($h > 0 ? "{$h}h " : '') . "{$m}m",
        'total_bs' => round($totalBs, 2),
        'fecha_salida' => $salida->format('Y-m-d H:i:s'),
    ];
}

function generarQrSimulado($tokenQr, $uidRfid, $totalBs, $fechaSalida)
{
    $referencia = 'PAY-QR-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $payload = [
        'servicio' => 'SKY-BOX',
        'referencia' => $referencia,
        'ticket' => $tokenQr,
        'uid_rfid' => $uidRfid,
        'monto' => number_format($totalBs, 2, '.', ''),
        'moneda' => 'BOB',
        'fecha' => $fechaSalida,
    ];

    return [
        'referencia' => $referencia,
        'monto' => $totalBs,
        'moneda' => 'BOB',
        'qr_texto' => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ];
}

function procesarSalida($pdo, $entrada)
{
    $idRegistro = isset($entrada['id_registro']) ? (int) $entrada['id_registro'] : 0;
    $uidRfid = trim((string) ($entrada['uid_rfid'] ?? ''));
    $tokenQr = trim((string) ($entrada['token_qr'] ?? ''));
    $metodo = in_array($entrada['metodo_pago'] ?? '', ['efectivo', 'qr_transferencia'], true)
        ? $entrada['metodo_pago']
        : 'qr_transferencia';

    if ($idRegistro <= 0 && $uidRfid === '' && $tokenQr === '') {
        throw new InvalidArgumentException('Indica el registro, tarjeta RFID o ticket para cobrar.');
    }

    $sql = "SELECT r.id_registro, r.id_casilla, r.uid_rfid, r.token_qr, r.fecha_ingreso,
                   c.codigo AS casilla, t.nombre AS tarifa, t.costo_por_hora, t.costo_fraccion, t.minutos_tolerancia
            FROM registros r
            LEFT JOIN casillas c ON c.id_casilla = r.id_casilla
            INNER JOIN tarifas t ON t.id_tarifa = r.id_tarifa
            WHERE r.estado = 'en_curso' ";

    if ($idRegistro > 0) {
        $stmt = $pdo->prepare($sql . 'AND r.id_registro = ? LIMIT 1');
        $stmt->execute([$idRegistro]);
    } elseif ($uidRfid !== '') {
        $stmt = $pdo->prepare($sql . 'AND r.uid_rfid = ? ORDER BY r.id_registro DESC LIMIT 1');
        $stmt->execute([$uidRfid]);
    } else {
        $stmt = $pdo->prepare($sql . 'AND r.token_qr = ? LIMIT 1');
        $stmt->execute([$tokenQr]);
    }

    $registro = $stmt->fetch();
    if (!$registro) {
        throw new RuntimeException('No se encontró un vehículo activo para procesar la salida.');
    }

    $liq = calcularLiquidacion($registro);
    $qr = generarQrSimulado($registro['token_qr'], $registro['uid_rfid'], $liq['total_bs'], $liq['fecha_salida']);

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare(
            "UPDATE registros
             SET fecha_salida = ?, minutos_estadia = ?, total_pagado = ?, metodo_pago = ?, estado = 'pagado'
             WHERE id_registro = ? AND estado = 'en_curso'"
        );
        $upd->execute([$liq['fecha_salida'], $liq['minutos'], $liq['total_bs'], $metodo, (int) $registro['id_registro']]);

        if ($upd->rowCount() === 0) {
            throw new RuntimeException('Este ingreso ya fue cobrado.');
        }

        if (!empty($registro['id_casilla'])) {
            $pdo->prepare("UPDATE casillas SET estado = 'libre' WHERE id_casilla = ?")
                ->execute([(int) $registro['id_casilla']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'id_registro' => (int) $registro['id_registro'],
        'uid_rfid' => $registro['uid_rfid'],
        'token_qr' => $registro['token_qr'],
        'casilla' => $registro['casilla'],
        'tiempo' => $liq['tiempo'],
        'minutos_estadia' => $liq['minutos'],
        'total_pagado' => $liq['total_bs'],
        'metodo_pago' => $metodo,
        'qr_simulado' => $qr,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!in_array($_SESSION['rol'] ?? 'usuario', ['admin', 'operador'], true)) {
        responderSalida('danger', 'Solo un administrador u operador puede registrar salidas y cobros.');
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        responderSalida('danger', 'No hay conexión con la base de datos.');
    }

    try {
        $res = procesarSalida($pdo, $_POST);
        $cas = $res['casilla'] ?: 'Sin casilla';
        responderSalida(
            'success',
            "Salida registrada ({$cas}): {$res['uid_rfid']} estuvo {$res['tiempo']} — Total: {$res['total_pagado']} Bs.",
            $res
        );
    } catch (Throwable $e) {
        responderSalida('danger', $e->getMessage());
    }
}
