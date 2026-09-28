<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Menu lateral
   Archivo: app/parciales/menu.php
   Actividad de recuperacion, dia 12, punto 2

   Este parcial imprime el menu lateral entero: el velo, el <aside>, el boton de
   cerrar y la lista de entradas. Y la lista NO esta escrita aqui: se pinta
   desde el arreglo de app/config/menu.php, que es el unico sitio donde se
   decide que pantallas tiene el sistema.

   ---------------------------------------------------------------------------
   EL CAMBIO QUE TRAE ESTE ARCHIVO
   ---------------------------------------------------------------------------
   Antes, cada pagina traia sus seis <li> escritas a mano:

       <li><a href="dashboard.php" aria-current="page">Tablero</a></li>
       <li><a href="productos.php">Productos</a></li>
       ...

   y el aria-current lo ponia a mano la pagina que era la actual. Cuatro
   archivos, cuatro listas, y el "activo" puesto a pulso. Un dia se anadio una
   pantalla nueva a tres de los cuatro archivos y se olvido del cuarto, y desde
   ese dia el menu de esa pagina no tenia forma de marcar donde estabas.

   Ahora las seis lineas estan en el arreglo, y este archivo las recorre.

   ---------------------------------------------------------------------------
   COMO SE MARCA EL ACTIVO, Y POR QUE CON aria-current
   ---------------------------------------------------------------------------
   Con aria-current="page", que es el atributo de ARIA que dice "este es el
   enlace de la pagina en la que estas". Es lo que anuncia el lector de pantalla
   ("Tablero, enlace, pagina actual") y lo que pinta la hoja de estilos con

       .panel__menu a[aria-current="page"]

   que pone la letra en negrita, el fondo de marca y una raya dorada a la
   izquierda.

   Y no se pone comparando la ruta: la pagina pasa su CLAVE, que es un nombre
   corto y fijo ("tablero", "productos"), y se compara eso:

       $entrada['clave'] === $itemActual

   Si se compararan las rutas tendria que escribir en cada pagina la misma
   cadena que esta en el arreglo, y un error de teclear en esa cadena no da
   ningun error: la pagina se abre bien y simplemente no se marca nada. Con la
   clave, el error se ve en el menu, porque no hay ningun enlace marcado.

   ---------------------------------------------------------------------------
   QUIEN VE QUE, Y DE DONDE SALE EL ROL
   ---------------------------------------------------------------------------
   Del arreglo: cada entrada trae su lista de roles en 'roles'. El rol de la
   persona sale de la sesion, de usuarioActual()['rol'], que es el mismo valor
   que la pagina comparo con exigirRol() cuando decidio dejarla entrar.

   Si no hay sesion se usa el texto 'visitante', que es un rol que no esta en
   el ENUM de la tabla usuarios pero si esta en la entrada de cerrar sesion.
   Asi el menu se puede pintar en una pagina sin sesion sin tener que preguntar
   dos veces, y lo que se ve es lo mismo: solo el boton de cerrar.

   ---------------------------------------------------------------------------
   EL ROL QUE NO ESTA EN NINGUNA LISTA
   ---------------------------------------------------------------------------
   Si alguien estampara un rol mal escrito en usuarios.rol, no se le asigna
   ningun rol a mano: no ve ninguna entrada, ni siquiera la de cerrar sesion.
   Es lo que corresponde a un dato que no esta en la lista, y la hoja de estilos
   no necesita saber nada del caso.
   =========================================================================== */

require_once __DIR__ . '/../seguridad/sesion.php';
require_once __DIR__ . '/../seguridad/salida.php';

/* El arreglo del menu se carga con require y NO con require_once, y con el
   resultado asignado, a proposito:

     - sin asignar, el require ejecuta el archivo y tira el valor que devuelve.
       El require_once solo no llega a ningun sitio: el "return" se pierde.
     - require_once devolveria el arreglo la primera vez y true (un booleano)
       la segunda, si algun archivo lo pidiera dos veces. Con un require a
       secas, un archivo que solo devuelve un arreglo, cada inclusion da lo
       mismo: no tiene efectos secundarios que repetir y no puede quedar a
       medias si se corta a mitad de camino. */
