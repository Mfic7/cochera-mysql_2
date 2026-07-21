-- Mi Cochera — esquema de base de datos (MariaDB / InnoDB)
CREATE DATABASE IF NOT EXISTS mi_cochera CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mi_cochera;

CREATE TABLE espacios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(10) NOT NULL UNIQUE,
  numero INT NOT NULL,
  zona VARCHAR(50) NULL,
  estado ENUM('disponible','ocupado','mantenimiento') NOT NULL DEFAULT 'disponible',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuarios_admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','operador') NOT NULL DEFAULT 'admin',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE reservas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  token CHAR(32) NOT NULL UNIQUE,
  espacio_id INT NOT NULL,
  cliente_nombre VARCHAR(120) NOT NULL,
  cliente_celular VARCHAR(20) NOT NULL,
  fecha_hora_inicio DATETIME NOT NULL,
  horas_estimadas DECIMAL(4,2) NOT NULL DEFAULT 2,
  fecha_hora_fin DATETIME NOT NULL,
  tarifa_hora DECIMAL(8,2) NOT NULL,
  monto_total DECIMAL(8,2) NOT NULL,
  monto_adelanto DECIMAL(8,2) NOT NULL,
  monto_restante DECIMAL(8,2) NOT NULL,
  estado ENUM('pendiente_pago','en_validacion','adelanto_pagado','pago_completo','cancelada','vencida')
         NOT NULL DEFAULT 'pendiente_pago',
  hold_expira_en DATETIME NULL,
  ip_origen VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reserva_espacio FOREIGN KEY (espacio_id) REFERENCES espacios(id),
  KEY idx_espacio_estado (espacio_id, estado),
  KEY idx_rango (fecha_hora_inicio, fecha_hora_fin),
  KEY idx_hold_expira (estado, hold_expira_en)
) ENGINE=InnoDB;

CREATE TABLE pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  tipo ENUM('adelanto','saldo') NOT NULL,
  metodo ENUM('yape','plin','transferencia','efectivo') NOT NULL,
  monto DECIMAL(8,2) NOT NULL,
  numero_operacion VARCHAR(50) NULL,
  comprobante_path VARCHAR(255) NULL,
  estado ENUM('en_validacion','aprobado','rechazado') NOT NULL DEFAULT 'en_validacion',
  admin_id INT NULL,
  motivo_rechazo VARCHAR(255) NULL,
  revisado_en DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pago_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
  CONSTRAINT fk_pago_admin FOREIGN KEY (admin_id) REFERENCES usuarios_admin(id),
  KEY idx_reserva (reserva_id),
  KEY idx_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE reserva_estado_historial (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  estado_anterior VARCHAR(30) NULL,
  estado_nuevo VARCHAR(30) NOT NULL,
  actor_tipo ENUM('sistema','cliente','admin') NOT NULL,
  actor_id INT NULL,
  nota VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
  KEY idx_reserva (reserva_id)
) ENGINE=InnoDB;

CREATE TABLE metodos_pago (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('yape','plin','transferencia') NOT NULL UNIQUE,
  titular VARCHAR(120) NOT NULL,
  numero_cuenta VARCHAR(50) NOT NULL,
  banco VARCHAR(80) NULL,
  qr_image_path VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  clave VARCHAR(60) PRIMARY KEY,
  valor TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
