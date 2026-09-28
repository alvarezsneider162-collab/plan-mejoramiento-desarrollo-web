<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Modelo de pedido
   Archivo: app/modelos/PedidoModelo.php
   Actividad de recuperacion, dia 13, punto 3

   El alta de un pedido con su detalle, y lo que de verdad es el punto de este
   archivo: que se guarde entero o no se guarde nada.

   ---------------------------------------------------------------------------
   UN PEDIDO SON TRES COSAS ESCRITAS EN TRES TABLAS
   ---------------------------------------------------------------------------
       INSERT en pedidos           la cabecera
       INSERT en detalle_pedidos   una fila por producto
       UPDATE en productos         el stock de cada producto que se lleva

   Con AUTO_INCREMENT y sin nada mas, esas tres escrituras son independientes:
   si la segunda falla porque el producto ya no tiene existencias, la primera
   ya esta guardada y queda un pedido con cero lineas, que es un pedido que
   nadie pidio. Y si el proceso se corta entre la segunda y la tercera, el
   detalle esta guardado y el stock no se ha descontado, que es peor: el salon
   cree que tiene existencias que ya estan en la calle.

   ---------------------------------------------------------------------------
   LA TRANSACCION, Y LAS CUATRO COSAS QUE HACE
   ---------------------------------------------------------------------------
   beginTransaction() abre la transaccion, y dentro:

     1. SELECT ... FOR UPDATE   sobre cada producto del pedido, en orden de id
     2. UPDATE productos        con la resta, que ademas lleva la condicion
     3. INSERT detalle_pedidos  con el precio YA COPIADO del producto
     4. UPDATE pedidos          con el total ya calculado

   Y al final, commit(). Si algo falla en medio, rollBack() y no queda rastro:
   ni cabecera, ni detalle, ni stock. Esa es la garantia que pide el punto.

   ---------------------------------------------------------------------------
   POR QUE EL SELECT ... FOR UPDATE
   ---------------------------------------------------------------------------
   Porque comprobar el stock y luego restarlo, sin bloque, tiene una ventana
   entre medias. Si dos personas hacen el mismo pedido a la vez, las dos pueden
   leer "quedan 2" antes de que ninguna haya escrito:

       persona A lee 2      persona B lee 2
       A resta: 2 -> 0      B resta: 0 -> -2      (si no hubiera mas condiciones)

   El FOR UPDATE le dice a MySQL: "estas filas estan mias hasta que cierre la
   transaccion". La segunda persona que llegue a ese SELECT se queda esperando,
   y cuando A cierre y la lea otra vez, ya vera 0 y la rechazara con un mensaje
   de "no hay existencias", que es la verdad.

   Y por que se leen en ORDEN DE ID ASC y no en el orden que pidio el
   formulario: dos transacciones que bloquean las mismas filas en distinto
   orden se pueden quedar esperando la una a la otra, y eso se llama bloqueo
   deadlock. MySQL lo detecta y mata una de las dos, pero mejor no crear el
   problema: se leen siempre en el mismo orden.

   ---------------------------------------------------------------------------
   POR QUE LA RESTA LLEVA LA CONDICION DENTO
   ---------------------------------------------------------------------------
   La resta no se fia del SELECT anterior. Es un UPDATE con la condicion puesta:

       UPDATE productos
       SET existencias = existencias - :cantidad
       WHERE id = :id AND existencias >= :cantidad

   Es la misma comprobacion otra vez, en la misma sentencia que escribe, de
   modo que no hay hueco entre "comprobar" y "restar". Y se mira lo que cambio
   la sentencia con rowCount(): si no cambio ninguna fila, no habia stock
   suficiente, y se aborta la transaccion entera.

   El stock nunca queda negativo, y no por el CHECK de la tabla —que en MySQL
   8.0.16 y superior se avisa pero no siempre se hace cumplir— sino porque
   aqui la condicion esta en el WHERE.
   =========================================================================== */

