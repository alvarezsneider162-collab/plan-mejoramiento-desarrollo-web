-- ===========================================================================
-- LA CARAMBOLA DORADA - Estructura de la base de datos
-- Archivo: sql/estructura.sql
-- Actividad de recuperacion, dia 9, punto 2
--
-- Crea la base y las SEIS tablas del proyecto, con claves foraneas,
-- tipos ajustados a lo que cada columna guarda e indices.
--
-- Para cargarlo:
--   mysql -u root < sql/estructura.sql
--
-- Las seis tablas y por que esta cada una:
--   categorias      los cuatro tipos de la columna "categoria" del formulario
--   proveedores     de donde sale el campo "proveedor" del formulario
--   productos       el inventario, que es lo que pinta productos.php
--   clientes        quien alquila
--   pedidos         un alquiler, con su fecha y su estado
--   detalle_pedidos que productos lleva cada pedido y a que precio
--
-- InnoDB en todas: sin InnoDB no hay claves foraneas, que es justo lo que
-- el enunciado pide declarar.
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS carambola_doradoa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE carambola_doradoa;

-- Las tablas se borran de la mas dependiente a la menos dependiente, porque
-- una tabla no puede desaparecer mientras otra la apunte con una clave
-- foranea.
DROP TABLE IF EXISTS detalle_pedidos;
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS proveedores;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS categorias;


-- ---------------------------------------------------------------------------
-- categorias
-- Cuatro filas: implemento de juego, consumible, repuesto y mesa de billar.
-- ---------------------------------------------------------------------------
CREATE TABLE categorias (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(60)     NOT NULL,
  descripcion VARCHAR(200)    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Tipo de producto: implemento, consumible, repuesto o mesa';


-- ---------------------------------------------------------------------------
-- proveedores
-- Sale del campo "proveedor" del formulario de alta.
-- ---------------------------------------------------------------------------
CREATE TABLE proveedores (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(80)  NOT NULL,
  contacto VARCHAR(80)  NULL,
  telefono VARCHAR(20)  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proveedores_nombre (nombre)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Casa comercial que surte el inventario';


-- ---------------------------------------------------------------------------
-- productos
-- El inventario. Los nombres de las columnas son los que ya usa el
-- formulario del dia 2, no los del ejemplo del enunciado.
-- ---------------------------------------------------------------------------
CREATE TABLE productos (
  id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  codigo         CHAR(7)          NOT NULL COMMENT 'TAC-001: tres letras, guion, tres digitos',
  nombre         VARCHAR(60)      NOT NULL,
  categoria_id   INT UNSIGNED     NOT NULL,
  condicion      ENUM('Nuevo', 'Semi-nuevo', 'En reparación')
                                 NOT NULL DEFAULT 'Nuevo',
  existencias    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  valor_alquiler DECIMAL(10, 2) UNSIGNED NOT NULL,
  proveedor_id   INT UNSIGNED     NULL,
  descripcion    VARCHAR(200)     NULL,
  fecha_ingreso  DATE             NOT NULL,
  PRIMARY KEY (id),

  -- El codigo identifica un producto, asi que no puede repetirse.
  UNIQUE KEY uq_productos_codigo (codigo),

  -- El buscador del dia 9 filtra por nombre, asi que el nombre se indexa.
  KEY idx_productos_nombre (nombre),

  -- Indices de las dos claves foraneas. InnoDB ya crea uno por cada una, pero
  -- declararlos a mano deja el motivo a la vista y aguanta bien el caso de
  -- que alguien cambie el motor de la tabla.
  KEY idx_productos_categoria (categoria_id),
  KEY idx_productos_proveedor (proveedor_id),

  -- Las claves foraneas. RESTRICT en vez de CASCADE porque borrar una categoria
  -- con veinte productos encima no deberia borrar esos productos sin querer.
  CONSTRAINT fk_productos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_productos_proveedor
    FOREIGN KEY (proveedor_id) REFERENCES proveedores (id)
    ON UPDATE CASCADE ON DELETE SET NULL,

  -- Tipos ajustados: no se puede tener stock negativo, ni un alquiler de cero.
  CONSTRAINT chk_productos_existencias CHECK (existencias >= 0),
  CONSTRAINT chk_productos_valor       CHECK (valor_alquiler > 0)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Inventario del salon';


-- ---------------------------------------------------------------------------
-- clientes
-- Quien alquila. El documento es lo que los distingue.
-- ---------------------------------------------------------------------------
CREATE TABLE clientes (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(80)  NOT NULL,
  documento VARCHAR(20) NOT NULL,
  telefono VARCHAR(20)  NULL,
  email    VARCHAR(120) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clientes_documento (documento),
  KEY idx_clientes_nombre (nombre)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Personas o empresas que alquilan los productos';


-- ---------------------------------------------------------------------------
-- pedidos
-- Un alquiler. El total se guarda ya calculado para poder listar rapido.
-- ---------------------------------------------------------------------------
CREATE TABLE pedidos (
  id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED      NOT NULL,
  fecha      DATE              NOT NULL,
  estado     ENUM('Pendiente', 'Confirmado', 'Entregado')
                            NOT NULL DEFAULT 'Pendiente',
  total      DECIMAL(12, 2)    NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_pedidos_cliente (cliente_id),
  KEY idx_pedidos_fecha   (fecha),
  KEY idx_pedidos_estado  (estado),
  CONSTRAINT fk_pedidos_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Alquileres hechos en el salon';


-- ---------------------------------------------------------------------------
-- detalle_pedidos
-- La tabla puente: que producto lleva cada pedido, cuantos y a que precio.
-- El precio se copia aqui a proposito, porque el valor de alquiler de un
-- producto puede cambiar manana y el pedido de ayer debe seguir cuadrando.
-- ---------------------------------------------------------------------------
CREATE TABLE detalle_pedidos (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  pedido_id       INT UNSIGNED  NOT NULL,
  producto_id     INT UNSIGNED  NOT NULL,
  cantidad        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (id),
  -- Un producto no puede aparecer dos veces en el mismo pedido: se suman las
  -- unidades en vez de repetir la fila.
  UNIQUE KEY uq_detalle_pedido_producto (pedido_id, producto_id),
  KEY idx_detalle_producto (producto_id),
  CONSTRAINT fk_detalle_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto
    FOREIGN KEY (producto_id) REFERENCES productos (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Productos de cada pedido, con su cantidad y su precio';
