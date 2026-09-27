<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Modelo de producto
   Archivo: app/modelos/ProductoModelo.php
   Actividad de recuperacion, dia 9, punto 3 y punto 4

   Aqui vive la consulta que lee el inventario. El enunciado da este ejemplo y
   esta funcion lo sigue de cerca; lo unico que se anade al final es
   contarProductos(), que products.php usa para el rotulo de resultados.
   =========================================================================== */

/**
 * Busca productos por nombre, categoria o codigo.
 *
 * El texto se manda con prepare() y marcadores, jamas pegado dentro del SQL.
 * Los comodines % van en el VALUE que se envia, no en la consulta: si el % fuera
 * parte del SQL, el usuario podria poner sus propios comodines y traerse toda
 * la tabla.
 *
 * POR QUE HAY TRES MARCADORES Y NO UNO SOLO
 *
 * El enunciado escribe WHERE p.nombre LIKE :texto, y con un solo marcador el
 * ejemplo funciona. Aqui se busca tambien por categoria y por codigo, asi que
 * la primera version de esta funcion repetia :texto tres veces:
 *
 *     WHERE p.nombre LIKE :texto OR c.nombre LIKE :texto OR p.codigo LIKE :texto
 *
 * Eso revienta con el error
 *
 *     SQLSTATE[HY093]: Invalid parameter number
 *
 * porque conexion.php pone PDO::ATTR_EMULATE_PREPARES en false, y con esa
 * opcion MySQL no recibe marcadores con nombre: recibe signos de pregunta, y
 * cada signo de pregunta se llena con un bindValue. Al repetir el nombre, solo
 * se llenaba el primer signo y los otros dos quedaban sin valor.
 *
 * La unica forma de repetir un mismo texto con prepares nativos es poner un
 * marcador distinto en cada comparacion y enlazar el mismo valor a los tres,
 * que es lo que hace esta funcion.
 *
 * @return array<int, array<string, mixed>>
 */
function buscarProductos(PDO $pdo, string $texto, int $limite = 20): array
{
    $sql = "SELECT p.id, p.codigo, p.nombre, p.valor_alquiler, p.existencias, p.condicion,
                   c.nombre AS categoria
            FROM productos p
            INNER JOIN categorias c ON c.id = p.categoria_id
            WHERE p.nombre LIKE :por_nombre
               OR c.nombre LIKE :por_categoria
               OR p.codigo LIKE :por_codigo
            ORDER BY p.nombre
            LIMIT :limite";

    $st = $pdo->prepare($sql);

    /* El comodin va pegado al VALOR, nunca a la consulta. Esta linea junta el
       % con el texto del usuario, pero es un dato, no es SQL: lo que sale de
       aqui viaja como un unico parametro por bindValue, y la consulta de
       arriba ya esta escrita sin ningun signo de pregunta que alguien pueda
       romper. Si el % fuera parte del texto de la consulta, el usuario podria
       escribir %% y traerse la tabla entera.

       El mismo valor se enlaza a los tres marcadores porque con prepares
       nativos un marcador no se puede repetir, como se explica arriba. */
    $patronBusqueda = '%' . $texto . '%';
    $st->bindValue(':por_nombre',    $patronBusqueda, PDO::PARAM_STR);
    $st->bindValue(':por_categoria', $patronBusqueda, PDO::PARAM_STR);
    $st->bindValue(':por_codigo',    $patronBusqueda, PDO::PARAM_STR);
    $st->bindValue(':limite', $limite, PDO::PARAM_INT);

    $st->execute();

    return $st->fetchAll();
}

/**
 * Cuenta cuantos productos hay en total, sin filtro.
 *
 * El punto 5 lo usa para comprobar, despues del intento de inyeccion, que la
 * tabla sigue entera: si un DROP hubiera pasado, esto devolveria cero.
 */
function contarProductos(PDO $pdo): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM productos');
    $st->execute();

    return (int) $st->fetchColumn();
}
