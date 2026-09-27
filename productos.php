<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Listado de productos
   Archivo: productos.php
   Actividad de recuperacion, dia 9, punto 4

   Este archivo reemplaza a productos.html, que existia hasta el dia 8. La
   diferencia es de donde salen las filas: antes las pintaba app/productos.js
   desde js/datos-prueba.js, y ahora las trae MySQL.

   Por eso el archivo tiene que terminar en .php y no en .html: es el servidor
   quien lo ejecuta. Un .html lo entrega Apache tal cual, sin ejecutar nada, y
   entonces no habria forma de preguntar nada a la base de datos.

   QUE NO HAY EN ESTE ARCHIVO, A PROPOSITO:
     - Ninguna consulta con el texto pegado dentro. Todo va por prepare() con
       marcadores, en app/modelos/ProductoModelo.php.
     - La tabla no se pinta con JavaScript. Las filas salen del bucle de PHP de
       mas abajo, con htmlspecialchars en cada celda para que un producto
       llamado "<script>" no se ejecute.
     - No hay UPDATE ni DELETE. El dia 9 no los pide.
   =========================================================================== */

/* La conexion se escribe una vez en app/config/credenciales.php y se reutiliza
   desde aca. */
require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/modelos/ProductoModelo.php';

/* El texto que el usuario escribio. Se lee de $_GET, que es un arreglo, y se
   pasa por htmlspecialchars apenas entra, antes de tocar la base de datos. */
$texto = trim((string) ($_GET['buscar'] ?? ''));

$productos = [];
$total     = 0;
$error     = null;

try {
    $pdo = Conexion::obtener();
    /* El limite se pasa explicito y alto para que quepan los 21 productos. El
       20 que trae la funcion es solo el valor por defecto del ejemplo. */
    $productos = buscarProductos($pdo, $texto, 100);
    $total     = contarProductos($pdo);
} catch (Throwable $e) {
    /* Si MySQL esta apagado o las credenciales estan mal, la pagina lo dice en
       vez de quedar en blanco. El detalle tecnico va en un comentario para no
       ensuciar la pagina, y el error de verdad si se muestra. */
    $error = 'No se pudo leer el inventario desde la base de datos. Revisa que MySQL '
           . 'esté encendido y que app/config/credenciales.php tenga la base '
           . 'y la clave correctas.';
    $detalle = $e->getMessage();
}

