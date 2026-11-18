<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

$rutaPhpQrCode = __DIR__ . '/../phpqrcode/qrlib.php';
if (is_file($rutaPhpQrCode)) {
    include_once $rutaPhpQrCode;
}

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
    $montoTexto = number_format((float) $totalBs, 2, '.', '');
    $mensajeQr = "SKY-BOX | Ha pagado {$montoTexto} Bs. | Ticket: {$tokenQr} | RFID: {$uidRfid} | Ref: {$referencia}";

    $payload = [
        'servicio' => 'SKY-BOX',
        'referencia' => $referencia,
        'ticket' => $tokenQr,
        'uid_rfid' => $uidRfid,
        'monto' => $montoTexto,
        'moneda' => 'BOB',
        'fecha' => $fechaSalida,
    ];

    $qrImagen = '';
    if (class_exists('QRcode') && function_exists('imagepng')) {
        $dirQr = __DIR__ . '/../assets/qr';
        if (is_dir($dirQr) || mkdir($dirQr, 0775, true)) {
            $archivoQr = $dirQr . '/qr_pago.png';
            // Genera el QR localmente (Texto, Archivo, Nivel de correccion, Tamano)
            QRcode::png($mensajeQr, $archivoQr, QR_ECLEVEL_L, 6);
            if (is_file($archivoQr)) {
                $qrImagen = 'assets/qr/qr_pago.png';
            }
        }
    }

    return [
        'referencia' => $referencia,
        'monto' => $totalBs,
        'moneda' => 'BOB',
        'mensaje_qr' => $mensajeQr,
        'qr_imagen' => $qrImagen,
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
        $stmt = $pdo->prepare($sql . 'AND (r.uid_rfid = ? OR r.token_qr = ?) ORDER BY r.id_registro DESC LIMIT 1');
        $stmt->execute([$uidRfid, $uidRfid]);
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

    if ($qr['qr_imagen'] !== '') {
        $eventoPantalla = [
            'evento' => $qr['referencia'],
            'ticket' => (string) $registro['token_qr'],
            'casilla' => (string) ($registro['casilla'] ?? '-'),
            'tiempo' => $liq['tiempo'],
            'total_bs' => number_format((float) $liq['total_bs'], 2, '.', ''),
            'qr_imagen' => $qr['qr_imagen'],
            'creado_en' => time(),
        ];
        file_put_contents(
            __DIR__ . '/../assets/qr/ultimo_pago.json',
            json_encode($eventoPantalla, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
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

    if (($_POST['accion'] ?? '') === 'limpiar_pantalla') {
        $dirQr = __DIR__ . '/../assets/qr';
        if (is_dir($dirQr) || mkdir($dirQr, 0775, true)) {
            file_put_contents($dirQr . '/ultimo_pago.json', '{"evento":""}', LOCK_EX);
        }
        responderSalida('success', 'Pantalla exterior vuelta al mensaje de bienvenida.');
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
