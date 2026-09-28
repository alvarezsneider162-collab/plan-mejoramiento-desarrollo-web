<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Modelo del tablero
   Archivo: app/modelos/TableroModelo.php
   Actividad de recuperacion, dia 12, punto 3

   Aqui viven las consultas que pintan el tablero de control. Todas son sueltas
   y reciben el PDO como primer argumento, el mismo reparto que
   ProductoModelo.php y UsuarioModelo.php: el SQL en el modelo, el HTML en la
   pagina, y ninguna consulta escrita dentro de un <div>.

   ---------------------------------------------------------------------------
   LAS CUATRO TARJETAS Y DE QUE TABLA SALE CADA UNA
   ---------------------------------------------------------------------------
     Mesas ocupadas           SELECT COUNT(*) sobre mesas WHERE estado = ...
     Mesas libres             SELECT COUNT(*) sobre mesas WHERE estado = ...
     Cobro acumulado del dia  SELECT SUM(total) sobre pedidos de HOY
     Implementos por devolver  SUM(cantidad) de detalle_pedidos de pedidos
                              que siguen Pendientes

   Antes esas cuatro tarjetas de dashboard.html traian los numeros escritos a
   mano: "5 de 8", "3", "$ 84.000", "4". Eran siempre los mismos, en el
   navegador de todo el mundo, y no cambiaban ni cuando cambiaba la base. Por
   eso el punto 3 pide datos reales: ahora cada numero sale de una consulta y
   cambia cuando cambia la informacion.

   ---------------------------------------------------------------------------
   LAS DOS PRIMERAS NECESITAN UNA TABLA QUE NO EXISTIA
   ---------------------------------------------------------------------------
   La base del dia 9 tiene categorias, proveedores, productos, clientes,
   pedidos y detalle_pedidos, y la del dia 10 anade usuarios e intentos_acceso.
   En ninguna de las ocho hay una forma de saber cuantas mesas tiene el salon
   ni cuantas estan ocupadas, que es justamente lo que las dos primeras
   tarjetas preguntan.

   Por eso este punto incluye sql/mesas.sql. No es inventar informacion: es
   guardar en la base lo que antes estaba escrito en el HTML.

   Y como ese archivo es aparte, hay una posibilidad real de que este tablero se
   abra sin la tabla cargada. Por eso existe existeTabla() al principio de todo
   en la pagina: si la tabla no esta, las dos tarjetas de mesas salen con un
   guion y se avisa de que falta sql/mesas.sql, en vez de dejar cuatro errores
   de MySQL en mitad de la pantalla.

   ---------------------------------------------------------------------------
   POR QUE "COBRO DEL DIA" USA LA FECHA DE PHP Y NO LA DE MySQL
   ---------------------------------------------------------------------------
   La consulta recibe la fecha de HOY como dato, y no la pide con CURDATE(),
   aunque las dos cosas darian el mismo resultado casi siempre. El motivo es el
   reloj:

     - la fecha la calcula date('Y-m-d') en PHP, con la zona que puso
       date_default_timezone_set('America/Bogota') en app/config/conexion.php.
     - CURDATE() la calcula el servidor de MySQL, con la suya.

   XAMPP recien instalado trae Europe/Berlin en php.ini, que va siete horas
   Adelante. Entre las 19:00 y las 24:00 en Colombia, PHP ya dice que es manana
   y MySQL todavia dice que es el mismo dia: la tarjeta diria cero cuando ya se
   facturo. Pasando la fecha como dato, los dos relojes dejan de mezclarse.

   ---------------------------------------------------------------------------
   POR QUE "IMPLEMENTOS POR DEVOLVER" SUMA LAS UNIDADES Y NO LAS FILAS
   ---------------------------------------------------------------------------
   Porque detalle_pedidos tiene una fila por producto, no por unidad. Un pedido
   con doce bolas de billar es una fila, no doce. Si se contaran filas
   sale el numero de tipos de producto distintos pendientes, que no es el
   numero de piezas que hay que devolver. Con SUM(cantidad) sale lo que la
   persona tiene que ir buscando al almacen.

   Y solo de los pedidos en estado 'Pendiente': un pedido Confirmado ya esta en
   camino y uno Entregado se devolvio. Los que se devuelven son los que ni
   siquiera han salido.
   =========================================================================== */

/**
 * Dice si una tabla existe en la base de datos en la que se esta conectado.
 *
 * Se pregunta a information_schema, que es donde MySQL guarda el catalogo, y
 * no a SHOW TABLES: SHOW TABLES no admite marcadores, asi que habria que
 * pegar el nombre de la tabla dentro de la consulta, que es justo lo que este
 * proyecto no hace en ningun sitio.
 *
 * TABLE_SCHEMA = DATABASE() compara con la base de la conexion abierta, que es
 * la de app/config/credenciales.php. Por eso el nombre va como dato y no como
 * texto en la consulta: si fuera texto, alguien podria escribir un nombre de
 * tabla con un UNION y sacar el catalogo entero.
 */
function existeTabla(PDO $pdo, string $tabla): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :tabla'
    );

    $st->bindValue(':tabla', $tabla, PDO::PARAM_STR);
    $st->execute();

    return (int) $st->fetchColumn() > 0;
}

/**
 * Cuenta cuantas mesas hay en un estado.
 *
 * El estado va como dato, no pegado en el SQL. Ademas de ser lo unico que hace
 * este proyecto, hay una razon tecnica: los estados son un ENUM de tres valores
 * y una comparacion contra un ENUM usa el indice idx_mesas_estado, mientras que
 * una funcion como CONCAT(estado, '') haria que MySQL leyera la tabla entera.
 */
