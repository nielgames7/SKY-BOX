import sys
import time
import json
import urllib.request
import urllib.error
import serial
import serial.tools.list_ports

URLS_SERVIDORES = [
    "http://192.168.56.101/controllers/api_iot.php",
]


def detectar_puerto():
    puertos = list(serial.tools.list_ports.comports())
    if not puertos:
        return None
    for p in puertos:
        desc = f"{p.description} {p.manufacturer or ''}".lower()
        if any(k in desc for k in ("arduino", "ch340", "usb-serial", "usb serial", "cp210")):
            return p.device
    return puertos[0].device


def enviar_evento(urls, payload):
    data = json.dumps(payload).encode("utf-8")
    for idx, url in enumerate(urls):
        req = urllib.request.Request(
            url,
            data=data,
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        etiqueta = "LARAGON (PC)" if "localhost" in url else "UBUNTU (VM)"
        try:
            with urllib.request.urlopen(req, timeout=2.5) as resp:
                body = json.loads(resp.read().decode("utf-8"))
                print(f"[{etiqueta} OK] {body.get('mensaje', body)}", flush=True)
        except urllib.error.HTTPError as e:
            err_body = e.read().decode("utf-8", errors="replace")
            print(f"[{etiqueta} ERROR {e.code}] {err_body}", flush=True)
        except Exception as e:
            if idx == 0:
                print(f"[{etiqueta} SIN CONEXION] {e}", flush=True)


def main():
    urls = [sys.argv[1]] if len(sys.argv) > 1 and sys.argv[1].startswith("http") else URLS_SERVIDORES
    puerto_manual = None
    for arg in sys.argv[1:]:
        if arg.upper().startswith("COM"):
            puerto_manual = arg.upper()

    print("=== PUENTE USB SKY-BOX (Arduino UNO -> Laragon PC y Ubuntu VM) ===", flush=True)
    for u in urls:
        print(f" -> Destino activo: {u}", flush=True)

    while True:
        puerto = puerto_manual or detectar_puerto()
        if not puerto:
            print("Esperando que conectes el Arduino UNO por USB...", flush=True)
            time.sleep(2)
            continue

        try:
            print(f"Conectando al Arduino en {puerto} (115200 baud)...", flush=True)
            with serial.Serial(puerto, 115200, timeout=1) as ser:
                time.sleep(2)
                print(f"[CONECTADO] Escuchando sensores y RFID en {puerto}. (Ctrl+C para salir)\n", flush=True)
                while True:
                    raw = ser.readline()
                    if not raw:
                        continue
                    linea = raw.decode("utf-8", errors="replace").strip()
                    if not linea:
                        continue

                    if linea == "READY":
                        print("[ARDUINO] Maqueta lista y enviando señales.", flush=True)
                    elif linea.startswith("SENSOR:"):
                        partes = linea.split(":")
                        if len(partes) == 3:
                            codigo, valor = partes[1], int(partes[2])
                            print(f"[SENSOR] {codigo} -> {'OCUPADO' if valor == 1 else 'LIBRE'}", flush=True)
                            enviar_evento(urls, {"evento": "sensor", "codigo": codigo, "ocupado": valor})
                    elif linea.startswith("RFID:"):
                        uid = linea.split(":", 1)[1].strip()
                        print(f"[RFID] Tarjeta leida: {uid}", flush=True)
                        enviar_evento(urls, {"evento": "rfid", "uid_rfid": uid})
                    else:
                        print(f"[ARDUINO] {linea}", flush=True)
        except KeyboardInterrupt:
            print("\nPuente detenido por el usuario.")
            break
        except Exception as e:
            print(f"[DESCONECTADO] {e}. Reintentando en 2s...", flush=True)
            time.sleep(2)


if __name__ == "__main__":
    main()
