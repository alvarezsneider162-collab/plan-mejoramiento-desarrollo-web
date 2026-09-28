<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Ingreso al panel
   Archivo: login.php
   Actividad de recuperacion, dia 10, punto 3

   Esta pagina reemplaza a login.html, que era el mismo formulario pero sin
   nada de esto: sin token, sin hash, sin consulta a la base. Este archivo es
   el primero que de verdad decide si alguien entra.

   QUE HAY QUE MIRAR EN ESTE ARCHIVO, EN ESTE ORDEN:

     1. iniciarSesionSegura() antes de tokenCsrf(), porque el token vive en la
        sesion.
     2. El POST se corta con un 403 si el token no valia. El 403 sale de una
     constante de app/seguridad/csrf.php, donde esta escrito por que no se usa
     el 419 del ejemplo del enunciado.
     3. Todo el decide es procesarLogin(), en app/controladores/login.php.
        Esta pagina no contiene ninguna consulta ni ninguna comparacion de
        contrasenas.
     4. El error que se muestra es UNO SOLO, el mismo para correo que no existe,
        contrasena que no es y cuenta bloqueada.
     5. Cuando entra, se abre la sesion y se muestra el nombre con esc(), que es
        lo que demuestra el punto 5.

   ---------------------------------------------------------------------------
   POR QUE NO HAY REDIRECCION
   ---------------------------------------------------------------------------
   El ejemplo del enunciado manda a /dashboard.php con header('Location: ...').
   Ese tablero con sesion es el dia 11. En vez de redirigir a una pagina que aun
   no existe, cuando el ingreso sale bien se muestra aqui mismo un panel de
   bienvenida con el nombre y el rol de quien entro. Asi el punto 5 se puede
   demostrar hoy: el nombre sale de la base de datos, pasa por la sesion y se
   imprime escapado.
   =========================================================================== */

require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/seguridad/salida.php';
require_once __DIR__ . '/app/seguridad/sesion.php';
require_once __DIR__ . '/app/seguridad/csrf.php';
require_once __DIR__ . '/app/controladores/login.php';

iniciarSesionSegura();

/* Los motivos por los que el guardian del dia 11 manda aqui. Cada uno es una
   situacion distinta y el visitante merece saber cual fue, sin que se le diga
   nada sobre la sesion de otra persona. */
$motivos = [
    'requiere_ingreso' => 'Esta página es privada. Entra con tu cuenta para verla.',
    'sesion_invalida'  => 'La sesión se cerró porque se detectó otro navegador. Vuelve a entrar.',
    'sesion_expirada'  => 'Tu sesión se cerró por inactividad o por estar abierta demasiado tiempo. Vuelve a entrar.',
    'sesion_cerrada'   => 'Cerraste la sesión. Entra otra vez cuando quieras.',
];
$avisoSesion = '';
if (isset($_GET['m']) && is_string($_GET['m']) && isset($motivos[$_GET['m']])) {
    $avisoSesion = $motivos[$_GET['m']];
}

$error      = '';
$ingreso    = null;
$codigo     = 200;
$yaEntrado  = usuarioActual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf(isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null)) {
        /* Sin token no se entra. Ni se mira el correo, ni se consulta la base,
           ni se anota el intento: si la peticion no vino de este formulario,
           tampoco cuenta como intento de alguien. */
        http_response_code(HTTP_TOKEN_INVALIDO);
        $codigo = HTTP_TOKEN_INVALIDO;
        $error  = 'La sesión del formulario no es válida. Recargue la página e intente de nuevo.';
    } else {
        try {
            $resultado = procesarLogin(Conexion::obtener(), $_POST);
            $error     = $resultado['error'];
            $codigo    = $resultado['codigo'];
            $ingreso   = $resultado['usuario'];

            if ($resultado['codigo'] === HTTP_TOKEN_INVALIDO) {
                http_response_code(HTTP_TOKEN_INVALIDO);
            }

            if ($ingreso !== null) {
                /* Solo cuando la contrasena esta verificada de verdad. En el dia 11
                   se abre la sesion y se redirige al tablero protegido. */
                abrirSesion($ingreso);
                redirigir(urlApp('dashboard.php'));
            }
        } catch (Throwable $e) {
            $error = 'No se pudo comprobar el ingreso. Revisa que MySQL esté encendido. Detalle: '
                   . $e->getMessage();
        }
    }
}

