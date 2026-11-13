<?php
/**
 * =====================================================================
 * PROYECTO: Sistema Web Inteligente de Estacionamiento - SkyBox
 * SPRINT 1 - HU-01: Registro y Gestión de Ingresos de Vehículos
 * VISTA Y CONSULTA: Listado Dinámico de Vehículos en Parqueo
 * RESPONSABLE: Jhoel (@jhoelito19) / Issue #6
 * ARCHIVO: listar_ingresos.php
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

try {
    // Consulta dinámica con PDO: JOIN entre registros, casillas y tarifas
    $sql = "
        SELECT 
            r.id_registro,
            r.uid_rfid,
            r.token_qr,
            r.fecha_ingreso,
            r.estado,
            c.codigo AS codigo_casilla,
            c.piso,
            t.nombre AS nombre_tarifa,
            t.costo_por_hora,
            TIMESTAMPDIFF(MINUTE, r.fecha_ingreso, NOW()) AS minutos_transcurridos
        FROM registros r
        LEFT JOIN casillas c ON r.id_casilla = c.id_casilla
        LEFT JOIN tarifas t ON r.id_tarifa = t.id_tarifa
        WHERE r.estado = 'en_curso'
        ORDER BY r.fecha_ingreso DESC
    ";

    $stmt = $conexion->query($sql);
    $ingresosActivos = $stmt->fetchAll();

    // Contadores para métricas
    $totalVehiculos = count($ingresosActivos);
    $totalCasillas = (int)$conexion->query("SELECT COUNT(*) FROM casillas")->fetchColumn();
    $libresCount   = (int)$conexion->query("SELECT COUNT(*) FROM casillas WHERE estado = 'libre'")->fetchColumn();

} catch (PDOException $e) {
    $errorBD = $e->getMessage();
}

// Función auxiliar para formatear tiempo transcurrido
function formatearTiempo($minutos) {
    $minutos = max(0, (int)$minutos);
    $horas = floor($minutos / 60);
    $restoMin = $minutos % 60;
    if ($horas > 0) {
        return "{$horas}h {$restoMin}m";
    }
    return "{$restoMin} min";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyBox | Vehículos Actualmente en Parqueo</title>
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
        }
        .table-custom thead th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            border: none;
        }
        .badge-casilla {
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
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
                    <a class="nav-link fw-semibold" href="index.php">
                        <i class="fa-solid fa-plus-circle me-1"></i> Registrar Ingreso
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="listar_ingresos.php">
                        <i class="fa-solid fa-car-side me-1"></i> Vehículos en Parqueo
                        <span class="badge bg-danger rounded-pill"><?= $totalVehiculos ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- CONTENIDO PRINCIPAL -->
<div class="container py-4">

    <!-- ENCABEZADO Y ACCIONES -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-car-tunnel text-primary me-2"></i> Vehículos Actualmente en Parqueo</h3>
            <p class="text-muted small mb-0">HU-01 / Issue #6: Monitoreo en tiempo real de estancias activas y tokens QR</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-primary shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> Nuevo Ingreso
            </a>
            <button onclick="location.reload()" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrows-rotate"></i> Actualizar
            </button>
        </div>
    </div>

    <!-- TARJETAS DE RESUMEN -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-custom p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">VEHÍCULOS EN PARQUEO</div>
                        <h2 class="fw-bold text-danger mb-0"><?= $totalVehiculos ?></h2>
                    </div>
                    <i class="fa-solid fa-car fs-1 text-danger opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">CASILLAS DISPONIBLES</div>
                        <h2 class="fw-bold text-success mb-0"><?= $libresCount ?></h2>
                    </div>
                    <i class="fa-solid fa-square-parking fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">CAPACIDAD TOTAL</div>
                        <h2 class="fw-bold text-primary mb-0"><?= $totalCasillas ?></h2>
                    </div>
                    <i class="fa-solid fa-building fs-1 text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE VEHÍCULOS ACTIVOS -->
    <div class="card card-custom p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h5 class="fw-bold mb-0">Listado de Registros en Curso</h5>
            <div style="min-width: 250px;">
                <input type="text" id="filtroTabla" class="form-control form-control-sm" placeholder="🔍 Filtrar por tarjeta, casilla o ticket...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom" id="tablaVehiculos">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Tarjeta / Placa (RFID)</th>
                        <th>Casilla Asignada</th>
                        <th>Tarifa</th>
                        <th>Fecha y Hora Ingreso</th>
                        <th>Tiempo Transcurrido</th>
                        <th>Token QR Móvil</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ingresosActivos)): ?>
                        <?php foreach ($ingresosActivos as $item): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark fw-normal">#<?= htmlspecialchars($item['id_registro']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold font-monospace text-primary">
                                        <i class="fa-solid fa-id-card text-muted me-1"></i><?= htmlspecialchars($item['uid_rfid']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['codigo_casilla']): ?>
                                        <span class="badge-casilla">
                                            <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($item['codigo_casilla']) ?> (Piso <?= $item['piso'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin Casilla</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="fw-semibold text-secondary">
                                        <?= htmlspecialchars($item['nombre_tarifa'] ?? 'General') ?>
                                    </small>
                                </td>
                                <td>
                                    <small><i class="fa-regular fa-clock text-muted me-1"></i><?= htmlspecialchars($item['fecha_ingreso']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark fw-bold">
                                        <i class="fa-solid fa-hourglass-half me-1"></i><?= formatearTiempo($item['minutos_transcurridos']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info text-dark" 
                                            onclick="mostrarModalQR('<?= htmlspecialchars($item['token_qr']) ?>', '<?= htmlspecialchars($item['uid_rfid']) ?>')">
                                        <i class="fa-solid fa-qrcode me-1"></i> Ver QR
                                    </button>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <i class="fa-solid fa-circle-dot me-1"></i> En Curso
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-car fs-1 opacity-25 d-block mb-3"></i>
                                <h6>No hay vehículos actualmente estacionados en el parqueo.</h6>
                                <p class="small mb-0">Usa el módulo de ingreso para registrar una nueva entrada.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL VISUALIZADOR DE QR -->
<div class="modal fade" id="modalQR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-qrcode me-2"></i> Token QR Consulta Web Móvil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="text-muted small mb-3">Tarjeta / Placa: <strong id="modalUid"></strong></p>
                <div class="p-3 bg-light rounded d-inline-block shadow-sm">
                    <img id="modalQrImg" src="" alt="QR" style="width: 160px; height: 160px;">
                </div>
                <div class="mt-3">
                    <code id="modalTokenCode" class="text-break small d-block"></code>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Filtro en vivo para la tabla
    document.getElementById('filtroTabla').addEventListener('keyup', function() {
        const busqueda = this.value.toLowerCase();
        const filas = document.querySelectorAll('#tablaVehiculos tbody tr');
        filas.forEach(fila => {
            const texto = fila.textContent.toLowerCase();
            fila.style.display = texto.includes(busqueda) ? '' : 'none';
        });
    });

    // Modal para visualizar QR
    const modalQR = new bootstrap.Modal(document.getElementById('modalQR'));
    function mostrarModalQR(token, uid) {
        document.getElementById('modalUid').innerText = uid;
        document.getElementById('modalTokenCode').innerText = token;
        document.getElementById('modalQrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(token);
        modalQR.show();
    }
</script>
</body>
</html>
