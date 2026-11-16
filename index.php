<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

$tieneLogicaCroquis = file_exists(__DIR__ . '/controllers/logica_croquis.php');
if ($tieneLogicaCroquis) {
    require_once __DIR__ . '/controllers/logica_croquis.php';
}

const TOTAL_PUESTOS = 6;

$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

$casillas = [];
$casillasLibres = [];
$tarifas = [];
$reservasActivas = [];
$conteo = ['libre' => 0, 'ocupado' => 0, 'reservado' => 0];
$errorBD = null;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $errorBD = 'No se pudo conectar con la base de datos skybox_db.';
} else {
    try {
        $casillas = $pdo->query('SELECT id_casilla, piso, codigo, estado FROM casillas ORDER BY piso ASC, codigo ASC LIMIT ' . TOTAL_PUESTOS)->fetchAll();
        foreach ($casillas as $casilla) {
            $estado = (string) $casilla['estado'];
            if (isset($conteo[$estado])) {
                $conteo[$estado]++;
            }
            if ($estado === 'libre') {
                $casillasLibres[] = $casilla;
            }
        }
        $tarifas = $pdo->query('SELECT id_tarifa, nombre, costo_por_hora FROM tarifas WHERE activa = 1 ORDER BY id_tarifa ASC')->fetchAll();
        $reservasActivas = $pdo->query("SELECT id_reserva, nombre_cliente, codigo_reserva FROM reservas WHERE estado = 'activa' ORDER BY fecha_hora_inicio ASC")->fetchAll();
    } catch (PDOException $e) {
        $errorBD = 'Error al consultar la disponibilidad del parqueo.';
    }
}

$puestos = array_pad($casillas, TOTAL_PUESTOS, null);
$filaSuperior = array_slice($puestos, 0, 3);
$filaInferior = array_slice($puestos, 3, 3);

