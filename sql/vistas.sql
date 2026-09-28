-- ===========================================================================
-- LA CARAMBOLA DORADA - Vistas para los graficos del tablero
-- Archivo: sql/vistas.sql
-- Actividad de recuperacion, dia 14, punto 1
--
-- Para cargarlo:
--   mysql -u root carambola_doradoa < sql/vistas.sql
--
-- ---------------------------------------------------------------------------
-- QUE SON LAS VISTAS Y POR QUE HACE FALTA ESTE ARCHIVO
-- ---------------------------------------------------------------------------
-- Una vista es una consulta con nombre. MySQL la guarda como si fuera una tabla
-- mas, asi que se le puede preguntar con SELECT ... FROM nombre_vista y no hay
-- que escribir el SELECT largo cada vez.
--
-- El punto 3 del enunciado pide que los graficos del tablero se alimenten de
-- las vistas, y ese es el motivo de que las cuatro esten aqui y no dentro de
-- api/graficos.php: la consulta que agrupa por mes no cambia cada dia, y si
-- estuviera escrita en el PHP habria que mantenerla ahi. En la vista vive una
-- vez, y tanto el tablero como la pagina que se quiera abrir en phpMyAdmin
-- ven lo mismo.
--
-- ---------------------------------------------------------------------------
-- POR QUE ESTA ARCHIVO NO BORRA NADA DE LA BASE
-- ---------------------------------------------------------------------------
-- Por la misma razon que sql/usuarios.sql y sql/mesas.sql: estructura.sql
-- empieza con DROP TABLE, y recargarlo dejaria el catalogo del dia 9 y los
-- datos de las capturas de evidencia sin cambios, pero este archivo solo borra
-- y recrea las CUATRO VISTAS. Las tablas de abajo no se tocan: son las del dia
-- 9 (productos, pedidos, detalle_pedidos, clientes, categorias) y las del dia
-- 10 (usuarios).
--
-- ---------------------------------------------------------------------------
-- LAS CUATRO VISTAS, Y QUE PREGUNTA CADA UNA
-- ---------------------------------------------------------------------------
--   1. ventas_por_mes        como se vendio mes a mes
--   2. ventas_por_categoria  que se vende mas
--   3. stock_critico         que productos se estan agotando
--   4. pedidos_por_cliente   pedidos y valor por cliente y fecha
--   5. clientes_top          quien mas ha comprado en total
--
-- Las vistas que usan los reportes exponen fecha o fecha_ingreso para que el
-- modelo aplique el rango. Una vista no recibe parametros, asi que el filtro
-- se aplica en la consulta que la lee con WHERE fecha BETWEEN ... .
--
-- ---------------------------------------------------------------------------
-- QUE ESTADOS CUENTAN COMO VENTA
-- ---------------------------------------------------------------------------
-- Un pedido en estado Pendiente todavia no es una venta: esta en el salon o en
-- la bandeja, y se puede cancelar. Por eso las dos vistas de ventas cuentan
-- Confirmado y Entregado, y no los quince pedidos de la base. Es una decision
-- que esta escrita aqui porque es la unica que un lector podria preguntar
-- ("por que el grafico no suma los cuatro pendientes?"): son once pedidos y
-- 1.379.500 pesos.
--
-- Con esto el total de ventas_por_mes tiene que coincidir con la suma de
-- pedidos.total de los estados Confirmado y Entregado. Si un dia no coincide,
-- es que alguien sumo otra vez el total de los pendientes.
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS carambola_doradoa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE carambola_doradoa;

-- ---------------------------------------------------------------------------
-- 1. ventas_por_mes
-- ---------------------------------------------------------------------------
-- Una fila por mes de pedido. Es la que se dibuja como linea: lo que interesa
-- de una serie de meses es la tendencia, que con barras de un mes al lado de
-- otro se lee peor.
--
-- La columna clave es la del mes, en texto "2026-09", porque ORDER BY de un
-- texto con ese formato ordena igual que la fecha: los meses van en orden
-- cronologico y no "10 antes que 2", que es lo que pasaria si el mes fuera un
-- numero suelto.
DROP VIEW IF EXISTS ventas_por_mes;

CREATE VIEW ventas_por_mes AS
SELECT
    DATE_FORMAT(p.fecha, '%Y-%m') AS mes,
    /* El dia del mes se deja a mano, porque es lo que hace falta para filtrar
       por rango: el WHERE de la API va contra esta columna y no contra el mes,
       ya que "2026-09" no se puede comparar con BETWEEN. */
    MIN(p.fecha)                 AS fecha,
    COUNT(*)                     AS pedidos,
    SUM(p.total)                 AS total,
    ROUND(AVG(p.total), 2)       AS ticket_promedio
FROM pedidos AS p
WHERE p.estado IN ('Confirmado', 'Entregado')
GROUP BY DATE_FORMAT(p.fecha, '%Y-%m');

