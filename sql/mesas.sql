-- ===========================================================================
-- LA CARAMBOLA DORADA - Mesas del salon
-- Archivo: sql/mesas.sql
-- Actividad de recuperacion, dia 12, punto 3
--
-- Para cargarlo:
--   mysql -u root < sql/mesas.sql
--
-- ---------------------------------------------------------------------------
-- POR QUE ESTE ARCHIVO VA APARTE Y NO DENTRO DE estructura.sql
-- ---------------------------------------------------------------------------
-- Por la misma razon que sql/usuarios.sql: estructura.sql empieza con DROP
-- TABLE, asi que si se vuelve a cargar borra las sixteen mesas, y con ellas las
-- capturas de evidencia del dia 12 dejan de cuadrar. Aqui solo se borra y se
-- recrea la tabla de este dia, y el catalogo del dia 9 (productos, pedidos,
-- clientes...) y las dos tablas del dia 10 (usuarios, intentos_acceso) no se
-- tocan.
--
-- ---------------------------------------------------------------------------
-- QUE HACE FALTA Y POR QUE NO HABIA NADA
-- ---------------------------------------------------------------------------
-- El punto 3 pide que las cuatro tarjetas de indicadores del tablero se pinten
-- con datos REALES de la base de datos. Con las tablas del dia 9 y del dia 10
-- se podia saber el cobro del dia y los implementos pendientes, pero no habia
-- forma de saber cuantas mesas hay ni cuantas estan ocupadas: en el salon una
-- mesa es lo que se ocupa.
--
-- Asi que el numero de mesas y su estado se guardan aqui, no en el HTML. Antes
-- las sixteen mesas estaban escritas a mano en dashboard.html, con su jugador y
-- su hora de inicio, y por eso eran los mismos datos en el navegador de todo el
-- mundo: en un cartel de la sala 1 y en el tablero de la sala 2 salia
-- "Mesa 3: Derek Cespedes, 17:20".
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS carambola_doradoa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE carambola_doradoa;

DROP TABLE IF EXISTS mesas;

