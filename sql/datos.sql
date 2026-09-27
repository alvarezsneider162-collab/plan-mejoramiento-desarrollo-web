-- ===========================================================================
-- LA CARAMBOLA DORADA - Datos de prueba
-- Archivo: sql/datos.sql
-- Actividad de recuperacion, dia 9, punto 2
--
-- El enunciado pide minimo 20 productos, 10 clientes y 15 pedidos. Van 21, 10
-- y 15. Se entra por este archivo despues de estructura.sql:
--   mysql -u root < sql/datos.sql
--
-- Los veinte y un productos son los dieciseis del dia 7 mas cinco nuevos. Los
-- clientes y los pedidos se apoyan en el nombre de los ocho pedidos del dia 7
-- para que los datos de los dos dias se parezca entre si.
--
-- Los acentos van puestos a proposito: las columnas son utf8mb4 y los nombres
-- de los productos del dia 7 los traian, asi que el buscador del dia 9 tiene
-- que seguir encontrandolos con y sin tilde.
--
-- Los pedidos se insertan con su total ya hecho. El total es la suma de
-- cantidad por precio_unitario de cada linea de detalle, y como el precio se
-- copia al detalle, el total no cambia aunque manana suban el valor de alquiler.
-- ===========================================================================

USE carambola_doradoa;

-- ---------------------------------------------------------------------------
-- 4 categorias: los cuatro valores del desplegable "categoria" del formulario.
-- ---------------------------------------------------------------------------
INSERT INTO categorias (nombre, descripcion) VALUES
  ('Implemento de juego', 'Tacos, bolas y todo lo que se usa para jugar'),
  ('Consumible',          'Tiza, guantes y productos que se gastan'),
  ('Repuesto',            'Piezas de repuesto de los implementos y las mesas'),
  ('Mesa de billar',      'Mesas completas del salón');


-- ---------------------------------------------------------------------------
-- 4 proveedores: de donde sale el campo "proveedor" del formulario.
-- ---------------------------------------------------------------------------
INSERT INTO proveedores (nombre, contacto, telefono) VALUES
  ('Importadora El Trompo',        'Carolina Restrepo', '604 432 1180'),
  ('Deportesivos Delux',           'Andres Villa',      '604 411 2277'),
  ('Lutería El Puente',            'Lucía Fernández',   '604 448 9034'),
  ('Distribuidora Industrial S.A.', 'Jorge Ospina',      '604 423 5561');


-- ---------------------------------------------------------------------------
-- 21 productos. Los dieciseis primeros son los del dia 7, con el mismo codigo,
-- el mismo nombre y el mismo valor, para que cambiar el origen de los datos
-- no se note. condicion: 'Nuevo', 'Semi-nuevo' o 'En reparación'.
-- ---------------------------------------------------------------------------
INSERT INTO productos
  (codigo, nombre, categoria_id, condicion, existencias, valor_alquiler, proveedor_id, descripcion, fecha_ingreso)
