<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

$casillas = [];
$casillasLibres = [];
$tarifas = [];
$reservasActivas = [];
$conteoEstados = [
    'libre' => 0,
    'ocupado' => 0,
    'reservado' => 0,
];
$errorBD = null;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $errorBD = 'No se pudo conectar con la base de datos skybox_db.';
} else {
    try {
        $casillas = $pdo->query('SELECT id_casilla, piso, codigo, estado FROM casillas ORDER BY piso ASC, codigo ASC')->fetchAll();
        foreach ($casillas as $casilla) {
            $estado = (string) ($casilla['estado'] ?? '');
            if (isset($conteoEstados[$estado])) {
                $conteoEstados[$estado]++;
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKY-BOX | Registro de Ingresos</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <main class="container">
        <header class="page-header">
            <h1>SKY-BOX &mdash; Registro de Ingresos</h1>
            <p class="summary">
                Casillas libres: <strong><?= $conteoEstados['libre'] ?></strong> |
                Ocupadas: <strong><?= $conteoEstados['ocupado'] ?></strong> |
                Reservadas: <strong><?= $conteoEstados['reservado'] ?></strong>
            </p>
        </header>

        <?php if ($errorBD !== null) : ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorBD, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($alerta) && !empty($alerta['mensaje'])) : ?>
            <?php $tipoClase = ($alerta['tipo'] ?? '') === 'success' ? 'alert-success' : 'alert-danger'; ?>
            <div class="alert <?= $tipoClase ?>" role="status" aria-live="polite">
                <span><?= htmlspecialchars((string) $alerta['mensaje'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($alerta['token_qr'])) : ?>
                    <strong> | Ticket: <?= htmlspecialchars((string) $alerta['token_qr'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid-main">
            <section class="card" aria-labelledby="titulo-registro">
                <h2 id="titulo-registro">Registrar Ingreso</h2>
                <form id="form-ingreso" action="controllers/registrar_ingreso.php" method="POST" aria-labelledby="titulo-registro">
                    <div class="form-group">
                        <label for="uid_rfid">Código Único de Tarjeta (UID RFID) *</label>
                        <span id="uid-hint" class="hint">De 3 a 32 caracteres alfanuméricos (ej. RFID-001).</span>
                        <input
                            type="text"
                            id="uid_rfid"
                            name="uid_rfid"
                            class="form-control"
                            required
                            minlength="3"
                            maxlength="32"
                            pattern="[A-Za-z0-9_-]{3,32}"
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="none"
                            spellcheck="false"
                            enterkeyhint="next"
                            aria-describedby="uid-hint"
                            data-error="uid-error"
                            placeholder="Ej: RFID-001"
                        >
                        <div id="uid-error" class="error-msg">
                            Error: Ingrese un código de tarjeta válido (3 a 32 caracteres).
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_casilla">Casilla</label>
                        <select id="id_casilla" name="id_casilla" class="form-control">
                            <option value="">Asignación automática (primera libre)</option>
                            <?php foreach ($casillasLibres as $casilla) : ?>
                                <option value="<?= (int) $casilla['id_casilla'] ?>">
                                    <?= htmlspecialchars((string) $casilla['codigo'], ENT_QUOTES, 'UTF-8') ?> (Piso <?= (int) $casilla['piso'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_tarifa">Tarifa *</label>
                        <select id="id_tarifa" name="id_tarifa" class="form-control" required>
                            <?php if (empty($tarifas)) : ?>
                                <option value="1">Tarifa General Vehicular</option>
                            <?php else : ?>
                                <?php foreach ($tarifas as $tarifa) : ?>
                                    <option value="<?= (int) $tarifa['id_tarifa'] ?>">
                                        <?= htmlspecialchars((string) $tarifa['nombre'], ENT_QUOTES, 'UTF-8') ?> (<?= number_format((float) $tarifa['costo_por_hora'], 2) ?> Bs/h)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if (!empty($reservasActivas)) : ?>
                        <div class="form-group">
                            <label for="id_reserva">Reserva (Opcional)</label>
                            <select id="id_reserva" name="id_reserva" class="form-control">
                                <option value="">Sin reserva</option>
                                <?php foreach ($reservasActivas as $res) : ?>
                                    <option value="<?= (int) $res['id_reserva'] ?>">
                                        <?= htmlspecialchars((string) $res['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string) $res['nombre_cliente'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <button type="submit" id="btn-submit" class="btn">Registrar Ingreso</button>
                </form>
            </section>

            <section class="card" aria-labelledby="titulo-casillas">
                <h2 id="titulo-casillas">Casillas</h2>
                <?php if (empty($casillas)) : ?>
                    <p class="empty">No hay casillas registradas.</p>
                <?php else : ?>
                    <div class="table-wrapper" tabindex="0" role="region" aria-labelledby="titulo-casillas">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Piso</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($casillas as $casilla) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $casilla['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $casilla['piso'] ?></td>
                                        <td><?= htmlspecialchars((string) $casilla['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="table-wrapper" tabindex="0" role="region" aria-labelledby="titulo-ingresos-activos">
            <?php require_once __DIR__ . '/controllers/listar_ingresos.php'; ?>
        </div>
    </main>

    <script>
        const form = document.getElementById('form-ingreso');
        const submitBtn = document.getElementById('btn-submit');

        const linkError = (el, shown) => {
            const errorId = el.dataset.error;
            if (!errorId) return;
            const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== errorId);
            if (shown) ids.push(errorId);
            if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
            else el.removeAttribute('aria-describedby');
        };

        const syncAria = (el) => {
            if (!el.matches?.('input, select')) return;
            const invalid = el.matches(':user-invalid');
            el.setAttribute('aria-invalid', invalid ? 'true' : 'false');
            linkError(el, invalid);
        };

        form.querySelectorAll('input, select').forEach((el) => el.setAttribute('aria-invalid', 'false'));
        form.addEventListener('blur', (e) => syncAria(e.target), true);
        form.addEventListener('input', (e) => syncAria(e.target));
        form.addEventListener('invalid', (e) => syncAria(e.target), true);
        form.addEventListener('submit', () => {
            if (form.checkValidity()) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Registrando...';
            }
        });
    </script>
</body>
</html>
