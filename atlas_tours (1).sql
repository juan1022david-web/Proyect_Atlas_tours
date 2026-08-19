-- ============================================================
--  atlas_tours.sql  –  Base de datos completa Atlas Tours
-- ============================================================

CREATE DATABASE IF NOT EXISTS atlas_tours
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE atlas_tours;

-- 1. tipo_documento
CREATE TABLE IF NOT EXISTS tipo_documento (
    id_tipo_documento INT AUTO_INCREMENT PRIMARY KEY,
    tipo              VARCHAR(50) NOT NULL
);

INSERT INTO tipo_documento (id_tipo_documento, tipo) VALUES
    (1, 'CC – Cédula de Ciudadanía'),
    (2, 'CE – Cédula de Extranjería'),
    (3, 'Pasaporte')
ON DUPLICATE KEY UPDATE tipo = VALUES(tipo);

-- 2. usuario
CREATE TABLE IF NOT EXISTS usuario (
    id_usuario             INT AUTO_INCREMENT PRIMARY KEY,
    nombres                VARCHAR(100) NOT NULL,
    apellidos              VARCHAR(100) NOT NULL,
    id_tipo_documento      INT NOT NULL,
    numero_identificacion  VARCHAR(30) UNIQUE NOT NULL,
    correo                 VARCHAR(100) UNIQUE NOT NULL,
    telefono               VARCHAR(20),
    contrasena             VARCHAR(255) NOT NULL,
    creado_en              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_tipo_documento
        FOREIGN KEY (id_tipo_documento)
        REFERENCES tipo_documento(id_tipo_documento)
);

-- 3. tipo_servicio
CREATE TABLE IF NOT EXISTS tipo_servicio (
    id_tipo_servicio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_servicio  VARCHAR(100) NOT NULL
);

INSERT INTO tipo_servicio (nombre_servicio) VALUES
    ('Hotelero'),
    ('Empresarial'),
    ('Turistico'),
    ('Persona Natural'),
    ('Otro')
ON DUPLICATE KEY UPDATE nombre_servicio = VALUES(nombre_servicio);

-- 4. cotizar
CREATE TABLE IF NOT EXISTS cotizar (
    id_cotizacion       INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario          INT, -- NULL si visita sin sesión
    id_tipo_servicio    INT NOT NULL,
    fecha_hora          DATETIME NOT NULL,
    origen              VARCHAR(255) NOT NULL,
    destino             VARCHAR(255) NOT NULL,
    n_personas          INT,
    detalles_opcionales TEXT,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cotizar_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE SET NULL,
    CONSTRAINT fk_cotizar_tipo_servicio
        FOREIGN KEY (id_tipo_servicio)
        REFERENCES tipo_servicio(id_tipo_servicio)
);

-- 5. mensaje
CREATE TABLE IF NOT EXISTS mensaje (
    id_mensaje     INT AUTO_INCREMENT PRIMARY KEY,
    primer_nombre  VARCHAR(50),
    segundo_nombre VARCHAR(50),
    apellidos      VARCHAR(100),
    correo         VARCHAR(100),
    telefono       VARCHAR(20),
    asunto         VARCHAR(150),
    mensaje        TEXT,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

--TABLA DE ADMINISTRADORES
CREATE TABLE  admin (
    id_admin   INT AUTO_INCREMENT PRIMARY KEY,
    correo     VARCHAR(255) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL
);

INSERT INTO admin (correo, contrasena)
VALUES ('juan1022david@gmail.com', '$2y$10$jfgrJBsp/C6tqPom6.uwh.L9n0a7PSjJQ.7hPfZ3KVfGhbNPAfxBu')
ON DUPLICATE KEY UPDATE contrasena = VALUES(contrasena);

CREATE TABLE destinos (
    id_destino      INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100)        NOT NULL,
    descripcion     TEXT                NOT NULL,
    imagen          VARCHAR(255)        NOT NULL,   -- ruta o nombre de archivo, NO base64
    telefono        VARCHAR(50)         NULL,
    estado          ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    fecha_creacion  DATE                NOT NULL DEFAULT (CURRENT_DATE),
    fecha_actualizacion TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


--TABLA DE VEHICULOS CRUD
CREATE TABLE vehiculos (
    id_vehiculo INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(20) NOT NULL,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    capacidad INT NOT NULL,
    descripcion TEXT,
    imagen VARCHAR(255) NOT NULL,
    estado VARCHAR(20) DEFAULT 'Activo'
); // sql