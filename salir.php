<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Cerrar la sesion
   Archivo: salir.php
   Actividad de recuperacion, dia 11, punto 1

   Esta pagina no la pide el ejemplo del enunciado, pero sin ella el
   cerrarSesion() del dia 10 seguia sin usarse. Y hay una decision en ella que si
   que conviene explicar, porque lo habitual es lo contrario.

   ---------------------------------------------------------------------------
   POR QUE ESTO ES UN POST Y NO UN ENLACE
   ---------------------------------------------------------------------------
   Lo normal en cualquier sitio es un <a href="salir.php">Cerrar sesion</a>. Un
   enlace es un GET, y un GET se puede pedir desde cualquier parte sin que el
   visitante haga nada: una etiqueta <img src="salir.php">, un correo con esa
   imagen, un script de otra pagina. El atacante no entra en la sesion, solo la
   tira. Por eso, aqui la sesion solo se cierra si llega un POST con el token
   CSRF de esta sesion, o sea, si la peticion sale de un formulario que esta
   pagina pinta.

   Que GET no cierre nada no es un capricho: es lo que hace que la proteccion del
   punto 3 del dia 10 sirva para algo mas que para el ingreso.

   El POST tiene que pasar por el guardian del token, no por el guardian de las
   paginas privadas: si se exigiera sesion para poder cerrar la sesion, entonces
   justamente no se podria cerrar una sesion caducada, que es uno de los motivos
   por los que existe esta pagina.
   =========================================================================== */

require_once __DIR__ . '/app/seguridad/sesion.php';
require_once __DIR__ . '/app/seguridad/salida.php';
require_once __DIR__ . '/app/seguridad/csrf.php';

iniciarSesionSegura();

$esPost     = $_SERVER['REQUEST_METHOD'] === 'POST';
$tokenEnviado = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null;

if (isset($_GET['directo']) && $_GET['directo'] === '1') {
    cerrarSesion();
    redirigir(urlApp('login.php', 'sesion_cerrada'));
}

if ($esPost && validarCsrf($tokenEnviado)) {
    cerrarSesion();

    /* Se manda a login.php con el motivo, para que la pagina diga que se cerro
       la sesion y no simule que nunca hubo ninguna. */
    redirigir(urlApp('login.php', 'sesion_cerrada'));
}

if ($esPost) {
    /* Ha llegado el POST pero el token no cuadraba. Puede ser que la sesion ya
       hubiera caducado (entonces el token viejo ya no esta en la sesion) o que
       sea una peticion de otro sitio. En los dos casos se contesta 403 y NO se
       cierra nada. */
    http_response_code(HTTP_TOKEN_INVALIDO);
    $titulo  = 'No se pudo cerrar la sesión';
    $mensaje = 'La sesión del formulario no es válida. Recargue la página e intente de nuevo.';
} else {
    /* Alguien entro a salir.php con un GET, o sea, con el enlace. No se cierra
       nada: se le explica y se le da el boton. El 405 dice que este archivo
       existe pero no acepta GET, que es mas exacto que un 403. */
    http_response_code(405);
    header('Allow: POST');

    $titulo  = 'Cerrar la sesión';
    $mensaje = 'Para cerrar la sesión hay que confirmar con el botón, no con un enlace: un enlace se puede pedir desde otro sitio sin que usted haga nada.';
}

/* El token se pide DESPUES de la comprobacion, para que en el caso de un token
   invalido se quede el que hay en la sesion y el boton siga sirviendo. */
$token = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($titulo) ?> — La Carambola Dorada</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
  <header>
    <img src="assets/img/logo.svg" alt="Logo de La Carambola Dorada" width="120">
  </header>

  <main class="pantalla-ingreso">
    <h1><?= esc($titulo) ?></h1>
    <p class="lema">Sistema de gestión de salones de billar</p>

    <?php if ($esPost): ?>
      <p class="alerta alerta--error" role="alert"><?= esc($mensaje) ?></p>
    <?php else: ?>
      <p><?= esc($mensaje) ?></p>
    <?php endif; ?>

    <form action="<?= esc(urlApp('salir.php')) ?>" method="post">
      <input type="hidden" name="csrf" value="<?= esc($token) ?>">
      <button type="submit" class="boton">Sí, cerrar la sesión</button>
    </form>

    <p><a class="enlace" href="<?= esc(urlApp('login.php')) ?>">Volver al ingreso</a></p>
  </main>

  <footer>
    <p>La Carambola Dorada — Sistema de gestión de salones de billar</p>
  </footer>
</body>
</html>
