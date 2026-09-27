<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Usuarios
   Archivo: usuarios.php
   Actividad de recuperacion, dia 11, punto 4

   Esta pagina NO existia. El enunciado del punto 4 pide comprobar que un
   consultor que entra por URL a usuarios.php recibe un 403, pero hasta el dia 11
   no habia ningun usuarios.php en el proyecto: la prueba no se podia hacer
   porque la puerta no existia. Este archivo es esa puerta.

   ---------------------------------------------------------------------------
   LAS TRES PUERTAS, EN ORDEN, Y POR QUE EN ESE ORDEN
   ---------------------------------------------------------------------------
   Esta pagina tiene tres controles, y ninguno sustituye a otro:

     1. ¿Hay sesion?          Lo responde el guardian, al incluirse.
     2. ¿Cerrar la sesion?    El guardian, tambien.
     3. ¿Es administrador?    exigirRol(), aqui, en la pagina.

   El orden importa. Si el 403 se comprobara antes que la sesion, un visitante
   sin entrar y un consultor con sesion recibirian el mismo 403, y eso no es lo
   que se quiere: el que no ha entrado tiene que ir al ingreso, y el que ha
   entrado pero no puede, tiene que saber que esta prohibido.

   Y al reves tampoco vale: si exigiera rol una pagina publica, bastaria con
   adivinar la URL para enterarse de que existe una seccion restringida.

   ---------------------------------------------------------------------------
   POR QUE 403 Y NO 302
   ---------------------------------------------------------------------------
   Un 302 con la sesion cerrada es un 401/403 camuflado: el navegador cumple,
   casi sin pestanear, y se acabo. Para una prueba de autorizacion eso no vale
   para nada, porque no se ve el codigo. exigirRol() responde 403 y escribe el
   motivo, sin plantilla, que es lo que pide el enunciado.

   El 403 no dice a que pagina se estaba intentando entrar ni por que el rol
   concreto no da permiso. Un atacante que va probando URLs veria un mapa de las
   secciones restringidas, y eso ya es informacion. Aqui se corta y ya.

   ---------------------------------------------------------------------------
   SOLO ADMINISTRADOR
   ---------------------------------------------------------------------------
   exigirRol('administrador') y nada mas. El motivo de que sea solo ese rol es
   que esta pagina enseña el correo y el rol de TODOS los usuarios: el listado
   entero es informacion que no le hace falta a un vendedor ni a un consultor.
   Por eso el punto 4 pide que el consultor reciba 403, y por eso el
   administrador es el unico que pasa.

   Si manana el vendedor necesita verlo, se anade 'vendedor' a la llamada y ya:
   el control esta en un sitio, no repartido por la pagina.

   ---------------------------------------------------------------------------
   LO QUE NO SE PINTA
   ---------------------------------------------------------------------------
   Ni clave_hash ni caracteres, porque UsuarioModelo.php ni siquiera los pide en
   el SELECT. La razon esta escrita alli, y es que un hash que no hace falta no
   tiene por que viajar desde la base de datos hasta la pantalla.
   =========================================================================== */

/* 1 y 2: la sesion. Este include va antes de la cabecera HTML, como en todas
   las paginas privadas, para que no se pixele ni un byte antes de comprobar. */
require_once __DIR__ . '/app/seguridad/guardia.php';

/* 3: el rol. Esta llamada no devuelve nunca cuando el rol no vale, porque
   exigirRol() corta con exit por dentro. Si se llega a la linea de abajo, es que
   el usuario es administrador. */
exigirRol('administrador');

require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/modelos/UsuarioModelo.php';

/* conexion.php NO deja la conexion en una variable $pdo: la guarda dentro de la
   clase y se pide con Conexion::obtener(). Es lo que hace productos.php, y por
   eso se copia esta linea y no otra: si aqui se escribiera $pdo a pelo, PHP
   daria "Undefined variable $pdo" y despues "listarUsuarios(): Argument #1
   ($pdo) must be of type PDO, null given". */
$pdo     = Conexion::obtener();
$usuarios = listarUsuarios($pdo);
$total    = contarUsuarios($pdo);

/* El estado de la cuenta y el rol se pintan como .badge, que ya existia en la
   hoja de estilos del dia 8; no hay que inventar una clase nueva. Un usuario
   con la cuenta dada de baja o con la clave en bloqueo se ve distinto de uno
   que puede entrar, y de un vistazo se sabe quien esta operando. */