-- ---------------------------------------------------------------------------
-- mesas
-- ---------------------------------------------------------------------------
--   id            numero de mesa, para ordenarlas sin depender del texto
--   numero        el numero que se ve pintado en la mesa del salon
--   estado        Libre, Ocupada o Reservada. Es un ENUM y no un VARCHAR porque
--                 son tres estados y ninguno mas: asi MySQL no deja guardar
--                 "ocupada" en minuscula o "ocupadas" por error, y el tablero
--                 no tiene que contemplating un cuarto caso que nunca va a
--                 llegar.
--   jugador       quien la tiene. NULL si esta libre: no se pone la cadena
--                 vacia, porque "" y NULL no son lo mismo y "esta ocupada pero
--                 no se sabe de quien" no es un estado que el salon tenga.
--   precio_hora   lo que se cobra por hora. Vive en la base y no en la pagina,
--                 porque el precio cambia y no puede ser una constante escrita
--                 en el HTML.
--   ocupada_desde el momento en que se ocupa. De aqui sale el "tiempo jugado"
--                 de la tabla del tablero, que se calcula al pintar, no se
--                 guarda: guardar un tiempo "ya jugado" en la base obliga a
--                 actualizar la fila cada segundo.
-- ---------------------------------------------------------------------------
CREATE TABLE mesas (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  numero        TINYINT UNSIGNED NOT NULL COMMENT 'Numero de mesa: 1 a 16',
  estado        ENUM('Libre', 'Ocupada', 'Reservada') NOT NULL DEFAULT 'Libre',
  jugador       VARCHAR(80)     NULL DEFAULT NULL,
  precio_hora   DECIMAL(10, 2)  UNSIGNED NOT NULL DEFAULT 35000.00,
  ocupada_desde DATETIME        NULL DEFAULT NULL,
  PRIMARY KEY (id),

  -- El numero identifica la mesa, asi que no puede repetirse.
  UNIQUE KEY uq_mesas_numero (numero),

  -- El indice por estado es el que sostiene las dos tarjetas de indicadores:
  -- cuentan las mesas de un estado, y sin el MySQL tendria que leer la tabla
  -- entera en vez de usar el indice.
  --
  -- ESTA ES LA ULTIMA LINEA DE LA DEFINICION Y NO LLEVA COMA. Da igual lo que
  -- venga despues, incluso si lo que viene es un comentario: despues de la
  -- coma MySQL exige otro elemento de la tabla, y si no lo encuentra da
  -- "ERROR 1064 ... near ') ENGINE = InnoDB'", que es un mensaje que no senala
  -- la coma de ninguna parte. Por eso el comentario va antes y la coma nunca.
  KEY idx_mesas_estado (estado)

  -- ocupada_desde solo tiene sentido con la mesa ocupada, y la hora de inicio
  -- solo con la ocupada. MySQL 8.0.16 y siguientes lo detectan y avisan como
  -- advertencia, y el enunciado no pide CHECK en esta tabla, asi que la regla se
  -- documenta aqui y la cumple el formulario que da de alta una mesa.
  --
  -- La regla, escrita sin comentar para quien quiera activarla despues, es:
  --   CONSTRAINT chk_mesas_ocupacion CHECK (
  --     (estado = 'Ocupada' AND ocupada_desde IS NOT NULL)
  --     OR (estado <> 'Ocupada')
  --   )
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Las dieciseis mesas del salon y su estado';

-- ---------------------------------------------------------------------------
-- LAS DIECISEIS MESAS
-- ---------------------------------------------------------------------------
-- Las mismas dieciseis del prototipo, con el estado repartido para que las
-- tarjetas salgan distintas entre si: hay libres, hay ocupadas y hay
-- reservadas.
--
-- occupied_desde se deja con la hora de ahora menos los minutos que tenia la
-- mesa en el prototipo, con la funcion NOW(). Asi el "tiempo jugado" que se
-- calcula al pintar sale un numero verosimil desde el primer momento, sin
-- tener que escribir 16 horas a mano, y sin que la fecha quede pegada para
-- siempre: dentro de un mes estas mesas habran seguido ocupadas.
-- ---------------------------------------------------------------------------
INSERT INTO mesas (numero, estado, jugador, precio_hora, ocupada_desde) VALUES
  ( 1, 'Ocupada',   'Jhon Ballen',      35000.00, DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
  ( 2, 'Ocupada',   'Andrea Rios',      35000.00, DATE_SUB(NOW(), INTERVAL 50 MINUTE)),
  ( 3, 'Ocupada',   'Derek Cespedes',   35000.00, DATE_SUB(NOW(), INTERVAL 80 MINUTE)),
  ( 4, 'Libre',     NULL,               35000.00, NULL),
  ( 5, 'Ocupada',   'Marcela Ortiz',    35000.00, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
  ( 6, 'Libre',     NULL,               35000.00, NULL),
  ( 7, 'Reservada', 'Turno de la noche',40000.00, NULL),
  ( 8, 'Libre',     NULL,               35000.00, NULL),
  ( 9, 'Libre',     NULL,               35000.00, NULL),
  (10, 'Ocupada',   'Camilo Torres',    35000.00, DATE_SUB(NOW(), INTERVAL 60 MINUTE)),
  (11, 'Libre',     NULL,               35000.00, NULL),
  (12, 'Ocupada',   'Laura Jimenez',    35000.00, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
  (13, 'Libre',     NULL,               35000.00, NULL),
  (14, 'Reservada', 'Evento privado',   45000.00, NULL),
  (15, 'Libre',     NULL,               35000.00, NULL),
  (16, 'Ocupada',   'Esteban Mora',     35000.00, DATE_SUB(NOW(), INTERVAL 45 MINUTE));

-- ===========================================================================
-- POR QUE ESTAS 16 MESAS NO ESTAN EN UN .php DE EJEMPLO
-- ===========================================================================
-- La contrasena de los tres usuarios del dia 10 no esta escrita en ningun
-- archivo porque se recibe del formulario y se guarda con password_hash(). Aqui
-- no hay ningun secreto: el estado de una mesa del salon no es una contrasena,
-- y un INSERT de sixteen filas en un .sql es mas facil de leer y de repetir que
-- un bucle de PHP que ademas tendria que escribir la hora con el reloj de PHP.
-- ===========================================================================
