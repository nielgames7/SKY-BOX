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

        <?php require_once __DIR__ . '/../controllers/listar_ingresos.php'; ?>
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
