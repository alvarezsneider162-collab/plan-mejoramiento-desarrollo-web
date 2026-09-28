<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Tablero de control
   Archivo: dashboard.php
   Actividad de recuperacion, dia 12, punto 1, 2, 3 y 4

   Esta pagina se reescribio entera en el dia 12. Lo que cambio:

     1. Los seis <li> del menu y el pie, que estaban escritos aqui, los
        pinta app/parciales/menu.php y app/parciales/pie.php. La cabecera, con
        el boton del menu y el <head>, la pinta app/parciales/cabecera.php.
     2. El menu sale del arreglo app/config/menu.php, y el "activo" lo marca
        aria-current="page" comparando la clave 'tablero' de este archivo con
        la clave de cada entrada.
     3. Las cuatro tarjetas de indicadores ya no traen numeros escritos a
        mano: los cuatro salen de consultas, en app/modelos/TableroModelo.php.
        Y la tabla de "Estado de las mesas" tambien: las dieciseis filas salen
        de la tabla mesas, no de este archivo.
     4. El boton del menu del dia 8 sigue siendo el mismo, pero el punto 4 de
        este dia verifica que su aria-expanded se mantenga al dia. La
        verificacion esta al final de este archivo, en comentarios.

   ---------------------------------------------------------------------------
   EL ORDEN DE ESTE ARCHIVO, Y POR QUE IMPORTA
   ---------------------------------------------------------------------------
     1. El guardian, el primero de todo, antes de la cabecera HTML.
     2. Los require_once de los archivos con funciones.
     3. Los datos: la sesion, la fecha de hoy y las consultas al tablero.
     4. Las variables que le pasa a los parciales.
     5. El HTML.

   Nada de esto se puede mover. Si el guardian no fuera el primero, un visitante
   sin sesion veria un trozo de tablero antes de que saliera el 302 al ingreso.
   Si las consultas se hicieran despues de abrir un <div>, la pagina ya habria
   empezado a pintarse y un error de MySQL saldria en mitad del HTML.
   =========================================================================== */

/* ---- 1. El guardian, antes de ni un byte de la pagina ------------------------
   El archivo entero se puede abrir sin sesion valida desde productos.php,
   usuarios.php y phpinfo.php. Los cuatro hacen lo mismo: include en la
   PRIMERA linea y nada antes, porque header() y Set-Cookie tienen que salir
   antes que el primer byte, o PHP avisa con "headers already sent" y el 302 no
   se manda. */
require_once __DIR__ . '/app/seguridad/guardia.php';

/* ---- 2. Las dependencias ---------------------------------------------------- */
require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/seguridad/salida.php';
require_once __DIR__ . '/app/modelos/TableroModelo.php';

/* ---- 3. Los datos ----------------------------------------------------------- */

/* La fecha de HOY se calcula con el reloj de PHP, que es el que tiene la zona
   America/Bogota de app/config/conexion.php. Va antes de las consultas porque
   es un dato que necesitan, y se calcula una sola vez: si dos consultas
   preguntaran "¿qué día es?" en un minuto en el que se cruza la medianoche,
   una podría ver un día y la otra otro, y las dos serían correctas. */
$hoy   = date('Y-m-d');
$ahora = time();

/* Un error de conexion no puede dejar la pagina en blanco. Las consultas van
   dentro de un try, y si MySQL esta apagado o falta la tabla mesas, cada
   tarjeta sale con un guion y con la razon al lado. Un tablero con cuatro
   guiones y un aviso dice mucho mas que cuatro errores de MySQL. */
$errorTablero = null;

$indicadores = [
    'mesasOcupadas' => null,
    'mesasLibres'   => null,
    'mesasTotal'    => null,
    'cobroDia'      => null,
    'porDevolver'   => null,
];
$mesas    = [];
$avisos   = [];
$hayMesas = false;

