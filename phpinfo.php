<?php
declare(strict_types=1);

/* Punto 1 del dia 9: comprobar la version de PHP con phpinfo().
   Este archivo es solo una herramienta de comprobacion, por eso esta en el
   .gitignore: publicar un phpinfo() en un servidor real revela la ruta de
   instalacion, la version exacta y las extensiones activas, y por lo tanto
   sirve de mapa a quien busca fallos contra esa version. */

$version      = PHP_VERSION;
$versionNumer = PHP_VERSION_ID;
$minima       = 80000;                       // PHP 8.0.0
$cumple       = $versionNumer >= $minima;
$extensiones   = ['pdo_mysql', 'mysqli', 'mbstring'];
$faltan       = array_values(array_filter(
    $extensiones,
    static fn(string $e): bool => !extension_loaded($e)
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Versión de PHP — La Carambola Dorada</title>
  <link rel="stylesheet" href="css/estilos.css">
  <style>
    .ficha { background: var(--color-surface); border: 1px solid var(--border-color);
             border-radius: 8px; padding: 1.2rem; margin: 0 0 1.2rem; }
    .ficha dl { display: grid; gap: .4rem 1rem; margin: 0; }
    .ficha dt { font-size: var(--tipo-xs); color: var(--color-text-muted);
                text-transform: uppercase; letter-spacing: .04em; }
    .ficha dd { margin: 0 0 .8rem; font-family: Consolas, 'Courier New', monospace; }
    .ficha dd:last-child { margin-bottom: 0; }
    .veredicto { padding: .2rem .5rem; border-radius: 3px; font-weight: 700;
                 font-family: var(--tipo-sm); }
    .veredicto--bien { color: var(--color-success); background: var(--color-marca-light); }
    .veredicto--mal  { color: var(--color-error);   background: var(--color-surface-alt); }
  </style>
</head>
<body>
  <div class="panel">
    <header class="panel__barra">
      <img src="assets/img/logo.svg" alt="La Carambola Dorada">
      <p class="panel__sesion"><strong>Entorno</strong> &middot; Punto 1 del día 9</p>
    </header>

    <main class="panel__contenido">
      <h1>Versión de PHP</h1>

      <div class="ficha">
        <dl>
          <dt>Versión instalada</dt>
          <dd><?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></dd>

          <dt>Versión mínima pedida</dt>
          <dd>8.0.0</dd>

          <dt>Resultado</dt>
          <dd>
            <?php if ($cumple): ?>
              <span class="veredicto veredicto--bien">CUMPLE: PHP 8.0 o superior</span>
            <?php else: ?>
              <span class="veredicto veredicto--mal">NO CUMPLE: hay que actualizar a 8.0 o superior</span>
            <?php endif; ?>
          </dd>

          <dt>Motor de base de datos</dt>
          <dd><?php
            /* Esto no lee datos de la base: solo pregunta la version del
               servidor. Y aun asi va por prepare(), sin query() directo, para
               que en este proyecto TODA consulta pase por prepare() y el
               criterio del dia 9 se pueda comprobar leyendo los archivos. */
            $cfg = require __DIR__ . '/app/config/credenciales.php';
            $dsn = "mysql:host={$cfg['host']};charset=utf8mb4";
            try {
                $servidor = new PDO($dsn, $cfg['usuario'], $cfg['clave'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $st = $servidor->prepare('SELECT VERSION()');
                $st->execute();
                echo htmlspecialchars((string) $st->fetchColumn(), ENT_QUOTES, 'UTF-8');
            } catch (PDOException) {
                echo 'servidor no disponible';
            }
          ?></dd>

          <dt>Extensiones necesarias</dt>
          <dd>
            <?php if ($faltan === []): ?>
              Las tres activas: pdo_mysql, mysqli y mbstring.
            <?php else: ?>
              Faltan: <?= htmlspecialchars(implode(', ', $faltan), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </dd>
        </dl>
      </div>

      <section>
        <h2>Salida completa de <code>phpinfo()</code></h2>
        <p class="alerta alerta--neutro">
          Abajo está la salida íntegra de <code>phpinfo()</code>, tal como la
          pide el enunciado. En producción esta página no debería existir: por eso el
          archivo queda fuera del repositorio.
        </p>
        <div class="phpinfo-salida">
          <?php phpinfo(); ?>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
