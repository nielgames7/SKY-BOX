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
            "SELECT r.id_registro, r.token_qr, r.uid_rfid, COALESCE(res.placa_vehiculo, 'Sin reserva') AS placa,
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
<section class="card border-0 shadow-sm h-100" aria-labelledby="titulo-ingresos-activos">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 id="titulo-ingresos-activos" class="h5 mb-0">Vehículos en Parqueo (En Curso)</h2>
            <span class="badge text-bg-danger"><?= count($ingresosActivos) ?> activos</span>
        </div>

        <?php if ($errorListado !== null) : ?>
            <div class="alert alert-danger mb-0"><?= htmlspecialchars($errorListado, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif (empty($ingresosActivos)) : ?>
            <p class="text-body-secondary mb-0">No hay vehículos registrados actualmente en el parqueo.</p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ticket</th>
                            <th>UID RFID</th>
                            <th>Placa</th>
                            <th>Puesto</th>
                            <th class="d-none d-md-table-cell">Tarifa</th>
                            <th>Entrada</th>
                            <th>Tiempo</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ingresosActivos as $fila) : ?>
                            <tr>
                                <td><span class="badge text-bg-secondary font-monospace"><?= htmlspecialchars((string) $fila['token_qr'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><code><?= htmlspecialchars((string) $fila['uid_rfid'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= htmlspecialchars((string) $fila['placa'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $fila['casilla'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars((string) $fila['tarifa'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $fila['fecha_ingreso'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars(calcularTiempoTranscurrido((string) $fila['fecha_ingreso']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-nowrap">
                                    <form action="controllers/registrar_salida.php" method="POST" class="d-inline-flex gap-1">
                                        <input type="hidden" name="id_registro" value="<?= (int) $fila['id_registro'] ?>">
                                        <button type="submit" name="metodo_pago" value="qr_transferencia" class="btn btn-sm btn-primary">Cobrar QR</button>
                                        <button type="submit" name="metodo_pago" value="efectivo" class="btn btn-sm btn-outline-secondary">Efectivo</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