function contarMesasPorEstado(PDO $pdo, string $estado): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM mesas WHERE estado = :estado');

    $st->bindValue(':estado', $estado, PDO::PARAM_STR);
    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Cuenta las mesas todas, para el "5 de 8" de la primera tarjeta.
 *
 * Es un COUNT aparte y no un count() sobre la lista de mesas, y por la misma
 * razon que contarUsuarios() de UsuarioModelo.php: la lista la trae otra
 * funcion, y si los dos numeros se calcularan de la misma forma en dos sitios
 * distintos, un dia alguien cambia una y se olvida de la otra.
 */
function contarMesas(PDO $pdo): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM mesas');
    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Suma lo facturado en un dia.
 *
 * La fecha llega como dato, y con eso el reloj es el de PHP. Se explica arriba
 * por que no se usa CURDATE().
 *
 * COALESCE y no un IFNULL: hacen lo mismo, pero COALESCE es el que va con
 * este motor y el que se lee igual en cualquier consulta. Sin el, un dia sin
 * pedidos devolveria NULL en vez de 0, y al imprimirlo en la tarjeta saldria
 * una cadena vacia donde deberia decir "0".
 */
function cobroDelDia(PDO $pdo, string $fecha): float
{
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE fecha = :fecha'
    );

    $st->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $st->execute();

    return (float) $st->fetchColumn();
}

/**
 * Cuenta las piezas que hay que devolver de los pedidos que siguen pendientes.
 *
 * El JOIN con pedidos es necesario: detalle_pedidos no sabe si su pedido esta
 * pendiente o entregado, y sin el JOIN se sumarian tambien las piezas de los
 * pedidos que ya se devolvieron.
 */
function contarImplementosPorDevolver(PDO $pdo): int
{
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(d.cantidad), 0)
         FROM detalle_pedidos d
         INNER JOIN pedidos p ON p.id = d.pedido_id
         WHERE p.estado = 'Pendiente'"
    );

    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Devuelve las mesas del salon con su estado y su jugador.
 *
 * Todas, y no solo las ocupadas: la tabla del tablero muestra el salon
 * completo, que es como lo trabaja la persona que entra a abrir. El COUNT de
 * las tarjetas es aparte, y por eso no se cuenta sobre esta lista.
 *
 * El tiempo jugado NO se pide. Se calcula en la pagina al pintar, restando
 * ocupada_desde a la hora actual: si se guardara en la base, habria que
 * actualizar la fila cada segundo, y el numero guardado siempre estaria
 * atrasado.
 */
function listarMesas(PDO $pdo): array
{
    $st = $pdo->prepare(
        'SELECT id, numero, estado, jugador, precio_hora, ocupada_desde
         FROM mesas
         ORDER BY numero'
    );

    $st->execute();

    return $st->fetchAll();
}

/**
 * Mesas ocupadas que llevan mas de una hora, que son las que hay que cobrar.
 *
 * El corte se pasa como dato en vez de escribir DATE_SUB(NOW(), INTERVAL 1
 * HOUR) por dos razones: el marcador es lo que hace que este proyecto no pegue
 * nunca un valor dentro del SQL, y el corte se calcula con el reloj de PHP,
 * que es el mismo que usa la tabla de arriba y que el guardian de la sesion.
 */
function mesasQueSuperanLaHora(PDO $pdo, string $corte): array
{
    $st = $pdo->prepare(
        "SELECT numero, jugador, ocupada_desde
         FROM mesas
         WHERE estado = 'Ocupada'
           AND ocupada_desde IS NOT NULL
           AND ocupada_desde <= :corte
         ORDER BY ocupada_desde"
    );

    $st->bindValue(':corte', $corte, PDO::PARAM_STR);
    $st->execute();

    return $st->fetchAll();
}

/**
 * Productos del inventario que se han quedado sin existencias.
 *
 * Es la alerta que mas se usa: un producto con existencias 0 no se puede
 * arrendar, y el alta del formulario de productos.php no avisa de nada cuando
 * eso pasa. El limite va como dato y con PARAM_INT porque con los prepares
 * nativos un LIMIT pegado en el texto llega a MySQL como texto, y al comparar
 * un LIMIT de texto con un numero la comparacion se resuelve comparando
 * cadenas. Es lo mismo que explica el PDO::ATTR_EMULATE_PREPARES en false de
 * app/config/conexion.php.
 *
 * @return array<int, array<string, mixed>>
 */
function productosSinExistencias(PDO $pdo, int $limite = 5): array
{
    $st = $pdo->prepare(
        'SELECT codigo, nombre, categoria_id
         FROM productos
         WHERE existencias = 0
         ORDER BY nombre
         LIMIT :limite'
    );

    $st->bindValue(':limite', $limite, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll();
}

/**
 * Mesas reservadas para mas adelante, que salen como aviso de informacion.
 *
 * No es una alerta de problema: es la lista de lo que viene, para que quien
 * esta en la caja sepa que mesa se tiene que preparar.
 *
 * @return array<int, array<string, mixed>>
 */
function listarMesasReservadas(PDO $pdo): array
{
    $st = $pdo->prepare(
        "SELECT numero, jugador FROM mesas WHERE estado = 'Reservada' ORDER BY numero"
    );

    $st->execute();

    return $st->fetchAll();
}
