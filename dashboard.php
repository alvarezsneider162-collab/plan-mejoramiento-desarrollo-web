<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Tablero de control
   Archivo: dashboard.php
   Actividad de recuperacion, dia 11, punto 2 y punto 3

   Antes que nada: este archivo se llamaba dashboard.html y lo era de verdad, un
   HTML plano. El dia 11 lo paso a ser .php, y no por capricho de renombrar, sino
   porque dos cosas que este tablero tiene que hacer no se pueden hacer desde un
   .html.

   La primera es el punto 2: la barra de arriba tiene que decir el nombre y el
   rol de quien esta entrando. Un archivo .html no tiene con quien hablar: lo que
   el navegador recibe es el texto del archivo tal cual, sin ejecutar nada. Si el
   nombre estuviera escrito dentro, en el HTML saldria siempre el mismo nombre
   en la pantalla de todo el mundo, que es justo lo que hacia el "Sneider
   Alvarez" que estaba puesto a mano. Para leer la sesion hace falta PHP.

   Y la segunda es el punto 3: la pagina tiene que ser privada. Un guardian de
   sesiones es codigo PHP; un .html no lo puede ejecutar ni aunque se lo metan
   dentro. Renombrar el archivo a .php no es un cambio de nombre: es lo que
   hace posible que la proteccion exista.

   La razon tecnica de que un .html no pueda, esta en la configuracion de Apache
   de XAMPP, en C:/xampp/apache/conf/extra/httpd-xampp.conf:

       <FilesMatch "\.php$">
           SetHandler application/x-httpd-php
       </FilesMatch>

   Solo lo que acaba en .php recibe el manejador de PHP. Cualquier otra
   extension se sirve tal cual, como texto. Por eso el tablero era estatico.

   ---------------------------------------------------------------------------
   POR QUE EL GUARDIAN VA PRIMERO DE TODO
   ---------------------------------------------------------------------------
   Este include hace tres cosas en este orden: abre la sesion con el nombre de
   cookie y los parametros correctos, comprueba que haya sesion, y corta si hay
   algo que no cuadre. Entre el require y el <!DOCTYPE html> no hay ni una linea
   de HTML, y a proposito.

   Si el HTML empieza a pintarse antes de comprobar la sesion, el navegador
   recibe los primeros bytes de la pagina, los pinta, y solo entonces llega el
   302 con el salto al ingreso. Durante ese instante el tablero se ve en
   pantalla sin ningun dato de verdad, y ademas el visitante ya empieza a
   descargar recursos que no le sirven. Con el include primero no se emite ni un
   solo byte de la pagina hasta que se sabe que la sesion es buena.

   El include NO lleva "once" a proposito, por la misma razon que en el resto del
   proyecto: include_once tiene que mirar el archivo en disco en cada inclusion
   para comparar rutas, y con require_once PHP resuelve el camino una vez. Este
   archivo se pide una vez por peticion, pero la costumbre se mantiene igual en
   todo el proyecto para que el criterio sea el mismo en todas partes.

   ---------------------------------------------------------------------------
   QUE SE VE SI NO HAY SESION
   ---------------------------------------------------------------------------
   El guardian manda a login.php?m=requiere_ingreso, y login.php explica que
   hace falta entrar. No se dice nada de por que: si aqui se distinguiera "no has
   entrado" de "tu sesion se acabo", la pagina estaria confirmando informacion
   sobre la sesion de otra persona.
   =========================================================================== */

/* El guardian va el primero, antes de la cabecera HTML. */
require_once __DIR__ . '/app/seguridad/guardia.php';

