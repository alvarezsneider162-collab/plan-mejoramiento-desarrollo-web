<?php
declare(strict_types=1);

$seccionesReporte = [
    'ventas-categoria' => ['texto' => 'Ventas por categoría', 'ruta' => 'reportes.php?tipo=ventas-categoria'],
    'stock-critico' => ['texto' => 'Inventario crítico', 'ruta' => 'reportes.php?tipo=stock-critico'],
    'pedidos-cliente' => ['texto' => 'Pedidos por cliente', 'ruta' => 'reportes.php?tipo=pedidos-cliente'],
];
$exportacionCsv = http_build_query([
    'tipo' => $tipoReporte,
    'desde' => $desde,
    'hasta' => $hasta,
    'exportar' => 'csv',
]);
?>
<header class="encabezado-reporte">
  <div class="encabezado-reporte__marca">
    <img src="assets/img/logo.svg" alt="La Carambola Dorada">
    <div>
      <p class="encabezado-reporte__sistema">La Carambola Dorada · Informes</p>
      <h1><?= esc($configReporte['titulo']) ?></h1>
      <p class="encabezado-reporte__descripcion"><?= esc($configReporte['descripcion']) ?></p>
    </div>
  </div>

  <nav class="navegacion-reportes" aria-label="Tipos de reporte">
    <?php foreach ($seccionesReporte as $clave => $seccion): ?>
      <a href="<?= esc($seccion['ruta']) ?>"<?= $clave === $tipoReporte ? ' aria-current="page"' : '' ?>>
        <?= esc($seccion['texto']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <form class="filtro-reporte" method="get" action="reportes.php">
    <input type="hidden" name="tipo" value="<?= esc($tipoReporte) ?>">
    <label>Desde
      <input class="campo" type="date" name="desde" value="<?= esc($desde) ?>" required>
    </label>
    <label>Hasta
      <input class="campo" type="date" name="hasta" value="<?= esc($hasta) ?>" required>
    </label>
    <button class="boton" type="submit">Aplicar rango</button>
  </form>

  <div class="acciones-reporte">
    <a class="boton" href="?<?= esc($exportacionCsv) ?>" download>Exportar CSV</a>
    <button class="boton" type="button" id="imprimir-reporte">Imprimir / PDF</button>
  </div>
  <p class="rango-reporte">Periodo consultado: <strong><?= esc($desde) ?></strong> a <strong><?= esc($hasta) ?></strong>.</p>
</header>