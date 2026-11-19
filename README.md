# SKY-BOX — Sistema Web de Estacionamiento Inteligente

Aplicación web en **PHP 8 + MySQL (PDO) + Bootstrap 5** para la gestión del estacionamiento multinivel **SKY-BOX** (10 pisos × 6 puestos = 60 casillas), control de roles, alquiler por horas, cobro con código **QR local (`phpqrcode`)** y **pantalla exterior de salida** en tiempo real.

## Requisitos
- **Laragon** (Apache + MySQL 8 / MariaDB 10.4+)
- **PHP 8.1+** con extensiones `pdo_mysql` y `gd` habilitadas

## Instalación rápida
1. Clona el repositorio dentro de `C:\laragon\www\SKYBOX`:
   ```bash
   git clone https://github.com/nielgames7/SKY-BOX.git SKYBOX
   ```
2. Inicia **Apache** y **MySQL** en Laragon.
3. Importa `sql/skybox_db.sql` en MySQL / phpMyAdmin (crea `skybox_db`, 60 casillas, tarifa base y cuentas iniciales).
4. Abre en el navegador:
   - Sistema principal: `http://localhost/SKYBOX/index.php`
   - Pantalla exterior de salida (kiosco QR): `http://localhost/SKYBOX/pantalla_salida.php`

## Roles y cuentas de prueba
*(Contraseña inicial de todas las cuentas semilla: `admin123`)*

| Rol | Usuario | Permisos |
|---|---|---|
| **Usuario (sin sesión)** | — | Ver croquis y disponibilidad en tiempo real (solo lectura). |
| **Usuario registrado** | `cliente1` | Alquilar puestos libres por horas y ver sus alquileres. |
| **Operador** | `operador` | Croquis interactivo, ingresos RFID/reservas, salidas con QR, tarifas y pantalla exterior. |
| **Administrador** | `admin` | Todo lo del operador + crear cuentas, otorgar/cambiar roles y activar/desactivar usuarios. |

## Estructura del proyecto
- `index.php` — Controlador principal de vista y carga de datos.
- `pantalla_salida.php` — Pantalla exterior de bienvenida y QR de pago sincronizada vía `assets/qr/ultimo_pago.json`.
- `config/conexion.php` — Conexión PDO y utilidades CSRF.
- `controllers/` — Lógica de negocio (`auth.php`, `logica_croquis.php`, `logica_alquileres.php`, `registrar_ingreso.php`, `registrar_salida.php`, `listar_ingresos.php`).
- `views/` — Vistas parciales (`croquis.php`, `alquileres.php`, `panel_admin.php`, `modales.php`).
- `assets/` — Estilos (`css/styles.css`), scripts (`js/app.js`) y carpeta temporal de QR (`qr/`).
- `phpqrcode/` — Librería local para generación de códigos QR en PNG.
- `sql/skybox_db.sql` — Esquema relacional y datos iniciales.
