<?php
/**
 * =====================================================================
 * PROYECTO: Sistema Web Inteligente de Estacionamiento - SkyBox
 * SPRINT 1 - HU-01: Registro y Gestión de Ingresos de Vehículos
 * RESPONSABLE: Mirkho (@MRCC-NIX)
 * ARCHIVO: controllers/registrar_ingreso.php
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar conexión a la base de datos (desarrollada por Jhoel en config/conexion.php)
if (file_exists(__DIR__ . '/../config/conexion.php')) {
    require_once __DIR__ . '/../config/conexion.php';
} elseif (file_exists(__DIR__ . '/conexion.php')) {
    require_once __DIR__ . '/conexion.php';
} else {
    // Conexión de respaldo por si aún no se ha integrado conexion.php
    try {
        $conexion = new PDO("mysql:host=localhost;dbname=skybox_db;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        $_SESSION['alerta'] = [
            'tipo' => 'danger',
            'mensaje' => 'Error de conexión a la base de datos: ' . $e->getMessage()
        ];
        header("Location: ../index.php");
        exit;
    }
}

// Asegurarse de que $conexion o $pdo esté definido
$db = isset($conexion) ? $conexion : ($pdo ?? null);
if (!$db) {
    $_SESSION['alerta'] = [
        'tipo' => 'danger',
        'mensaje' => 'No se encontró la instancia de conexión PDO.'
    ];
    header("Location: ../index.php");
    exit;
}

// Validar que la petición sea vía POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// 1. Capturar y sanitizar datos enviados desde el formulario (Daniel - index.php)
$uid_rfid    = isset($_POST['uid_rfid']) ? trim(filter_var($_POST['uid_rfid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)) : '';
$id_casilla  = isset($_POST['id_casilla']) ? filter_var($_POST['id_casilla'], FILTER_VALIDATE_INT) : null;
$id_tarifa   = isset($_POST['id_tarifa']) ? filter_var($_POST['id_tarifa'], FILTER_VALIDATE_INT) : null;

// Validar campos obligatorios
if (empty($uid_rfid)) {
    $_SESSION['alerta'] = [
        'tipo' => 'warning',
        'mensaje' => 'El código de tarjeta RFID es obligatorio.'
    ];
    header("Location: ../index.php");
    exit;
}

try {
    // 2. Validar que la tarjeta / vehículo no tenga un ingreso actualmente en curso
    $stmtVerificar = $db->prepare("SELECT id_registro FROM registros WHERE uid_rfid = :uid AND estado = 'en_curso' LIMIT 1");
    $stmtVerificar->execute([':uid' => $uid_rfid]);
    if ($stmtVerificar->fetch()) {
        $_SESSION['alerta'] = [
            'tipo' => 'danger',
            'mensaje' => "La tarjeta o vehículo con UID '{$uid_rfid}' ya cuenta con un ingreso activo en el parqueo."
        ];
        header("Location: ../index.php");
        exit;
    }

    // 3. Validar disponibilidad de la casilla si fue seleccionada
    if ($id_casilla) {
        $stmtCasilla = $db->prepare("SELECT id_casilla, codigo, estado FROM casillas WHERE id_casilla = :id LIMIT 1");
        $stmtCasilla->execute([':id' => $id_casilla]);
        $casilla = $stmtCasilla->fetch();

        if (!$casilla) {
            $_SESSION['alerta'] = [
                'tipo' => 'danger',
                'mensaje' => 'La casilla seleccionada no existe.'
            ];
            header("Location: ../index.php");
            exit;
        }

        if ($casilla['estado'] !== 'libre') {
            $_SESSION['alerta'] = [
                'tipo' => 'warning',
                'mensaje' => "La casilla {$casilla['codigo']} ya se encuentra ocupada o reservada."
            ];
            header("Location: ../index.php");
            exit;
        }
    }

    // 4. Generar token QR criptográfico único (32 caracteres hexadecimales)
    $token_qr = bin2hex(random_bytes(16));

    // 5. Iniciar transacción para garantizar consistencia entre registros y casillas
    $db->beginTransaction();

    $stmtInsert = $db->prepare("
        INSERT INTO registros (
            uid_rfid, 
            token_qr, 
            id_casilla, 
            fecha_ingreso, 
            estado
        ) VALUES (
            :uid_rfid, 
            :token_qr, 
            :id_casilla, 
            NOW(), 
            'en_curso'
        )
    ");

    $stmtInsert->execute([
        ':uid_rfid'   => $uid_rfid,
        ':token_qr'   => $token_qr,
        ':id_casilla' => $id_casilla ?: null
    ]);

    $id_nuevo_registro = $db->lastInsertId();

    // Actualizar estado de la casilla a 'ocupado' si fue asignada
    if ($id_casilla) {
        $stmtUpdateCasilla = $db->prepare("UPDATE casillas SET estado = 'ocupado' WHERE id_casilla = :id");
        $stmtUpdateCasilla->execute([':id' => $id_casilla]);
    }

    $db->commit();

    // 6. Mensaje de éxito y datos para ticket
    $_SESSION['alerta'] = [
        'tipo' => 'success',
        'mensaje' => "¡Ingreso registrado exitosamente! ID Registro #{$id_nuevo_registro}",
        'token_qr' => $token_qr,
        'uid_rfid' => $uid_rfid,
        'id_registro' => $id_nuevo_registro
    ];

    header("Location: ../index.php?status=success&token=" . urlencode($token_qr));
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['alerta'] = [
        'tipo' => 'danger',
        'mensaje' => 'Error al procesar el ingreso: ' . $e->getMessage()
    ];
    header("Location: ../index.php?status=error");
    exit;
}