-- ---------------------------------------------------------------------------
-- 2. ventas_por_categoria
-- ---------------------------------------------------------------------------
-- Una fila por categoria y por dia, porque el filtro de fechas del punto 4 va
-- dia a dia. El reparto se hace desde detalle_pedidos y no desde pedidos
-- porque el total del pedido es uno solo y no dice que parte es bolas y que
-- parte es mesas: para saber que se vende mas hay que mirar las lineas.
--
-- El importe sale de detalle_pedidos.precio_unitario * cantidad y no de
-- productos.valor_alquiler, porque el precio que se cobro es el que quedo
-- escrito en la linea el dia que se hizo el pedido. Si el precio de un producto
-- sube manana, las ventas de ayer tienen que seguir valiendo lo que valieron
-- ayer.
CREATE VIEW ventas_por_categoria AS
SELECT
    p.fecha              AS fecha,
    c.id                 AS categoria_id,
    c.nombre             AS categoria,
    SUM(d.cantidad)      AS unidades,
    SUM(d.cantidad * d.precio_unitario) AS total
FROM detalle_pedidos AS d
INNER JOIN pedidos  AS p ON p.id = d.pedido_id
INNER JOIN productos AS pr ON pr.id = d.producto_id
INNER JOIN categorias AS c ON c.id = pr.categoria_id
WHERE p.estado IN ('Confirmado', 'Entregado')
GROUP BY p.fecha, c.id, c.nombre;

-- ---------------------------------------------------------------------------
-- 3. stock_critico
-- ---------------------------------------------------------------------------
-- Los productos que se estan agotando, ordenados de menos a mas.
--
-- El limite de cinco unidades esta escrito aqui y no en un campo de la tabla
-- porque productos no tiene columna de stock minimo: la decision de que es
-- "poco" es del negocio y no del producto. Si manana cambia, se cambia este
-- 5 y las capturas de evidencia de este dia dejan de cuadrar, que es
-- justamente por lo que esta en un solo sitio.
--
-- Solo los activos: un producto desactivado no se esta agotando, se decidio
-- dejar de ofrecerlo. fecha_ingreso permite filtrar el inventario actual por
-- la cohorte de productos ingresados en el rango seleccionado.
CREATE VIEW stock_critico AS
SELECT
    pr.id            AS producto_id,
    pr.codigo        AS codigo,
    pr.nombre        AS nombre,
    c.nombre         AS categoria,
    pr.existencias   AS existencias,
    pr.fecha_ingreso AS fecha_ingreso,
    5                AS limite
FROM productos AS pr
INNER JOIN categorias AS c ON c.id = pr.categoria_id
WHERE pr.activo = 1
  AND pr.existencias <= 5
ORDER BY pr.existencias ASC, pr.nombre ASC;

-- ---------------------------------------------------------------------------
-- 4. pedidos_por_cliente
-- ---------------------------------------------------------------------------
-- Una fila por pedido con su cliente, fecha, estado, unidades y total.
-- La vista conserva los pedidos pendientes: el reporte es de pedidos, no solo
-- de ventas confirmadas. El rango se aplica sobre p.fecha desde el modelo.
DROP VIEW IF EXISTS pedidos_por_cliente;

CREATE VIEW pedidos_por_cliente AS
SELECT
    p.id             AS pedido_id,
    p.fecha          AS fecha,
    p.estado         AS estado,
    cl.id            AS cliente_id,
    cl.nombre        AS cliente,
    COALESCE(SUM(d.cantidad), 0) AS unidades,
    p.total          AS total
FROM pedidos AS p
INNER JOIN clientes AS cl ON cl.id = p.cliente_id
LEFT JOIN detalle_pedidos AS d ON d.pedido_id = p.id
GROUP BY p.id, p.fecha, p.estado, cl.id, cl.nombre, p.total;

-- ---------------------------------------------------------------------------
-- 5. clientes_top
-- ---------------------------------------------------------------------------
-- Cuanto ha comprado cada cliente, de mas a menos. Se suma linea por linea
-- (detalle_pedidos) y no pedidos.total, porque lo que se gasta un cliente es
-- la suma de lo que se llevo y el total del pedido es lo mismo solo cuando el
-- pedido tiene una sola linea.
--
-- ultima_compra es MAX(p.fecha) y no la fecha del ultimo pedido confirmado: un
-- cliente puede tener un pedido viejo entregado y uno de ayer pendiente, y el
-- que se lleva la ultima vez es el entregado.
--
-- El LIMIT 10 es de la vista y no de quien la pregunta, para que el grafico de
-- barras horizontales tenga siempre las mismas diez y el filtro de fechas pueda
-- cambiar el orden sin que la vista dependa de quien la llama.
CREATE VIEW clientes_top AS
SELECT
    cl.id             AS cliente_id,
    cl.nombre         AS cliente,
    cl.documento      AS documento,
    COUNT(DISTINCT p.id) AS pedidos,
    SUM(d.cantidad)   AS unidades,
    SUM(d.cantidad * d.precio_unitario) AS total,
    MAX(p.fecha)      AS ultima_compra
FROM detalle_pedidos AS d
INNER JOIN pedidos  AS p  ON p.id = d.pedido_id
INNER JOIN clientes AS cl ON cl.id = p.cliente_id
WHERE p.estado IN ('Confirmado', 'Entregado')
GROUP BY cl.id, cl.nombre, cl.documento
ORDER BY total DESC
LIMIT 10;
