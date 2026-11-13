<?php
/**
 * =====================================================================
 * PROYECTO: Sistema Web Inteligente de Estacionamiento - SkyBox
 * SPRINT 1 - HISTORIA DE USUARIO: HU-01 (Registro y Gestión de Ingresos)
 * ACTIVIDAD / ISSUE #5: Programación de la lógica backend en PHP
 * RESPONSABLE PRINCIPAL: Mirkho (@MRCC-NIX)
 * ARCHIVO: controllers/registrar_ingreso.php
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------
// CONEXIÓN PDO A LA BASE DE DATOS (skybox_db)
// Se enlaza con config/conexion.php desarrollado por Jhoel (Issue #3)
// ---------------------------------------------------------------------
$db = null;

if (file_exists(__DIR__ . '/../config/conexion.php')) {
    require_once __DIR__ . '/../config/conexion.php';
} elseif (file_exists(__DIR__ . '/conexion.php')) {
    require_once __DIR__ . '/conexion.php';
}

// Detectar la variable instanciada por conexion.php ($conexion o $pdo)
if (isset($conexion) && $conexion instanceof PDO) {
    $db = $conexion;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} else {
    // Respaldo para entorno local Laragon mientras Jhoel sube conexion.php
    try {
        $db = new PDO("mysql:host=localhost;dbname=skybox_db;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]);
    } catch (PDOException $e) {
        responderResultado(false, "Error de conexión con la base de datos: " . $e->getMessage());
        exit;
    }
}

// ---------------------------------------------------------------------
// FUNCIÓN AUXILIAR DE RESPUESTA (Soporta redirección HTTP y peticiones AJAX)
// ---------------------------------------------------------------------
function responderResultado(bool $exito, string $mensaje, array $extra = []) {
    // Si la petición es JSON o AJAX (fetch / XMLHttpRequest)
    $esAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    );

    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($exito ? 200 : 400);
        echo json_encode(array_merge([
            'success' => $exito,
            'mensaje' => $mensaje
        ], $extra));
        exit;
    }

    // Modo estándar: Almacenar en sesión y redireccionar a index.php
    $_SESSION['alerta'] = array_merge([
        'tipo'    => $exito ? 'success' : 'danger',
        'mensaje' => $mensaje
    ], $extra);

    $urlRetorno = '../index.php';
    if (!file_exists(__DIR__ . '/../index.php') && file_exists(__DIR__ . '/index.php')) {
        $urlRetorno = 'index.php';
    }

    $param = $exito ? 'status=success' : 'status=error';
    if (!empty($extra['token_qr'])) {
        $param .= '&token=' . urlencode($extra['token_qr']);
    }

    header("Location: {$urlRetorno}?{$param}");
    exit;
}

// ---------------------------------------------------------------------
// 1. VALIDACIÓN DEL MÉTODO DE SOLICITUD
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderResultado(false, "Método de solicitud no permitido. Se requiere POST.");
}

// ---------------------------------------------------------------------
// 2. CAPTURAR Y SANITIZAR DATOS ENVIADOS DESDE EL FORMULARIO
//    Campos recibidos desde la vista index.php de Daniel (Issue #4)
// ---------------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

$postData = is_array($jsonInput) ? $jsonInput : $_POST;

// uid_rfid: Código único leído por el lector RFID RC522 o ingresado en vista
$uid_rfid = isset($postData['uid_rfid']) 
    ? trim(htmlspecialchars(strip_tags((string)$postData['uid_rfid']), ENT_QUOTES, 'UTF-8'))
    : '';

// Si la vista envió campo alternativo 'placa' o 'tarjeta', se unifica
if (empty($uid_rfid) && !empty($postData['placa'])) {
    $uid_rfid = trim(htmlspecialchars(strip_tags((string)$postData['placa']), ENT_QUOTES, 'UTF-8'));
}

// id_casilla: Identificador de la casilla seleccionada
$id_casilla = !empty($postData['id_casilla']) ? filter_var($postData['id_casilla'], FILTER_VALIDATE_INT) : null;

// id_tarifa: Tarifa a aplicar (por defecto 1 = General Vehicular)
$id_tarifa = !empty($postData['id_tarifa']) ? filter_var($postData['id_tarifa'], FILTER_VALIDATE_INT) : 1;

// id_reserva: Opcional, si el cliente tenía reserva previa vía web móvil
$id_reserva = !empty($postData['id_reserva']) ? filter_var($postData['id_reserva'], FILTER_VALIDATE_INT) : null;

// Validación de campo obligatorio
if (empty($uid_rfid)) {
    responderResultado(false, "El código único de tarjeta (UID RFID) o placa es obligatorio.");
}

try {
    // -----------------------------------------------------------------
    // 3. VALIDAR DUPLICADOS: La tarjeta/vehículo no debe tener un ingreso 'en_curso'
    // -----------------------------------------------------------------
    $sqlVerificar = "SELECT id_registro, fecha_ingreso FROM registros WHERE uid_rfid = :uid AND estado = 'en_curso' LIMIT 1";
    $stmtVerificar = $db->prepare($sqlVerificar);
    $stmtVerificar->execute([':uid' => $uid_rfid]);
    $registroActivo = $stmtVerificar->fetch();

    if ($registroActivo) {
        responderResultado(false, "La tarjeta con UID '{$uid_rfid}' ya tiene un ingreso activo en el parqueo desde {$registroActivo['fecha_ingreso']}. No se permiten duplicados sin registrar salida.");
    }

    // -----------------------------------------------------------------
    // 4. VALIDACIÓN DE DISPONIBILIDAD DE CASILLA
    // -----------------------------------------------------------------
    if ($id_casilla) {
        $stmtCasilla = $db->prepare("SELECT id_casilla, codigo, estado, piso FROM casillas WHERE id_casilla = :id LIMIT 1");
        $stmtCasilla->execute([':id' => $id_casilla]);
        $casilla = $stmtCasilla->fetch();

        if (!$casilla) {
            responderResultado(false, "La casilla seleccionada (ID: {$id_casilla}) no existe en el sistema.");
        }

        if ($casilla['estado'] !== 'libre') {
            responderResultado(false, "La casilla {$casilla['codigo']} (Piso {$casilla['piso']}) se encuentra actualmente en estado '{$casilla['estado']}'. Elija una casilla libre.");
        }
    } else {
        // Asignación automática: buscar la primera casilla 'libre' disponible
        $stmtAutoCasilla = $db->query("SELECT id_casilla, codigo FROM casillas WHERE estado = 'libre' ORDER BY piso ASC, codigo ASC LIMIT 1");
        $casillaDisponible = $stmtAutoCasilla->fetch();
        if ($casillaDisponible) {
            $id_casilla = (int)$casillaDisponible['id_casilla'];
        }
    }

    // -----------------------------------------------------------------
    // 5. GENERACIÓN DEL TOKEN QR ÚNICO CRIPTOGRÁFICO
    //    Permite al conductor consultar el estado de su estadía en la web móvil
    // -----------------------------------------------------------------
    $token_qr = bin2hex(random_bytes(16)); // Genera un hash único de 32 caracteres hex

    // -----------------------------------------------------------------
    // 6. EJECUTAR TRANSACCIÓN PDO: REGISTRO + ACTUALIZACIÓN DE ESTADO CASILLA
    // -----------------------------------------------------------------
    $db->beginTransaction();

    $sqlInsert = "
        INSERT INTO registros (
            id_reserva,
            uid_rfid,
            token_qr,
            id_casilla,
            id_tarifa,
            fecha_ingreso,
            estado
        ) VALUES (
            :id_reserva,
            :uid_rfid,
            :token_qr,
            :id_casilla,
            :id_tarifa,
            NOW(),
            'en_curso'
        )
    ";

    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        ':id_reserva' => $id_reserva ?: null,
        ':uid_rfid'   => $uid_rfid,
        ':token_qr'   => $token_qr,
        ':id_casilla' => $id_casilla ?: null,
        ':id_tarifa'  => $id_tarifa ?: 1
    ]);

    $id_nuevo_registro = (int)$db->lastInsertId();

    // Si se asignó casilla, actualizar su estado en tiempo real a 'ocupado'
    if ($id_casilla) {
        $stmtUpdateCasilla = $db->prepare("UPDATE casillas SET estado = 'ocupado' WHERE id_casilla = :id");
        $stmtUpdateCasilla->execute([':id' => $id_casilla]);
    }

    // Confirmar transacción
    $db->commit();

    // -----------------------------------------------------------------
    // 7. RESPUESTA EXITOSA
    // -----------------------------------------------------------------
    responderResultado(true, "¡Ingreso de vehículo registrado exitosamente!", [
        'id_registro' => $id_nuevo_registro,
        'uid_rfid'    => $uid_rfid,
        'token_qr'    => $token_qr,
        'id_casilla'  => $id_casilla,
        'fecha'       => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    responderResultado(false, "Error de base de datos al registrar ingreso: " . $e->getMessage());
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    responderResultado(false, "Error interno en el servidor: " . $e->getMessage());
}
