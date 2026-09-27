<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Sesion, cookie y ruta base
   Archivo: app/seguridad/sesion.php
   Actividad de recuperacion, dia 11, punto 1

   Este archivo deja la sesion en condiciones que sirvan, que no es solo llamar
   a session_start(). Son cuatro cosas:

     1. La cookie con httponly, secure y samesite, porque el php.ini de este
        XAMPP los deja VACIOS. Sin httponly, cualquier script de la pagina puede
        leer la cookie con document.cookie.
     2. session.use_strict_mode en 1, para que PHP no acepte un id de sesion
        inventado por el visitante. Sin eso, alguien puede elegir su propio id de
        sesion, obtener una cookie valida y esperar a que alguien entre ahi.
     3. Un nombre de sesion propio, ZDTL_SESS, en vez de PHPSESSID, para no
        delatar en la cabecera de la pagina que corre en PHP.
     4. session_regenerate_id(true) al entrar, para que el id que traia el
        visitante antes de autenticarse deje de valer.

   ---------------------------------------------------------------------------
   LOS DOS RELOJES DE LA SESION
   ---------------------------------------------------------------------------
   INACTIVIDAD_MAX y SESION_MAX son los dos relojes que consulta el guardian:

     - INACTIVIDAD_MAX: 30 minutos sin hacer nada y la sesion se cae.
     - SESION_MAX: 8 horas en total, aunque no haya un segundo de inactividad.

   Los dos estan en segundos porque se comparan contra time(), que es el reloj de
   PHP. Y ese reloj ya se puso en la misma hora que el de MySQL el dia 10, con
   date_default_timezone_set('America/Bogota') en app/config/conexion.php: si no,
   el calculo de "cuanto lleva abierta esta sesion" seria una cuenta sobre una
   hora que no es la que se uso para guardarla.

   ---------------------------------------------------------------------------
   LA HUELLA
   ---------------------------------------------------------------------------
   $_SESSION['huella'] es el sha256 del User-Agent. Si en mitad de una sesion el
   User-Agent cambia, es que no es el mismo navegador, y la sesion se cae. Es una
   comprobacion debil, porque el User-Agent lo elige el cliente y se puede mandar
   a proposito, pero sirve para cerrar una sesion robada desde otro equipo. No es
   un sustituto de un segundo factor: es una capa mas, no la unica.

   ---------------------------------------------------------------------------
   POR QUE ESTA RUTA_APP EN VEZ DE / A SECAS
   ---------------------------------------------------------------------------
   El ejemplo del enunciado redirige con header('Location: /login.php?...'). Esa
   barra es la RAIZ DEL SERVIDOR, y esta aplicacion no vive en la raiz: vive en
   /plan-mejoramiento-desarrollo-web/. Con la redireccion tal cual, /login.php da
   404 y el visitante que entre sin sesion aterriza en una pagina que no existe.

   rutaApp() saca la carpeta de donde se esta sirviendo la pagina, mirando
   SCRIPT_NAME, que es siempre la pagina principal que se esta ejecutando, no el
   archivo que se incluye. Asi si el proyecto se copia a otra carpeta, o se
   instala en otra maquina, la redireccion sigue acertando sola.
   =========================================================================== */

/** 30 minutos de inactividad y la sesion se cae. */
const INACTIVIDAD_MAX = 1800;

/** 8 horas como maximo, entre mas o menos este o el otro. */
const SESION_MAX = 28800;

/**
 * Carpeta donde vive la aplicacion, sin barra final. "" si esta en la raiz.
 *
 * Se calcula con SCRIPT_NAME y no con __DIR__ a proposito: __DIR__ daria la
 * carpeta de ESTE archivo (app/seguridad), que no es la carpeta de la pagina, y
 * desde ahi la redireccion se calcularia con un nivel de mas.
 */
function rutaApp(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base   = str_replace('\\', '/', dirname($script));

    if ($base === '/' || $base === '.' || $base === '') {
        return '';
    }

    return rtrim($base, '/');
}

/**
 * Construye una URL dentro de la aplicacion, con la base ya puesta.
 *
 * urlApp('login.php')            -> /plan-mejoramiento-desarrollo-web/login.php
 * urlApp('login.php', 'expirada') -> lo mismo con ?m=expirada
 */
function urlApp(string $ruta, string $motivo = ''): string
{
    $url = rutaApp() . '/' . ltrim($ruta, '/');

    return $motivo === '' ? $url : $url . '?m=' . rawurlencode($motivo);
}

/** Manda al visitante a otra pagina y termina el script. */
function redirigir(string $url, int $codigo = 302): never
{
    header('Location: ' . $url, true, $codigo);
    exit;
}

