<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - El guardian de las paginas privadas
   Archivo: app/seguridad/guardia.php
   Actividad de recuperacion, dia 11, punto 1

   Este archivo se incluye en la PRIMERA LINEA de cada pagina privada:

       <?php
       require_once __DIR__ . '/app/seguridad/guardia.php';

   Primera linea y nada antes, por dos razones que no son de estilo:

     1. header() tiene que ejecutarse antes de que se mande el primer byte de la
        pagina. Si se ha escrito un espacio o un <br> antes, PHP avisa con
        "headers already sent" y la redireccion NO sale: el visitante se queda
        viendo la pagina privada, que es justo lo que el guardian evita.
     2. session_start() manda la cabecera Set-Cookie. Lo mismo.

   Que NO se vuelva a incluir en una pagina que ya lo tenga, gracias al
   require_once: si no, PHP daria el aviso de "Cannot redeclare function".

   ---------------------------------------------------------------------------
   LAS CUATRO COMPROBACIONES, EN ORDEN
   ---------------------------------------------------------------------------
   El orden importa y es el del enunciado:

     1. ¿Hay sesion?            Si no hay, no hay nada que comprobar: se va.
     2. ¿Es el mismo navegador? Se comprueba antes que los relojes, porque si el
                                 User-Agent cambio, los relojes de esa sesion no
                                 son de fiar: estan midiendo otra sesion.
     3. ¿Se paso de tiempo?     Inactividad de 30 minutos o 8 horas en total.
     4. ¿Le toca a este rol?    exigirRol(), que se llama desde la pagina.

   La 4 no se ejecuta sola: exigirRol() y puede() se declaran para que las
   llamen las paginas, porque que el usuario tenga sesion no significa que pueda
   hacer de todo. Estar dentro no es lo mismo que tener permiso.
   =========================================================================== */

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/salida.php';

iniciarSesionSegura();

/* ---- 1. ¿Hay sesion? ---------------------------------------------------- */
if (empty($_SESSION['usuario']['id'])) {
    /* Sin sesion no se mira nada mas. En particular NO se dice por que: si aqui
       se distinguiera "no has entrado" de "tu sesion se acabo", la pagina
       estaria confirmando datos sobre la sesion de otra persona. */
    redirigir(urlApp('login.php', 'requiere_ingreso'));
}

/* ---- 2. ¿Es el mismo navegador? ----------------------------------------- */
if (($_SESSION['huella'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
    cerrarSesion();
    redirigir(urlApp('login.php', 'sesion_invalida'));
}

/* ---- 3. Inactividad y duracion maxima ----------------------------------- */
$ahora = time();

if ($ahora - (int) $_SESSION['ultima_actividad'] > INACTIVIDAD_MAX
 || $ahora - (int) $_SESSION['inicio'] > SESION_MAX) {
    cerrarSesion();
    redirigir(urlApp('login.php', 'sesion_expirada'));
}

/* Solo se pasa esta linea si la sesion es buena de verdad, asi que aqui ya se
   puede escribir en ella: la peticion cuenta como actividad del usuario. */
$_SESSION['ultima_actividad'] = $ahora;

/* ---- 4. Autorizacion por rol ------------------------------------------- */
/**
 * Corta la pagina si el usuario de la sesion no tiene ninguno de esos roles.
 *
 * Se exit con 403 y un texto, sin plantilla: es lo que hace el ejemplo del
 * enunciado, y tiene sentido. Si en vez de esto se quisiera una pagina bonita de
 * error, la redireccion tendria que pasar por un header() y entonces esta
 * funcion dejaria de poder decidir el codigo de salida, que es justo lo que se le
 * pide.
 */
function exigirRol(string ...$roles): void
{
    if (!puede(...$roles)) {
        http_response_code(403);
        exit('403 — No tiene permiso para esta operación.');
    }
}

/**
 * Dice si el usuario de la sesion tiene alguno de esos roles.
 *
 * Ojo con el ?? '' del segundo argumento: si no hay sesion, la clave 'rol' no
 * existe, y sin ese valor por defecto el in_array recibiría null. Con null y el
 * comparador estricto no hay problema, pero es mas limpio que la funcion se
 * pueda llamar antes de abrir la sesion sin dar error.
 */
function puede(string ...$roles): bool
{
    $rol = (string) ($_SESSION['usuario']['rol'] ?? '');

    return in_array($rol, $roles, true);
}