/* El correo se vuelve a poner para no obligar a escribirlo otra vez, pero la
   clave NO. Volver a imprimir la clave en el HTML la dejaria escrita en el
   historial del navegador, que es justo lo que se quiere evitar. */
$correo = esc($_POST['correo'] ?? '');

/* Dia 12, punto 1: lo que le pasa a los parciales. $pantalla vale 'publico', y
   con eso cabecera.php imprime el <header> simple con el logo en vez de la
   barra con el boton del menu: esta pantalla no tiene menu lateral, y por eso
   el interruptor y el boton no tienen que aparecer. El <head>, el logo y el pie
   si son los mismos que en las pantallas del panel, y por eso salen de aqui y
   no estan escritos a mano en este archivo. */
$tituloPagina = 'Ingreso — La Carambola Dorada';
$pantalla     = 'publico';
?>

<?php require __DIR__ . '/app/parciales/cabecera.php'; ?>

  <main class="pantalla-ingreso">
    <h1>Ingreso al panel de gestión</h1>
    <p class="lema">Sistema de gestión de salones de billar</p>

    <?php if ($avisoSesion !== ''): ?>
      <p class="alerta" role="status"><?= esc($avisoSesion) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <p class="alerta alerta--error" id="error-acceso" role="alert"><?= esc($error) ?></p>
    <?php endif; ?>

    <?php if ($yaEntrado !== null): ?>
      <div class="alerta alerta--exito" id="bienvenida" role="status">
        <h2>Sesión iniciada</h2>
        <p>Bienvenido, <strong id="nombre-usuario"><?= esc($yaEntrado['nombre']) ?></strong>.</p>
        <ul>
          <li>Correo: <?= esc($yaEntrado['correo']) ?></li>
          <li>Rol: <span class="badge badge--exito" id="rol-usuario"><?= esc($yaEntrado['rol']) ?></span></li>
          <li>Entró a las: <time><?= esc($yaEntrado['inicio_legible']) ?></time></li>
        </ul>
        <p>El nombre de arriba sale de la base de datos y se imprime con
        <code>htmlspecialchars()</code>. Si ese nombre trae un
        <code>&lt;script&gt;</code>, se ve escrito y no se ejecuta.</p>
        <p>
          <a class="boton" href="<?= esc(urlApp('dashboard.php')) ?>" style="display:inline-block; margin-right: 0.8rem;">Ir al Tablero</a>
          <a class="enlace" href="registro.php">Registrar otro usuario</a>
        </p>
      </div>
    <?php else: ?>

      <form action="login.php" method="post" novalidate autocomplete="on">
        <input type="hidden" name="csrf" value="<?= esc(tokenCsrf()) ?>">

        <fieldset>
          <legend>Credenciales</legend>

          <label for="correo">Correo electrónico</label>
          <input type="email" id="correo" name="correo" class="campo"
                 required autocomplete="username" value="<?= $correo ?>">

          <label for="clave">Contraseña</label>
          <input type="password" id="clave" name="clave" class="campo" required
                 autocomplete="current-password" minlength="8">
        </fieldset>

        <button type="submit" class="boton">Iniciar sesión</button>
      </form>

      <p><a class="enlace" href="registro.php">No tengo cuenta, quiero registrarme</a></p>
    <?php endif; ?>

    <p class="sub">Este mensaje de error es el mismo para un correo que no está
    registrado, para una contraseña equivocada y para una cuenta bloqueada: si
    fueran distintos, la página serviría para averiguar qué correos existen.</p>
  </main>

<?php require __DIR__ . '/app/parciales/pie.php'; ?>
