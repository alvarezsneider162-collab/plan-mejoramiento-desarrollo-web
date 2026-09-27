<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Barra superior
   Archivo: app/parciales/barra.php
   Actividad de recuperacion, dia 11, punto 2

   Es el <p class="panel__sesion"> de la barra de arriba, y escribe el nombre y
   el rol del usuario que esta en la sesion, escapados con esc().

   ---------------------------------------------------------------------------
   POR QUE ESTO ES UN PARCIAL Y NO ESTA COPIADO EN CADA PAGINA
   ---------------------------------------------------------------------------
   La barra estaba escrita a mano en dashboard.html, productos.php y phpinfo.php,
   con un nombre de persona escrito en el HTML:

       <p class="panel__sesion">
         <strong>Inventario</strong> &middot; Sneider Alvarez
       </p>

   Ese "Sneider Alvarez" era un texto fijo, igual en todas las paginas y en todos
   los navegadores del mundo. La barra tiene que decir quien esta entrando, y un
   texto escrito en el HTML no lo puede saber: por mucho que se cambie el
   archivo, el mismo texto sale en la pantalla de Juan y en la de Maria. Ademas,
   al estar repetido en tres archivos, cualquier correccion tendria que aplicarse
   tres veces y es facil que una se quede atras.

   Con el parcial hay un solo lugar donde vive la barra. Si manana se cambia el
   formato, o se anade la hora de entrada, se toca este archivo y ya.

   ---------------------------------------------------------------------------
   POR QUE ESTE PARCIAL SACA SOLO EL <p> Y NO EL <header> ENTERO
   ---------------------------------------------------------------------------
   Porque las tres pantallas no comparten la cabecera completa. dashboard.php y
   productos.php llevan el boton de menu y el <aside> del menu lateral, y
   phpinfo.php no lleva ninguno de los dos: es una hoja de datos, no una
   pantalla del panel. Si este archivo pintara el <header> entero, phpinfo.php
   recibiria un boton de menu que no lleva ningun sitio.

   Asi que el parcial emite solo el parrafo, y cada pagina conserva su <header>
   con el boton, el logo y lo demas. Lo unico que cambia entre pantallas es la
   etiqueta de la izquierda, y esa se pasa por una variable.

   ---------------------------------------------------------------------------
   EL ESCAPADO, QUE NO ES COSA ESTETICA
   ---------------------------------------------------------------------------
   El nombre sale de la base de datos y la base de datos tiene esta fila:

       id = 4, nombre = <script>alert(1)</script>

   No es inventada: es el usuario del punto 5 del dia 10, el que se creo para
   comprobar que el punto 5 escapaba el nombre. Si aqui se escribiera el nombre
   sin pasar por esc(), ese usuario tendria un <script> ejecutandose en la barra
   superior de todas las pantallas del sistema, en cuanto abriera cualquiera de
   ellas.

   esc() es htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), asi
   que el nombre sale escrito tal cual, con las etiquetas a la vista, y el
   navegador lo enseña como texto en vez de ejecutarlo.

   ---------------------------------------------------------------------------
   POR QUE SE USA require_once Y NO include
   ---------------------------------------------------------------------------
   Por la misma razon que en el resto del proyecto: include ejecuta el archivo
   otra vez cada vez que se llama, e include_once volveria a mirar el archivo en
   disco en cada inclusion. require_once resuelve el camino una vez y de ahi
   para adelante no vuelve a mirar nada. Con un parcial que se incluye en cada
   pagina, esa diferencia se nota.
   =========================================================================== */

require_once __DIR__ . '/../seguridad/sesion.php';
require_once __DIR__ . '/../seguridad/salida.php';

/* La etiqueta de la izquierda la pasa la pagina. El ?? '' es para que el
   parcial no se rompa si alguna se olvida de definirlas: es una parte de la
   barra, no un requisito para que la pagina funcione. */
$etiqueta = $etiqueta ?? '';

/* Se pregunta a la sesion, no a la pagina. La razon es que usuarioActual()
   devuelve null cuando no hay nadie dentro, y este archivo se usa en paginas
   publicas, donde puede no haber sesion: en ese caso sale solo la etiqueta y
   nada mas, en vez de un error por leer una clave de un array vacio. */
$usuario = usuarioActual();
?>
<p class="panel__sesion">
  <strong><?= esc($etiqueta) ?></strong>
  <?php if ($usuario !== null): ?>
    &middot; <?= esc($usuario['nombre']) ?>
    &middot; <?= esc(ucfirst($usuario['rol'])) ?>
  <?php endif; ?>
</p>
