<?php
/**
 * Generador de código único de ticket y listado dinámico de ingresos en curso.
 * Issue #6 - [HU-01] Generador de codigo unico de ticket y consulta dinamica de vehiculos.
 *
 * Adaptado al esquema real sql/skybox_db.sql:
 * - registros (id_registro, id_reserva, uid_rfid, token_qr, id_casilla, id_tarifa, fecha_ingreso, estado)
 * - casillas (id_casilla, piso, codigo)
 * - reservas (id_reserva, placa_vehiculo, codigo_reserva)
 * - tarifas (id_tarifa, nombre, costo_por_hora)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/conexion.php';

if (!function_exists('esc')) {
    function esc(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generarCodigoTicket')) {
    /**
     * Genera un código único de ticket/token con formato TCK-YYYYMMDD-XXXX.
     * Si se pasa $pdo, verifica en BD que no exista en registros.token_qr.
     */
    function generarCodigoTicket(?PDO $pdo = null, int $maxIntentos = 10): string
    {
        $alfabeto = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $largoAlfabeto = strlen($alfabeto);
        $fecha = date('Ymd');

        for ($intento = 0; $intento < $maxIntentos; $intento++) {
            $sufijo = '';
            for ($i = 0; $i < 4; $i++) {
                $sufijo .= $alfabeto[random_int(0, $largoAlfabeto - 1)];
            }

            $codigo = sprintf('TCK-%s-%s', $fecha, $sufijo);

            if ($pdo === null) {
                return $codigo;
            }

            $stmt = $pdo->prepare('SELECT 1 FROM registros WHERE token_qr = :codigo LIMIT 1');
            $stmt->execute([':codigo' => $codigo]);
            if ($stmt->fetchColumn() === false) {
                return $codigo;
            }
        }

        return sprintf('TCK-%s-%s', $fecha, strtoupper(bin2hex(random_bytes(3))));
    }
}

if (!function_exists('calcularTiempoTranscurrido')) {
    /**
     * Calcula el tiempo transcurrido desde la fecha de ingreso hasta ahora.
     */
    function calcularTiempoTranscurrido(string $fechaIngreso): string
    {
        try {
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
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('obtenerIngresosActivos')) {
    /**
     * Consulta los vehículos actualmente en parqueo (estado = 'en_curso').
     *
     * @return array<int, array<string, mixed>>
     */
    function obtenerIngresosActivos(PDO $pdo): array
    {
        $sql = "
            SELECT
                r.id_registro,
                r.token_qr,
                r.uid_rfid,
                COALESCE(res.placa_vehiculo, 'Sin reserva') AS placa,
                COALESCE(c.codigo, 'Sin asignar') AS casilla,
                c.piso,
                t.nombre AS tarifa,
                t.costo_por_hora,
                r.fecha_ingreso
            FROM registros r
            LEFT JOIN casillas c ON c.id_casilla = r.id_casilla
            LEFT JOIN reservas res ON res.id_reserva = r.id_reserva
            INNER JOIN tarifas t ON t.id_tarifa = r.id_tarifa
            WHERE r.estado = 'en_curso'
            ORDER BY r.fecha_ingreso DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

$scriptActual = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
$esAccesoDirecto = $scriptActual === __FILE__ || $scriptActual === realpath(__DIR__ . '/../listar_ingresos.php');
$errorCarga = null;
$ingresosActivos = [];

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $errorCarga = 'No se pudo establecer conexión con la base de datos.';
} else {
    try {
        $ingresosActivos = obtenerIngresosActivos($pdo);
    } catch (PDOException $e) {
        error_log('[SKY-BOX] Error al listar ingresos activos: ' . $e->getMessage());
        $errorCarga = 'Error al consultar los vehículos activos en parqueo.';
    }
}

if ($esAccesoDirecto) :
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKY-BOX | Vehículos en Parqueo</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 2rem; background: #f5f7fa; color: #1f2937; }
        .card { background: #fff; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .badge { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 9999px; background: #e0f2fe; color: #0369a1; font-size: 0.85rem; font-weight: 600; }
        .alert-error { padding: 0.75rem 1rem; border-radius: 6px; background: #fee2e2; color: #991b1b; }
        .empty { color: #6b7280; padding: 1rem 0; }
    </style>
</head>
<body>
<?php endif; ?>

<section class="card" aria-labelledby="titulo-ingresos-activos">
    <h2 id="titulo-ingresos-activos">Vehículos en Parqueo (En Curso)</h2>

    <?php if ($errorCarga !== null) : ?>
        <p class="alert-error"><?= esc($errorCarga) ?></p>
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
                    <?php
                        $fechaIngreso = (string) ($fila['fecha_ingreso'] ?? '');
                        $horaFormateada = $fechaIngreso !== '' ? date('d/m/Y H:i', strtotime($fechaIngreso)) : '-';
                    ?>
                    <tr>
                        <td><span class="badge"><?= esc((string) ($fila['token_qr'] ?? '')) ?></span></td>
                        <td><code><?= esc((string) ($fila['uid_rfid'] ?? '')) ?></code></td>
                        <td><strong><?= esc((string) ($fila['placa'] ?? '')) ?></strong></td>
                        <td><?= esc((string) ($fila['casilla'] ?? '')) ?></td>
                        <td><?= esc((string) ($fila['tarifa'] ?? '')) ?></td>
                        <td><?= esc($horaFormateada) ?></td>
                        <td><?= esc(calcularTiempoTranscurrido($fechaIngreso)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php if ($esAccesoDirecto) : ?>
</body>
</html>
<?php endif; ?>
