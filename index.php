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

$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

$idUsuarioActual = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : 0;
$nombreUsuarioActual = trim(str_replace('(Demo Usuario)', '', (string) ($_SESSION['nombre_usuario'] ?? '')));
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

            foreach ($pdo->query("SELECT id_casilla, uid_rfid, fecha_ingreso FROM registros WHERE estado = 'en_curso' AND id_casilla IS NOT NULL") as $act) {
                $ocupantesPorCasilla[(int) $act['id_casilla']] = $act;
            }
            foreach ($pdo->query("SELECT id_casilla, codigo_reserva, placa_vehiculo FROM reservas WHERE estado = 'activa'") as $rc) {
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

            $totalRecaudado = (float) $pdo->query("SELECT COALESCE(SUM(total_pagado), 0) FROM registros WHERE estado = 'pagado'")->fetchColumn()
                + (float) $pdo->query("SELECT COALESCE(SUM(monto_total), 0) FROM reservas WHERE pagado = 1")->fetchColumn();

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
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= file_exists(__DIR__ . '/assets/css/styles.css') ? filemtime(__DIR__ . '/assets/css/styles.css') : time() ?>">
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
                <button type="button" id="btn-theme-toggle" class="btn btn-sm btn-outline-secondary theme-btn" aria-label="Cambiar tema claro u oscuro">
                    <svg class="theme-ico-dark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="theme-ico-light" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="5"/>
                        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                    </svg>
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
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-open-auth="register">Registrarse</button>
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

        <?php require __DIR__ . '/views/croquis.php'; ?>
        <?php require __DIR__ . '/views/alquileres.php'; ?>
        <?php if ($esAdmin) { require __DIR__ . '/views/panel_admin.php'; } ?>
    </main>

    <?php require __DIR__ . '/views/modales.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
