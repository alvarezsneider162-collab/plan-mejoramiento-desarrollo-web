<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Pie comun
   Archivo: app/parciales/pie.php
   Actividad de recuperacion, dia 12, punto 1

   El pie de la pagina, y de paso todo lo que va despues: los <script> del
   final y el cierre del documento. Con esto, la ultima linea de cada pagina es
   un require y no un </body></html> escrito a mano.

   ---------------------------------------------------------------------------
   POR QUE ESTE PARCIAL TAMBIEN CIERRA LAS ETIQUETAS
   ---------------------------------------------------------------------------
   Porque el error tipico al partir una pagina en parciales es dejar el cierre
   a mano en cada una, y un dia una pagina nueva se olvida del </div> del
   <div class="panel">. El navegador aguanta y la pagina sale, pero el arbol
   queda mal y el menu se descoloca.

   Aqui no queda nada opcional: si $pantalla es 'panel' se cierra el div de la
   rejilla, y siempre se cierra el body y el html. Por eso el parcial necesita
   saber en que pantalla esta, con la misma bandera que usa cabecera.php.

   ---------------------------------------------------------------------------
   POR QUE LOS SCRIPT VAN AQUI Y NO EN CADA PAGINA
   ---------------------------------------------------------------------------
   Un <script> en el <head> se descarga y ejecuta ANTES de que exista el DOM,
   y entonces document.querySelector('.boton-menu') devuelve null y el archivo
   se para. Por eso van al final del body: ahi el HTML ya esta entero.

   El orden tambien importa. app/menu.js primero, porque conecta el boton del
   menu, y despues los archivos propios de cada pagina, que pueden buscar el
   menu sin problema porque menu.js ya lo dejo en su sitio.

   ---------------------------------------------------------------------------
   EL TEXTO DEL PIE Y SUS DOS CLASES
   ---------------------------------------------------------------------------
   El texto sale de $textoPie, y por defecto es el del sistema. Hay una pagina
   que necesita el suyo: componentes.php escribe que es la guia de estilo, porque
   no es una pantalla del salon sino la lista de piezas del proyecto. Ponerle el
   texto del sistema era lo que hacia antes, y hacia que la guia de estilo
   dijera "Sistema de gestion de salones de billar" en el pie, que no es falso
   pero no es lo que queria decir.

   Y lo que sale por $textoPie NO se escapa con esc(), a proposito: el valor por
   defecto ya trae las entidades &mdash; y &eacute; escritas, que esc()
   dejaria igual, pero el de componentes.php tambien. Si alguien pasa por ahi
   un dato de la base de datos, lo que tiene que hacer es llamar a esc() en la
   pagina que se lo pasa, no aqui: este archivo no sabe de donde viene cada
   texto y escaparlo dos veces imprimiria &amp;lt; en pantalla.

   La clase del <footer> sale de $pantalla, la misma bandera que usa
   cabecera.php:

     .panel__pie   en las pantallas del panel. Es una zona de la rejilla
                   (grid-area: pie), pegada abajo y con fondo claro.
     (ninguna)     en las pantallas publicas. Ahi lo pinta "body > footer",
                   que es un bloque normal al final de la pagina.
   =========================================================================== */

$pantalla     = $pantalla ?? 'panel';
$scriptsExtra = $scriptsExtra ?? '';
$textoPie     = $textoPie ?? 'La Carambola Dorada &mdash; Sistema de gesti&oacute;n de salones de billar';
?>

<?php /* La clase del <footer> se decide con una ternaria y no con dos
        <footer> distintos: el texto y la estructura son los mismos, y lo
        unico que cambia es la clase que aplica la hoja de estilos. */ ?>
<footer class="<?= $pantalla === 'panel' ? 'panel__pie' : '' ?>">
  <p><?= $textoPie ?></p>
</footer>

<?php if ($pantalla === 'panel'): ?>
  </div><?php /* Cierra el <div class="panel"> que abrio cabecera.php. */ ?>
<?php endif; ?>

<?php if ($scriptsExtra !== ''): ?>
  <?= $scriptsExtra ?>
<?php endif; ?>
</body>
</html>
