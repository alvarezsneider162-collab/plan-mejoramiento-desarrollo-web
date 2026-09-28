<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Modelo de cliente
   Archivo: app/modelos/ClienteModelo.php
   Actividad de recuperacion, dia 13, punto 2

   El CRUD de clientes. Lo unico que tiene de particular es que hay dos
   unicidades que mirar antes de guardar, y por que las dos estan en el sitio
   que estan.

   ---------------------------------------------------------------------------
   POR QUE CORREO Y DOCUMENTO SON UNICOS
   ---------------------------------------------------------------------------
   El punto 2 pide las dos cosas: "validacion de correo y documento unico".
   Son dos reglas distintas y no se puede comprobar con una.

   El documento es el identificador de una persona o de una empresa ante la
   Dian, asi que dos clientes con el mismo documento son el mismo cliente
   escrito dos veces. El correo es distinto: una empresa puede tener a cuatro
   personas de contacto con el mismo correo general, pero en este salon el
   correo se usa para mandar la confirmacion del pedido a una sola direccion,
   de modo que dos clientes con el mismo correo harian que una respuesta
   llegase a los dos.

   Y en el mismo sitio: un indice UNIQUE de MySQL es una garantia de que la
   base no va a dejar pasar el duplicado aunque la aplicacion tenga un fallo.
   Las dos reglas estan en el codigo (que puede tener errores) y en el indice
   (que no).

   ---------------------------------------------------------------------------
   EL ORDEN DE LAS COMPROBACIONES
   ---------------------------------------------------------------------------
   Primero se mira si el correo o el documento ya estan usados y por quien, y
   solo si estan libres se guarda. Al reves, el INSERT fallaria con el error 1062
   "Duplicate entry", que es un error de base de datos, y el mensaje que necesita
   la persona es "ese documento ya lo tiene tal cliente".

   Cuando se EDITA hay que excluir al propio cliente de la comprobacion del
   correo, por la misma razon que en productos: si no, se rechazarian todos los
   cambios porque el correo ya existe... en el mismo cliente. Por eso las dos
   funciones de unicidad reciben un id a ignorar, que es null en el alta.

   ---------------------------------------------------------------------------
   EL BORRADO, Y POR QUE NO ES BORRADO LOGICO
   ---------------------------------------------------------------------------
   El punto 4 pide borrado logico para los PRODUCTOS, y no para los clientes.
   Aqui el borrado es de verdad, y la base lo impide cuando hace falta:
   clientes esta con ON DELETE RESTRICT desde pedidos, de modo que borrar un
   cliente que tiene pedidos no llega ni a intentarlo; lo dice la base con el
   error 1451 y la pagina lo cuenta como un caso previsto, no como una falla.

   La razon de que en productos haga falta el borrado logico y aqui no, es que
   un producto aparece en detalle_pedidos, que es historico y no se puede
   reescribir, mientras que un cliente que no tiene pedidos no ha dejado nada
   atras. Un cliente con pedidos no se borra: se queda.
   =========================================================================== */

/**
 * Las columnas del listado por las que se puede ordenar, con la columna real al
 * lado. Es la misma lista blanca que usa ProductoModelo, por el mismo motivo:
 * el nombre de la columna va pegado en el ORDER BY, asi que si el nombre llega
 * de la URL sin comprobar, un ?orden=<script> seria SQL injection.
 */
const COLUMNAS_ORDEN_CLIENTE = [
    'nombre'     => 'c.nombre',
    'documento'  => 'c.documento',
    'correo'     => 'c.email',
    'pedidos'    => 'pedidos',
];

/**
 * Traduce el nombre de columna de la URL a la columna real, o null si no existe.
 */
function resolverOrdenCliente(string $columna): ?string
{
    return COLUMNAS_ORDEN_CLIENTE[$columna] ?? null;
}

/**
 * Traduce la dirección de la URL a 'asc' o 'desc'. Cualquier otra cosa es asc.
 */
