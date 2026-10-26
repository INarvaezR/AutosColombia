
-- =====================================================
-- PROYECTO: AUTOS COLOMBIA
-- MODELO FISICO DE LA BASE DE DATOS
-- Motor: MySQL 8.0
-- Codificacion: utf8mb4
-- =====================================================

CREATE DATABASE IF NOT EXISTS autos_colombia
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE autos_colombia;

-- =====================================================
-- TABLA: VEHICULOS
-- =====================================================

CREATE TABLE IF NOT EXISTS vehiculos (
    id_vehiculo INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(10) NOT NULL UNIQUE,
    tipo_vehiculo VARCHAR(30) NOT NULL,
    marca VARCHAR(50),
    color VARCHAR(30),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: REGISTROS_PARQUEO
-- =====================================================

CREATE TABLE IF NOT EXISTS registros_parqueo (
    id_registro INT AUTO_INCREMENT PRIMARY KEY,
    id_vehiculo INT NOT NULL,
    fecha_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_salida DATETIME NULL,
    observaciones VARCHAR(255),

    CONSTRAINT fk_registros_vehiculos
        FOREIGN KEY (id_vehiculo)
        REFERENCES vehiculos(id_vehiculo)
) ENGINE=InnoDB;
