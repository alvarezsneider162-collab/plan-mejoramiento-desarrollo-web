<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Cabecera comun
   Archivo: app/parciales/cabecera.php
   Actividad de recuperacion, dia 12, punto 1

   Este parcial imprime TODO lo que va antes del contenido: el <!DOCTYPE>, el
   <head>, el <body> y, en las pantallas del panel, la barra de arriba con el
   boton del menu y el interruptor que la gobierna.

   ---------------------------------------------------------------------------
   QUE SE EXTRAE Y QUE SE QUEDA EN CADA PAGINA
   ---------------------------------------------------------------------------
   El <head> estaba repetido en los ocho archivos PHP del proyecto, y las ocho
   copias tenian ya una diferencia: cuatro de ellas llevaban la etiqueta
   charset, tres no, y una llevaba la hoja de estilos y las otras no. Eso no se
   ve mirando el codigo de una pagina, se ve cuando alguien mete una
   etiqueta mas en una de las copias y se queda sin charset.

   Este parcial es el unico sitio donde se decide el idioma del documento, el
   viewport, la hoja de estilos y la estructura de la rejilla. Si manana se
   anade una hoja mas, se anade aqui y aparece en las nueve pantallas.

   ---------------------------------------------------------------------------
   QUE VARIABLES ESPERA, Y QUE PASA SI NO LE LLEGAN
   ---------------------------------------------------------------------------
   Todas usan "??", y ninguna es obligatoria:

     $tituloPagina  el texto de la pestaña. Sin el, sale el nombre del sistema.
     $etiqueta      lo que va en el <p class="panel__sesion">. La pinta
                    barra.php, que es quien la escapa.
     $pantalla      'panel' (por defecto) o 'publico'. Decide si se abre el
                    <div class="panel"> con la rejilla y la barra de arriba, o
                    si se abre el <header> simple de las pantallas de ingreso.
     $estilosExtra  un bloque de <style> para las paginas que lo necesitan
                    (phpinfo.php). Vacio en las demas.
     $scriptsExtra  los <script> del final de la pagina. Los pone pie.php, no
                    este archivo, porque van antes de </body>.

   El "??" no es por descuido: este parcial se incluye en nueve archivos, y un
   typo en el nombre de una variable en cualquiera de ellos no puede dejar la
   pagina en blanco. Es un trocito de maquetacion, no una condicion para que la
   pagina exista.

   ---------------------------------------------------------------------------
   POR QUE $pantalla EN LUGAR DE DOS PARCIALES
   ---------------------------------------------------------------------------
   Porque hay dos formas de pagina en el proyecto y las dos usan el mismo
   <head>, el mismo logo y el mismo pie. Si se hicieran dos parciales, el
   <head> volveria a estar duplicado, que es justo lo que este punto viene a
   quitar. Un solo archivo con una bandera es peor de leer que dos, y mejor de
   mantener: el <head> esta escrito una vez.
   =========================================================================== */

/* app/seguridad/sesion.php trae usuarioActual(), que necesita la sesion
   abierta, y app/seguridad/salida.php trae esc(), que usan tanto barra.php
   como este archivo. Los dos son require_once porque son archivos con funciones
   y con constantes: incluirlos dos veces daria "Cannot redeclare function". */
require_once __DIR__ . '/../seguridad/sesion.php';
require_once __DIR__ . '/../seguridad/salida.php';

$tituloPagina = $tituloPagina ?? 'La Carambola Dorada';
$etiqueta     = $etiqueta ?? '';
$pantalla     = $pantalla ?? 'panel';
$estilosExtra = $estilosExtra ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($tituloPagina) ?></title>
  <link rel="stylesheet" href="css/estilos.css">
<?php /* El <style> de las paginas que lo necesitan, y solo de esas. El if y el
        endif van pegados al HTML a proposito: si cada uno va en su linea, PHP
        deja un salto de linea de mas en las ocho paginas que no tienen
        estilos propios, y el HTML que sale al navegador ya no es el que el
        archivo dice. */ ?>
<?php if ($estilosExtra !== ''): ?><style><?= $estilosExtra ?></style><?php endif; ?>
</head>
<body>

<?php if ($pantalla === 'panel'): ?>

  <div class="panel">

    <?php /* EL INTERRUPTOR, Y POR QUE SIGUE SIENDO UN CHECKBOX ESCONDIDO
            ------------------------------------------------------------------
            El menu se abre con este checkbox desde el dia 6, con el selector
            hermano general:

                .interruptor-menu:checked ~ .panel__menu { ... }

            Ese selector no se ha tocado en ningun dia, y cambiarlo ahora
            significaria reescribir la parte de la hoja de estilos que ya
            funciona. Ademas, un checkbox marcado es un estado que el navegador
            guarda entre recargas y al girar el telefono, y el boton de app/
            menu.js solo marca y desmarca esta misma casilla.

            Escondido, pero NO con display: none, que lo sacaria del
            formulario y del orden de tabulacion: se esconde con la tecnica
            de la hoja de estilos, que lo deja en el DOM y sin pintar.

            aria-label va porque el checkbox no tiene etiqueta visible: es el
            estado del menu, no un campo que se rellene. El que se anuncia es
            el boton, con aria-expanded.
            ------------------------------------------------------------------ */ ?>
    <input type="checkbox" class="interruptor-menu" id="interruptor-menu"
           aria-label="Abrir o cerrar el menú lateral">

    <header class="panel__barra">

      <?php /* El boton lleva las tres cosas que hacen que un boton sea un
              boton y no un <div> con aspecto de boton:

                - type="button". Sin esto, dentro de un <form> seria un
                  "submit" y mandaria el formulario sin querer.
                - aria-expanded. Es lo que anuncia el estado del menu a un
                  lector de pantalla, y app/menu.js lo mantiene al dia. Es el
                  atributo que el punto 4 del dia 12 verifica.
                - aria-controls. Dice que boton controla que region, para que
                  el lector de pantalla pueda saltar al menu.

              En escritorio no se ve: la hoja de estilos lo esconde a partir de
              1024 px, porque ahi el menu ya es una columna de la rejilla y no
              hay nada que plegar. */ ?>
      <button type="button" class="boton-menu" id="boton-menu"
              aria-expanded="false" aria-controls="menu-lateral">
        <span class="boton-menu__icono" aria-hidden="true"></span>
        <span class="boton-menu__texto">Menú</span>
      </button>

      <img src="assets/img/logo.svg" alt="La Carambola Dorada">

      <?php /* El <p> de la sesion lo pinta barra.php con el nombre y el rol de
              quien esta dentro. Se incluye con require y NO con require_once,
              y en este punto del <header>, que es donde tiene que caer: si se
              incluyera arriba del archivo, $etiqueta todavia no valdria nada y
              la barra saldria vacia. La razon esta entera en barra.php. */ ?>
      <?php require __DIR__ . '/barra.php'; ?>

    </header>

<?php else: ?>

  <?php /* Las pantallas publicas (ingreso, registro y cierre de sesion) no
          tienen menu lateral ni rejilla: son una sola tarjeta en medio de la
          pagina. Llueven el mismo <head> y el mismo logo, y por eso cabe aqui
          y no en un archivo aparte. El <header> simple lo pide la hoja de
          estilos con "body > header". */ ?>
  <header>
    <img src="assets/img/logo.svg" alt="Logo de La Carambola Dorada" width="120">
  </header>

<?php endif; ?>
