-- ===========================================================================
-- LA CARAMBOLA DORADA - Usuarios e intentos de acceso
-- Archivo: sql/usuarios.sql
-- Actividad de recuperacion, dia 10, punto 1
--
-- Para cargarlo:
--   mysql -u root < sql/usuarios.sql
--
-- Este archivo va aparte, y no pegado dentro de sql/estructura.sql, por una
-- razon concreta: estructura.sql empieza con DROP TABLE, asi que si se vuelve a
-- cargar borra los usuarios que se hayan registrado y las capturas de evidencia
-- dejan de cuadrar. Aqui solo se borran y se recrean las dos tablas de este dia,
-- y el catalogo del dia 9 (productos, pedidos y los demas) no se toca.
--
-- ---------------------------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------------------------
-- Las nueve columnas que pide el enunciado:
--
--   id                numero de usuario
--   caracteres        cuantos caracteres tiene la clave. SOLO el numero, jamas
--                     la clave: por eso es una columna aparte y se rellena con
--                     strlen() de lo que el usuario escribio.
--   rol               administrador, vendedor o consultor
--   nombre            nombre de la persona, que es donde va el <script> del
--                     punto 5
--   correo            correo electronico, UNIQUE: no puede haber dos usuarios
--                     con el mismo
--   clave_hash        255 caracteres. La contrasena NUNCA se guarda; lo que se
--                     guarda es su hash, que empieza por $2y$ y del que no se
--                     puede volver a la contrasena.
--   activo            1 = puede entrar, 0 = dado de baja
--   bloqueado_hasta    fecha y hora en que se levanta el bloqueo, NULL si no
--                     esta bloqueado
--   creado_en         momento del registro
--
-- Por que clave_hash es VARCHAR(255) y no VARCHAR(60):
-- hoy password_hash() con PASSWORD_DEFAULT produce bcrypt, que ocupa 60
-- caracteres, pero el enunciado pide 255 y ese ancho deja sitio para el dia en
-- que el algoritmo por defecto cambie a argon2, que es mas largo. Si mas tarde
-- se cambia, los hashes viejos siguen siendo validos.
--
-- Por que el correo es VARCHAR(190) y no mas largo: en utf8mb4 un indice unico
-- solo puede cubrir 191 caracteres, porque cada caracter puede ocupar 4 bytes y
-- el limite de un indice en InnoDB con utf8mb4 son 767 bytes en esta
-- configuracion. Con 190 el indice entra de sobra.
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS carambola_doradoa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE carambola_doradoa;

DROP TABLE IF EXISTS intentos_acceso;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  caracteres        SMALLINT UNSIGNED NOT NULL,
  rol               ENUM('administrador', 'vendedor', 'consultor') NOT NULL,
  nombre            VARCHAR(80)     NOT NULL,
  correo            VARCHAR(190)    NOT NULL,
  clave_hash        VARCHAR(255)    NOT NULL,
  activo            TINYINT(1)      NOT NULL DEFAULT 1,
  bloqueado_hasta   DATETIME        NULL DEFAULT NULL,
  creado_en         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo),
  KEY idx_usuarios_rol (rol),
  KEY idx_usuarios_bloqueo (bloqueado_hasta),

  /* No se puede registrar una clave de menos de 8 caracteres. El numero va en
     la columna caracteres, pero el JavaScript y el formulario no son la linea
     de defensa: esta comprobacion tambien esta en el PHP, porque el CHECK se
     cumple en el servidor aunque alguien se saltase el formulario. */
  CONSTRAINT chk_usuarios_caracteres CHECK (caracteres >= 8)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ===========================================================================
-- intentos_acceso
-- ===========================================================================
-- Una fila por cada intento de entrar, salga bien o mal. Es lo que permite
-- contar los cinco fallos en quince minutos sin depender de la memoria de la
-- pagina, que se pierde en cuanto se recarga.
--
-- NO lleva clave foranea a usuarios, y es a proposito: el intento fallido con
-- un correo que no existe tambien se guarda, porque es justamente el caso
-- interesante. Si solo se guardaran los intentos de usuarios reales, el numero
-- de filas diria si el correo existe o no.
--
-- Tampoco lleva la contrasena. Ni la fallida ni la correcta: aqui solo queda
-- el correo, si se logro o no, desde que IP y cuando. Asi el historico de
-- intentos no es un archivo de contrasenas.
--
-- El indice (correo, exito, creado_en) es el que hace la consulta del punto 3:
-- contar los fallos de un correo en una ventana de tiempo. Sin el, habria que
-- traer toda la tabla y filtrarla en PHP.
-- ===========================================================================
CREATE TABLE intentos_acceso (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  correo      VARCHAR(190) NOT NULL,
  exito       TINYINT(1)    NOT NULL DEFAULT 0,
  ip          VARCHAR(45)  NULL DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_intentos_correo_ventana (correo, exito, creado_en)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ===========================================================================
-- NOTA SOBRE LOS DATOS
-- ===========================================================================
-- Este archivo NO mete ningun usuario. Los tres usuarios del punto 2 (un
-- administrador, un vendedor y un consultor) y el del punto 5 se crean
-- entrando al formulario de registro.php, no escribiendo aqui.
--
-- La razon es que la contrasena de cada uno se recibe en el formulario, se
-- convierte en hash con password_hash() y se guarda solo el hash. Si el
-- usuario se escribiera en este archivo, su contrasena quedaria escrita en un
-- .sql de texto plano, que es justo lo que el enunciado prohibe.
-- ===========================================================================
