<?php
declare(strict_types=1);

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

$pctOcup = $totalCasillas > 0 ? (($totalCasillas - $conteo['libre']) / $totalCasillas) : 0;
$circ = 282.7;
$dashOff = number_format($circ * $pctOcup, 1, '.', '');
?>
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