try {
    $pdo = Conexion::obtener();

    /* Esta pregunta va la PRIMERA, antes de las demas. Si la tabla mesas no esta
       cargada, todo lo que viene despues sobre mesas fallaria, y con esto se
       sabe desde el principio que hay que dejarlo con guiones en vez de
       intentar y catching cuatro veces. */
    $hayMesas = existeTabla($pdo, 'mesas');

    if ($hayMesas) {
        $indicadores['mesasOcupadas'] = contarMesasPorEstado($pdo, 'Ocupada');
        $indicadores['mesasLibres']   = contarMesasPorEstado($pdo, 'Libre');
        $indicadores['mesasTotal']    = contarMesas($pdo);
        $mesas                        = listarMesas($pdo);

        /* Las dos alertas de mesas se calculan aqui y no en la pagina, para que
           la pagina no tenga que saber ni de indices ni de horas. */
        $avisos['mesasCobrar'] = mesasQueSuperanLaHora(
            $pdo,
            date('Y-m-d H:i:s', $ahora - 3600)
        );
        $avisos['reservadas'] = listarMesasReservadas($pdo);
    } else {
        $errorTablero = 'La tabla <code>mesas</code> no está en la base de datos, '
                      . 'así que las dos tarjetas de mesas salen con un guion. '
                      . 'Carga <code>sql/mesas.sql</code> y recarga la página.';
    }

    /* Estas dos NO dependen de la tabla mesas, asi que se preguntan igual. Son
       las que dependen de la base del dia 9, que ya esta cargada. */
    $indicadores['cobroDia']    = cobroDelDia($pdo, $hoy);
    $indicadores['porDevolver'] = contarImplementosPorDevolver($pdo);
    $avisos['sinExistencias']  = productosSinExistencias($pdo, 5);
} catch (Throwable $e) {
    /* El detalle tecnico va a un comentario, no a la pagina: el mensaje de
       MySQL puede traer el nombre de la tabla y el de la base, que no hace
       falta que lea quien esta mirando el tablero. Lo que se muestra es el
       mismo texto que usa productos.php, para que las dos paginas del panel
       hablen el mismo idioma cuando MySQL este apagado. */
    $errorTablero = 'No se pudieron leer los indicadores desde la base de datos. '
                  . 'Revisa que MySQL esté encendido y que '
                  . 'app/config/credenciales.php tenga la base y la clave correctas.';
    /* <?= esc($e->getMessage()) ?> */
}

/* ---------------------------------------------------------------------------
   EL TIEMPO JUGADO, QUE SE CALCULA AL PINTAR
   ---------------------------------------------------------------------------
   occupied_desde viene de la base como "2026-09-27 18:05:00", y el tiempo
   jugado es la diferencia con la hora de ahora. Se calcula aqui, en PHP, y no
   con TIMESTAMPDIFF en la consulta, por dos razones:

     - el formato. La tabla muestra "35 min" o "1 h 20 min", y eso es una
       division y un redondeo, no un TIMESTAMPDIFF pelado.
     - el reloj. occupied_desde lo escribio MySQL y la hora actual la calcula
       PHP. Si las dos vinieran de MySQL serian el mismo reloj, pero al
       calcularlo aqui la cuenta se hace una sola vez con el mismo reloj que
       las dos alertas de arriba, y las horas de la tabla y las de la alerta no
       pueden contradecirse.
   --------------------------------------------------------------------------- */

/**
 * Pinta un intervalo en minutos como "35 min" o "1 h 20 min".
 *
 * Es una funcion suelta y no un metodo de una clase, por el mismo reparto que
 * ProductoModelo.php y UsuarioModelo.php: en este proyecto las funciones se
 * declaran sueltas y el HTML se queda en la pagina.
 *
 * Se declara con function_exists porque el archivo puede incluirse dos veces en
 * el mismo proceso si alguien lo requiere desde otro sitio, y PHP no perdona
 * dos definiciones de la misma funcion.
 */
if (!function_exists('formatearTiempoJugado')) {
    function formatearTiempoJugado(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos . ' min';
        }

        return intdiv($minutos, 60) . ' h ' . str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT) . ' min';
    }
}

/* El mismo reparto para la clase del badge del estado. usuarios.php tiene la
   suya, claseEstado(), que es para activo 1 o 0; esta es para los tres estados
   del ENUM. Son funciones distintas con nombres distintos a proposito: si las
   dos se llamaran igual y un archivo cargara las dos, PHP daria "Cannot
   redeclare". */
