<?php
declare(strict_types=1);

function calcularTiempoTranscurrido(string $fechaIngreso): string
{
    $entrada = new DateTimeImmutable($fechaIngreso);
    $ahora = new DateTimeImmutable('now');
    if ($entrada > $ahora) {
        return '0m';
    }
    $diff = $entrada->diff($ahora);
    $partes = [];
    if ($diff->days > 0) {
        $partes[] = $diff->days . 'd';
    }
    if ($diff->h > 0 || $diff->days > 0) {
        $partes[] = $diff->h . 'h';
    }
    $partes[] = $diff->i . 'm';
    return implode(' ', $partes);
}

$ingresosActivos = [];
$errorListado = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $ingresosActivos = $pdo->query(
            "SELECT r.token_qr, r.uid_rfid, COALESCE(res.placa_vehiculo, 'Sin reserva') AS placa,
                    COALESCE(c.codigo, 'Sin asignar') AS casilla, t.nombre AS tarifa, r.fecha_ingreso
             FROM registros r
             LEFT JOIN casillas c ON c.id_casilla = r.id_casilla
             LEFT JOIN reservas res ON res.id_reserva = r.id_reserva
             INNER JOIN tarifas t ON t.id_tarifa = r.id_tarifa
             WHERE r.estado = 'en_curso'
             ORDER BY r.fecha_ingreso DESC"
        )->fetchAll();
    } catch (PDOException $e) {
        $errorListado = 'Error al consultar los vehículos activos.';
    }
}
?>
<section class="card" aria-labelledby="titulo-ingresos-activos">
    <h2 id="titulo-ingresos-activos">Vehículos en Parqueo (En Curso)</h2>
    <?php if ($errorListado !== null) : ?>
        <p class="alert alert-danger"><?= htmlspecialchars($errorListado, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (empty($ingresosActivos)) : ?>
        <p class="empty">No hay vehículos registrados actualmente en el parqueo.</p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Token / Ticket</th>
                    <th>UID RFID</th>
                    <th>Placa</th>
                    <th>Casilla</th>
                    <th>Tarifa</th>
                    <th>Hora de Entrada</th>
                    <th>Tiempo Transcurrido</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ingresosActivos as $fila) : ?>
                    <tr>
                        <td><span class="badge"><?= htmlspecialchars((string) $fila['token_qr'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><code><?= htmlspecialchars((string) $fila['uid_rfid'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars((string) $fila['placa'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $fila['casilla'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $fila['tarifa'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $fila['fecha_ingreso'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(calcularTiempoTranscurrido((string) $fila['fecha_ingreso']), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
