<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - API de los graficos del tablero
   Archivo: api/graficos.php
   Actividad de recuperacion, dia 14, punto 2

   QUE DEVUELVE
   ---------------------------------------------------------------------------
   Un JSON con los tres conjuntos de datos que el tablero necesita para dibujar
   sus tres graficos, ya agrupados y con el rango de fechas aplicado. No
   devuelve HTML ni acepta ninguna otra cosa.

   QUE GRAFICO SE SACA DE QUE VISTA
   ---------------------------------------------------------------------------
      linea  "Ventas por mes"        ventas_por_mes        (punto 1, vista 1)
      barras "Ventas por categoria"  ventas_por_categoria  (punto 1, vista 2)
      barras "Stock critico"         stock_critico         (punto 1, vista 3)

   La cuarta vista del punto 1, clientes_top, no entra aqui: es una compra
   acumulada de toda la vida del cliente, sin fecha que filtrar. Se deja como
   esta porque el enunciado la pide, y se consulta a mano desde phpMyAdmin.

   ---------------------------------------------------------------------------
   EL GUARDIAN VA PRIMERO, Y ESO ES EL PUNTO 2
   ---------------------------------------------------------------------------
   Es la primera instruccion del archivo, antes de la cabecera y antes de
   cualquier require_once, por la misma razon que en las paginas del dia 11:
   header() y Set-Cookie tienen que salir antes del primer byte de la respuesta.

   Lo que hace no es "contestar un error": es no contestar NADA. Sin sesion este
   archivo responde 302 hacia la pantalla de ingreso y el cuerpo de la respuesta
   va vacio. No hay un {"error": ...}, no hay un JSON con ceros, y no hay ni una
   fila de la base en el cable. La unica forma de que alguien sin sesion reciba
   estos numeros es que tenga la sesion de otra persona.

   Por eso va el guardian y no un if con json_encode: el guardian es el que sabe
   comprobar la sesion, la huella del navegador y los relojes de inactividad, y
   escribir una segunda comprobacion seria una copia que se quedaria vieja.

   ---------------------------------------------------------------------------
   LA CARPETA api/ Y rutaApp()
   ---------------------------------------------------------------------------
   Este archivo vive un nivel mas abajo que el resto del panel, y el guardian
   construye la URL del ingreso con rutaApp(), que sale de dirname() del script.
   Sin el ajuste del dia 14 en app/seguridad/sesion.php el 302 apuntaria a
   api/login.php y el visitante recibiria un 404 en vez de la pantalla de
   ingreso. Ese ajuste esta anotado en esa funcion.

   ---------------------------------------------------------------------------
   NINGUNA CONSULTA SQL LLEGA AL NAVEGADOR
   ---------------------------------------------------------------------------
   Lo que sale por aqui son numeros y texto, nunca la consulta. Las tres
   consultas que se ejecutan estan escritas enteras en este archivo, con el
   nombre de la vista a mano y sin parametro para el nombre: no hay un
   $_GET que llegue hasta la consulta. Y aunque lo hubiera, lo unico que se
   filtra desde la URL son dos fechas, y van como parametros de un statement
   preparado, que es lo unico que MySQL separa de verdad del codigo.
   =========================================================================== */

require_once __DIR__ . '/../app/seguridad/guardia.php';

/* A partir de aqui ya se sabe que hay sesion. El content-type va antes de la
   primera consulta: si MySQL fallara, lo que se veria es el error de PHP y no
   un JSON mal formado, que es lo que hay que ver para saber que paso. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../app/config/conexion.php';

/**
 * El rango de fechas por defecto: desde el primer pedido hasta el ultimo.
 *
 * Se declara antes de usarse, y no al final como otras funciones del proyecto,
 * porque esta va dentro de un if (!function_exists()) y una declaracion dentro
 * de una condicion no se adelanta: si se llamara antes de llegar al if, PHP
 * diria "Call to undefined function" y el tablero se quedaria sin graficos.
 */
if (!function_exists('rangoHistorico')) {
    function rangoHistorico(): array
    {
        $pdo = Conexion::obtener();

        $fila = $pdo->query(
            'SELECT MIN(fecha) AS desde, MAX(fecha) AS hasta FROM pedidos'
        )->fetch(PDO::FETCH_ASSOC);

        $desde = (string) ($fila['desde'] ?? '');
        $hasta = (string) ($fila['hasta'] ?? '');

        /* Si no hay ni un pedido en la base, MIN y MAX vienen a NULL y las dos
           fechas serian una cadena vacia, el filtro se quedaria sin valor y el
           tablero no tendria nada que dibujar. En ese caso el rango es el mes
           que viene. */
        if ($desde === '' || $hasta === '') {
            return ['desde' => date('Y-m-01'), 'hasta' => date('Y-m-d')];
        }

        return ['desde' => $desde, 'hasta' => $hasta];
    }
}

/**
 * Contesta un error en JSON y termina.
 *
 * Un exit con json_encode y no un die: este archivo responde JSON siempre, y
 * asi app/graficos.js no tiene que adivinar si lo que le llego fue una pagina
 * de error de PHP o un JSON.
 */
