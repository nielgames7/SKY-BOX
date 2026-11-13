# SKY-BOX 🚗🏢
### Sistema Web Inteligente de Estacionamiento con IoT y Web Móvil
**Caso de Estudio:** Edificio Multinivel SkyBox  
**Metodología:** Desarrollo Ágil (Scrum) — **Sprint 1**

---

## 📌 Sprint 1 — Historia de Usuario HU-01
**Objetivo:** Registro y Gestión de Ingresos de Vehículos en portería/recepción con emisión de tickets, generación de Token QR criptográfico y asignación de casillas en tiempo real.

### 👥 Equipo y Responsabilidades:
* **Daniel (@nielgames7):** Entorno de desarrollo local Laragon, estructura base y maqueta de vista web de portería (`index.php`).
* **Mirkho (@MRCC-NIX):** Diseño relacional y creación de tablas MySQL (`sql/skybox_db.sql`) y lógica backend con validaciones PDO (`controllers/registrar_ingreso.php`).
* **Jhoel (@jhoelito19):** Conexión PDO a base de datos (`config/conexion.php`), generador de Token QR y consulta dinámica de vehículos en parqueo (`listar_ingresos.php`).

---

## 📂 Estructura del Proyecto

```text
repoproyecto/
├── config/
│   └── conexion.php            # Script de conexión PDO a skybox_db (Jhoel - Issue #3)
├── controllers/
│   └── registrar_ingreso.php   # Lógica backend, sanitización, transacciones PDO (Mirkho - Issue #5)
├── sql/
│   ├── skybox_db.sql           # Script DDL/DML de la base de datos MySQL (Daniel/Mirkho - Issue #2)
│   └── db_skybox.sql           # Esquema relacional con datos semilla
├── index.php                   # Vista web de registro de ingresos en portería (Daniel - Issue #4)
├── listar_ingresos.php         # Monitoreo en vivo de vehículos activos y QR (Jhoel - Issue #6)
├── registrar_ingreso.php       # Enrutador hacia controllers/registrar_ingreso.php
└── README.md                   # Documentación oficial del repositorio
```

---

## 🚀 Puesta en Marcha en Laragon (Local)

1. **Clonar o copiar el proyecto** dentro de la carpeta `www` de Laragon:
   ```bash
   C:\laragon\www\skybox
   # o acceder directamente desde http://localhost/repoproyecto si está en el Desktop con enlace simbólico
   ```

2. **Crear e importar la base de datos:**
   * Abrir **HeidiSQL** o **phpMyAdmin** desde Laragon.
   * Ejecutar el script: `sql/skybox_db.sql`.
   * Esto creará la base de datos `skybox_db` con las tablas: `usuarios`, `tarifas`, `casillas`, `reservas` y `registros` más datos iniciales de prueba.

3. **Abrir en el Navegador:**
   * `http://localhost/repoproyecto/` (o el Virtual Host `http://skybox.test/`).

---

## 🧪 Pruebas del Flujo Completo (Issue #7)
1. Abrir `index.php`.
2. Ingresar un código de tarjeta RFID (o dar clic en uno de los botones rápidos de simulación, ej: `A4-9F-21-B3`).
3. Seleccionar una casilla libre o dejar la asignación automática inteligente.
4. Dar clic en **"Confirmar y Registrar Ingreso"**.
5. Se generará el **Ticket de Ingreso** con el **Token QR** y se actualizará la casilla a `ocupado`.
6. En `listar_ingresos.php` verás el vehículo estacionado con el tiempo transcurrido en vivo.
7. Si intentas registrar la misma tarjeta sin haber salido, el sistema rechazará la duplicidad.
