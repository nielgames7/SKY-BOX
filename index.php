<?php
/**
 * =====================================================================
 * PROYECTO: Sistema Web Inteligente de Estacionamiento - SkyBox
 * SPRINT 1 - HU-01: Registro y Gestión de Ingresos de Vehículos
 * VISTA: Portería y Registro de Ingresos
 * RESPONSABLE: Daniel (@nielgames7) / Vista Principal
 * ARCHIVO: index.php
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

// Obtener alerta en sesión si existe
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

// Consultar casillas disponibles (estado 'libre') agrupadas por piso
try {
    $stmtCasillas = $conexion->query("SELECT id_casilla, codigo, piso, estado FROM casillas WHERE estado = 'libre' ORDER BY piso ASC, codigo ASC");
    $casillasLibres = $stmtCasillas->fetchAll();

    // Consultar tarifas activas
    $stmtTarifas = $conexion->query("SELECT id_tarifa, nombre, costo_por_hora, costo_fraccion FROM tarifas WHERE activa = 1 ORDER BY id_tarifa ASC");
    $tarifas = $stmtTarifas->fetchAll();

    // Métricas rápidas para el panel
    $totalCasillas = (int)$conexion->query("SELECT COUNT(*) FROM casillas")->fetchColumn();
    $libresCount   = (int)$conexion->query("SELECT COUNT(*) FROM casillas WHERE estado = 'libre'")->fetchColumn();
    $ocupadasCount = (int)$conexion->query("SELECT COUNT(*) FROM casillas WHERE estado = 'ocupado'")->fetchColumn();
    $autosActivos  = (int)$conexion->query("SELECT COUNT(*) FROM registros WHERE estado = 'en_curso'")->fetchColumn();

} catch (PDOException $e) {
    $errorBD = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyBox | Registro de Ingresos de Vehículos</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --skybox-primary: #1e3a8a;
            --skybox-accent: #0284c7;
            --skybox-dark: #0f172a;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }
        .navbar-skybox {
            background: linear-gradient(135deg, var(--skybox-primary), var(--skybox-dark));
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card {
            border-left: 5px solid var(--skybox-accent);
        }
        .badge-slot {
            font-size: 0.9rem;
            padding: 6px 12px;
            border-radius: 8px;
        }
        .ticket-box {
            background: #fff;
            border: 2px dashed #0284c7;
            border-radius: 12px;
            padding: 20px;
        }
        .btn-quick-rfid {
            font-size: 0.75rem;
            padding: 3px 8px;
            margin: 2px;
        }
    </style>
</head>
<body>

<!-- BARRA DE NAVEGACIÓN -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-skybox py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <i class="fa-solid fa-square-parking fs-3 text-info"></i>
            <span>SKY-BOX <span class="badge bg-info text-dark fs-6 fw-normal">Smart IoT</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="index.php">
                        <i class="fa-solid fa-plus-circle me-1"></i> Registrar Ingreso
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="listar_ingresos.php">
                        <i class="fa-solid fa-car-side me-1"></i> Vehículos en Parqueo
                        <?php if (!empty($autosActivos)): ?>
                            <span class="badge bg-danger rounded-pill"><?= $autosActivos ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- CONTENEDOR PRINCIPAL -->
<div class="container py-4">

    <!-- MENSAJES DE ALERTA -->
    <?php if ($alerta): ?>
        <div class="alert alert-<?= htmlspecialchars($alerta['tipo']) ?> alert-dismissible fade show card-custom" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php if ($alerta['tipo'] === 'success'): ?>
                    <i class="fa-solid fa-circle-check fs-4"></i>
                <?php else: ?>
                    <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                <?php endif; ?>
                <div>
                    <strong><?= htmlspecialchars($alerta['mensaje']) ?></strong>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <!-- TICKET GENERADO (SI FUE EXITOSO) -->
        <?php if (!empty($alerta['token_qr'])): ?>
            <div class="card card-custom ticket-box mb-4 animate__animated animate__fadeIn">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="fw-bold text-primary mb-2">
                            <i class="fa-solid fa-ticket me-2"></i> Ticket de Ingreso Generado #<?= htmlspecialchars($alerta['id_registro'] ?? '') ?>
                        </h5>
                        <p class="mb-1"><strong>Tarjeta / UID RFID:</strong> <code><?= htmlspecialchars($alerta['uid_rfid'] ?? '') ?></code></p>
                        <p class="mb-1"><strong>Token QR Consulta Móvil:</strong> <code class="text-break"><?= htmlspecialchars($alerta['token_qr']) ?></code></p>
                        <p class="mb-0 text-muted small"><i class="fa-regular fa-clock me-1"></i> Ingreso registrado: <?= htmlspecialchars($alerta['fecha'] ?? date('Y-m-d H:i:s')) ?></p>
                    </div>
                    <div class="col-md-4 text-center mt-3 mt-md-0 border-start">
                        <div class="p-2 bg-light rounded d-inline-block">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode($alerta['token_qr']) ?>" alt="QR Ticket" class="img-fluid" style="width: 110px; height: 110px;">
                        </div>
                        <div class="mt-2">
                            <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-print me-1"></i> Imprimir Ticket
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TARJETAS DE MÉTRICAS RÁPIDAS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-custom stat-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">TOTAL CASILLAS</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $totalCasillas ?? 0 ?></h3>
                    </div>
                    <i class="fa-solid fa-warehouse fs-2 text-primary opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-custom stat-card p-3 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">DISPONIBLES</div>
                        <h3 class="fw-bold mb-0 text-success"><?= $libresCount ?? 0 ?></h3>
                    </div>
                    <i class="fa-solid fa-square-check fs-2 text-success opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-custom stat-card p-3 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">OCUPADAS</div>
                        <h3 class="fw-bold mb-0 text-warning"><?= $ocupadasCount ?? 0 ?></h3>
                    </div>
                    <i class="fa-solid fa-car-tunnel fs-2 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-custom stat-card p-3 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">EN PARQUEO</div>
                        <h3 class="fw-bold mb-0 text-danger"><?= $autosActivos ?? 0 ?></h3>
                    </div>
                    <i class="fa-solid fa-stopwatch fs-2 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO DE REGISTRO -->
    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <div class="card card-custom p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div class="bg-primary text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-id-card fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">Módulo de Ingreso de Vehículos</h4>
                        <small class="text-muted">HU-01: Captura de tarjeta RFID / placa y asignación de espacio</small>
                    </div>
                </div>

                <form action="controllers/registrar_ingreso.php" method="POST" id="formIngreso">
                    <!-- 1. Tarjeta RFID o Placa -->
                    <div class="mb-3">
                        <label for="uid_rfid" class="form-label fw-semibold">
                            <i class="fa-solid fa-rss text-info me-1"></i> Código de Tarjeta RFID (RC522) o Placa: <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-microchip"></i></span>
                            <input type="text" 
                                   class="form-control form-control-lg font-monospace text-uppercase" 
                                   id="uid_rfid" 
                                   name="uid_rfid" 
                                   placeholder="Ej: A4-32-F1-08 o 3421-ABC" 
                                   required 
                                   autofocus>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-1 mt-2">
                            <small class="text-muted me-2">Simular lectura RFID:</small>
                            <button type="button" class="btn btn-outline-secondary btn-quick-rfid" onclick="setRfid('A4-9F-21-B3')">A4-9F-21-B3</button>
                            <button type="button" class="btn btn-outline-secondary btn-quick-rfid" onclick="setRfid('8C-12-E5-7A')">8C-12-E5-7A</button>
                            <button type="button" class="btn btn-outline-secondary btn-quick-rfid" onclick="setRfid('F2-77-90-1C')">F2-77-90-1C</button>
                            <button type="button" class="btn btn-outline-secondary btn-quick-rfid" onclick="setRfid('4589-KLP')">Placa: 4589-KLP</button>
                        </div>
                    </div>

                    <!-- 2. Selector de Casilla -->
                    <div class="mb-3">
                        <label for="id_casilla" class="form-label fw-semibold">
                            <i class="fa-solid fa-square-parking text-primary me-1"></i> Asignación de Casilla / Espacio:
                        </label>
                        <select class="form-select form-select-lg" id="id_casilla" name="id_casilla">
                            <option value="">✨ Asignación Automática Inteligente (Primera libre)</option>
                            <?php if (!empty($casillasLibres)): ?>
                                <optgroup label="Casillas Libres Disponibles">
                                    <?php foreach ($casillasLibres as $c): ?>
                                        <option value="<?= $c['id_casilla'] ?>">
                                            Casilla <?= htmlspecialchars($c['codigo']) ?> — Nivel / Piso <?= $c['piso'] ?> (Libre)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php else: ?>
                                <option value="" disabled>⚠️ No hay casillas libres en este momento</option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Si dejas la opción automática, el sistema elegirá el espacio más cercano libre.</div>
                    </div>

                    <!-- 3. Selector de Tarifa -->
                    <div class="mb-4">
                        <label for="id_tarifa" class="form-label fw-semibold">
                            <i class="fa-solid fa-tag text-success me-1"></i> Tarifa Aplicable:
                        </label>
                        <select class="form-select" id="id_tarifa" name="id_tarifa">
                            <?php foreach ($tarifas as $t): ?>
                                <option value="<?= $t['id_tarifa'] ?>">
                                    <?= htmlspecialchars($t['nombre']) ?> — <?= number_format($t['costo_por_hora'], 2) ?> Bs/Hora (Fracción: <?= number_format($t['costo_fraccion'], 2) ?> Bs)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Botón de Envío -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold py-3 shadow-sm">
                            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Confirmar y Registrar Ingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PANEL LATERAL INFORMATIVO -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-layer-group text-primary me-2"></i> Estado de Pisos</h5>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fa-solid fa-arrow-up-right-dots text-info me-2"></i> Planta Baja (PB)</span>
                        <span class="badge bg-primary rounded-pill">Sensores FC-51</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fa-solid fa-building text-info me-2"></i> Piso 2</span>
                        <span class="badge bg-secondary rounded-pill">ESP32 IoT</span>
                    </li>
                </ul>
                <div class="mt-3">
                    <a href="listar_ingresos.php" class="btn btn-outline-dark btn-sm w-100">
                        <i class="fa-solid fa-list-check me-1"></i> Ver Tabla de Vehículos Activos
                    </a>
                </div>
            </div>

            <div class="card card-custom p-4 bg-light border-0">
                <h6 class="fw-bold text-muted mb-2"><i class="fa-solid fa-info-circle me-1"></i> Información del Sprint 1</h6>
                <p class="small text-muted mb-1"><strong>HU-01:</strong> Registro y Gestión de Ingresos de Vehículos.</p>
                <p class="small text-muted mb-0">Cada ingreso genera un <strong>Token QR</strong> único para consultar el estado del vehículo en la web móvil.</p>
            </div>
        </div>
    </div>

</div>

<!-- FOOTER -->
<footer class="text-center py-4 text-muted small mt-5 border-top bg-white">
    <div class="container">
        SkyBox Parking Inteligente &copy; <?= date('Y') ?> — Desarrollo de Software Agile (Sprint 1)
    </div>
</footer>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function setRfid(valor) {
        document.getElementById('uid_rfid').value = valor;
        document.getElementById('uid_rfid').focus();
    }
</script>
</body>
</html>
