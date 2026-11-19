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
                        <?= csrfCampo() ?>
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
                                                        <?= csrfCampo() ?>
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
