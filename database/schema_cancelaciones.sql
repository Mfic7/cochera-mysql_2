-- Nueva tabla para cancelaciones de reservas
CREATE TABLE cancelaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  motivo VARCHAR(255) NOT NULL,
  numero_operacion VARCHAR(100) NULL,
  comprobante_path VARCHAR(255) NULL,
  revisado TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cancelacion_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id)
) ENGINE=InnoDB;
