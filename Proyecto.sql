CREATE DATABASE IF NOT EXISTS sistema_estacionamiento;
USE sistema_estacionamiento;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'usuario', 'invitado') NOT NULL DEFAULT 'usuario',
    ultimo_acceso DATETIME
    
);
select * from usuarios;

CREATE TABLE parqueos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    placa VARCHAR(20) NOT NULL,
    hora_entrada DATETIME NOT NULL,
    hora_salida DATETIME DEFAULT NULL,
    estado ENUM('activo', 'salido') DEFAULT 'activo',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
select * from parqueos;
CREATE TABLE reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    espacio VARCHAR(10) NOT NULL UNIQUE,
    fecha_reserva DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
select * from reservas

CREATE TABLE espacios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL UNIQUE,
);
select * from espacios;