/** Escapa un valor para poder ponerlo dentro del HTML. */
function esc(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Productos — La Carambola Dorada</title>
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
      <p class="panel__sesion">
        <strong>Inventario</strong> &middot; Sneider Alvarez
      </p>
    </header>

    <label class="menu-velo" for="interruptor-menu"></label>

    <aside class="panel__menu" id="menu-lateral">
      <button type="button" class="menu-cerrar">Cerrar</button>
      <nav aria-labelledby="menu-titulo-lateral">
        <h2 id="menu-titulo-lateral">Menú principal</h2>
        <ul>
          <li><a href="dashboard.html">Tablero</a></li>
          <li><a href="productos.php" aria-current="page">Productos</a></li>
          <li><a href="componentes.html">Componentes</a></li>
          <li><a href="login.html">Cerrar sesión</a></li>
        </ul>
      </nav>
    </aside>

    <main class="panel__contenido">
      <h1>Productos</h1>

      <?php if ($error !== null): ?>
        <p class="alerta alerta--error" role="alert"><?= esc($error) ?></p>
        <!-- <?= esc($detalle ?? '') ?> -->
      <?php else: ?>

      <section>
        <h2>Inventario del salón</h2>

        <!-- El buscador ahora es un formulario GET: el texto viaja en la barra de
             direcciones y MySQL es el que filtra. El wildcard % se le pega al
             valor en ProductoModelo.php, nunca a la consulta. -->
        <form class="buscador" method="get" action="productos.php" role="search">
          <label for="buscar">Buscar en el inventario</label>
          <div class="buscador__fila">
            <input type="search" id="buscar" name="buscar" autocomplete="off"
                   placeholder="Escribe un nombre, una categoría o un código"
                   value="<?= esc($texto) ?>"
                   aria-describedby="buscador-conteo">
            <button type="submit" class="boton">Buscar</button>
          </div>
          <p class="buscador__conteo" id="buscador-conteo" role="status">
            <?php if ($texto === ''): ?>
              <?= count($productos) ?> de <?= $total ?> productos, leídos de MySQL.
            <?php else: ?>
              <?= count($productos) ?> de <?= $total ?> productos coinciden con
              &laquo;<?= esc($texto) ?>&raquo;.
            <?php endif; ?>
          </p>
        </form>

        <div class="tabla-scroll">
        <table class="tabla-productos" id="tabla-productos">
          <caption>Implementos y consumibles disponibles para alquiler</caption>
          <thead>
            <tr>
              <th scope="col">Código</th>
              <th scope="col">Producto</th>
              <th scope="col">Categoría</th>
              <th scope="col">Condición</th>
              <th scope="col">Existencias</th>
              <th scope="col">Valor de alquiler</th>
            </tr>
          </thead>
          <!-- Las filas salen del bucle de PHP de abajo. El data-label es lo que
               permite que la tabla se vuelva una lista de tarjetas en el
               teléfono, y lo pone el propio PHP. -->
          <tbody>
            <?php foreach ($productos as $fila): ?>
              <tr>
                <th scope="row" data-label="Código"><code><?= esc($fila['codigo']) ?></code></th>
                <td data-label="Producto"><?= esc($fila['nombre']) ?></td>
                <td data-label="Categoría"><?= esc($fila['categoria']) ?></td>
                <td data-label="Condición"><?= esc($fila['condicion']) ?></td>
                <td data-label="Existencias"><?= (int) $fila['existencias'] ?></td>
                <td data-label="Valor de alquiler">$<?= number_format((float) $fila['valor_alquiler'], 0, '.', ',') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>

        <?php if ($productos === []): ?>
          <p class="alerta alerta--neutro" id="tabla-vacia">
            <?php if ($texto === ''): ?>
              No hay productos en la base de datos. Carga sql/estructura.sql y
              sql/datos.sql.
            <?php else: ?>
              Ningún producto coincide con &laquo;<?= esc($texto) ?>&raquo;.
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <p class="alerta alerta--neutro">
          Cada fila viene de una consulta preparada de
          <code>app/modelos/ProductoModelo.php</code>. El texto que escribas en
          el buscador viaja como dato, nunca como parte de la consulta.
        </p>
      </section>

      <?php endif; ?>

      <section>
        <h2>Registro de producto</h2>

        <form action="productos.php" method="post" novalidate id="form-producto">
          <fieldset>
            <legend>Datos del producto</legend>

            <label for="codigo">Código del producto</label>
            <input type="text" id="codigo" name="codigo" required
                   pattern="[A-Z]{3}-[0-9]{3}"
                   title="Tres letras en mayúscula, guion y tres dígitos. Ejemplo: TAC-001"
                   maxlength="7">

            <label for="nombre">Nombre del producto</label>
            <input type="text" id="nombre" name="nombre" required
                   minlength="3" maxlength="60">
            <p class="campo-error" id="error-nombre" role="alert"></p>

            <label for="categoria">Categoría</label>
            <select id="categoria" name="categoria" required>
              <option value="">Seleccione una categoría</option>
              <option value="implemento">Implemento de juego</option>
              <option value="consumible">Consumible</option>
              <option value="repuesto">Repuesto</option>
              <option value="mesa">Mesa de billar</option>
            </select>
            <p class="campo-error" id="error-categoria" role="alert"></p>

            <label for="cantidad">Existencias</label>
            <input type="number" id="cantidad" name="cantidad" required min="0" step="1">
            <p class="campo-error" id="error-cantidad" role="alert"></p>

            <label for="valor_alquiler">Valor de alquiler en pesos</label>
            <input type="number" id="valor_alquiler" name="valor_alquiler" required
                   min="0.01" step="0.01">
            <p class="campo-error" id="error-valor_alquiler" role="alert"></p>

            <label for="fecha_ingreso">Fecha de ingreso al inventario</label>
            <input type="date" id="fecha_ingreso" name="fecha_ingreso" required>

            <label for="proveedor">Proveedor</label>
            <input type="text" id="proveedor" name="proveedor" maxlength="80">

            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="200"></textarea>
          </fieldset>

          <fieldset>
            <legend>Condición del producto</legend>

            <input type="radio" id="nuevo" name="condicion" value="nuevo" checked>
            <label for="nuevo">Nuevo</label>

            <input type="radio" id="semi_nuevo" name="condicion" value="semi_nuevo">
            <label for="semi_nuevo">Semi-nuevo</label>

            <input type="radio" id="reparacion" name="condicion" value="reparacion">
            <label for="reparacion">En reparación</label>
          </fieldset>

          <button type="submit" class="boton" id="boton-guardar">Registrar producto</button>
          <p class="estado-form" id="estado-form" role="status"></p>
        </form>
      </section>
    </main>

    <footer class="panel__pie">
      <p>La Carambola Dorada — Sistema de gestión de salones de billar</p>
    </footer>

  </div>

  <!-- El menu viene de app/menu.js. app/productos.js ya solo valida el
       formulario: la tabla la pinta este archivo de PHP, no el navegador. -->
  <script src="app/menu.js"></script>
  <script src="app/productos.js"></script>
</body>
</html>