VALUES
  ('TAC-001', 'Taco de pool 1,45 m',           1, 'Nuevo',         8, 12000.00, 1, 'Taco de cedro con puntera de cuero',           '2026-09-01'),
  ('BOL-004', 'Juego de bolas de billar',      1, 'Nuevo',         3, 45000.00, 1, 'Juego de seis bolas numeradas',                 '2026-09-01'),
  ('TRI-002', 'Triángulo para bolas',          1, 'Nuevo',         5,  8000.00, 3, 'Triángulo acrílico con pocket de aluminio',      '2026-09-02'),
  ('EXT-005', 'Extensión de taco',             1, 'En reparación', 1, 10000.00, 3, 'Extensora para taco, en taller',                '2026-09-03'),
  ('PUN-006', 'Puntería profesional',          1, 'Nuevo',         4, 18000.00, 1, 'Puntera de repuesto para taco',                  '2026-09-03'),
  ('SOP-016', 'Soporete para taco',            1, 'Nuevo',        15,  5000.00, 1, 'Soporete plástico de repuesto',                 '2026-09-04'),
  ('TIZ-010', 'Tiza de billar',                2, 'Nuevo',        24,  2000.00, 1, 'Caja con cuatro barras de tiza',                '2026-09-05'),
  ('GUA-003', 'Guantes de fibra',              2, 'Semi-nuevo',    6, 15000.00, 1, 'Guantes con funda de fibra',                     '2026-09-05'),
  ('GUA-007', 'Guantes de cuero sintético',    2, 'Semi-nuevo',    7, 22000.00, 1, 'Guantes de cuero sintético talla L',            '2026-09-06'),
  ('AGT-014', 'Agente de limpieza para mesas', 2, 'Nuevo',        12,  7500.00, 2, 'Botella de un litro para mesas y bolas',        '2026-09-07'),
  ('TIZ-015', 'Tiza de Tournament',            2, 'Nuevo',        20,  4500.00, 1, 'Barra de tiza de Tournament',                   '2026-09-07'),
  ('REP-011', 'Repuesto de punta de taco',     3, 'Nuevo',        18,  3500.00, 2, 'Paquete de diez puntas de repuesto',            '2026-09-08'),
  ('REP-012', 'Banda de goma para pocket',     3, 'Nuevo',         9,  6000.00, 2, 'Banda de goma para el pocket de las mesas',     '2026-09-08'),
  ('CON-013', 'Conector de extensión',         3, 'En reparación', 2,  9000.00, 3, 'Conector de latón, en taller',                  '2026-09-09'),
  ('MES-008', 'Mesa de billar 8 pies',         4, 'Nuevo',         2, 60000.00, 4, 'Mesa de 8 pies con tela nacional',              '2026-09-10'),
  ('MES-009', 'Mesa de billar 9 pies',         4, 'Nuevo',         1, 85000.00, 4, 'Mesa de 9 pies con tela nacional',              '2026-09-10'),
  ('TAC-017', 'Taco de pool 1,55 m',           1, 'Nuevo',         4, 14000.00, 1, 'Taco largo para mesa de 9 pies',                '2026-09-12'),
  ('AGT-019', 'Paños de microfibra',           2, 'Nuevo',        30,  1500.00, 2, 'Paquete de seis paños limpios',                 '2026-09-12'),
  ('REP-020', 'Lodillo de cuero para pocket',  3, 'Nuevo',        10,  7000.00, 3, 'Lodillo de cuero para limpiar el pocket',       '2026-09-13'),
  ('MES-021', 'Mesa de billar 7 pies',         4, 'Semi-nuevo',    1, 55000.00, 4, 'Mesa de 7 pies, seminueva',                     '2026-09-14'),
  ('SOP-022', 'Extensora rígida',              1, 'Nuevo',         6, 11000.00, 1, 'Extensora rígida de fibra de vidrio',            '2026-09-15');


-- ---------------------------------------------------------------------------
-- 10 clientes. documento es la cedula o el NIT.
-- ---------------------------------------------------------------------------
INSERT INTO clientes (nombre, documento, telefono, email) VALUES
  ('Club de Billar La 8',           '900123456-1', '604 555 0101', 'admin@clubbillar8.com'),
  ('Federación Antioqueña de Pool', '900222333-2', '604 555 0102', 'deportes@fedapool.co'),
  ('Cafeterías del Centro',         '900444555-3', '604 555 0103', 'compras@cafeteriascentro.co'),
  ('Universidad de Antioquia',      '890904353-4', '604 555 0104', 'alquiler@udea.edu.co'),
  ('Hotel Boutique del Río',        '900888777-5', '604 555 0105', 'eventos@hotelboutiquedelrio.com'),
  ('Torneo Municipal de Pool',      '901111222-6', '604 555 0106', 'inscripciones@torneopool.co'),
  ('Cafetería La Curva',            '901222333-7', '604 555 0107', 'lacurva@correo.com.co'),
  ('Club Deportivo El Tambor',      '901333444-8', '604 555 0108', 'deportes@eltambor.co'),
  ('Centro Cultural de Medellín',   '901444555-9', '604 555 0109', 'alquiler@centrocultural.co'),
  ('Bar El Rinconcito',             '901555666-0', '604 555 0110', 'bar@elrinconcito.co');


