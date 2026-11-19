<?php
declare(strict_types=1);

$dirQr = __DIR__ . '/assets/qr';
$estadoFile = $dirQr . '/ultimo_pago.json';
if (!is_dir($dirQr)) {
    mkdir($dirQr, 0775, true);
}
if (!is_file($estadoFile)) {
    file_put_contents($estadoFile, '{"evento":""}', LOCK_EX);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKY-BOX | Pantalla Exterior de Salida</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: radial-gradient(circle at top, #1e293b, #0f172a 70%);
            color: #f8fafc;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .panel {
            width: min(480px, 100%);
            background: #ffffff;
            color: #0f172a;
            border-radius: 18px;
            padding: 2.25rem 1.75rem;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
        }
        .marca {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-weight: 800;
            letter-spacing: 0.1em;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        h1 { margin: 0 0 0.5rem; font-size: 1.75rem; }
        .sub { color: #475569; margin: 0 0 1.25rem; font-size: 1rem; line-height: 1.5; }
        .estado-dot {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #15803d;
            background: #dcfce7;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            font-weight: 600;
        }
        .estado-dot::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #16a34a;
        }
        .qr-img {
            width: 240px;
            height: 240px;
            object-fit: contain;
            image-rendering: pixelated;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px;
            background: #ffffff;
            margin: 0.75rem auto 1rem;
            display: block;
        }
        .monto { font-size: 2.25rem; font-weight: 800; color: #0d9488; margin: 0.25rem 0; }
        .detalle {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            color: #334155;
            margin: 0.75rem 0 1rem;
        }
        .cuenta { font-size: 0.82rem; color: #64748b; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <main class="panel">
        <span class="marca">SKY-BOX</span>

        <section id="vista-bienvenida">
            <h1>Bienvenido a SKY-BOX</h1>
            <p class="sub">Estacionamiento Inteligente.<br>Al salir, acerca tu tarjeta RFID o espera al operador para ver tu QR de pago.</p>
            <div class="estado-dot">Pantalla activa &middot; Esperando salida</div>
        </section>

        <section id="vista-qr" hidden aria-live="polite">
            <h1>Escanea para Pagar</h1>
            <img id="qr-img" class="qr-img" src="" alt="Código QR de Pago">
            <div id="qr-monto" class="monto">0.00 Bs</div>
            <div class="detalle">
                <div><strong>Puesto:</strong> <span id="qr-casilla">-</span> &middot; <strong>Estadía:</strong> <span id="qr-tiempo">-</span></div>
                <div><strong>Ticket:</strong> <span id="qr-ticket">-</span></div>
                <div><strong>Ref:</strong> <span id="qr-ref">-</span></div>
            </div>
            <div class="cuenta">Escanea el QR con tu celular &middot; Esperando confirmación del operador</div>
        </section>
    </main>

    <script>
    (() => {
        const welcome = document.getElementById('vista-bienvenida');
        const qrView = document.getElementById('vista-qr');
        const img = document.getElementById('qr-img');
        const monto = document.getElementById('qr-monto');
        const casilla = document.getElementById('qr-casilla');
        const tiempo = document.getElementById('qr-tiempo');
        const ticket = document.getElementById('qr-ticket');
        const ref = document.getElementById('qr-ref');

        let ultimoEvento = '';

        const mostrarBienvenida = () => {
            ultimoEvento = '';
            qrView.hidden = true;
            welcome.hidden = false;
        };

        const mostrarQr = (d) => {
            ultimoEvento = d.evento;
            img.src = d.qr_imagen + '?t=' + Date.now();
            monto.textContent = d.total_bs + ' Bs';
            casilla.textContent = d.casilla;
            tiempo.textContent = d.tiempo;
            ticket.textContent = d.ticket;
            ref.textContent = d.evento;
            welcome.hidden = true;
            qrView.hidden = false;
        };

        const revisar = async () => {
            try {
                const res = await fetch('assets/qr/ultimo_pago.json?t=' + Date.now(), { cache: 'no-store' });
                if (!res.ok) return;
                const d = await res.json();
                if (!d || !d.evento) {
                    if (!qrView.hidden) mostrarBienvenida();
                    return;
                }
                if (d.evento !== ultimoEvento && d.qr_imagen) {
                    mostrarQr(d);
                }
            } catch (_) {}
        };

        revisar();
        setInterval(revisar, 1500);
    })();
    </script>
</body>
</html>
