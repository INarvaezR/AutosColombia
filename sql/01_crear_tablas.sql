
USE autos_colombia;

-- Tabla de vehículos
CREATE TABLE vehiculos (
    id_vehiculo INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(10) NOT NULL UNIQUE,
    tipo_vehiculo VARCHAR(30) NOT NULL,
    marca VARCHAR(50),
    color VARCHAR(30),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de registros de entrada y salida
CREATE TABLE registros_parqueo (
    id_registro INT AUTO_INCREMENT PRIMARY KEY,
    id_vehiculo INT NOT NULL,
    fecha_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_salida DATETIME NULL,
    observaciones VARCHAR(255),
    FOREIGN KEY (id_vehiculo)
        REFERENCES vehiculos(id_vehiculo)
);

-- Consultar las tablas creadas
SHOW TABLES;