if (!function_exists('claseEstadoMesa')) {
    function claseEstadoMesa(string $estado): string
    {
        return match ($estado) {
            'Ocupada'   => 'badge--advertencia',
            'Reservada' => 'badge--info',
            default     => 'badge--exito',
        };
    }
}

/* Los minutos jugados de cada mesa, ya calculados. Se hacen aqui y no en el
   bucle de la tabla para que el bucle solo pinte: si el calculo estuviera en el
   HTML habria un date() dentro del <td>, y la fecha de cada fila se volveria a
   preguntar al sistema sixteen veces. */
$minutosPorMesa = [];
foreach ($mesas as $fila) {
    if ($fila['ocupada_desde'] === null) {
        continue;
    }

    /* strtotime devuelve false si el valor no es una fecha que PHP entienda.
       Con el ternario, un valor raro da 0 minutos en vez de un warning. */
    $desde = strtotime((string) $fila['ocupada_desde']);
    $minutosPorMesa[(int) $fila['id']] = $desde === false
        ? 0
        : max(0, intdiv($ahora - $desde, 60));
}

/* ---- 4. Lo que se le pasa a los parciales ----------------------------------- */
$tituloPagina = 'Tablero de control — La Carambola Dorada';
$etiqueta     = 'Sala 1';

/* La clave de esta pagina en el menu. Es la que se compara con cada
   entrada['clave'] del arreglo para marcar el activo. */
$itemActual = 'tablero';
?>

<?php
/* ---- 5. El HTML --------------------------------------------------------------
   A partir de aqui ya no hay consultas: todo lo que se necesita esta en las
   variables de arriba, y el HTML solo las pinta. */
require __DIR__ . '/app/parciales/cabecera.php';
?>

<?php /* El menu va FUERA de <main>, antes de el, porque en la rejilla de
        escritorio es una columna propia: "menu contenido". Si estuviera dentro
        del <main>, en el telefono se pintaria debajo de la barra pero en
        escritorio se comeria el ancho del contenido, que es justo lo que la
        hoja de estilos dice en grid-template-areas. */ ?>
<?php require __DIR__ . '/app/parciales/menu.php'; ?>