function h(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function renderPuesto(?array $casilla, int $numero, string $orientacion, bool $interactivo): void
{
    $estado = $casilla['estado'] ?? 'sin_registro';
    $etiquetas = ['libre' => 'Libre', 'ocupado' => 'Ocupado', 'reservado' => 'Reservado', 'sin_registro' => 'Sin registro'];
    $codigo = $casilla !== null ? (string) $casilla['codigo'] : sprintf('P-%02d', $numero);
    $clases = 'puesto puesto-' . $estado . ' puesto-' . $orientacion;

    $contenido = '<span class="puesto-numero">' . sprintf('%02d', $numero) . '</span>';
    if ($estado === 'ocupado') {
        $contenido .= '<svg class="auto" viewBox="0 0 40 70" aria-hidden="true">'
            . '<rect x="3" y="2" width="34" height="66" rx="9" class="auto-carroceria"/>'
            . '<rect x="7" y="13" width="26" height="12" rx="3" class="auto-vidrio"/>'
            . '<rect x="7" y="46" width="26" height="9" rx="3" class="auto-vidrio"/>'
            . '<rect x="9" y="27" width="22" height="17" rx="2" class="auto-techo"/>'
            . '</svg>';
    } elseif ($estado === 'reservado') {
        $contenido .= '<span class="puesto-icono">R</span>';
    }
    $contenido .= '<span class="puesto-codigo">' . h($codigo) . '</span>'
        . '<span class="puesto-estado">' . $etiquetas[$estado] . '</span>';

    if ($interactivo && $casilla !== null) {
        echo '<form action="controllers/logica_croquis.php" method="POST" class="puesto-form">'
            . '<input type="hidden" name="id_casilla" value="' . (int) $casilla['id_casilla'] . '">'
            . '<button type="submit" class="' . $clases . '" title="' . h($codigo) . ': ' . $etiquetas[$estado] . '">' . $contenido . '</button>'
            . '</form>';
        return;
    }

    echo '<div class="' . $clases . '" title="' . h($codigo) . ': ' . $etiquetas[$estado] . '">' . $contenido . '</div>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKY-BOX | Croquis del Estacionamiento</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar bg-dark" data-bs-theme="dark">
        <div class="container-fluid px-3 px-lg-4">
            <span class="navbar-brand fw-bold mb-0">SKY-BOX</span>
            <span class="navbar-text small">Estacionamiento &middot; <?= TOTAL_PUESTOS ?> puestos</span>
        </div>
    </nav>

    <main class="container-fluid px-3 px-lg-4 py-3 py-lg-4">
        <?php if ($errorBD !== null) : ?>
            <div class="alert alert-danger" role="alert"><?= h($errorBD) ?></div>
        <?php endif; ?>

        <?php if (is_array($alerta) && !empty($alerta['mensaje'])) : ?>
            <div class="alert <?= ($alerta['tipo'] ?? '') === 'success' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="status">
                <span><?= h((string) $alerta['mensaje']) ?></span>
                <?php if (!empty($alerta['token_qr'])) : ?>
                    <strong> | Ticket: <?= h((string) $alerta['token_qr']) ?></strong>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 g-lg-4 mb-3 mb-lg-4">
            <div class="col-12 col-xl-8 d-flex flex-column gap-3">
                <section class="row g-3" aria-label="Resumen de disponibilidad">
                    <?php foreach (['libre' => 'Libres', 'ocupado' => 'Ocupados', 'reservado' => 'Reservados'] as $clave => $texto) : ?>
                        <div class="col-12 col-sm-4">
                            <div class="card shadow-sm resumen-item resumen-<?= $clave ?> h-100">
                                <div class="card-body d-flex align-items-center gap-3 py-2 py-md-3">
                                    <strong class="fs-2 lh-1"><?= $conteo[$clave] ?></strong>
                                    <span class="text-body-secondary fw-semibold"><?= $texto ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <section class="card shadow-sm flex-grow-1" aria-labelledby="titulo-croquis">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h2 id="titulo-croquis" class="h5 mb-0">Croquis del Estacionamiento</h2>
                            <ul class="list-inline mb-0 small leyenda" aria-label="Leyenda">
                                <li class="list-inline-item"><span class="punto punto-libre"></span>Libre</li>
                                <li class="list-inline-item"><span class="punto punto-ocupado"></span>Ocupado</li>
                                <li class="list-inline-item"><span class="punto punto-reservado"></span>Reservado</li>
                            </ul>
                        </div>

                        <div class="parqueo" role="img" aria-label="Croquis de 6 puestos con carril central">
                            <div class="fila fila-a">
                                <?php foreach ($filaSuperior as $i => $casilla) : ?>
                                    <?php renderPuesto($casilla, $i + 1, 'arriba', $tieneLogicaCroquis); ?>
                                <?php endforeach; ?>
                            </div>

                            <div class="carril">
                                <span class="acceso">ENTRADA &rarr;</span>
                                <span class="carril-linea" aria-hidden="true"></span>
                                <span class="acceso">&rarr; SALIDA</span>
                            </div>

                            <div class="fila fila-b">
                                <?php foreach ($filaInferior as $i => $casilla) : ?>
                                    <?php renderPuesto($casilla, $i + 4, 'abajo', $tieneLogicaCroquis); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="card shadow-sm h-100" aria-labelledby="titulo-registro">
                    <div class="card-body">
                        <h2 id="titulo-registro" class="h5 card-title mb-3">Registrar Ingreso</h2>
                        <form action="controllers/registrar_ingreso.php" method="POST" class="row g-3" aria-labelledby="titulo-registro">
                            <div class="col-12 col-md-6 col-xl-12">
                                <label for="uid_rfid" class="form-label fw-semibold">UID de Tarjeta RFID *</label>
                                <input type="text" id="uid_rfid" name="uid_rfid" class="form-control" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_-]{3,32}" autocomplete="off" spellcheck="false" placeholder="Ej: RFID-001">
                            </div>

                            <div class="col-12 col-md-6 col-xl-12">
                                <label for="id_casilla" class="form-label fw-semibold">Puesto</label>
                                <select id="id_casilla" name="id_casilla" class="form-select">
                                    <option value="">Asignación automática</option>
                                    <?php foreach ($casillasLibres as $casilla) : ?>
                                        <option value="<?= (int) $casilla['id_casilla'] ?>"><?= h((string) $casilla['codigo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-xl-12">
                                <label for="id_tarifa" class="form-label fw-semibold">Tarifa *</label>
                                <select id="id_tarifa" name="id_tarifa" class="form-select" required>
                                    <?php foreach ($tarifas as $tarifa) : ?>
                                        <option value="<?= (int) $tarifa['id_tarifa'] ?>"><?= h((string) $tarifa['nombre']) ?> (<?= number_format((float) $tarifa['costo_por_hora'], 2) ?> Bs/h)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (!empty($reservasActivas)) : ?>
                                <div class="col-12 col-md-6 col-xl-12">
                                    <label for="id_reserva" class="form-label fw-semibold">Reserva (opcional)</label>
                                    <select id="id_reserva" name="id_reserva" class="form-select">
                                        <option value="">Sin reserva</option>
                                        <?php foreach ($reservasActivas as $res) : ?>
                                            <option value="<?= (int) $res['id_reserva'] ?>"><?= h((string) $res['codigo_reserva']) ?> - <?= h((string) $res['nombre_cliente']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">Registrar Ingreso</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>

        <?php require_once __DIR__ . '/controllers/listar_ingresos.php'; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
