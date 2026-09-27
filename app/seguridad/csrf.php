<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Token CSRF
   Archivo: app/seguridad/csrf.php
   Actividad de recuperacion, dia 10, punto 3

   Que es un CSRF y por que hace falta esto:

   Un formulario que se envia por POST lo puede mandar CUALQUIER pagina. Si yo
   tengo un formulario de ingreso abierto en el navegador y el usuario, sin
   querer, entra a una pagina mia que tiene esto:

       <form action="http://localhost/.../login.php" method="post">
         <input name="correo" value="admin@carambola.test">
         <input name="clave"  value="una-clave-que-ya-se">
       </form>

   ...el navegador envia ese formulario solo, con las cookies de la sesion, y mi
   pagina queda con la sesion iniciada. El usuario no hizo nada, pero quedo
   dentro. Eso es un CSRF.

   La defensa es que cada formulario lleve un token secreto que el navegador no
   puede inventar por su cuenta, porque lo pone el servidor dentro de la pagina.
   Si al recibir el POST el token no es el que esta en la sesion, la peticion no
   es de la pagina legitima y se rechaza con el codigo de HTTP_TOKEN_INVALIDO.

   ---------------------------------------------------------------------------
   POR QUE 403 Y NO EL 419 DEL ENUNCIADO
   ---------------------------------------------------------------------------
   El ejemplo del enunciado usa 419. Aqui se usa 403, y no por capricho: se
   probo y en esta maqueta el 419 no sale. Apache en Windows no tiene la frase
   de estado del 419, no sabe escribir la linea de estado y la cambia por un
   500, que ademas dice "error del servidor" cuando el servidor esta bien. Se
   comprobo con una sonda que pedia 400, 403, 409, 419, 422 y 429, y todos
   pasaban tal cual menos el 419.

   Un 500 por un token faltante seria mentira: diria que se cayo la pagina
   cuando en realidad la pagina esta haciendo exactamente lo que debe. El 403
   dice justo lo que paso: "entendi lo que pediste y no te lo concedo". Se deja
   el 419 aqui anotado para cuando el servidor sepa ese codigo; si algum dia se
   cambia, solo se cambia esta constante.
   =========================================================================== */

/**
 * Codigo HTTP con el que se rechaza un POST cuyo token no cuadra.
 *
 * Vive aqui, y no escrito en cada pagina, para que el numero este en un solo
 * sitio. Cambiarlo aqui lo cambia en toda la aplicacion.
 */
const HTTP_TOKEN_INVALIDO = 403;

/**
 * Devuelve el token CSRF de la sesion, y lo crea si es que no habia ninguno.
 *
 * Ojo con una cosa importante: la sesion tiene que estar abierta antes de
 * llamar a esta funcion, porque el token vive dentro de ella. De eso se encarga
 * iniciarSesionSegura() de app/seguridad/sesion.php, que todas las paginas
 * llaman antes de usar el token.
 */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        /* 32 bytes al azar se vuelven 64 caracteres en hexadecimal. Es bastante
           mas largo que el token de la sesion y que el id de la cookie, que
           tambien van por aqui, y no se puede adivinar. */
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

/**
 * Comprueba que el token que vino en el POST sea el de esta sesion.
 *
 * La comparacion es con hash_equals() y no con == a proposito. == compara el
 * texto de izquierda a derecha y se devuelve en cuanto encuentra una diferencia,
 * asi que con comparar el token a mano se puede acertar el token bueno letra a
 * letra midiendo cuanto tarda en responder. hash_equals() tarda lo mismo
 * compare la cadena entera haya diferencias o no, y ademas avisa si se le pasan
 * dos tipos distintos, que con == pasaria desapercibido.
 *
 * Devuelve false tambien si no se envio nada o si el token es null, que es lo
 * que pasa si alguien entra a login.php mandando un POST a pelo.
 */
function validarCsrf(?string $enviado): bool
{
    return is_string($enviado)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

/**
 * Cambia el token de la sesion por uno nuevo.
 *
 * Se llama despues de entrar, para que el token que estaba escrito en el
 * formulario de ingreso no sirva para nada mas. Si no, ese token sigue siendo
 * bueno para siempre y, si alguien lo llega a leer, puede reusarlo.
 */
function rotarCsrf(): string
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    return $_SESSION['csrf'];
}