-- ---------------------------------------------------------------------------
-- 15 pedidos. cliente_id va en el mismo orden de los 10 clientes de arriba.
-- Los ocho primeros son los del dia 7, con las mismas fechas y los mismos
-- estados.
--
-- El total de cada uno es la suma de cantidad por precio_unitario de sus
-- lineas de detalle, que van mas abajo. Para que los datos queden cuadrados
-- entre si, al final del archivo hay un UPDATE que los recalcula: si alguien
-- cambia una linea de detalle y vuelve a correr este archivo, el total se
-- vuelve a armar solo en vez de quedar viejo.
-- ---------------------------------------------------------------------------
INSERT INTO pedidos (cliente_id, fecha, estado, total) VALUES
  (1,  '2026-09-27', 'Confirmado', 108000.00),
  (2,  '2026-09-28', 'Confirmado', 210000.00),
  (3,  '2026-09-28', 'Pendiente',  34500.00),
  (4,  '2026-09-29', 'Entregado', 230000.00),
  (1,  '2026-09-29', 'Confirmado',  76000.00),
  (5,  '2026-09-30', 'Pendiente',  84000.00),
  (6,  '2026-10-01', 'Confirmado', 235000.00),
  (7,  '2026-10-02', 'Entregado',  22500.00),
  (8,  '2026-10-03', 'Confirmado',  66000.00),
  (9,  '2026-10-05', 'Pendiente', 104000.00),
  (2,  '2026-10-06', 'Confirmado',  51000.00),
  (5,  '2026-10-07', 'Entregado', 205000.00),
  (10, '2026-10-08', 'Pendiente',  48000.00),
  (3,  '2026-10-09', 'Confirmado',  52000.00),
  (6,  '2026-10-10', 'Entregado', 124000.00);


-- ---------------------------------------------------------------------------
-- Lineas de detalle. producto_id va en el mismo orden de los 21 productos.
-- ---------------------------------------------------------------------------
INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario) VALUES
  (1,  15, 1, 60000.00), (1,  1, 4, 12000.00),
  (2,  15, 2, 60000.00), (2,  2, 2, 45000.00),
  (3,   7, 6,  2000.00), (3, 10, 3,  7500.00),
  (4,  16, 2, 85000.00), (4, 15, 1, 60000.00),
  (5,   3, 5,  8000.00), (5,  5, 2, 18000.00),
  (6,  14, 4,  9000.00), (6,  1, 4, 12000.00),
  (7,  16, 1, 85000.00), (7, 15, 1, 60000.00), (7, 2, 2, 45000.00),
  (8,   7, 4,  2000.00), (8, 10, 1,  7500.00), (8, 13, 2, 3500.00),
  (9,   1, 3, 12000.00), (9,  6, 6,  5000.00),
  (10,  8, 4, 15000.00), (10, 9, 2, 22000.00),
  (11, 11, 8,  4500.00), (11, 18, 10, 1500.00),
  (12, 20, 1, 55000.00), (12, 2, 2, 45000.00), (12, 15, 1, 60000.00),
  (13, 17, 2, 14000.00), (13, 6, 4,  5000.00),
  (14,  4, 2, 10000.00), (14, 12, 3,  6000.00), (14, 19, 2,  7000.00),
  (15, 21, 4, 11000.00), (15, 1, 4, 12000.00), (15, 3, 4,  8000.00);


-- ---------------------------------------------------------------------------
-- Recalcula el total de cada pedido desde sus lineas de detalle.
--
-- Esta es la consulta que hace que los tres datos cuadren entre si. Se escribe
-- despues del INSERT a proposito: asi el total nunca se escribe a mano y
-- siempre es la suma de lo que el pedido lleva de verdad.
-- ---------------------------------------------------------------------------
UPDATE pedidos p
SET p.total = (
  SELECT SUM(d.cantidad * d.precio_unitario)
  FROM detalle_pedidos d
  WHERE d.pedido_id = p.id
);
