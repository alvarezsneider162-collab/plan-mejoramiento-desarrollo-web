<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Sesion y cookie
   Archivo: app/seguridad/sesion.php
   Actividad de recuperacion, dia 10, punto 3

   Este archivo deja la sesion en condiciones que sirvan, que no es solo
   llamar a session_start():

     1. La cookie de sesion va con httponly y samesite, porque el php.ini de
        este XAMPP los deja VACIOS. Sin httponly, cualquier script de la pagina
        puede leer la cookie de sesion con document.cookie.
     2. session_regenerate_id() al entrar, para que el id de sesion que el
        visitante traia antes de iniciar no siga siendo valido.

   La sesion se abre UNA vez por peticion. Todas las funciones de aqui comprueban
   si ya esta abierta antes de abrirla, porque abrirla dos veces genera un aviso
   de PHP y, peor, un id de sesion distinto del que se acababa de crear.
   =========================================================================== */

/**
 * Abre la sesion si no esta abierta, con la cookie bien puesta.
 *
 * La llamada va SIEMPRE antes de tokenCsrf(), porque el token CSRF vive dentro
 * de la sesion.
 */
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    /* secure queda en false porque esto corre en http://localhost, sin TLS. En
       un servidor de verdad con https esto tiene que ser true, porque si no la
       cookie viaja en claro. */
    session_set_cookie_params([
        'lifetime' => 0,          /* cookie de sesion: se borra al cerrar el navegador */
        'path'     => '/',
        'httponly' => true,       /* JavaScript no puede leerla */
        'samesite' => 'Lax',      /* no viaja en peticiones que venga de otro sitio */
        'secure'   => false,
    ]);

    session_start();
}

/**
 * Guarda los datos del usuario en la sesion y renueva el id.
 *
 * El session_regenerate_id(true) es importante y va primero: hasta este
 * momento el id de sesion es el que envio el visitante, y ese se supone que es
 * publico, asi que no puede seguir siendo el que manda despues de
 * autenticarse. Con true ademas se borra la sesion vieja.
 */
function abrirSesion(array $usuario): void
{
    iniciarSesion();

    session_regenerate_id(true);

    $_SESSION['usuario_id']       = (int) $usuario['id'];
    $_SESSION['usuario_nombre']   = (string) $usuario['nombre'];
    $_SESSION['usuario_correo']   = (string) $usuario['correo'];
    $_SESSION['usuario_rol']      = (string) $usuario['rol'];

    /* Cuando se entro. Va en la sesion y no en la base a proposito: el ultimo
       acceso no es un dato de negocio y ensuciaria la tabla de usuarios. */
    $_SESSION['inicio_sesion']    = date('Y-m-d H:i:s');

    /* El token del formulario de ingreso ya cumplio. Se cambia por uno nuevo para
       que el token viejo, que estuvo escrito en una pagina, no sirva para
       mandar otra peticion. */
    if (function_exists('rotarCsrf')) {
        rotarCsrf();
    }
}

/**
 * Cierra la sesion del usuario. Se deja el archivo aunque hoy no haya pagina que
 * lo use: en cuanto haya un tablero protegido, este es el boton de "Cerrar
   sesion".
 */
function cerrarSesion(): void
{
    iniciarSesion();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
            'secure'   => $p['secure'],
        ]);
    }

    session_destroy();
}

/**
 * Dice quien esta con la sesion abierta, o null si no hay nadie.
 *
 * El nombre sale de la sesion, no de una consulta: por eso hay que escaparlo
 * con htmlspecialchars() cada vez que se muestre. Si en el nombre hay un
 * <script>, se pinta como texto y no se ejecuta.
 */
function usuarioActual(): ?array
{
    iniciarSesion();

    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id'            => (int) $_SESSION['usuario_id'],
        'nombre'        => (string) $_SESSION['usuario_nombre'],
        'correo'        => (string) $_SESSION['usuario_correo'],
        'rol'           => (string) $_SESSION['usuario_rol'],
        'inicio_sesion' => (string) ($_SESSION['inicio_sesion'] ?? ''),
    ];
}

/** Indica si hay alguien con la sesion abierta. */
function haySesion(): bool
{
    return usuarioActual() !== null;
}
