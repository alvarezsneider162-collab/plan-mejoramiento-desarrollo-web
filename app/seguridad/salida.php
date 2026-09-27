<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Escapar la salida
   Archivo: app/seguridad/salida.php
   Actividad de recuperacion, dia 10, punto 5

   Este archivo tiene UNA sola funcion, y es la que hace que el punto 5 no se
   cumpla en contra. Sirve para lo mismo que esc() de productos.php, que se
   queda ahi por no tocar un archivo que ya funciona; las paginas nuevas usan
   esta.

   QUE ES ESCAPAR:

   PHP escribe HTML. Cuando PHP pone un valor dentro del HTML, ese valor puede
   traer su propia sintaxis. Si el nombre de un usuario es

       <script>alert(1)</script>

   y la pagina lo escribe asi:

       <p>Bienvenido, <?= $nombre ?></p>

   lo que sale en pantalla no es el texto del nombre: es un script entero, y el
   navegador lo ejecuta. Eso es XSS.

   Con htmlspecialchars() los caracteres < > " ' se cambian por &lt; &gt; &quot;
   &#039;, que el navegador muestra como los caracteres que son. El <script> se
   ve escrito, que es justo lo que pide el punto 5.

   La regla es corta: TODO lo que venga de la base de datos o de un formulario
   se escapa al imprimirlo. Nunca al guardarlo, porque al guardarlo se
   modificaria el dato de verdad.
   =========================================================================== */

/**
 * Prepara un valor para poder ponerlo dentro del HTML.
 *
 * ENT_QUOTES  escapa tambien las comillas simples, que sin esto se escaparian
 *             de un atributo como title="..." y romperian el HTML.
 * ENT_SUBSTITUTE  si el texto trae una secuencia de bytes que no es UTF-8
 *                 valido, en vez de devolver una cadena vacia devuelve un signo
 *                 de interrogacion. Sin esta opcion, un nombre raro podria
 *                 dejar la pagina en blanco.
 */
function esc(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
