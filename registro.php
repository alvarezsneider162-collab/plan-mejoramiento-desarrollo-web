<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Alta de usuarios
   Archivo: registro.php
   Actividad de recuperacion, dia 10, punto 2

   Esta pagina es el punto 2 del enunciado: el formulario por donde se registran
   las personas, y donde la contrasena se convierte en hash antes de tocar la
   base de datos.

   QUE HAY QUE MIRAR EN ESTE ARCHIVO, EN ESTE ORDEN:

     1. iniciarSesionSegura() antes de tokenCsrf(), porque el token vive en la
        sesion.
     2. El POST se corta si el token no valia. No se mira ni el correo ni la
        contrasena: si la peticion no vino de este formulario, no se procesa.
     3. crearUsuario() hace el INSERT con el hash, en Autenticacion.php.
     4. Todo lo que se imprime pasa por esc().

   En QUE NO HAY NADA en este archivo: la contrasena nunca se imprime, ni se
   guarda en una variable que se muestre, ni llega al HTML. Lo unico que se
   ensea es el hash, que es lo que quedo en la base.
   =========================================================================== */

require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/seguridad/salida.php';
require_once __DIR__ . '/app/seguridad/sesion.php';
require_once __DIR__ . '/app/seguridad/csrf.php';
require_once __DIR__ . '/app/controladores/registro.php';

iniciarSesionSegura();

$errores = [];
$creado  = null;
$codigo  = 200;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf(isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null)) {
        http_response_code(HTTP_TOKEN_INVALIDO);
        $codigo  = HTTP_TOKEN_INVALIDO;
        $errores[] = 'La sesión del formulario no es válida. Recargue la página e intente de nuevo.';
    } else {
        try {
            $resultado = procesarRegistro(Conexion::obtener(), $_POST);
            $errores = $resultado['errores'];
            $creado  = $resultado['usuario'];
        } catch (Throwable $e) {
            $errores[] = 'No se pudo registrar el usuario. Revisa que MySQL esté encendido. Detalle: ' . $e->getMessage();
        }
    }
}

/* Lo que se vuelve a poner en el formulario cuando hay errores, para que la
   persona no tenga que escribirlo todo otra vez. La clave NO se vuelve a
   poner: recargarla en el HTML la dejaria en el historial del navegador. */
$nombre = esc($_POST['nombre'] ?? '');
$correo = esc($_POST['correo'] ?? '');
$rol    = esc($_POST['rol'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro de usuario &mdash; La Carambola Dorada</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
  <header>
    <img src="assets/img/logo.svg" alt="Logo de La Carambola Dorada" width="120">
  </header>

  <main class="pantalla-ingreso">
    <h1>Registrar usuario</h1>
    <p class="lema">Alta de administrador, vendedor y consultor</p>

    <?php if ($errores !== []): ?>
      <div class="alerta alerta--error" role="alert">
        <p><strong>No se pudo registrar:</strong></p>
        <ul>
          <?php foreach ($errores as $error): ?>
            <li><?= esc($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($creado !== null): ?>
      <div class="alerta alerta--exito" role="status">
        <p><strong>Usuario registrado.</strong> Ya se puede entrar con ese correo.</p>
        <ul>
          <li>Id: <?= (int) $creado['id'] ?></li>
          <li>Nombre: <?= esc($creado['nombre']) ?></li>
          <li>Correo: <?= esc($creado['correo']) ?></li>
          <li>Rol: <?= esc($creado['rol']) ?></li>
          <li>Caracteres de la contraseña: <?= (int) $creado['caracteres'] ?></li>
        </ul>
        <p>Lo que quedo guardado en <code>clave_hash</code>:</p>
        <p><code><?= esc($creado['clave_hash']) ?></code></p>
        <p>Esos 60 caracteres empiezan por <code>$2y$</code>, que es como empieza
        cualquier hash de <code>password_hash()</code>. La contraseña no se
        guardó en ninguna parte: de ese hash no se puede volver atrás.</p>
      </div>
    <?php endif; ?>

    <form action="registro.php" method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= esc(tokenCsrf()) ?>">

      <fieldset>
        <legend>Datos de la persona</legend>

        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" required maxlength="80"
               value="<?= $nombre ?>" autocomplete="name">

        <label for="correo">Correo electrónico</label>
        <input type="email" id="correo" name="correo" required maxlength="190"
               value="<?= $correo ?>" autocomplete="email">

        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" required minlength="8"
               autocomplete="new-password">
        <p class="sub">Mínimo 8 caracteres. Lo que se guarde es su hash, no la contraseña.</p>

        <label for="rol">Rol</label>
        <select id="rol" name="rol" required>
          <option value="">Seleccione un rol</option>
          <?php foreach (ROLES as $opcion): ?>
            <option value="<?= esc($opcion) ?>"<?= $rol === esc($opcion) ? ' selected' : '' ?>>
              <?= esc($opcion) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <button type="submit" class="boton">Registrar usuario</button>
    </form>

    <p><a class="enlace" href="login.php">Ya tengo cuenta, quiero entrar</a></p>
  </main>

  <footer>
    <p>La Carambola Dorada &mdash; Sistema de gestión de salones de billar</p>
  </footer>
</body>
</html>
