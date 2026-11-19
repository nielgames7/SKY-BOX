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
            <?= csrfCampo() ?>
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
            <div class="col-12 d-grid">
                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
            </div>
        </form>

        <form data-auth-panel="register" action="controllers/auth.php" method="POST" class="row g-3" hidden>
            <?= csrfCampo() ?>
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
            <?php if ($qrImg !== '' && is_file(__DIR__ . '/../' . $qrImg)) : ?>
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
                    <?= csrfCampo() ?>
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