function resolverDireccionCliente(string $direccion): string
{
    return strtolower($direccion) === 'desc' ? 'desc' : 'asc';
}

/**
 * Busca clientes por nombre, documento o correo.
 *
 * @return array<int, array<string, mixed>>
 */
function listarClientes(
    PDO $pdo,
    string $buscar = '',
    string $columnaOrden = 'nombre',
    string $direccion = 'asc',
    int $limite = 10,
    int $desplazamiento = 0
): array {
    $sql = 'SELECT c.id, c.nombre, c.documento, c.telefono, c.email,
                   (SELECT COUNT(*) FROM pedidos p WHERE p.cliente_id = c.id) AS pedidos
            FROM clientes c';

    $datos = [];

    if ($buscar !== '') {
        $patron = '%' . $buscar . '%';
        $sql .= ' WHERE c.nombre LIKE :por_nombre
                    OR c.documento LIKE :por_documento
                    OR c.email LIKE :por_correo';
        $datos[':por_nombre']    = $patron;
        $datos[':por_documento'] = $patron;
        $datos[':por_correo']    = $patron;
    }

    /* La columna sale de la lista blanca y la direccion de su propia funcion, y
       las dos se comprueban ANTES de escribir la consulta, no despues. El id
       va de segundo criterio para que la paginacion no baile: sin el, dos
       clientes con el mismo nombre pueden aparecer en dos paginas distintas. */
    $columnaSql  = resolverOrdenCliente($columnaOrden) ?? COLUMNAS_ORDEN_CLIENTE['nombre'];
    $direccionSql = resolverDireccionCliente($direccion);

    $sql .= ' ORDER BY ' . $columnaSql . ' ' . $direccionSql . ', c.id ASC LIMIT :limite OFFSET :desplazamiento';

    $st = $pdo->prepare($sql);

    foreach ($datos as $marcador => $valor) {
        $st->bindValue($marcador, $valor, PDO::PARAM_STR);
    }

    $st->bindValue(':limite', $limite, PDO::PARAM_INT);
    $st->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);

    $st->execute();

    return $st->fetchAll();
}

/**
 * Cuantos clientes hay con el mismo filtro de busqueda.
 */
