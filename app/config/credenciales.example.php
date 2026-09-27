<?php
/* ===========================================================================
   LA CARAMBOLA DORADA - Plantilla de credenciales
   Archivo: app/config/credenciales.example.php
   Actividad de recuperacion, dia 9, punto 3

   ESTE es el archivo que si se sube al repositorio. Tiene exactamente la misma
   forma que credenciales.php, que es el que usa de verdad la aplicacion, pero
   con valores de mentira.

   Para trabajar en tu maquina:
     1. Copia este archivo como credenciales.php, al lado de este.
     2. Cambia los cuatro valores por los de tu XAMPP o Laragon.
   La copia es la que queda fuera del repositorio.
   =========================================================================== */

return [
    /* Direccion del servidor de MySQL. Casi siempre es 127.0.0.1 o localhost. */
    'host'    => '127.0.0.1',

    /* Nombre de la base de datos. Es el que crea sql/estructura.sql. */
    'bd'      => 'carambola_doradoa',

    /* Usuario de MySQL. En XAMPP recien instalado suele ser root... */
    'usuario' => 'usuario_de_ejemplo',

    /* ...y en Laragon tambien, pero con clave. OJO: esta clave aqui es de
       mentira, es solo para que se vea donde va. */
    'clave'   => 'contrasena_de_ejemplo',
];