/**
 * El error de negocio de un pedido: no es una caida de MySQL, es que el pedido
 * no se puede guardar tal como viene.
 *
 * Se distingue de un PDOException porque la pagina trata los dos de forma
 * distinta: este se enseña a la persona con un aviso normal, y el otro es un
 * aviso de "algo se rompio" con el detalle tecnico en un comentario.
 */
final class PedidoRechazado extends RuntimeException
{
}

/**
 * Los productos que se pueden meter en un pedido.
 *
 * Solo los activos y solo los que tienen existencias. Un producto desactivado
 * no aparece: no se puede arrendar lo que se dio de baja, y si se pudiera, el
 * descuento de stock de un producto apagado estaria escondiendo la baja de ayer.
 *
 * @return array<int, array<string, mixed>>
 */
function productosParaPedido(PDO $pdo): array
{
    return $pdo->query(
        'SELECT p.id, p.codigo, p.nombre, p.existencias, p.valor_alquiler,
                c.nombre AS categoria
         FROM productos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         WHERE p.activo = 1 AND p.existencias > 0
         ORDER BY p.nombre'
    )->fetchAll();
}

/**
 * Las columnas del listado de pedidos por las que se puede ordenar, con la
 * columna real al lado.
 *
 * Es la misma lista blanca que usan ProductoModelo y ClienteModelo, y por el
 * mismo motivo: el nombre de la columna va pegado en el ORDER BY, asi que si
 * llegara de la URL sin comprobar, un ?orden=<script> seria SQL injection.
 *
 * Las dos ultimas son los alias de las dos subconsultas del SELECT (lineas y
 * piezas). MySQL deja ordenar por un alias de columna, asi que no hay que
 * repetir las subconsultas en el ORDER BY.
 */
const COLUMNAS_ORDEN_PEDIDO = [
    'fecha'   => 'pe.fecha',
    'cliente' => 'c.nombre',
    'estado'  => 'pe.estado',
    'total'   => 'pe.total',
    'lineas'  => 'lineas',
    'piezas'  => 'piezas',
];

/**
 * Traduce el nombre de columna de la URL a la columna real, o null si no existe.
 */
function resolverOrdenPedido(string $columna): ?string
{
    return COLUMNAS_ORDEN_PEDIDO[$columna] ?? null;
}

/**
 * Traduce la dirección de la URL a 'asc' o 'desc'. Cualquier otra cosa es desc,
 * porque el listado por defecto va del pedido más reciente al más antiguo.
 */
function resolverDireccionPedido(string $direccion): string
{
    return strtolower($direccion) === 'asc' ? 'asc' : 'desc';
}

/**
 * Los pedidos con el nombre del cliente, con búsqueda, orden y paginación.
 *
 * La búsqueda mira el cliente, su documento y el estado, que son las tres cosas
 * por las que alguien busca un pedido. No mira el total: nadie busca un pedido
 * "¿de 50000?". Y no mira las lineas de detalle a proposito, porque el texto
 * de cada linea esta en otra tabla y buscarla exigiria un JOIN mas por cada
 * fila.
 *
 * El id va de segundo criterio en el ORDER BY para que la paginación no baile:
 * sin el, dos pedidos del mismo dia y del mismo total pueden aparecer en dos
 * paginas distintas, y al pasar de pagina falta uno y se repite otro.
 *
 * @return array<int, array<string, mixed>>
 */
