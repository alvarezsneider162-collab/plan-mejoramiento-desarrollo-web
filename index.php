<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Puerta de entrada
   Archivo: index.php
   Actividad de recuperacion, dia 12, punto 1

   ---------------------------------------------------------------------------
   POR QUE HAY UN index.php QUE SOLO REDIRIGE
   ---------------------------------------------------------------------------
   Sin este archivo, entrar por http://localhost/plan-mejoramiento-desarrollo-web/
   hacia que Apache mostrara su listado de directorios: una tabla con todos los
   archivos del proyecto, incluidos app/, sql/ y las capturas de las bitacoras.
   No es un fallo de seguridad grave, pero es una pagina del proyecto que nadie
   escribio y que enseña como esta organizado por dentro.

   Con el archivo presente, Apache lo encuentra antes que el listado y no llega
   a pintarlo nunca.

   A donde manda: al tablero si ya hay sesion, y al ingreso si no la hay. Es una
   redireccion, no una pagina, asi que aqui no se imprimen ni cabecera.php ni
   pie.php: no hay ningun HTML que componer. Los parciales se usan en las
   paginas que de verdad tienen algo que mostrar.
   =========================================================================== */

require_once __DIR__ . '/app/seguridad/sesion.php';
require_once __DIR__ . '/app/seguridad/salida.php';

iniciarSesionSegura();

/* usuarioActual() devuelve null si no hay sesion o si la sesion caduco, que es
   justo lo que hay que comprobar: el 302 de la redireccion no protege nada por
   si mismo, lo protege el guardian de cada pagina privada. Aqui solo se decide
   a donde se va. */
redirigir(usuarioActual() === null ? urlApp('login.php') : urlApp('dashboard.php'));
