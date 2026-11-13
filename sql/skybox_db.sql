-- =====================================================================
-- PROYECTO: Sistema Web Inteligente de Estacionamiento con IoT y Web Móvil
-- CASO DE ESTUDIO: Edificio Multinivel SkyBox
-- MOTOR: MySQL 8.0+ / MariaDB 10.4+ (Entorno Laragon)
-- =====================================================================

-- 1. Base de datos
CREATE DATABASE IF NOT EXISTS skybox_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE skybox_db;

-- 2. Limpieza de tablas previas en orden seguro
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS registros;
DROP TABLE IF EXISTS reservas;
DROP TABLE IF EXISTS casillas;
DROP TABLE IF EXISTS tarifas;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- TABLA 1: USUARIOS (Personal administrativo y operadores de portería)
-- =====================================================================
CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,                     -- Hash generado con password_hash() en PHP
    rol ENUM('admin', 'operador') NOT NULL DEFAULT 'operador',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 2: TARIFAS (Costo por hora, fracciones y tolerancia)
-- =====================================================================
CREATE TABLE tarifas (
    id_tarifa INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,                        -- Ej: 'Tarifa General Vehicular'
    costo_por_hora DECIMAL(8,2) NOT NULL,               -- Ej: 5.00 Bs
    costo_fraccion DECIMAL(8,2) NOT NULL DEFAULT 0.00,  -- Ej: 3.00 Bs por media hora extra
    minutos_tolerancia INT UNSIGNED NOT NULL DEFAULT 15,-- Minutos de gracia al salir
    activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 3: CASILLAS (Pisos del edificio + Sensores Infrarrojos FC-51)
-- =====================================================================
CREATE TABLE casillas (
    id_casilla INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    piso INT NOT NULL,                                  -- Nivel: 1 (PB), 2 (Piso 2), etc.
    codigo VARCHAR(10) NOT NULL UNIQUE,                 -- Identificador: 'PB-01', 'P2-01'
    estado ENUM('libre', 'ocupado', 'reservado') NOT NULL DEFAULT 'libre',
    pin_sensor VARCHAR(20) NULL,                        -- Pin del ESP32 (ej: 'GPIO_4')
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_piso_estado (piso, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 4: RESERVAS (Apartado anticipado de espacios vía Web Móvil)
-- =====================================================================
CREATE TABLE reservas (
    id_reserva INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_casilla INT UNSIGNED NOT NULL,
    nombre_cliente VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NULL,
    placa_vehiculo VARCHAR(15) NOT NULL,
    codigo_reserva VARCHAR(20) NOT NULL UNIQUE,         -- PIN o código generado para el conductor
    fecha_hora_inicio DATETIME NOT NULL,
    fecha_hora_fin DATETIME NOT NULL,
    estado ENUM('pendiente', 'activa', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reserva_casilla 
        FOREIGN KEY (id_casilla) 
        REFERENCES casillas(id_casilla) 
        ON UPDATE CASCADE 
        ON DELETE CASCADE,
    INDEX idx_codigo_reserva (codigo_reserva),
    INDEX idx_fechas_reserva (fecha_hora_inicio, fecha_hora_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 5: REGISTROS (Entradas/Salidas por RFID, Token QR y Cobro)
-- =====================================================================
CREATE TABLE registros (
    id_registro INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT UNSIGNED NULL,                       -- Si ingresó con reserva previa
    uid_rfid VARCHAR(32) NOT NULL,                      -- UID leído por el módulo RC522
    token_qr VARCHAR(64) NOT NULL UNIQUE,               -- Hash único para que el conductor consulte la web móvil
    id_casilla INT UNSIGNED NULL,                       -- Casilla ocupada
    fecha_ingreso DATETIME NOT NULL,
    fecha_salida DATETIME NULL,
    minutos_estadia INT UNSIGNED DEFAULT 0,             -- Minutos transcurridos calculados al salir
    total_pagado DECIMAL(8,2) NOT NULL DEFAULT 0.00,    -- Monto cobrado
    metodo_pago ENUM('efectivo', 'qr_transferencia', 'pendiente') NOT NULL DEFAULT 'pendiente',
    estado ENUM('en_curso', 'pagado', 'cancelado') NOT NULL DEFAULT 'en_curso',
    id_usuario INT UNSIGNED NULL,                       -- Operador que liquidó el cobro
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_registro_reserva 
        FOREIGN KEY (id_reserva) 
        REFERENCES reservas(id_reserva) 
        ON UPDATE CASCADE 
        ON DELETE SET NULL,
    CONSTRAINT fk_registro_casilla 
        FOREIGN KEY (id_casilla) 
        REFERENCES casillas(id_casilla) 
        ON UPDATE CASCADE 
        ON DELETE SET NULL,
    CONSTRAINT fk_registro_usuario 
        FOREIGN KEY (id_usuario) 
        REFERENCES usuarios(id_usuario) 
        ON UPDATE CASCADE 
        ON DELETE SET NULL,
    INDEX idx_rfid_estado (uid_rfid, estado),
    INDEX idx_token_qr (token_qr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DATOS INICIALES DE PRUEBA (Para la maqueta en Laragon)
-- =====================================================================

-- Usuarios del sistema (contraseña de prueba: 'admin123')
INSERT INTO usuarios (nombre, usuario, password, rol) VALUES
('Carlos Méndez Rojas', 'admin', '$2y$10$4n9x5rYQ7qjRsmZ1X3XQeeYQkZ2zWqUeY8PzO8d1yX6qYw5r0t4iu', 'admin'),
('Operador Portería', 'operador', '$2y$10$4n9x5rYQ7qjRsmZ1X3XQeeYQkZ2zWqUeY8PzO8d1yX6qYw5r0t4iu', 'operador');

-- Tarifa estándar
INSERT INTO tarifas (nombre, costo_por_hora, costo_fraccion, minutos_tolerancia, activa) VALUES
('Tarifa General Vehicular', 5.00, 3.00, 15, 1);

-- 4 casillas de ejemplo distribuidas en 2 niveles (con pines del ESP32)
INSERT INTO casillas (piso, codigo, estado, pin_sensor) VALUES
(1, 'PB-01', 'libre', 'GPIO_4'),
(1, 'PB-02', 'libre', 'GPIO_5'),
(2, 'P2-01', 'libre', 'GPIO_18'),
(2, 'P2-02', 'libre', 'GPIO_19');

-- Reserva de prueba
INSERT INTO reservas (id_casilla, nombre_cliente, telefono, placa_vehiculo, codigo_reserva, fecha_hora_inicio, fecha_hora_fin, estado) VALUES
(3, 'Juan Pérez', '71234567', '3421-ABC', 'RES-4589', NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), 'activa');

-- Ponemos la casilla 3 en estado reservado
UPDATE casillas SET estado = 'reservado' WHERE id_casilla = 3;