$configMenu = require __DIR__ . '/../config/menu.php';

/* El rol de quien esta dentro, o la palabra 'visitante' si no hay nadie. Se
   calcula una vez y se usa en el bucle: usuarioActual() abre la sesion si hace
   falta, y llamarlo por cada entrada seria repetir ese trabajo seis veces. */
$usuario = usuarioActual();
$rol     = $usuario !== null ? (string) $usuario['rol'] : 'visitante';

/* La clave de la pagina actual. La pasa la pagina antes de incluir este
   parcial, igual que $etiqueta con barra.php. El ?? '' es para que el menu se
   pueda pintar aunque se olvide: sin clave no se marca ninguna entrada, que es
   justo lo que pasa en una pagina que no esta en el menu. */
$itemActual = $itemActual ?? '';

/* El <h2> y el id del <nav> tambien salen del arreglo, y no estan escritos
   aqui. Antes cada pagina traia el suyo y no todos se llamaban igual. */
$tituloMenu = (string) $configMenu['titulo'];
$idNavegacion = (string) $configMenu['idNavegacion'];
?>

<?php /* EL VELO, Y POR QUE SIGUE SIENDO UN <label> Y NO UN <div>
        ------------------------------------------------------------------
        El velo es el fondo oscuro que tapa la pagina cuando el menu esta
        abierto en el telefono. Toca la casilla del interruptor, y para eso un
        <label for="interruptor-menu"> es exactamente lo correcto: al pulsarlo,
        el navegador le pasa el clic a la casilla, que es lo que abre el menu.

        Con un <div> habria que añadirle un manejador de clics en JavaScript
        para lo mismo, y sin JavaScript el menu se cerraria tocando fuera pero
        no tocando el fondo, que es el gesto que la gente espera.

        La diferencia con el boton del dia 8 es que el label no es un control:
        no recibe el foco y Enter no lo activa. Para el velo da igual, porque
        el velo es una superficie, no un boton. El boton de verdad es el otro,
        el de la barra, que si es un <button>. */ ?>
<label class="menu-velo" for="interruptor-menu"></label>

<aside class="panel__menu" id="menu-lateral">

  <?php /* El boton de cerrar solo se ve en el telefono; la hoja de estilos lo
          esconde a partir de 1024 px. Es un <button> y no un <label> por lo
          mismo que el de la barra: para que Enter y Espacio lo activen sin
          ayuda de JavaScript. */ ?>
  <button type="button" class="menu-cerrar">Cerrar</button>

  <nav aria-labelledby="<?= esc($idNavegacion) ?>">
    <h2 id="<?= esc($idNavegacion) ?>"><?= esc($tituloMenu) ?></h2>

    <ul>
      <?php /* El bucle entero va en una linea por entrada. No es por brevedad:
              es que un if de varias lineas dentro del HTML deja un salto de
              linea en la salida por cada entrada que se salta, y el HTML que
              llega al navegador acaba con lineas en blanco entre un <li> y otro
              que no sirven para nada. El enlace entero va en una linea por lo
              mismo, para que en el HTML se lea aria-current="page" pegado a su
              <a> y no partido en dos lineas. */ ?>
      <?php foreach ($configMenu['entradas'] as $entrada): ?>
        <?php /* El filtro de roles va AQUI, antes de abrir el <li>, y no
                despues: si el <li> se abriera siempre, el menu tendria el
                mismo numero de lineas para todos y con seis huecos sin
                nombre para el que no puede verlas.

                in_array con el comparador estricto (la coma del cuarto
                argumento de la llamada) es lo que hace que no haya sorpresas
                con los tipos: el rol es un texto que viene de la sesion y la
                lista es de textos escritos aqui. Sin el comparador estricto,
                un rol que valiera 0 en lugar de texto podria compararse como
                vacio con una entrada. */ ?>
        <?php if (!in_array($rol, $entrada['roles'], true)) { continue; } ?>
        <li><a href="<?= esc(urlApp($entrada['ruta'])) ?>"<?= $entrada['clave'] === $itemActual ? ' aria-current="page"' : '' ?>><?= esc($entrada['texto']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
</aside>