function responderError(string $mensaje, int $codigo): never
{
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------------------------------------------------------------------
   EL RANGO DE FECHAS DEL PUNTO 4
   ---------------------------------------------------------------------------
   Las dos fechas llegan por la URL, del filtro del tablero. Se comprueban con
   un formato exacto y no con strtotime(), porque strtotime() acepta "ayer" o
   "manana" y aqui se espera una fecha y solo una fecha.

   Cuando no llega ninguna (la primera vez que se abre el tablero) el rango es
   el de todo el historico que hay en la base, preguntado con MIN y MAX y no
   escrito a mano: si manana se carga sql/datos.sql con otras fechas, un rango
   fijo dejaria pedidos fuera de los graficos sin que se notara.
*/
$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));

if ($desde === '' && $hasta === '') {
    $rango = rangoHistorico();
    $desde = $rango['desde'];
    $hasta = $rango['hasta'];
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) !== 1
 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) !== 1) {
    responderError('Las fechas tienen que ir en formato AAAA-MM-DD.', 400);
}

/* Comparadas como texto, y por eso el formato se comprueba antes: "2026-10-09"
   es mayor que "2026-10-1" solo si los dos dias llevan dos digitos. Con
   el formato ya comprobado, comparar cadenas es comparar fechas. */
if ($desde > $hasta) {
    responderError('La fecha inicial es posterior a la fecha final.', 400);
}

/* ---------------------------------------------------------------------------
   LAS TRES CONSULTAS
   ---------------------------------------------------------------------------
   Las dos de ventas se filtran con el rango:

     ventas_por_mes        agrupa por MES, y su única columna de fecha es el
                           primer día de cada mes. No tiene sentido filtrar esa
                           columna día a día: un rango "del 10 al 20 de octubre"
                           rompería el mes a pedazos y la línea no podría
                           dibujarlo. Por eso el WHERE va sobre el mes derivado
                           del rango: DATE_FORMAT de las dos fechas, y el mes que
                           toca el rango entra entero. Efecto del filtro: quita
                           los meses que no caen dentro del rango.
     ventas_por_categoria  agrupa por día, así que el WHERE va directo sobre la
                           fecha y aquí se suma por categoría.

   La tercera, stock_critico, no lleva WHERE: no tiene columna de fecha porque
   una existencia no es de ninguna fecha. Es el único gráfico que no cambia al
   mover el filtro, y el tablero lo dice al lado para que no parezca un fallo.
*/
try {
    $pdo = Conexion::obtener();

    $meses = $pdo->prepare(
        'SELECT mes, MIN(fecha) AS fecha, SUM(pedidos) AS pedidos, SUM(total) AS total
           FROM ventas_por_mes
          WHERE mes BETWEEN ? AND ?
          GROUP BY mes
          ORDER BY mes ASC'
    );
    $meses->execute([
        substr($desde, 0, 7),
        substr($hasta, 0, 7),
    ]);

    $categorias = $pdo->prepare(
        'SELECT categoria, SUM(unidades) AS unidades, SUM(total) AS total
           FROM ventas_por_categoria
          WHERE fecha BETWEEN ? AND ?
          GROUP BY categoria
          ORDER BY total DESC'
    );
    $categorias->execute([$desde, $hasta]);

    $stock = $pdo->query(
        'SELECT codigo, nombre, categoria, existencias, limite
           FROM stock_critico'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    /* El detalle tecnico no sale en el JSON: el mensaje de MySQL puede traer el
       nombre de la vista y el de la base, y eso no hace falta que lo lea quien
       esta mirando el tablero. Al log de PHP si, que ahi si hace falta. */
    error_log('api/graficos.php: ' . $e->getMessage());
    responderError('No se pudieron leer los datos de los graficos.', 500);
}

/* ---------------------------------------------------------------------------
   LA RESPUESTA
   ---------------------------------------------------------------------------
   Los numeros salen como numeros y no como texto. En PHP un SUM() sobre una
   columna DECIMAL viene como cadena ("755500.00"), y una cadena en el JSON
   llega al JavaScript como texto: al sumar dos barras se concatenarian en vez
   de sumarse, y el grafico saldria con un numero gigante. Por eso se castean a
   float antes de meterlos en el arreglo.

   La clave de cada serie ("mes", "categoria", "stock") es el mismo id que tiene
   su <figure> en el tablero, para que app/graficos.js no tenga una tabla de
   equivalencias que se pueda quedar vieja.
*/
echo json_encode([
    'ok'     => true,
    'rango'  => ['desde' => $desde, 'hasta' => $hasta],
    'series' => [
        'mes' => array_map(static function (array $fila): array {
            return [
                'etiqueta' => (string) $fila['mes'],
                'valor'    => (float) $fila['total'],
                'extra'    => (int) $fila['pedidos'],
            ];
        }, $meses->fetchAll(PDO::FETCH_ASSOC)),

        'categoria' => array_map(static function (array $fila): array {
            return [
                'etiqueta' => (string) $fila['categoria'],
                'valor'    => (float) $fila['total'],
                'extra'    => (int) $fila['unidades'],
            ];
        }, $categorias->fetchAll(PDO::FETCH_ASSOC)),

        'stock' => array_map(static function (array $fila): array {
            return [
                'etiqueta' => (string) $fila['nombre'],
                'valor'    => (int) $fila['existencias'],
                'extra'    => (string) $fila['codigo'],
                'limite'   => (int) $fila['limite'],
            ];
        }, $stock),
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
