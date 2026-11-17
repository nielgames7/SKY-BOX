<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

function obtenerDatosCroquis($pdo)
{
    $sql = 'SELECT id_casilla, piso, codigo, estado FROM casillas ORDER BY piso ASC, codigo ASC';
    $casillas = $pdo->query($sql)->fetchAll();

    $conteo = [
        'libre' => 0,
        'ocupado' => 0,
        'reservado' => 0,
    ];
    $pisos = [];

    foreach ($casillas as $casilla) {
        $estado = $casilla['estado'];
        if (isset($conteo[$estado])) {
            $conteo[$estado]++;
        }
        $pisos[$casilla['piso']][] = $casilla;
    }

    return [
        'conteo' => $conteo,
        'pisos' => $pisos,
    ];
}

function volverCroquis($tipo, $mensaje)
{
    $_SESSION['alerta'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    header('Location: ../index.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (($_SESSION['rol'] ?? '') === 'usuario') {
        volverCroquis('danger', 'Solo lectura: el rol usuario no puede modificar el croquis.');
    }

    if (!$pdo) {
        volverCroquis('danger', 'No hay conexión con la base de datos.');
    }

    $idCasilla = isset($_POST['id_casilla']) ? (int) $_POST['id_casilla'] : 0;
    if ($idCasilla <= 0) {
        volverCroquis('danger', 'Casilla no válida.');
    }

    $consulta = $pdo->prepare('SELECT codigo, estado FROM casillas WHERE id_casilla = ? LIMIT 1');
    $consulta->execute([$idCasilla]);
    $casilla = $consulta->fetch();

    if (!$casilla) {
        volverCroquis('danger', 'La casilla no existe.');
    }

    if ($casilla['estado'] === 'reservado') {
        volverCroquis('danger', 'Casilla ' . $casilla['codigo'] . ' en reserva (se automatizará en Sprint 3).');
    }

    $nuevoEstado = ($casilla['estado'] === 'libre') ? 'ocupado' : 'libre';

    $actualizar = $pdo->prepare('UPDATE casillas SET estado = ? WHERE id_casilla = ?');
    $actualizar->execute([$nuevoEstado, $idCasilla]);

    volverCroquis('success', 'Casilla ' . $casilla['codigo'] . ' marcada como ' . $nuevoEstado . '.');
}