/**
 * Abre la sesion si no esta abierta, con la cookie bien puesta.
 *
 * Se puede llamar tantas veces como haga falta: la segunda vez no hace nada, y
 * por eso todas las funciones de aqui y el guardian pueden llamarla sin miedo a
 * abrir dos sesiones (que genera un aviso de PHP y, peor, un id distinto del
 * que se acababa de crear).
 *
 * La llamada va SIEMPRE antes de tokenCsrf(), porque el token CSRF vive dentro
 * de la sesion.
 */
function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    /* Sin esto PHP acepta un id de sesion cualquiera que venga en la cookie,
       incluido uno que el visitante se haya inventado. */
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,          /* cookie de sesion: se borra al cerrar el navegador */
        /* El scope es la carpeta de la aplicacion, no todo el servidor: asi la
           cookie no viaja a phpMyAdmin ni a otra aplicacion que comparta XAMPP.
           En la raiz del servidor esto se queda en "/", que es lo correcto. */
        'path'     => rutaApp() . '/',
        'httponly' => true,       /* JavaScript no puede leerla */
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Strict',   /* no viaja en peticiones que venga de otro sitio */
    ]);

    session_name('ZDTL_SESS');

    session_start();
}

/** Alias de compatibilidad para iniciarSesionSegura() */
function iniciarSesion(): void
{
    iniciarSesionSegura();
}

/**
 * Guarda los datos del usuario en la sesion y renueva el id.
 *
 * El session_regenerate_id(true) es importante y va primero: hasta este momento
 * el id de sesion es el que envio el visitante, y ese se supone que es publico,
 * asi que no puede seguir siendo el que manda despues de autenticarse. Con true
 * ademas se borra la sesion vieja.
 *
 * El array va anidado en $_SESSION['usuario'] y no en claves sueltas
 * (usuario_id, usuario_nombre...) porque es lo que espera el guardian, y porque
 * asi todo lo del usuario esta en un solo sitio y se vacia con unset entero.
 *
 * Se guardan dos campos mas que el ejemplo del enunciado: el correo y el inicio.
 *   - El correo lo ensefena el panel de bienvenida de login.php.
 *   - El inicio lo necesita el guardian (SESION_MAX) y lo ensefena el mismo panel.
 * No son inventos: son los dos datos que ya seaban mostrando el dia 10.
 */
function abrirSesion(array $usuario): void
{
    iniciarSesionSegura();

    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id'     => (int) $usuario['id'],
        'nombre' => (string) $usuario['nombre'],
        'correo' => (string) $usuario['correo'],
        'rol'    => (string) $usuario['rol'],
    ];

    /* Los dos relojes arrancan aqui. El guardian los va moviendo: inicio nunca,
       porque es cuando se entro; ultima_actividad, en cada pagina que se abre. */
    $_SESSION['inicio']           = time();
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['huella']           = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    /* El token del formulario de ingreso ya cumplio. Se cambia por uno nuevo para
       que el token viejo, que estuvo escrito en una pagina, no sirva para
       mandar otra peticion. */
    if (function_exists('rotarCsrf')) {
        rotarCsrf();
    }
}

/** Cierra la sesion del usuario y borra su cookie. */
function cerrarSesion(): void
{
    iniciarSesionSegura();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        /* El setcookie del ejemplo del enunciado usa la forma vieja de ocho
           parametros, y esa forma NO lleva samesite: al borrar la cookie se
           borraria sin ese atributo y el navegador podria no reconocerla como
           la misma cookie. La forma de array, que es la que se usa aqui, si lo
           lleva. */
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Strict',
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
    iniciarSesionSegura();

    if (empty($_SESSION['usuario']['id'])) {
        return null;
    }

    $u = $_SESSION['usuario'];

    return [
        'id'     => (int) $u['id'],
        'nombre' => (string) ($u['nombre'] ?? ''),
        'correo' => (string) ($u['correo'] ?? ''),
        'rol'    => (string) ($u['rol'] ?? ''),
        /* El numero crudo, que es lo que compara el guardian con SESION_MAX, y el
           mismo numero ya escrito para pintar. Se calculan los dos aqui para
           que ninguna pagina tenga que acordarse de formatearlo. */
        'inicio'           => (int) ($_SESSION['inicio'] ?? 0),
        'inicio_legible'   => date('Y-m-d H:i:s', (int) ($_SESSION['inicio'] ?? 0)),
        'inicio_sesion'    => date('Y-m-d H:i:s', (int) ($_SESSION['inicio'] ?? 0)),
    ];
}

/** Indica si hay alguien con la sesion abierta. */
function haySesion(): bool
{
    return usuarioActual() !== null;
}