<main class="panel__contenido">
  <h1>Tablero de control</h1>

  <?php if ($errorTablero !== null): ?>
    <p class="alerta alerta--error" role="alert"><?= $errorTablero ?></p>
  <?php endif; ?>

  <section>
    <h2>Resumen del d&iacute;a</h2>

    <div class="indicadores">

      <article class="tarjeta">
        <h3>Mesas ocupadas</h3>
        <?php if ($indicadores['mesasOcupadas'] === null): ?>
          <p aria-label="Sin datos">&mdash;</p>
        <?php else: ?>
          <p><?= (int) $indicadores['mesasOcupadas'] ?>
            <?php if ($indicadores['mesasTotal'] !== null): ?>
              <small>de <?= (int) $indicadores['mesasTotal'] ?></small>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </article>

      <article class="tarjeta">
        <h3>Mesas libres</h3>
        <?php if ($indicadores['mesasLibres'] === null): ?>
          <p aria-label="Sin datos">&mdash;</p>
        <?php else: ?>
          <p><?= (int) $indicadores['mesasLibres'] ?></p>
        <?php endif; ?>
      </article>

      <article class="tarjeta">
        <h3>Cobro acumulado del d&iacute;a</h3>
        <?php if ($indicadores['cobroDia'] === null): ?>
          <p aria-label="Sin datos">&mdash;</p>
        <?php else: ?>
          <?php /* number_format() pide el separador DECIMAL primero y el de MILES
                  segundo, en ese orden, que es al reves de como se escribe un
                  numero en ingles. Con la coma y el punto al reves, que es lo
                  que hacia productos.php, un cobro de 108.000 pesos salia
                  "108,000" y parecia una cuenta en dolares. Aqui se escribe
                  108.000, que es como se lee un peso colombiano. */ ?>
          <p>$ <?= number_format((float) $indicadores['cobroDia'], 0, ',', '.') ?></p>
        <?php endif; ?>
      </article>

      <article class="tarjeta">
        <h3>Implementos por devolver</h3>
        <?php if ($indicadores['porDevolver'] === null): ?>
          <p aria-label="Sin datos">&mdash;</p>
        <?php else: ?>
          <p><?= (int) $indicadores['porDevolver'] ?></p>
        <?php endif; ?>
      </article>

    </div>

    <p class="alerta alerta--neutro">
      Las cuatro tarjetas salen de consultas preparadas de
      <code>app/modelos/TableroModelo.php</code>, le&iacute;das de MySQL el
      <?= esc(date('Y-m-d', $ahora)) ?>. Si cambias el estado de una mesa en
      la base, esta pagina cambia en el siguiente F5, sin tocar este archivo.
    </p>
  </section>

  <section>
    <h2>Estado de las mesas</h2>

    <div class="tabla-scroll">
      <table class="tabla-productos">
        <caption>Las dieciséis mesas del salón, con su estado y su jugador.</caption>
        <thead>
          <tr>
            <th scope="col">Mesa</th>
            <th scope="col">Estado</th>
            <th scope="col">Jugador actual</th>
            <th scope="col">Hora de inicio</th>
            <th scope="col">Tiempo jugado</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($mesas === []): ?>
            <tr>
              <td colspan="5" data-label="Aviso">
                <?php if ($hayMesas): ?>
                  No hay mesas en la base de datos.
                <?php else: ?>
                  La tabla <code>mesas</code> no está cargada. Ejecuta
                  <code>sql/mesas.sql</code>.
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($mesas as $fila): ?>
            <tr>
              <th scope="row" data-label="Mesa">Mesa <?= (int) $fila['numero'] ?></th>
              <td data-label="Estado">
                <span class="badge <?= esc(claseEstadoMesa((string) $fila['estado'])) ?>">
                  <?= esc($fila['estado']) ?>
                </span>
              </td>
              <td data-label="Jugador actual">
                <?= $fila['jugador'] === null ? '&mdash;' : esc($fila['jugador']) ?>
              </td>
              <td data-label="Hora de inicio">
                <?php if ($fila['ocupada_desde'] === null): ?>
                  &mdash;
                <?php else: ?>
                  <?= esc(substr((string) $fila['ocupada_desde'], 11, 5)) ?>
                <?php endif; ?>
              </td>
              <td data-label="Tiempo jugado">
                <?php if (!isset($minutosPorMesa[(int) $fila['id']])): ?>
                  &mdash;
                <?php else: ?>
                  <?= esc(formatearTiempoJugado($minutosPorMesa[(int) $fila['id']])) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <aside>
    <h2>Alertas</h2>

    <ul class="lista-alertas">
      <?php /* Las alertas se pintan con un if por cada consulta y no con un
              bucle sobre un arreglo "todo mezclado": asi se ve de donde sale
              cada una leyendo el HTML, que es lo que hace falta cuando hay que
              corregir algo a las tres de la manana. */ ?>

      <?php foreach ($avisos['mesasCobrar'] ?? [] as $cobrar): ?>
        <li class="alerta alerta--advertencia">
          Mesa <?= (int) $cobrar['numero'] ?>: <?= esc($cobrar['jugador'] ?? 'alguien') ?>
          lleva m&aacute;s de una hora ocupada.
        </li>
      <?php endforeach; ?>

      <?php foreach ($avisos['sinExistencias'] ?? [] as $producto): ?>
        <li class="alerta alerta--error">
          <?= esc($producto['nombre']) ?> (<?= esc($producto['codigo']) ?>)
          se qued&oacute; sin existencias.
        </li>
      <?php endforeach; ?>

      <?php if (($indicadores['porDevolver'] ?? 0) > 0): ?>
        <li class="alerta alerta--info">
          Hay <?= (int) $indicadores['porDevolver'] ?> piezas en pedidos que
          siguen pendientes de entregar.
        </li>
      <?php endif; ?>

      <?php foreach ($avisos['reservadas'] ?? [] as $reserva): ?>
        <li class="alerta alerta--info">
          Mesa <?= (int) $reserva['numero'] ?> reservada:
          <?= esc($reserva['jugador'] ?? 'sin nombre') ?>.
        </li>
      <?php endforeach; ?>

      <?php if ($avisos === [] && $errorTablero === null): ?>
        <li class="alerta alerta--exito">Sin alertas: todo en orden.</li>
      <?php endif; ?>
    </ul>
  </aside>
