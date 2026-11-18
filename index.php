<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/controllers/logica_croquis.php';
require_once __DIR__ . '/controllers/logica_alquileres.php';

function h(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function renderPuesto(array $casilla, int $numero, string $orientacion, bool $esAdmin, ?array $ocupante = null, ?array $reserva = null): void
{
    $estado = (string) $casilla['estado'];
    $etiquetas = ['libre' => 'Libre', 'ocupado' => 'Ocupado', 'reservado' => 'Reservado'];
    $codigo = (string) $casilla['codigo'];
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
        . '<span class="puesto-estado">' . ($etiquetas[$estado] ?? h($estado)) . '</span>';

    if ($esAdmin) {
        if ($estado === 'ocupado' && $ocupante) {
            $contenido .= '<span class="puesto-extra font-monospace">' . h((string) $ocupante['uid_rfid']) . '</span>';
        } elseif ($estado === 'reservado' && $reserva) {
            $contenido .= '<span class="puesto-extra">' . h((string) ($reserva['codigo_reserva'] ?? '')) . '</span>';
        }
        echo '<form action="controllers/logica_croquis.php" method="POST" class="puesto-form">'
            . '<input type="hidden" name="id_casilla" value="' . (int) $casilla['id_casilla'] . '">'
            . '<button type="submit" class="' . $clases . '" title="' . h($codigo) . ': ' . ($etiquetas[$estado] ?? h($estado)) . '">' . $contenido . '</button>'
            . '</form>';
        return;
    }

    $attrs = $estado === 'libre'
        ? ' data-pick-casilla="' . (int) $casilla['id_casilla'] . '" data-pick-piso="' . (int) $casilla['piso'] . '"'
        : '';
    echo '<div class="' . $clases . '"' . $attrs . ' title="' . h($codigo) . ': ' . ($etiquetas[$estado] ?? h($estado)) . '">' . $contenido . '</div>';
}

$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

$idUsuarioActual = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : 0;
$nombreUsuarioActual = (string) ($_SESSION['nombre_usuario'] ?? '');
$rolActual = (string) ($_SESSION['rol'] ?? 'usuario');
$esAdmin = in_array($rolActual, ['admin', 'operador'], true);

$datosCroquis = ['conteo' => ['libre' => 0, 'ocupado' => 0, 'reservado' => 0], 'pisos' => []];
$casillasLibres = [];
$tarifas = [];
$reservasActivas = [];
$alquileresVisibles = [];
$salidasRecientes = [];
$usuariosLista = [];
$ocupantesPorCasilla = [];
$reservasPorCasilla = [];
$totalRecaudado = 0.0;
$precioHoraBase = 5.00;
$errorBD = null;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $errorBD = 'No se pudo conectar con la base de datos skybox_db.';
} else {
    try {
        $datosCroquis = obtenerDatosCroquis($pdo);
        $casillasLibres = obtenerCasillasLibres($pdo);
        $tarifas = $pdo->query('SELECT id_tarifa, nombre, costo_por_hora, costo_fraccion, minutos_tolerancia FROM tarifas WHERE activa = 1 ORDER BY id_tarifa ASC')->fetchAll();
        if (!empty($tarifas)) {
            $precioHoraBase = (float) $tarifas[0]['costo_por_hora'];
        }

        if ($esAdmin) {
            $reservasActivas = $pdo->query("SELECT id_reserva, nombre_cliente, codigo_reserva FROM reservas WHERE estado = 'activa' ORDER BY fecha_hora_inicio ASC")->fetchAll();
            $alquileresVisibles = obtenerAlquileres($pdo);

            $activos = $pdo->query("SELECT id_casilla, uid_rfid, fecha_ingreso FROM registros WHERE estado = 'en_curso' AND id_casilla IS NOT NULL")->fetchAll();
            foreach ($activos as $act) {
                $ocupantesPorCasilla[(int) $act['id_casilla']] = $act;
            }

            $resCas = $pdo->query("SELECT id_casilla, codigo_reserva, placa_vehiculo FROM reservas WHERE estado = 'activa'")->fetchAll();
            foreach ($resCas as $rc) {
                $reservasPorCasilla[(int) $rc['id_casilla']] = $rc;
            }

            $salidasRecientes = $pdo->query(
                "SELECT r.token_qr, r.uid_rfid, r.fecha_salida, r.minutos_estadia, r.total_pagado, r.metodo_pago,
                        COALESCE(c.codigo, 'Sin asignar') AS casilla
                 FROM registros r
                 LEFT JOIN casillas c ON c.id_casilla = r.id_casilla
                 WHERE r.estado = 'pagado'
                 ORDER BY r.fecha_salida DESC LIMIT 8"
            )->fetchAll();

            $recaudoReg = (float) $pdo->query("SELECT COALESCE(SUM(total_pagado), 0) FROM registros WHERE estado = 'pagado'")->fetchColumn();
            $recaudoAlq = (float) $pdo->query("SELECT COALESCE(SUM(monto_total), 0) FROM reservas WHERE pagado = 1")->fetchColumn();
            $totalRecaudado = $recaudoReg + $recaudoAlq;

            $usuariosLista = ($rolActual === 'admin')
                ? $pdo->query('SELECT id_usuario, nombre, correo, usuario, rol, activo, creado_en FROM usuarios ORDER BY id_usuario DESC')->fetchAll()
                : [];
        } elseif ($idUsuarioActual > 0) {
            $alquileresVisibles = obtenerAlquileres($pdo, $idUsuarioActual);
        }
    } catch (PDOException $e) {
        $errorBD = 'Error al consultar los datos del estacionamiento.';
    }
}