/* La barra de arriba la pinta el parcial, con el nombre y el rol de verdad. */
$etiqueta = 'Sala 1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tablero de control — La Carambola Dorada</title>
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
      <?php require __DIR__ . '/app/parciales/barra.php'; ?>
    </header>

    <label class="menu-velo" for="interruptor-menu"></label>

    <aside class="panel__menu" id="menu-lateral">
      <button type="button" class="menu-cerrar">Cerrar</button>
      <nav aria-labelledby="menu-titulo-lateral">
        <h2 id="menu-titulo-lateral">Menú principal</h2>
        <ul>
          <li><a href="dashboard.php" aria-current="page">Tablero</a></li>
          <li><a href="productos.php">Productos</a></li>
          <li><a href="componentes.html">Componentes</a></li>
          <li><a href="usuarios.php">Usuarios</a></li>
          <li><a href="login.php">Ingreso</a></li>
          <li><a href="salir.php">Cerrar sesi&oacute;n</a></li>
        </ul>
      </nav>
    </aside>

    <main class="panel__contenido">
      <h1>Tablero de control</h1>

      <section>
        <h2>Resumen del día</h2>
        <div class="indicadores">
          <article class="tarjeta">
            <h3>Mesas ocupadas</h3>
            <p>5 de 8</p>
          </article>
          <article class="tarjeta">
            <h3>Mesas libres</h3>
            <p>3</p>
          </article>
          <article class="tarjeta">
            <h3>Cobro acumulado del día</h3>
            <p>$ 84.000</p>
          </article>
          <article class="tarjeta">
            <h3>Implementos por devolver</h3>
            <p>4</p>
          </article>
        </div>
      </section>

      <section>
        <h2>Estado de las mesas</h2>
        <div class="tabla-scroll">
        <table class="tabla-productos">
          <caption>Ocupación de las mesas a las 6:40 p.&nbsp;m.</caption>
          <thead>
            <tr>
              <th scope="col">Mesa</th>
              <th scope="col">Estado</th>
              <th scope="col">Jugador actual</th>
              <th scope="col">Hora de inicio</th>
              <th scope="col">Tiempo jugado</th>
            </tr>
          </thead>
          <tbody>
            <tr><th scope="row">Mesa 1</th><td data-label="Estado">Ocupada</td><td data-label="Jugador actual">Jhon Ballén</td><td data-label="Hora de inicio">18:05</td><td data-label="Tiempo jugado">35 min</td></tr>
            <tr><th scope="row">Mesa 2</th><td data-label="Estado">Ocupada</td><td data-label="Jugador actual">Andrea Ríos</td><td data-label="Hora de inicio">17:50</td><td data-label="Tiempo jugado">50 min</td></tr>
            <tr><th scope="row">Mesa 3</th><td data-label="Estado">Ocupada</td><td data-label="Jugador actual">Derek Céspedes</td><td data-label="Hora de inicio">17:20</td><td data-label="Tiempo jugado">1 h 20 min</td></tr>
            <tr><th scope="row">Mesa 4</th><td data-label="Estado">Libre</td><td data-label="Jugador actual">—</td><td data-label="Hora de inicio">—</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 5</th><td data-label="Estado">Ocupada</td><td data-label="Jugador actual">Marcela Ortiz</td><td data-label="Hora de inicio">18:30</td><td data-label="Tiempo jugado">10 min</td></tr>
            <tr><th scope="row">Mesa 6</th><td data-label="Estado">Libre</td><td data-label="Jugador actual">—</td><td data-label="Hora de inicio">—</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 7</th><td data-label="Estado">Reservada</td><td data-label="Jugador actual">Turno de la noche</td><td data-label="Hora de inicio">19:00</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 8</th><td data-label="Estado">Libre</td><td data-label="Jugador actual">—</td><td data-label="Hora de inicio">—</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 9</th><td data-label="Estado">Libre</td><td data-label="Jugador actual">—</td><td data-label="Hora de inicio">—</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 10</th><td data-label="Estado">Ocupada</td><td data-label="Jugador actual">Camilo Torres</td><td data-label="Hora de inicio">17:40</td><td data-label="Tiempo jugado">1 h 00 min</td></tr>
            <tr><th scope="row">Mesa 11</th><td data-label="Estado">Libre</td><td data-label="Jugador actual">—</td><td data-label="Hora de inicio">—</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 12</th><td data-label="Estado">Ocupada</td><td data-label="Jugador actual">Laura Jiménez</td><td data-label="Hora de inicio">18:15</td><td data-label="Tiempo jugado">25 min</td></tr>
            <tr><th scope="row">Mesa 13</th><td data-label="Estado">Libre</td><td data-label="Jugador actual">—</td><td data-label="Hora de inicio">—</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 14</th><td data-label="Estado">Reservada</td><td data-label="Jugador actual">Evento privado</td><td data-label="Hora de inicio">20:00</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 15</th><td data-label="Estado">Libre</td><td data-label="Jugador actual">—</td><td data-label="Hora de inicio">—</td><td data-label="Tiempo jugado">—</td></tr>
            <tr><th scope="row">Mesa 16</th><td data-label="Estado">Ocupada</td><td data-label="Jugador actual">Esteban Mora</td><td data-label="Hora de inicio">17:55</td><td data-label="Tiempo jugado">45 min</td></tr>
          </tbody>
        </table>
        </div>
      </section>

      <aside>
        <h2>Alertas</h2>
        <ul class="lista-alertas">
          <li class="alerta alerta--advertencia">Mesa 3: faltan 5 minutos para completar la hora pagada.</li>
          <li class="alerta alerta--info">Mesa 1: el jugador solicita 2 horas más de mesa.</li>
          <li class="alerta alerta--error">Faltan 4 bolas de billar por devolver de un alquiler anterior.</li>
          <li class="alerta alerta--info">Mesa 10: revisando tiempo de ocupación.</li>
          <li class="alerta alerta--advertencia">Mesa 16: próxima a cumplir hora de cobro.</li>
        </ul>
      </aside>
    </main>

    <footer class="panel__pie">
      <p>La Carambola Dorada — Sistema de gestión de salones de billar</p>
    </footer>

  </div>

  <script src="app/menu.js"></script>
</body>
</html>