function listarPedidos(
    PDO $pdo,
    string $buscar = '',
    string $columnaOrden = 'fecha',
    string $direccion = 'desc',
    int $limite = 10,
    int $desplazamiento = 0
): array {
    $sql = 'SELECT pe.id, pe.fecha, pe.estado, pe.total,
                   c.nombre AS cliente, c.documento,
                   (SELECT COUNT(*) FROM detalle_pedidos d WHERE d.pedido_id = pe.id) AS lineas,
                   (SELECT COALESCE(SUM(d.cantidad), 0) FROM detalle_pedidos d WHERE d.pedido_id = pe.id) AS piezas
            FROM pedidos pe
            INNER JOIN clientes c ON c.id = pe.cliente_id';

    $datos = [];

    if ($buscar !== '') {
        $patron = '%' . $buscar . '%';
        $sql .= ' WHERE c.nombre LIKE :por_cliente
                    OR c.documento LIKE :por_documento
                    OR pe.estado LIKE :por_estado
                    OR pe.id = :por_id';
        $datos[':por_cliente']   = $patron;
        $datos[':por_documento'] = $patron;
        $datos[':por_estado']    = $patron;
        $datos[':por_id']        = (int) preg_replace('/\D/', '', $buscar) ?: 0;
    }

    $columnaSql  = resolverOrdenPedido($columnaOrden) ?? COLUMNAS_ORDEN_PEDIDO['fecha'];
    $direccionSql = resolverDireccionPedido($direccion);

    /* La columna de segundo criterio va en el mismo sentido que la principal, y
       no siempre asc como en los otros dos modelos: si se esta ordenando por
       total de mayor a menor, desempatar por fecha de mas antigua a mas
       reciente seria justo lo contrario de lo que espera quien esta mirando. */
    $sql .= ' ORDER BY ' . $columnaSql . ' ' . $direccionSql
          . ', pe.id ' . $direccionSql . ' LIMIT :limite OFFSET :desplazamiento';

    $st = $pdo->prepare($sql);

    foreach ($datos as $marcador => $valor) {
        $st->bindValue($marcador, $valor, $marcador === ':por_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $st->bindValue(':limite', $limite, PDO::PARAM_INT);
    $st->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll();
}

/**
 * Cuantos pedidos hay en total, con el mismo filtro de búsqueda.
 */
function contarPedidos(PDO $pdo, string $buscar = ''): int
{
    if ($buscar === '') {
        return (int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
    }

    $st = $pdo->prepare(
        'SELECT COUNT(*)
         FROM pedidos pe
         INNER JOIN clientes c ON c.id = pe.cliente_id
         WHERE c.nombre LIKE :por_cliente
            OR c.documento LIKE :por_documento
            OR pe.estado LIKE :por_estado
            OR pe.id = :por_id'
    );

    $patron = '%' . $buscar . '%';
    $st->bindValue(':por_cliente', $patron, PDO::PARAM_STR);
    $st->bindValue(':por_documento', $patron, PDO::PARAM_STR);
    $st->bindValue(':por_estado', $patron, PDO::PARAM_STR);
    $st->bindValue(':por_id', (int) preg_replace('/\D/', '', $buscar) ?: 0, PDO::PARAM_INT);
    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Una cabecera de pedido con su cliente.
 *
 * @return array<string, mixed>|null
 */
function obtenerPedido(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT pe.id, pe.fecha, pe.estado, pe.total, pe.cliente_id,
                c.nombre AS cliente, c.documento, c.telefono, c.email
         FROM pedidos pe
         INNER JOIN clientes c ON c.id = pe.cliente_id
         WHERE pe.id = :id'
    );

    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    $fila = $st->fetch();

    return $fila === false ? null : $fila;
}

/**
 * El detalle de un pedido.
 *
 * El producto se trae con LEFT JOIN y no con INNER JOIN, y esa es la razon de
 * que este JOIN sea de la izquierda.
 *
 * Un INNER JOIN solo devuelve las filas cuyo producto exista. Como el borrado de
 * productos es LOGICO, un producto desactivado sigue existiendo y un INNER
 * JOIN tambien lo traeria... pero en cuanto se pase el dia 13 a un borrado de
 * verdad, o se cargue una base vieja sin esa fila, un INNER JOIN haria que el
 * detalle de un pedido antiguo apareciera con una linea menos, sin avisar. Con
 * LEFT JOIN la linea sale siempre, y si el producto no estuviera, el nombre
 * saldria vacio en vez de desaparecer la linea entera.
 *
 * Este es el punto 4 visto desde el otro lado: el listado de productos
 * esconde los desactivados, el detalle de los pedidos NO los esconde, porque
 * un pedido que se hizo ayer existio aunque el producto se apague hoy.
 *
 * @return array<int, array<string, mixed>>
 */
function listarDetalle(PDO $pdo, int $pedidoId): array
{
    $st = $pdo->prepare(
        'SELECT d.id, d.cantidad, d.precio_unitario,
                d.cantidad * d.precio_unitario AS subtotal,
                p.id AS producto_id, p.nombre AS producto, p.codigo,
                p.activo, p.existencias
         FROM detalle_pedidos d
         LEFT JOIN productos p ON p.id = d.producto_id
         WHERE d.pedido_id = :pedido_id
         ORDER BY d.id'
    );

    $st->bindValue(':pedido_id', $pedidoId, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll();
}

/**
 * Guarda un pedido con su detalle y descuenta el stock. Todo o nada.
 *
 * @param array<int, array{producto_id: int, cantidad: int}> $lineas
 *
 * @return int El id del pedido guardado.
 *
 * @throws PedidoRechazado Si el pedido no se puede guardar por sus datos.
 */
function crearPedidoConDetalle(PDO $pdo, int $clienteId, array $lineas): int
{
    if ($clienteId <= 0) {
        throw new PedidoRechazado('No se dijo a quien es el pedido.');
    }

    if ($lineas === []) {
        throw new PedidoRechazado('El pedido tiene que llevar al menos un producto.');
    }

    /* ------------------------------------------------------------------------
       PASO 1: las lineas se juntan antes de tocar la base
       ------------------------------------------------------------------------
       Si el formulario trae dos veces el mismo producto, detalle_pedidos tiene
       un indice UNIQUE (pedido_id, producto_id) y la segunda fila seria
       rechazada con el error 1062. La respuesta no es quitar el indice, que
       esta a proposito, sino juntar aqui las unidades: un pedido de dos
       bolas de billar es una linea de dos, no dos lineas de una.

       Y lo mismo con las cantidad en cero o negativas: se limpian aqui, con
       un aviso, en vez de llegar a la base. */
    $unidades = [];

    foreach ($lineas as $linea) {
        $productoId = (int) ($linea['producto_id'] ?? 0);
        $cantidad   = (int) ($linea['cantidad'] ?? 0);

        if ($productoId <= 0 || $cantidad <= 0) {
            continue;
        }

        $unidades[$productoId] = ($unidades[$productoId] ?? 0) + $cantidad;
    }

    if ($unidades === []) {
        throw new PedidoRechazado('El pedido tiene que llevar al menos un producto con cantidad mayor que cero.');
    }

    /* ksort() ordena las claves del arreglo por su valor, y aqui el valor es
       la id del producto: por eso las filas se leen siempre en el mismo orden
       y no se puede producir un bloqueo deadlock. */
    ksort($unidades);

    /* ------------------------------------------------------------------------
       PASO 2: la transaccion
       ------------------------------------------------------------------------ */
    $pdo->beginTransaction();

    try {
        /* --- 2.1 Se leen y se bloquean los productos --------------------- */
        $precios = [];

        foreach ($unidades as $productoId => $cantidad) {
            $st = $pdo->prepare(
                'SELECT id, nombre, existencias, valor_alquiler, activo
                 FROM productos
                 WHERE id = :id
                 FOR UPDATE'
            );

            $st->bindValue(':id', $productoId, PDO::PARAM_INT);
            $st->execute();

            $producto = $st->fetch();

            if ($producto === false) {
                throw new PedidoRechazado('El producto ' . $productoId . ' no existe en el inventario.');
            }

            if ((int) $producto['activo'] !== 1) {
                throw new PedidoRechazado(
                    '«' . $producto['nombre'] . '» está desactivado y no se puede pedir.'
                );
            }

            if ((int) $producto['existencias'] < $cantidad) {
                throw new PedidoRechazado(
                    '«' . $producto['nombre'] . '» solo tiene ' . (int) $producto['existencias']
                    . ' unidad(es) y el pedido pide ' . $cantidad . '.'
                );
            }

            /* El precio se LEE aqui, con la fila bloqueada, y se copia al
               detalle despues. Copiarlo al momento de guardar el detalle, sin
               bloque, permitiria que alguien cambiara el precio entre la
               lectura y el INSERT, y el pedido guardaria un precio que no era
               el de cuando se pidio. */
            $precios[$productoId] = (float) $producto['valor_alquiler'];
        }

        /* --- 2.2 La cabecera ------------------------------------------- */
        /* El total se deja en 0.00 y se corrige al final, cuando ya se sabe lo
           que suma el detalle. Ponerlo aqui obligaria a recorrer las lineas
           dos veces: una para calcularlo y otra para guardarlas. */
        /* El INSERT va con comillas dobles y no con simples por una cosa que
           sale aqui mismo: el valor 'Pendiente' lleva comillas simples dentro
           de la cadena, y en una cadena de comillas simples de PHP el unico
           comodin para poner una comilla simple es la barra invertida, porque
           dos comillas seguidas no escapan nada y PHP da error de sintaxis. */
        $st = $pdo->prepare(
            "INSERT INTO pedidos (cliente_id, fecha, estado, total)
             VALUES (:cliente_id, CURDATE(), 'Pendiente', 0.00)"
        );

        $st->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
        $st->execute();

        $pedidoId = (int) $pdo->lastInsertId();

        /* --- 2.3 El detalle y el stock ---------------------------------- */
        $total = 0.0;

        foreach ($unidades as $productoId => $cantidad) {
            $st = $pdo->prepare(
                'UPDATE productos
                 SET existencias = existencias - :cantidad
                 WHERE id = :id
                   AND existencias >= :cantidad_falta'
            );

            $st->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $st->bindValue(':id', $productoId, PDO::PARAM_INT);
            $st->bindValue(':cantidad_falta', $cantidad, PDO::PARAM_INT);
            $st->execute();

            /* rowCount() es el numero de filas que CAMBIO la sentencia. Si la
               condicion no se cumplio, no cambio ninguna: no habia stock. Se
               aborta todo y la transaccion se deshace. */
            if ($st->rowCount() !== 1) {
                throw new PedidoRechazado(
                    'No quedaban ' . $cantidad . ' unidad(es) del producto ' . $productoId
                    . ' en el momento de guardar. No se guardó nada.'
                );
            }

            $precio = $precios[$productoId];

            $st = $pdo->prepare(
                'INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario)
                 VALUES (:pedido_id, :producto_id, :cantidad, :precio_unitario)'
            );

            $st->bindValue(':pedido_id', $pedidoId, PDO::PARAM_INT);
            $st->bindValue(':producto_id', $productoId, PDO::PARAM_INT);
            $st->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $st->bindValue(':precio_unitario', number_format($precio, 2, '.', ''), PDO::PARAM_STR);

            $st->execute();

            $total += $precio * $cantidad;
        }

        /* --- 2.4 El total, ya con las lineas dentro --------------------- */
        $st = $pdo->prepare('UPDATE pedidos SET total = :total WHERE id = :id');
        $st->bindValue(':total', number_format($total, 2, '.', ''), PDO::PARAM_STR);
        $st->bindValue(':id', $pedidoId, PDO::PARAM_INT);
        $st->execute();

        $pdo->commit();

        return $pedidoId;
    } catch (Throwable $e) {
        /* El rollback va en el catch y no despues: si el fallo fue del propio
           commit, o de algo que ya habia deshecho la transaccion, llamar a
           rollBack() sin una transaccion abierta lanza otro error y esconde
           el primero, que es el que dice que paso. Con inTransaction() se
           deshace solo lo que siga abierto, y si no hay nada abierto no se
           toca nada. */
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Cambia el estado de un pedido.
 *
 * No lo pide el punto 3, pero un pedido que se crea siempre en Pendiente y no
 * se puede cambiar de estado se queda a medio camino: el salon no tiene forma
 * de decir que ya salio. Es un UPDATE de una columna, con lista blanca de los
 * tres valores del ENUM, por el mismo motivo que el orden del listado.
 */
function cambiarEstadoPedido(PDO $pdo, int $id, string $estado): bool
{
    if (!in_array($estado, ['Pendiente', 'Confirmado', 'Entregado'], true)) {
        return false;
    }

    $st = $pdo->prepare('UPDATE pedidos SET estado = :estado WHERE id = :id');
    $st->bindValue(':estado', $estado, PDO::PARAM_STR);
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    return $st->rowCount() > 0;
}
