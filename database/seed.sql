-- Mi Cochera — datos semilla
USE mi_cochera;

-- 30 espacios en 3 filas de 10 (zona A/B/C), coincide con el grid del mockup admin
INSERT INTO espacios (codigo, numero, zona, estado) VALUES
('01',1,'A','disponible'),('02',2,'A','ocupado'),('03',3,'A','disponible'),('04',4,'A','disponible'),('05',5,'A','disponible'),
('06',6,'A','disponible'),('07',7,'A','ocupado'),('08',8,'A','disponible'),('09',9,'A','disponible'),('10',10,'A','disponible'),
('11',11,'B','disponible'),('12',12,'B','disponible'),('13',13,'B','ocupado'),('14',14,'B','disponible'),('15',15,'B','disponible'),
('16',16,'B','disponible'),('17',17,'B','disponible'),('18',18,'B','ocupado'),('19',19,'B','disponible'),('20',20,'B','disponible'),
('21',21,'C','disponible'),('22',22,'C','disponible'),('23',23,'C','ocupado'),('24',24,'C','disponible'),('25',25,'C','disponible'),
('26',26,'C','disponible'),('27',27,'C','disponible'),('28',28,'C','disponible'),('29',29,'C','disponible'),('30',30,'C','disponible');

-- admin por defecto: admin@micochera.com / admin123 (cambiar tras el primer login)
INSERT INTO usuarios_admin (nombre, email, password_hash, rol) VALUES
('Administrador', 'admin@micochera.com', '$2y$10$dUZDJnwZi6yfax2fVNNVOe1jet/0.jaQ9fFN8PkzYB/2QWFvRzmCe', 'admin');

INSERT INTO metodos_pago (tipo, titular, numero_cuenta, banco) VALUES
('yape', 'COCHERA DEL NORTE SAC', '987654321', NULL),
('plin', 'COCHERA DEL NORTE SAC', '987654321', NULL),
('transferencia', 'COCHERA DEL NORTE SAC', '191-1234567-0-12', 'BCP');

INSERT INTO configuracion (clave, valor) VALUES
('nombre_negocio', 'Mi Cochera'),
('direccion', 'Av. Principal 123, Lima'),
('horario', '24 Horas'),
('telefono', '999 123 456'),
('tarifa_hora', '20.00'),
('hold_minutes', '5'),
('adelanto_porcentaje', '50');