$conteo = $datosCroquis['conteo'];
$totalCasillas = $conteo['libre'] + $conteo['ocupado'] + $conteo['reservado'];
$ahoraLocal = date('Y-m-d\TH:i');
$dosHorasLocal = date('Y-m-d\TH:i', strtotime('+2 hours'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <script>
    (() => {
        try {
            const s = localStorage.getItem('color-scheme') || 'light dark';
            document.querySelector('meta[name="color-scheme"]').content = s;
            const d = s === 'dark' || (s === 'light dark' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.bsTheme = d ? 'dark' : 'light';
        } catch (_) {}
    })();
    </script>
    <title>SKY-BOX | Estacionamiento Inteligente</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="topbar-skybox sticky-top">
        <div class="container-xxl px-3 px-lg-4 py-2 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2 brand-skybox">
                <span class="fw-black fs-4 tracking-tight">SKY-BOX</span>
            </a>

            <nav class="d-none d-md-flex align-items-center gap-4 small fw-semibold" aria-label="Navegación principal">
                <a href="#seccion-disponibilidad" class="nav-link-sb">Disponibilidad</a>
                <a href="#seccion-alquiler" class="nav-link-sb">Mis Alquileres</a>
                <?php if ($esAdmin) : ?>
                    <a href="#seccion-operacion" class="nav-link-sb">Operación</a>
                    <a href="pantalla_salida.php" target="_blank" rel="noopener" class="nav-link-sb">Pantalla Exterior</a>
                <?php endif; ?>
            </nav>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" id="btn-theme-toggle" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 theme-btn" aria-label="Cambiar tema claro u oscuro">
                    <span class="theme-ico-dark" aria-hidden="true">&#9790;</span>
                    <span class="theme-ico-light" aria-hidden="true">&#9728;</span>
                    <span class="visually-hidden">Alternar modo oscuro</span>
                </button>
                <?php if ($esAdmin) : ?>
                    <span class="badge rounded-pill text-bg-primary">Admin: <?= h($nombreUsuarioActual) ?></span>
                    <a href="controllers/auth.php?accion=logout" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Cerrar Sesión</a>
                <?php elseif ($idUsuarioActual > 0) : ?>
                    <span class="badge rounded-pill text-bg-success">Usuario: <?= h($nombreUsuarioActual) ?></span>
                    <a href="controllers/auth.php?accion=logout" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Cerrar Sesión</a>
                <?php else : ?>
                    <span class="badge rounded-pill text-bg-secondary d-none d-sm-inline-block">Solo Lectura</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-open-auth="login">Iniciar Sesión</button>
                    <button type="button" class="btn btn-sm btn-dark rounded-pill px-3" data-open-auth="register">Registrarse</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container-xxl px-3 px-lg-4 py-3 py-lg-4">
        <?php if ($errorBD !== null) : ?>
            <div class="alert alert-danger" role="alert"><?= h($errorBD) ?></div>
        <?php endif; ?>

        <?php if (is_array($alerta) && !empty($alerta['mensaje'])) : ?>
            <div class="alert <?= ($alerta['tipo'] ?? '') === 'success' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="status">
                <span><?= h((string) $alerta['mensaje']) ?></span>
                <?php if (!empty($alerta['token_qr'])) : ?>
                    <strong class="ms-2 font-monospace">| Ticket: <?= h((string) $alerta['token_qr']) ?></strong>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Barra flotante estilo Movilidad (Piso · Entrada · Salida · Acción) -->
        <section class="pill-searchbar mx-auto mb-4" aria-label="Filtro rápido de disponibilidad">
            <div class="pill-seg">
                <label for="pill-piso" class="pill-lbl">Nivel</label>
                <select id="pill-piso" class="pill-ctrl">
                    <?php for ($p = 1; $p <= 10; $p++) : ?>
                        <option value="<?= $p ?>">Piso <?= $p ?></option>
                    <?php endfor; ?>
                    <option value="todos">Todos los Pisos</option>
                </select>
            </div>
            <div class="pill-seg">
                <label for="pill-inicio" class="pill-lbl">Entrada</label>
                <input type="datetime-local" id="pill-inicio" class="pill-ctrl" value="<?= h($ahoraLocal) ?>">
            </div>
            <div class="pill-seg">
                <label for="pill-fin" class="pill-lbl">Salida</label>
                <input type="datetime-local" id="pill-fin" class="pill-ctrl" value="<?= h($dosHorasLocal) ?>">
            </div>
            <a href="#seccion-alquiler" class="btn pill-cta">Buscar Espacio</a>
        </section>

        <?php
        $pctOcup = $totalCasillas > 0 ? (($totalCasillas - $conteo['libre']) / $totalCasillas) : 0;
        $circ = 282.7;
        $dashOff = number_format($circ * $pctOcup, 1, '.', '');
        ?>

        <!-- Split Principal: Croquis Arquitectónico (Izquierda) + Resumen Tarifa & Anillo (Derecha) -->
        <section id="seccion-disponibilidad" class="row g-3 g-lg-4 mb-4 align-items-stretch" aria-labelledby="titulo-croquis">
            <div class="col-12 col-lg-8">
                <div class="sb-panel h-100 p-3 p-md-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h1 id="titulo-croquis" class="h5 fw-bold mb-0">Plano del Estacionamiento</h1>
                            <p class="small text-body-secondary mb-0">
                                <?= $esAdmin
                                    ? 'Modo Admin: clic en un puesto para cambiar libre/ocupado.'
                                    : 'Vista en tiempo real: selecciona un piso o toca un espacio libre.' ?>
                            </p>
                        </div>
                        <ul class="list-inline mb-0 small leyenda" aria-label="Leyenda">
                            <li class="list-inline-item"><span class="punto punto-libre"></span>Libre</li>
                            <li class="list-inline-item"><span class="punto punto-ocupado"></span>Ocupado</li>
                            <li class="list-inline-item"><span class="punto punto-reservado"></span>Reservado</li>
                        </ul>
                    </div>

                    <div class="floor-pills mb-3" role="group" aria-label="Seleccionar piso">
                        <?php foreach ($datosCroquis['pisos'] as $numPiso => $lista) : ?>
                            <?php $libresPiso = count(array_filter($lista, static fn($c) => $c['estado'] === 'libre')); ?>
                            <button type="button" class="floor-pill" data-piso="<?= (int) $numPiso ?>" aria-pressed="<?= $numPiso === 1 ? 'true' : 'false' ?>">
                                P<?= (int) $numPiso ?> (<?= $libresPiso ?>/6)
                            </button>
                        <?php endforeach; ?>
                        <button type="button" class="floor-pill" data-piso="todos" aria-pressed="false">Todos</button>
                    </div>

                    <div class="pisos-lista">
                        <?php foreach ($datosCroquis['pisos'] as $numPiso => $lista) : ?>
                            <?php
                            $libresPiso = count(array_filter($lista, static fn($c) => $c['estado'] === 'libre'));
                            $filaSuperior = array_slice($lista, 0, 3);
                            $filaInferior = array_slice($lista, 3, 3);
                            ?>
                            <article class="piso-card" data-piso="<?= (int) $numPiso ?>" <?= $numPiso !== 1 ? 'hidden' : '' ?>>
                                <div class="piso-titulo">
                                    <strong><?= $numPiso === 1 ? 'Piso 1 (Planta Baja)' : 'Piso ' . (int) $numPiso ?></strong>
                                    <span class="badge rounded-pill <?= $libresPiso > 0 ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $libresPiso ?>/6 libres</span>
                                </div>

                                <div class="parqueo" role="region" aria-label="Croquis de 6 puestos con carril central del Piso <?= (int) $numPiso ?>">
                                    <div class="fila fila-a">
                                        <?php foreach ($filaSuperior as $i => $cas) : ?>
                                            <?php
                                            $idCas = (int) $cas['id_casilla'];
                                            renderPuesto($cas, $i + 1, 'arriba', $esAdmin, $ocupantesPorCasilla[$idCas] ?? null, $reservasPorCasilla[$idCas] ?? null);
                                            ?>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="carril">
                                        <span class="acceso">ENTRADA &rarr;</span>
                                        <span class="carril-linea" aria-hidden="true"></span>
                                        <span class="acceso">&rarr; SALIDA</span>
                                    </div>

                                    <div class="fila fila-b">
                                        <?php foreach ($filaInferior as $i => $cas) : ?>
                                            <?php
                                            $idCas = (int) $cas['id_casilla'];
                                            renderPuesto($cas, $i + 4, 'abajo', $esAdmin, $ocupantesPorCasilla[$idCas] ?? null, $reservasPorCasilla[$idCas] ?? null);
                                            ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <aside class="sb-panel h-100 p-4 d-flex flex-column justify-content-between text-center" aria-label="Tarifa y disponibilidad">
                    <div>
                        <div class="small text-body-secondary fw-semibold">Tarifa</div>
                        <div class="display-6 fw-black mb-3"><?= number_format($precioHoraBase, 2) ?> Bs/h</div>
                        <hr class="opacity-25 my-3">
                        <div class="small text-body-secondary fw-semibold mb-2">Disponibilidad</div>

                        <div class="ring-wrap mx-auto my-2">
                            <svg viewBox="0 0 110 110" class="ring-svg" aria-hidden="true">
                                <circle cx="55" cy="55" r="45" class="ring-track"></circle>
                                <circle cx="55" cy="55" r="45" class="ring-fill" stroke-dasharray="<?= $circ ?>" stroke-dashoffset="<?= $dashOff ?>" transform="rotate(-90 55 55)"></circle>
                            </svg>
                            <div class="ring-center">
                                <span class="fs-2 fw-black lh-1"><?= $conteo['libre'] ?></span>
                                <span class="small text-body-secondary">libres de <?= $totalCasillas ?></span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-3 small mt-3">
                            <span><strong class="text-danger"><?= $conteo['ocupado'] ?></strong> Ocupados</span>
                            <span><strong class="text-warning-emphasis"><?= $conteo['reservado'] ?></strong> Reservas</span>
                        </div>

                        <?php if ($esAdmin) : ?>
                            <div class="mt-3 p-2 rounded-3 bg-body-tertiary border">
                                <div class="small text-body-secondary">Recaudado Total</div>
                                <div class="fs-5 fw-bold text-primary"><?= number_format($totalRecaudado, 2) ?> Bs</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3 pt-3 border-top d-flex align-items-center justify-content-between text-start gap-2">
                        <div>
                            <div class="fw-bold small">Pago instantáneo</div>
                            <div class="small text-body-secondary">Confirmación con QR</div>
                        </div>
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-body-secondary flex-shrink-0" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
                            <path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 20h2M20 14v2"/>
                        </svg>
                    </div>
                </aside>
            </div>
        </section>

        <section id="seccion-alquiler" class="row g-3 g-lg-4 mb-4" aria-label="Alquiler de puestos">
            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Alquilar Puesto por Horas</h2>
                        <?php if ($idUsuarioActual <= 0) : ?>
                            <p class="text-body-secondary">Para alquilar un puesto disponible y realizar el pago por horas, primero regístrate o inicia sesión.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-primary" data-open-auth="register">Registrarse para Alquilar</button>
                                <button type="button" class="btn btn-outline-secondary" data-open-auth="login">Ya tengo cuenta</button>
                            </div>
                        <?php else : ?>
                            <form action="controllers/logica_alquileres.php" method="POST" class="row g-3">
                                <input type="hidden" name="accion" value="alquilar">
                                <input type="hidden" name="volver" value="index.php">
                                <div class="col-6">
                                    <label for="alq-piso" class="form-label fw-semibold">Piso</label>
                                    <select id="alq-piso" class="form-select">
                                        <?php for ($p = 1; $p <= 10; $p++) : ?>
                                            <option value="<?= $p ?>">Piso <?= $p ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label for="alq-casilla" class="form-label fw-semibold">Puesto libre *</label>
                                    <select id="alq-casilla" name="id_casilla" class="form-select" required>
                                        <?php foreach ($casillasLibres as $cl) : ?>
                                            <option value="<?= (int) $cl['id_casilla'] ?>" data-piso="<?= (int) $cl['piso'] ?>">
                                                <?= h((string) $cl['codigo']) ?> (Piso <?= (int) $cl['piso'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label for="alq-tel" class="form-label fw-semibold">Teléfono</label>
                                    <input type="tel" id="alq-tel" name="telefono" class="form-control" autocomplete="tel" placeholder="71234567">
                                </div>
                                <div class="col-6">
                                    <label for="alq-placa" class="form-label fw-semibold">Placa</label>
                                    <input type="text" id="alq-placa" name="placa_vehiculo" class="form-control" maxlength="15" placeholder="3421-ABC">
                                </div>
                                <div class="col-6">
                                    <label for="alq-inicio" class="form-label fw-semibold">Inicio *</label>
                                    <input type="datetime-local" id="alq-inicio" name="fecha_hora_inicio" class="form-control" value="<?= h($ahoraLocal) ?>" required>
                                </div>
                                <div class="col-6">
                                    <label for="alq-fin" class="form-label fw-semibold">Fin *</label>
                                    <input type="datetime-local" id="alq-fin" name="fecha_hora_fin" class="form-control" value="<?= h($dosHorasLocal) ?>" required>
                                </div>
                                <div class="col-12">
                                    <div class="p-2 rounded bg-body-tertiary border small d-flex justify-content-between">
                                        <span>Total a pagar por tiempo:</span>
                                        <strong id="alq-estimado" data-precio-hora="<?= number_format($precioHoraBase, 2, '.', '') ?>"></strong>
                                    </div>
                                </div>
                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-success" <?= empty($casillasLibres) ? 'disabled' : '' ?>>Alquilar y Pagar Puesto</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><?= $esAdmin ? 'Todos los Alquileres y Reservas' : 'Mis Alquileres' ?></h2>
                        <?php if ($idUsuarioActual <= 0 && !$esAdmin) : ?>
                            <p class="text-body-secondary mb-0">Inicia sesión para consultar tu historial de alquileres pagados.</p>
                        <?php elseif (empty($alquileresVisibles)) : ?>
                            <p class="text-body-secondary mb-0">No hay alquileres registrados todavía.</p>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Código</th>
                                            <?php if ($esAdmin) : ?><th>Cliente</th><?php endif; ?>
                                            <th>Puesto</th>
                                            <th>Placa</th>
                                            <th>Horario</th>
                                            <th>Pagado</th>
                                            <th>Estado</th>
                                            <?php if ($esAdmin) : ?><th>Acción</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alquileresVisibles as $alq) : ?>
                                            <tr>
                                                <td><span class="font-monospace small"><?= h((string) $alq['codigo_reserva']) ?></span></td>
                                                <?php if ($esAdmin) : ?><td><?= h((string) $alq['nombre_cliente']) ?></td><?php endif; ?>
                                                <td>P<?= (int) $alq['piso'] ?> &middot; <?= h((string) $alq['casilla']) ?></td>
                                                <td><?= h((string) ($alq['placa_vehiculo'] ?: '-')) ?></td>
                                                <td class="small text-nowrap"><?= h(date('d/m H:i', strtotime((string) $alq['fecha_hora_inicio']))) ?> &rarr; <?= h(date('d/m H:i', strtotime((string) $alq['fecha_hora_fin']))) ?></td>
                                                <td><strong><?= number_format((float) $alq['monto_total'], 2) ?> Bs</strong></td>
                                                <td><span class="badge <?= $alq['estado'] === 'activa' ? 'text-bg-warning' : 'text-bg-secondary' ?>"><?= h((string) $alq['estado']) ?></span></td>
                                                <?php if ($esAdmin) : ?>
                                                    <td>
                                                        <?php if ($alq['estado'] === 'activa') : ?>
                                                            <form action="controllers/logica_alquileres.php" method="POST">
                                                                <input type="hidden" name="accion" value="cancelar">
                                                                <input type="hidden" name="volver" value="index.php">
                                                                <input type="hidden" name="id_reserva" value="<?= (int) $alq['id_reserva'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">Liberar</button>
                                                            </form>
                                                        <?php else : ?>—<?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($esAdmin) : ?>
            <section id="seccion-operacion" class="row g-3 g-lg-4 mb-4" aria-label="Control operativo de administrador">
                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Registrar Ingreso (RFID)</h2>
                            <form action="controllers/registrar_ingreso.php" method="POST" class="row g-3">
                                <div class="col-12">
                                    <label for="uid_rfid" class="form-label fw-semibold">UID de Tarjeta RFID *</label>
                                    <input type="text" id="uid_rfid" name="uid_rfid" class="form-control" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_-]{3,32}" autocomplete="off" spellcheck="false" placeholder="Ej: RFID-001">
                                </div>
                                <div class="col-12">
                                    <label for="id_casilla" class="form-label fw-semibold">Puesto</label>
                                    <select id="id_casilla" name="id_casilla" class="form-select">
                                        <option value="">Asignación automática</option>
                                        <?php foreach ($casillasLibres as $cl) : ?>
                                            <option value="<?= (int) $cl['id_casilla'] ?>"><?= h((string) $cl['codigo']) ?> (Piso <?= (int) $cl['piso'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="id_tarifa" class="form-label fw-semibold">Tarifa *</label>
                                    <select id="id_tarifa" name="id_tarifa" class="form-select" required>
                                        <?php foreach ($tarifas as $t) : ?>
                                            <option value="<?= (int) $t['id_tarifa'] ?>"><?= h((string) $t['nombre']) ?> (<?= number_format((float) $t['costo_por_hora'], 2) ?> Bs/h)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (!empty($reservasActivas)) : ?>
                                    <div class="col-12">
                                        <label for="id_reserva" class="form-label fw-semibold">Reserva (Opcional)</label>
                                        <select id="id_reserva" name="id_reserva" class="form-select">
                                            <option value="">Sin reserva</option>
                                            <?php foreach ($reservasActivas as $ra) : ?>
                                                <option value="<?= (int) $ra['id_reserva'] ?>"><?= h((string) $ra['codigo_reserva']) ?> - <?= h((string) $ra['nombre_cliente']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-primary">Registrar Ingreso</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-8 d-flex flex-column gap-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <h2 class="h5 mb-0">Escáner RFID de Salida (QR Automático)</h2>
                                <form action="controllers/registrar_salida.php" method="POST" class="d-inline">
                                    <input type="hidden" name="accion" value="limpiar_pantalla">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Quitar QR de Pantalla</button>
                                </form>
                            </div>
                            <form action="controllers/registrar_salida.php" method="POST" class="row g-2 align-items-end">
                                <div class="col-12 col-md-6">
                                    <label for="rfid-salida-scan" class="form-label small fw-semibold mb-1">Pasar tarjeta RFID o escribir UID / Ticket *</label>
                                    <input type="text" id="rfid-salida-scan" name="uid_rfid" class="form-control" required minlength="3" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Acerca tarjeta RFID (Enter automático)">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="rfid-salida-metodo" class="form-label small fw-semibold mb-1">Método</label>
                                    <select id="rfid-salida-metodo" name="metodo_pago" class="form-select">
                                        <option value="qr_transferencia">QR Pago</option>
                                        <option value="efectivo">Efectivo</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3 d-grid">
                                    <button type="submit" class="btn btn-success">Cobrar y Generar QR</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php require_once __DIR__ . '/controllers/listar_ingresos.php'; ?>
                </div>
            </section>

            <section class="row g-3 g-lg-4 mb-4" aria-label="Salidas y tarifas">
                <div class="col-12 col-xl-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Historial de Salidas y Cobros</h2>
                            <?php if (empty($salidasRecientes)) : ?>
                                <p class="text-body-secondary mb-0">Aún no se registraron salidas cobradas.</p>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Ticket</th>
                                                <th>RFID</th>
                                                <th>Puesto</th>
                                                <th>Tiempo</th>
                                                <th>Método</th>
                                                <th>Cobrado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($salidasRecientes as $sal) : ?>
                                                <?php $m = (int) $sal['minutos_estadia']; ?>
                                                <tr>
                                                    <td><span class="font-monospace small"><?= h((string) $sal['token_qr']) ?></span></td>
                                                    <td><code><?= h((string) $sal['uid_rfid']) ?></code></td>
                                                    <td><?= h((string) $sal['casilla']) ?></td>
                                                    <td><?= intdiv($m, 60) > 0 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : ($m % 60) . 'm' ?></td>
                                                    <td><?= h((string) $sal['metodo_pago']) ?></td>
                                                    <td><strong><?= number_format((float) $sal['total_pagado'], 2) ?> Bs</strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Precios y Tarifas (Bs)</h2>
                            <?php foreach ($tarifas as $t) : ?>
                                <form action="controllers/auth.php" method="POST" class="row g-2 align-items-end border rounded p-2 mb-2 bg-body-tertiary">
                                    <input type="hidden" name="accion" value="actualizar_tarifa">
                                    <input type="hidden" name="id_tarifa" value="<?= (int) $t['id_tarifa'] ?>">
                                    <div class="col-12 fw-semibold small"><?= h((string) $t['nombre']) ?></div>
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Bs/Hora</label>
                                        <input type="number" name="costo_por_hora" step="0.50" min="0.50" class="form-control form-control-sm" value="<?= number_format((float) $t['costo_por_hora'], 2, '.', '') ?>" required>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Fracción Bs</label>
                                        <input type="number" name="costo_fraccion" step="0.50" min="0" class="form-control form-control-sm" value="<?= number_format((float) $t['costo_fraccion'], 2, '.', '') ?>" required>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Tol. (min)</label>
                                        <input type="number" name="minutos_tolerancia" min="0" class="form-control form-control-sm" value="<?= (int) $t['minutos_tolerancia'] ?>" required>
                                    </div>
                                    <div class="col-12 d-grid mt-2">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Guardar Precio</button>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($rolActual === 'admin') : ?>
            <section class="row g-3 g-lg-4" aria-label="Gestión de administradores y usuarios">
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Registrar Administrador / Cuenta</h2>
                            <form action="controllers/auth.php" method="POST" class="row g-2">
                                <input type="hidden" name="accion" value="crear_admin">
                                <div class="col-12">
                                    <label for="adm-nombre" class="form-label small fw-semibold mb-1">Nombre completo *</label>
                                    <input type="text" id="adm-nombre" name="nombre" class="form-control form-control-sm" autocomplete="name" required>
                                </div>
                                <div class="col-12">
                                    <label for="adm-correo" class="form-label small fw-semibold mb-1">Correo electrónico *</label>
                                    <input type="email" id="adm-correo" name="correo" class="form-control form-control-sm" spellcheck="false" required>
                                </div>
                                <div class="col-6">
                                    <label for="adm-user" class="form-label small fw-semibold mb-1">Usuario *</label>
                                    <input type="text" id="adm-user" name="usuario" class="form-control form-control-sm" minlength="3" autocomplete="username" spellcheck="false" autocapitalize="none" required>
                                </div>
                                <div class="col-6">
                                    <label for="adm-rol" class="form-label small fw-semibold mb-1">Rol *</label>
                                    <select id="adm-rol" name="rol" class="form-select form-select-sm">
                                        <option value="admin">Administrador</option>
                                        <option value="operador">Operador</option>
                                        <option value="usuario">Usuario</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="adm-pass" class="form-label small fw-semibold mb-1">Contraseña *</label>
                                    <input type="password" id="adm-pass" name="password" class="form-control form-control-sm" minlength="4" autocomplete="new-password" required>
                                </div>
                                <div class="col-12 d-grid mt-2">
                                    <button type="submit" class="btn btn-sm btn-dark">Crear Cuenta</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Usuarios y Roles</h2>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Usuario / Correo</th>
                                            <th>Rol y Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuariosLista as $u) : ?>
                                            <?php $esMiCuenta = ((int) $u['id_usuario'] === $idUsuarioActual); ?>
                                            <tr>
                                                <td><?= h((string) $u['nombre']) ?></td>
                                                <td class="small">
                                                    <code><?= h((string) $u['usuario']) ?></code><br>
                                                    <span class="text-body-secondary"><?= h((string) ($u['correo'] ?: '-')) ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($esMiCuenta) : ?>
                                                        <span class="badge text-bg-primary"><?= h((string) $u['rol']) ?> (Tú)</span>
                                                    <?php else : ?>
                                                        <form action="controllers/auth.php" method="POST" class="d-flex flex-wrap gap-1 align-items-center">
                                                            <input type="hidden" name="accion" value="cambiar_rol">
                                                            <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                                            <select name="rol" class="form-select form-select-sm w-auto" aria-label="Rol de <?= h((string) $u['usuario']) ?>">
                                                                <option value="usuario" <?= $u['rol'] === 'usuario' ? 'selected' : '' ?>>usuario</option>
                                                                <option value="operador" <?= $u['rol'] === 'operador' ? 'selected' : '' ?>>operador</option>
                                                                <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                                            </select>
                                                            <select name="activo" class="form-select form-select-sm w-auto" aria-label="Estado de <?= h((string) $u['usuario']) ?>">
                                                                <option value="1" <?= (int) $u['activo'] === 1 ? 'selected' : '' ?>>Activo</option>
                                                                <option value="0" <?= (int) $u['activo'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <dialog id="modal-auth" class="auth-dialog" closedby="any" aria-labelledby="titulo-modal-auth">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-body-tertiary">
            <h2 id="titulo-modal-auth" class="h5 mb-0">Acceso a SKY-BOX</h2>
            <form method="dialog">
                <button type="submit" class="btn-close" aria-label="Cerrar"></button>
            </form>
        </div>
        <div class="p-3">
            <div class="btn-group w-100 mb-3" role="tablist">
                <button type="button" class="btn btn-outline-primary active" data-auth-tab="login" aria-selected="true">Iniciar Sesión</button>
                <button type="button" class="btn btn-outline-primary" data-auth-tab="register" aria-selected="false">Registrarse</button>
            </div>

            <form data-auth-panel="login" action="controllers/auth.php" method="POST" class="row g-3">
                <input type="hidden" name="accion" value="login">
                <div class="col-12">
                    <label for="login-id" class="form-label fw-semibold">Usuario o Correo *</label>
                    <input type="text" id="login-id" name="identificador" class="form-control" autocomplete="username" spellcheck="false" autocapitalize="none" autocorrect="off" required>
                </div>
                <div class="col-12">
                    <label for="login-pass" class="form-label fw-semibold">Contraseña *</label>
                    <div class="input-group">
                        <input type="password" id="login-pass" name="password" class="form-control" autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary" data-toggle-pass="login-pass">Ver</button>
                    </div>
                </div>
                <div class="col-12 small text-body-secondary">
                    Demo Admin: <code>admin</code> / <code>admin123</code> &middot; Demo Usuario: <code>cliente1</code> / <code>admin123</code>
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                </div>
            </form>

            <form data-auth-panel="register" action="controllers/auth.php" method="POST" class="row g-3" hidden>
                <input type="hidden" name="accion" value="registrar">
                <div class="col-12">
                    <label for="reg-nombre" class="form-label fw-semibold">Nombre Completo *</label>
                    <input type="text" id="reg-nombre" name="nombre" class="form-control" autocomplete="name" required>
                </div>
                <div class="col-12">
                    <label for="reg-correo" class="form-label fw-semibold">Correo Electrónico *</label>
                    <input type="email" id="reg-correo" name="correo" class="form-control" autocomplete="username" spellcheck="false" required>
                </div>
                <div class="col-12">
                    <label for="reg-usuario" class="form-label fw-semibold">Usuario *</label>
                    <input type="text" id="reg-usuario" name="usuario" class="form-control" minlength="3" autocomplete="username" spellcheck="false" autocapitalize="none" autocorrect="off" required>
                </div>
                <div class="col-12">
                    <label for="reg-pass" class="form-label fw-semibold">Contraseña *</label>
                    <div class="input-group">
                        <input type="password" id="reg-pass" name="password" class="form-control" minlength="4" autocomplete="new-password" required>
                        <button type="button" class="btn btn-outline-secondary" data-toggle-pass="reg-pass">Ver</button>
                    </div>
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" class="btn btn-success">Crear Cuenta</button>
                </div>
            </form>
        </div>
    </dialog>

    <?php if ($esAdmin && !empty($alerta['salida']['qr_simulado'])) : ?>
        <?php
        $sal = $alerta['salida'];
        $qrSim = $sal['qr_simulado'];
        $qrImg = (string) ($qrSim['qr_imagen'] ?? '');
        ?>
        <dialog id="modal-qr-salida" class="auth-dialog qr-dialog" closedby="any" data-auto-open="true" aria-labelledby="titulo-modal-qr">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-body-tertiary">
                <h2 id="titulo-modal-qr" class="h5 mb-0">Comprobante de Pago QR</h2>
                <form method="dialog">
                    <button type="submit" class="btn-close" aria-label="Cerrar"></button>
                </form>
            </div>
            <div class="p-4 text-center">
                <?php if ($qrImg !== '' && is_file(__DIR__ . '/' . $qrImg)) : ?>
                    <img src="<?= h($qrImg) ?>?t=<?= time() ?>" alt="QR Local de Pago" class="qr-img-local mx-auto mb-3">
                <?php endif; ?>
                <div class="fs-4 fw-bold text-primary mb-1"><?= number_format((float) $sal['total_pagado'], 2) ?> Bs</div>
                <div class="small text-body-secondary mb-2">Estadía: <strong><?= h((string) $sal['tiempo']) ?></strong> &middot; Puesto: <strong><?= h((string) ($sal['casilla'] ?? '-')) ?></strong></div>
                <div class="small bg-body-tertiary border rounded p-2 text-start mb-3">
                    <div><strong>Ticket:</strong> <span class="font-monospace"><?= h((string) $sal['token_qr']) ?></span></div>
                    <div><strong>RFID:</strong> <code><?= h((string) $sal['uid_rfid']) ?></code></div>
                    <div><strong>Referencia:</strong> <code><?= h((string) $qrSim['referencia']) ?></code></div>
                </div>
                <div class="d-grid gap-2">
                    <form action="controllers/registrar_salida.php" method="POST" class="d-grid">
                        <input type="hidden" name="accion" value="limpiar_pantalla">
                        <button type="submit" class="btn btn-primary">Confirmar Pago y Quitar QR Exterior</button>
                    </form>
                    <form method="dialog" class="d-grid">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Cerrar Solo Esta Ventana</button>
                    </form>
                </div>
            </div>
        </dialog>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