</main>

<?php
/* ---- El pie, los scripts y el cierre del documento ----------------------------
   pie.php cierra el <div class="panel"> que abrio cabecera.php, asi que el
   HTML se queda cuadrado sin depender de que la pagina se acuerde.

   El script se pasa por $scriptsExtra y no se escribe aqui, para que los tres
   parciales sean los unicos que traen <script>. El orden es menu.js primero:
   el boton del menu, y despues lo que esta pagina necesite. */
$scriptsExtra = '<script src="app/menu.js"></script>';

require __DIR__ . '/app/parciales/pie.php';
?>

<?php
/* ===========================================================================
   PUNTO 4: EL BOTON DEL MENU Y EL aria-expanded
   ---------------------------------------------------------------------------
   Que se conecto: el boton de la barra y el boton de cerrar del menu ya estaban
   en el HTML antes de este dia, y app/menu.js los conecta. Lo que hace el
   archivo, en orden:

     1. Al cargar, sincronizar() lee el estado de la casilla y pone
        aria-expanded="false", porque el HTML lo trae asi y el estado real
        puede venir ya marcado (por ejemplo, si alguien abrio el menu y luego
        recargo).
     2. Al pulsar el boton, alternar() da la vuelta a la casilla y vuelve a
        sincronizar, de modo que el atributo siempre dice lo mismo que la
        casilla.
     3. El velo es un <label for="interruptor-menu">, o sea, marca la misma
        casilla. Como el <label> no es un control, su evento no dispara click
        en el checkbox sino un "change", y por eso menu.js tambien escucha
        "change": si no, al tocar el velo la casilla se desmarcaria sin que
        aria-expanded se enterara y el boton seguiria diciendo "abierto".
     4. Escape y el envio del formulario no tocan nada de esto.

   COMO SE VERIFICA, SIN FIARSE DE LA PALABRA
   ---------------------------------------------------------------------------
   aria-expanded es un atributo que no se ve: abrir el menu en el navegador se
   ve igual con el atributo bien que mal. Por eso la verificacion de este dia
   no es "el menu abre", sino leer el DOM despues de que el menu haya cambiado
   de estado. Con el navegador en linea de ordenes:

       msedge --headless --dump-dom "http://localhost/.../dashboard.php"

   devuelve el HTML ya procesado por el navegador, con los atributos ya
   actualizados por menu.js. Con el menu cerrado sale:

       <button type="button" class="boton-menu" id="boton-menu"
               aria-expanded="false" aria-controls="menu-lateral">

   y con el menu abierto, el mismo boton con aria-expanded="true" y el <aside>
   con la clase "abierto". Las dos salidas estan en la bitacora del dia 12.

   Y esto es lo que hay que mirar en el HTML para estar seguro de que el
   atributo significa algo:

     - aria-expanded va en el <button>, no en el <aside>. El <aside> no es el
       que se despliega: el que se despliega es lo que el boton controla, y por
       eso aria-controls apunta a el y aria-expanded esta en el boton.
     - aria-expanded="false" en el HTML de partida, aunque el menu este
       visible. En escritorio el menu SI esta a la vista, pero plegado no esta:
       el atributo responde a si el menu se puede plegar, que es lo que el
       boton hace, y el boton ni se ve por encima de 1024 px.
     - aria-controls="menu-lateral" tiene que coincidir con el id del <aside>.
       Si el id cambia y el atributo se queda, el boton deja de declarar que
       controla y el lector de pantalla no puede relacionarlos.

   ---------------------------------------------------------------------------
   POR QUE NO HAY UN keydown EN menu.js PARA LA TECLA ENTER
   ---------------------------------------------------------------------------
   Porque un <button> ya emite click cuando se pulsa Enter. Si ademas se
   escuchara keydown, la tecla contaria dos veces, una por el keydown y otra por
   el click, y el menu se abriria y cerraria en el mismo instante. En el dia 6
   esto no pasaba porque el control era un <label>, que no es un control y no
   se activa con Enter: por eso el <label> se cambio por un <button> y el
   teclado empezo a funcionar sin escribir una sola linea de keydown.
   =========================================================================== */
