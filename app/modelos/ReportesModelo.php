<?php
declare(strict_types=1);

/** Devuelve el rango de fechas disponible en pedidos y productos. */
function rangoDisponibleReportes(PDO $pdo): array
{
    $fila = $pdo->query(
        'SELECT MIN(fecha) AS desde, MAX(fecha) AS hasta
           FROM (
             SELECT fecha FROM pedidos
             UNION ALL
             SELECT fecha_ingreso AS fecha FROM productos
           ) AS fechas'
    )->fetch();

    $desde = (string) ($fila['desde'] ?? '');
    $hasta = (string) ($fila['hasta'] ?? '');

    if ($desde === '' || $hasta === '') {
        return ['desde' => date('Y-m-01'), 'hasta' => date('Y-m-d')];
    }

    return ['desde' => $desde, 'hasta' => $hasta];
}

/** Normaliza fechas opcionales y rechaza fechas inexistentes o rangos invertidos. */
function normalizarRangoReporte(PDO $pdo, string $desde, string $hasta): array
{
    $disponible = rangoDisponibleReportes($pdo);
    $desde = $desde !== '' ? $desde : $disponible['desde'];
    $hasta = $hasta !== '' ? $hasta : $disponible['hasta'];

    foreach ([$desde, $hasta] as $fecha) {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes) !== 1
            || !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw new InvalidArgumentException('Selecciona fechas válidas en formato AAAA-MM-DD.');
        }
    }

    if ($desde > $hasta) {
        throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
    }

    return ['desde' => $desde, 'hasta' => $hasta];
}

/** Agrupa ventas por categoría a partir de la vista diaria. */
function reporteVentasPorCategoria(PDO $pdo, string $desde, string $hasta): array
{
    $consulta = $pdo->prepare(
        'SELECT categoria, SUM(unidades) AS unidades, SUM(total) AS total
           FROM ventas_por_categoria
          WHERE fecha BETWEEN :desde AND :hasta
          GROUP BY categoria
          ORDER BY total DESC, categoria ASC'
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);
    $filas = array_map(static function (array $fila): array {
        return [
            'categoria' => (string) $fila['categoria'],
            'unidades' => (int) $fila['unidades'],
            'total' => (float) $fila['total'],
        ];
    }, $consulta->fetchAll());

    return [
        'filas' => $filas,
        'totales' => [
            'categorias' => count($filas),
            'unidades' => array_sum(array_column($filas, 'unidades')),
            'total' => array_sum(array_column($filas, 'total')),
        ],
    ];
}

/** Lista el stock crítico de productos ingresados en el rango indicado. */
function reporteInventarioCritico(PDO $pdo, string $desde, string $hasta): array
{
    $consulta = $pdo->prepare(
        'SELECT codigo, nombre, categoria, existencias, limite, fecha_ingreso
           FROM stock_critico
          WHERE fecha_ingreso BETWEEN :desde AND :hasta
          ORDER BY existencias ASC, nombre ASC'
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);
    $filas = array_map(static function (array $fila): array {
        return [
            'codigo' => (string) $fila['codigo'],
            'nombre' => (string) $fila['nombre'],
            'categoria' => (string) $fila['categoria'],
            'existencias' => (int) $fila['existencias'],
            'limite' => (int) $fila['limite'],
            'fecha_ingreso' => (string) $fila['fecha_ingreso'],
        ];
    }, $consulta->fetchAll());

    return [
        'filas' => $filas,
        'totales' => [
            'productos' => count($filas),
            'existencias' => array_sum(array_column($filas, 'existencias')),
        ],
    ];
}

/** Resume pedidos por cliente a partir de la vista de pedidos. */
function reportePedidosPorCliente(PDO $pdo, string $desde, string $hasta): array
{
    $consulta = $pdo->prepare(
        'SELECT cliente_id, cliente, COUNT(*) AS pedidos,
                SUM(unidades) AS unidades, SUM(total) AS total
           FROM pedidos_por_cliente
          WHERE fecha BETWEEN :desde AND :hasta
          GROUP BY cliente_id, cliente
          ORDER BY total DESC, cliente ASC'
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);
    $filas = array_map(static function (array $fila): array {
        return [
            'cliente_id' => (int) $fila['cliente_id'],
            'cliente' => (string) $fila['cliente'],
            'pedidos' => (int) $fila['pedidos'],
            'unidades' => (int) $fila['unidades'],
            'total' => (float) $fila['total'],
        ];
    }, $consulta->fetchAll());

    return [
        'filas' => $filas,
        'totales' => [
            'clientes' => count($filas),
            'pedidos' => array_sum(array_column($filas, 'pedidos')),
            'unidades' => array_sum(array_column($filas, 'unidades')),
            'total' => array_sum(array_column($filas, 'total')),
        ],
    ];
}