function contarClientes(PDO $pdo, string $buscar = ''): int
{
    if ($buscar === '') {
        return (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    }

    $st = $pdo->prepare(
        'SELECT COUNT(*)
         FROM clientes c
         WHERE c.nombre LIKE :por_nombre
            OR c.documento LIKE :por_documento
            OR c.email LIKE :por_correo'
    );

    $patron = '%' . $buscar . '%';
    $st->bindValue(':por_nombre', $patron, PDO::PARAM_STR);
    $st->bindValue(':por_documento', $patron, PDO::PARAM_STR);
    $st->bindValue(':por_correo', $patron, PDO::PARAM_STR);
    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Un cliente por su id, o null.
 *
 * @return array<string, mixed>|null
 */
function obtenerCliente(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT id, nombre, documento, telefono, email
         FROM clientes
         WHERE id = :id'
    );

    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    $fila = $st->fetch();

    return $fila === false ? null : $fila;
}

/**
 * Guarda un cliente nuevo y devuelve su id.
 *
 * @param array<string, mixed> $datos
 */
function crearCliente(PDO $pdo, array $datos): int
{
    $st = $pdo->prepare(
        'INSERT INTO clientes (nombre, documento, telefono, email)
         VALUES (:nombre, :documento, :telefono, :correo)'
    );

    /* El correo va como PARAM_NULL de verdad cuando no hay correo, y no como
       cadena vacia, y por eso se normaliza antes con textoONulo(): en una
       columna que no es NOT NULL un "" se podria guardar tal cual, y entonces el
       indice UNIQUE trataria "" y "" como el mismo correo, que no es lo que
       significa "no tengo correo". Ademas, con dos clientes sin correo, al
       segundo le saltaria el error de duplicado sin que haya ningun duplicado. */
    $telefono = textoONulo($datos['telefono'] ?? null);
    $correo   = textoONulo($datos['correo'] ?? null);

    $st->bindValue(':nombre',     (string) $datos['nombre'],     PDO::PARAM_STR);
    $st->bindValue(':documento',  (string) $datos['documento'],  PDO::PARAM_STR);
    $st->bindValue(':telefono',   $telefono,                    $telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->bindValue(':correo',     $correo,                      $correo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $st->execute();

    return (int) $pdo->lastInsertId();
}

/**
 * Guarda los cambios de un cliente que ya existe.
 *
 * @param array<string, mixed> $datos
 */
function actualizarCliente(PDO $pdo, int $id, array $datos): void
{
    $st = $pdo->prepare(
        'UPDATE clientes
         SET nombre    = :nombre,
             documento = :documento,
             telefono  = :telefono,
             email     = :correo
         WHERE id = :id'
    );

    $telefono = textoONulo($datos['telefono'] ?? null);
    $correo   = textoONulo($datos['correo'] ?? null);

    $st->bindValue(':nombre',    (string) $datos['nombre'],    PDO::PARAM_STR);
    $st->bindValue(':documento', (string) $datos['documento'], PDO::PARAM_STR);
    $st->bindValue(':telefono',  $telefono,                   $telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->bindValue(':correo',    $correo,                     $correo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->bindValue(':id', $id, PDO::PARAM_INT);

    $st->execute();
}

/**
 * Un texto que es opcional: si no hay nada escrito, NULL de verdad.
 *
 * Vive en el modelo y no en la pagina porque el que decide si "" es o no un
 * valor valido es el que sabe que la columna es nullable y que encima tiene un
 * indice UNIQUE: si la normalizacion viviese en la pagina, bastaria con que otro
 * archivo escribiera "" para volver a tener dos clientes "iguales" sin correo.
 *
 * Se aplica a telefono y a correo, no a nombre ni a documento, porque esos dos
 * son obligatorios y la pagina ya los ha rechazado antes de llegar aqui.
 */
function textoONulo(mixed $valor): ?string
{
    if ($valor === null) {
        return null;
    }

    $texto = trim((string) $valor);

    return $texto === '' ? null : $texto;
}

/**
 * Dice si ese documento ya lo tiene otro cliente.
 *
 * @param int|null $ignorarId El cliente que se esta editando, si lo hay.
 */
function existeDocumentoCliente(PDO $pdo, string $documento, ?int $ignorarId = null): bool
{
    return existeValorUnico($pdo, 'documento', $documento, $ignorarId);
}

/**
 * Dice si ese correo ya lo tiene otro cliente.
 *
 * @param int|null $ignorarId El cliente que se esta editando, si lo hay.
 */
function existeCorreoCliente(PDO $pdo, string $correo, ?int $ignorarId = null): bool
{
    /* Un correo vacio no es un correo: si se llamara con "" dira que existe,
       porque el indice UNIQUE encuentra una fila con correo NULL en la
       comparacion, y dejaria impedir dar de alta un cliente sin correo. */
    if ($correo === '') {
        return false;
    }

    return existeValorUnico($pdo, 'email', $correo, $ignorarId);
}

/**
 * La comprobacion de unicidad, en una sola funcion.
 *
 * La columna va de la lista blanca de dos valores, no de la URL: aqui no hay
 * URL, el nombre lo elige el programador. Aun asi se comprueba con un match,
 * porque una columna mal escrita en una cadena seria un error de MySQL en
 * cuanto alguien la escribiera mal, y es mas barato que se note aqui.
 *
 * Y otra vez dos marcadores distintos con el mismo valor, por lo del
 * "Invalid parameter number" con prepares nativos.
 *
 * @param int|null $ignorarId
 */
function existeValorUnico(PDO $pdo, string $columna, string $valor, ?int $ignorarId = null): bool
{
    $columnaSql = match ($columna) {
        'documento' => 'documento',
        'email'     => 'email',
        default     => throw new InvalidArgumentException('Columna no permitida: ' . $columna),
    };

    $st = $pdo->prepare(
        'SELECT COUNT(*)
         FROM clientes
         WHERE ' . $columnaSql . ' = :valor
           AND (:es_alta = 1 OR id <> :ignorar_id)'
    );

    $st->bindValue(':valor', $valor, PDO::PARAM_STR);
    $st->bindValue(':es_alta', $ignorarId === null ? 1 : 0, PDO::PARAM_INT);
    $st->bindValue(':ignorar_id', $ignorarId, PDO::PARAM_INT);
    $st->execute();

    return (int) $st->fetchColumn() > 0;
}

/**
 * El nombre del cliente que ya usa ese documento o ese correo.
 *
 * Se usa para el mensaje de error: "ese documento ya lo tiene X" es mil veces
 * mas util que "documento duplicado", porque dice quien se tiene que corregir.
 *
 * @return string|null
 */
function nombreDuplicadoCliente(PDO $pdo, string $columna, string $valor, ?int $ignorarId = null): ?string
{
    $columnaSql = match ($columna) {
        'documento' => 'documento',
        'email'     => 'email',
        default     => null,
    };

    if ($columnaSql === null) {
        return null;
    }

    $st = $pdo->prepare(
        'SELECT nombre
         FROM clientes
         WHERE ' . $columnaSql . ' = :valor
           AND (:es_alta = 1 OR id <> :ignorar_id)
         LIMIT 1'
    );

    $st->bindValue(':valor', $valor, PDO::PARAM_STR);
    $st->bindValue(':es_alta', $ignorarId === null ? 1 : 0, PDO::PARAM_INT);
    $st->bindValue(':ignorar_id', $ignorarId, PDO::PARAM_INT);
    $st->execute();

    $nombre = $st->fetchColumn();

    return $nombre === false ? null : (string) $nombre;
}

/**
 * Quantos pedidos tiene un cliente, que es lo que decide si se puede borrar.
 */
function pedidosDeCliente(PDO $pdo, int $id): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE cliente_id = :id');
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Borra un cliente de verdad.
 *
 * Devuelve false si la base lo impide, que es lo que pasa cuando el cliente
 * tiene pedidos: la clave foranea fk_pedidos_cliente es ON DELETE RESTRICT, y
 * con la base ya escrita la excepcion salta sola, sin que este codigo la pida.
 *
 * Y por eso el try/catch no es defensivo por costumbre: sin el, la pagina que
 * llama a esta funcion sin comprobar antes se queda con una pantalla en blanco
 * y un error 500 si alguien la llama sin mirar. Con el, la pagina recibe un
 * false, pregunta cuantos pedidos tenia con pedidosDeCliente() y escribe el
 * mensaje de por que no se pudo.
 *
 * No se quita la clave foranea para arreglarlo. Un ON DELETE CASCADE borraria
 * tambien los pedidos de esa persona y todo su detalle, que es justo lo
 * contrario de lo razonable: el historico de lo vendido no se toca nunca.
 *
 * @return bool true si se borro, false si no existia o si tiene pedidos.
 */
function eliminarCliente(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare('DELETE FROM clientes WHERE id = :id');
    $st->bindValue(':id', $id, PDO::PARAM_INT);

    try {
        $st->execute();
    } catch (PDOException $e) {
        /* 23000 es el codigo de MySQL para "no se puede, por una restriccion".
           Se mira solo ese y el resto se deja subir: si la base esta caida, es
           un problema del servidor y hay que enterarse, no mostrar un mensaje
           bonito que no es verdad. */
        if ($e->getCode() === '23000') {
            return false;
        }

        throw $e;
    }

    return $st->rowCount() > 0;
}