function claseEstado(int $activo): string
{
    return $activo === 1 ? 'badge--exito' : 'badge--neutro';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios — La Carambola Dorada</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
  <div class="panel">

    <input type="checkbox" class="interruptor-menu" id="interruptor-menu" aria-label="Abrir o cerrar el menú lateral">

    <header class="panel__barra">
      <button type="button" class="boton-menu" id="boton-menu"
              aria-expanded="false" aria-controls="menu-lateral">
        <span class="boton-menu__icono" aria-hidden="true"></span>
        <span class="boton-menu__texto">Menú</span>
      </button>
      <img src="assets/img/logo.svg" alt="La Carambola Dorada">
      <?php
      /* El parcial se incluye aqui y no arriba del archivo, con la etiqueta ya
         puesta, y se incluye con require y NO con require_once.

         Si arriba del archivo hubiera un require_once de este parcial, se
         ejecutaria en ese momento, cuando $etiqueta todavia no vale nada, y
         pintaria la barra con la etiqueta vacia. Y este require de aqui ya no
         haria nada, porque el require_once de arriba se habria gastado la
         inclusion: la barra se quedaria como la pintara el primero.

         Es el mismo aviso que en productos.php, donde paso lo mismo con la otra
         copia de esc() antes de que el dia 11 le pusiera el guardian encima. */
      $etiqueta = 'Usuarios';
      require __DIR__ . '/app/parciales/barra.php';
      ?>
    </header>

    <label class="menu-velo" for="interruptor-menu"></label>

    <aside class="panel__menu" id="menu-lateral">
      <button type="button" class="menu-cerrar">Cerrar</button>
      <nav aria-labelledby="menu-titulo-lateral">
        <h2 id="menu-titulo-lateral">Menú principal</h2>
        <ul>
          <li><a href="dashboard.php">Tablero</a></li>
          <li><a href="productos.php">Productos</a></li>
          <li><a href="componentes.html">Componentes</a></li>
          <li><a href="usuarios.php" aria-current="page">Usuarios</a></li>
          <li><a href="login.php">Ingreso</a></li>
          <li><a href="salir.php">Cerrar sesión</a></li>
        </ul>
      </nav>
    </aside>

    <main class="panel__contenido">
      <h1>Usuarios</h1>
      <p class="lema">
        <?= (int) $total ?> <?= $total === 1 ? 'usuario' : 'usuarios' ?>
        registrados. Esta lista es solo para administradores.
      </p>

      <div class="tabla-scroll">
      <table class="tabla-productos" id="tabla-usuarios">
        <caption>Cuentas del sistema. El correo y el rol los guarda el día 10; la clave nunca se enseña.</caption>
        <thead>
          <tr>
            <th scope="col">Id</th>
            <th scope="col">Nombre</th>
            <th scope="col">Correo</th>
            <th scope="col">Rol</th>
            <th scope="col">Estado</th>
            <th scope="col">Alta</th>
          </tr>
        </thead>
        <!-- El data-label es lo que convierte cada fila en una tarjeta suelta en
             el telefono, y lo pone el propio PHP. -->
        <tbody>
          <?php foreach ($usuarios as $fila): ?>
            <tr>
              <th scope="row" data-label="Id"><code><?= (int) $fila['id'] ?></code></th>
              <td data-label="Nombre"><?= esc($fila['nombre']) ?></td>
              <td data-label="Correo"><?= esc($fila['correo']) ?></td>
              <td data-label="Rol">
                <span class="badge badge--info"><?= esc(ucfirst($fila['rol'])) ?></span>
              </td>
              <td data-label="Estado">
                <span class="badge <?= claseEstado((int) $fila['activo']) ?>">
                  <?= (int) $fila['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td data-label="Alta"><?= esc(substr((string) $fila['creado_en'], 0, 10)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <?php if ($usuarios === []): ?>
        <p class="alerta alerta--neutro" id="tabla-vacia">
          No hay usuarios en la base de datos. Carga sql/usuarios.sql y da de
          alta alguno en registro.php.
        </p>
      <?php endif; ?>
    </main>

    <footer class="panel__pie">
      <p>La Carambola Dorada — Sistema de gestión de salones de billar</p>
    </footer>

  </div>

  <script src="app/menu.js"></script>
</body>
</html>
