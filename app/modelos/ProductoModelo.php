<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Modelo de producto
   Archivo: app/modelos/ProductoModelo.php
   Actividad de recuperacion, dia 9 puntos 3 y 4, y dia 13 puntos 1, 3 y 4

   Aqui vive TODO lo que se consulta y se escribe de la tabla productos: el
   listado con buscador, orden y paginacion, el alta, la edicion y el borrado
   logico. El SQL esta aqui y el HTML esta en la pagina, como en los demas
   modelos del proyecto.

   ---------------------------------------------------------------------------
   LO QUE SE CONVIRTIO EN UN ARREGLO
   ---------------------------------------------------------------------------
   El dia 9 habia dos funciones: buscarProductos() y contarProductos(), con el
   texto del buscador pegado en la consulta y sin paginacion ni orden. El dia 13
   el listado hace tres cosas mas, y las tres llegan de la URL:

       ?buscar=bola      el texto a buscar
       ?orden=nombre     por que columna se ordena
       ?dir=desc         en que sentido
       ?pagina=2         que pagina de diez

   Todo eso llega desde el navegador, o sea que llega de cualquiera. Por eso el
   orden NO se escribe en la consulta: se busca en COLS.

   ---------------------------------------------------------------------------
   LA LISTA BLANCA DEL ORDEN, Y POR QUE NO PUEDE SER OTRA COSA
   ---------------------------------------------------------------------------
   El orden de una columna se pondria en la consulta asi:

       ORDER BY :columna

   que suena a dato, pero no lo es: el nombre de una columna no es un valor, es
   parte de la forma de la consulta. En ademas, con prepares nativos MySQL no
   acepta un marcador ahi. La forma de hacerlo bien seria una cadena:

       $columna = $_GET['orden'];          // "nombre; DROP TABLE productos --"
       $sql = "... ORDER BY $columna";      // pega el texto entero

   que es una inyeccion de SQL con la palabra ORDER BY de por medio, y que
   ademas permite escribir cualquier cosa en el ORDER BY, no solo una columna.

   Por eso COLS es una lista blanca: un arreglo que dice que clave de la URL
   corresponde a que columna real, y resolverOrden() devuelve null para
   cualquier otra cosa. Si el visitor manda ?orden=precio, que no esta en la
   lista, no se ejecuta nada raro: se cae al orden por defecto, que es el
   nombre. La pagina avisa de que el orden no es valido, pero no se rompe.

   Y las dos direcciones tambien son de la lista blanca: solo 'asc' o 'desc'.
   El valor va al lado de la columna, que si se puede construir porque ya se ha
   comprobado que es una de las dos.

   ---------------------------------------------------------------------------
   POR QUE LA PAGINACION CUENTA CON SU PROPIA CONSULTA
   ---------------------------------------------------------------------------
   El LIMIT dice cuantas filas se devuelven, no cuantas hay. Para pintar el
   "pagina 3 de 5" hace falta el total, y sale de un COUNT aparte con el mismo
   filtro. No se hace COUNT(*) sobre el resultado de la pagina: eso contaria
   diez, siempre diez.

   El desplazamiento va como dato con PARAM_INT, por lo mismo que el LIMIT de
   productosSinExistencias() en TableroModelo.php: con prepares nativos un
   numero pegado en el texto llega a MySQL como texto, y al comparar un LIMIT
   de texto con un entero la comparacion se resuelve comparando cadenas de
   texto, no numeros.
   =========================================================================== */

/**
 * Las columnas por las que se puede ordenar, y a que columna real corresponde
 * cada una de la URL.
 *
 * La clave es lo que viaja en ?orden=. El valor es lo que va al ORDER BY.
 *
 * @var array<string, string>
 */
const COLUMNAS_ORDEN_PRODUCTO = [
    'codigo'      => 'p.codigo',
    'nombre'      => 'p.nombre',
    'categoria'   => 'c.nombre',
    'condicion'   => 'p.condicion',
    'existencias' => 'p.existencias',
    'valor'       => 'p.valor_alquiler',
    'estado'      => 'p.activo',
];

/**
 * Traduce la columna de la URL a la columna real, o null si no es valida.
 *
 * Que devuelva null en vez de inventarse un nombre es lo importante: quien
 * llama tiene que poder distinguir "no era una columna" de "la columna es esta".
 */
function resolverOrdenProducto(string $columna): ?string
{
    return COLUMNAS_ORDEN_PRODUCTO[$columna] ?? null;
}

/**
 * Traduce la direccion de la URL a ASC o DESC, con lista blanca tambien.
 */
function resolverDireccionProducto(string $direccion): string
{
    return strtolower($direccion) === 'desc' ? 'DESC' : 'ASC';
}

/**
 * Los tres estados que puede pedir el listado, en una constante para que el
 * nombre no este escrito suelto por ahi.
 */
const ESTADOS_LISTADO_PRODUCTO = ['activos', 'inactivos', 'todos'];

/**
 * Traduce lo que llega por la URL a uno de los tres estados del listado.
 *
 * Igual que resolverOrdenProducto(): lo que no esta en la lista no es un error
 * que pare la pagina, es alguien escribiendo en la barra de direcciones, y lo
 * razonable es mostrar el listado de siempre.
 */
function resolverEstadoProducto(string $estado): string
{
    return in_array($estado, ESTADOS_LISTADO_PRODUCTO, true) ? $estado : 'activos';
}

/**
 * Devuelve la parte WHERE compartida por el listado y por el contador.
 *
 * Se escribe una sola vez y la usan las dos consultas por dos razones que se
 * han visto en este proyecto: si el filtro del buscador y el filtro de estado
 * estuvieran escritos dos veces, un dia se cambia uno y se olvida el otro, y el
 * listado dice "3 de 25" con 25 filas delante. Y devuelve los marcadores con el
 * nombre que cada consulta tiene que enlazar, porque con prepares nativos un
 * marcador no se puede repetir.
 *
 * @param string $estado 'activos', 'inactivos' o 'todos'.
 *
 * @return array{0: string, 1: array<string, string>}
 */
function filtroProductos(string $buscar, string $estado = 'activos'): array
{
    $where  = ['1 = 1'];
    $datos  = [];

    /* El filtro de estado va en el WHERE y no en el ON de la categoria a
       proposito: el buscador tambien busca por categoria, y con el filtro en el
       ON un producto desactivado tampoco saldria al buscar por su categoria. */
    if ($estado === 'activos') {
        $where[] = 'p.activo = 1';
    } elseif ($estado === 'inactivos') {
        $where[] = 'p.activo = 0';
    }

    if ($buscar !== '') {
        /* El comodin va pegado al VALOR, nunca a la consulta. Si el % fuera
           parte del SQL, el usuario podria escribir sus propios comodines y
           traerse la tabla entera, y podria ademas romper la consulta con un
           parentesis. */
        $patron = '%' . $buscar . '%';

        $where[] = '(p.nombre LIKE :por_nombre
                 OR p.codigo LIKE :por_codigo
                 OR c.nombre LIKE :por_categoria)';

        $datos[':por_nombre']    = $patron;
        $datos[':por_codigo']    = $patron;
        $datos[':por_categoria'] = $patron;
    }

    /* La categoria se pide SIEMPRE, activo este o no, porque el buscador
       busca tambien por categoria. */
    return [implode(' AND ', $where), $datos];
}

/**
 * El listado: busqueda, orden, paginacion y filtro de desactivados.
 *
 * @return array<int, array<string, mixed>>
 */
function listarProductos(
    PDO $pdo,
    string $buscar = '',
    string $columnaOrden = 'nombre',
    string $direccion = 'asc',
    int $limite = 10,
    int $desplazamiento = 0,
    string $estado = 'activos'
): array {
    [$donde, $datos] = filtroProductos($buscar, $estado);

    /* La columna y la direccion son las dos cosas que se comprueban aqui, y se
       comprueban antes de escribir la consulta, no despues. */
    $columnaSql  = resolverOrdenProducto($columnaOrden) ?? COLUMNAS_ORDEN_PRODUCTO['nombre'];
    $direccionSql = resolverDireccionProducto($direccion);

    /* El orden tiene un segundo criterio, el id. Sin el, las filas con el
       mismo valor de la columna salen en el orden que devuelva MySQL, que no
       esta garantizado, y al paginar un producto puede aparecer en dos
       paginas o en ninguna. */
    $sql = 'SELECT p.id, p.codigo, p.nombre, p.valor_alquiler, p.existencias,
                   p.condicion, p.activo, p.fecha_ingreso, p.descripcion,
                   p.desactivado_en,
                   c.nombre AS categoria,
                   pr.nombre AS proveedor
            FROM productos p
            INNER JOIN categorias c ON c.id = p.categoria_id
            LEFT  JOIN proveedores pr ON pr.id = p.proveedor_id
            WHERE ' . $donde . '
            ORDER BY ' . $columnaSql . ' ' . $direccionSql . ', p.id ASC
            LIMIT :limite OFFSET :desplazamiento';

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
 * Cuantos productos hay en total con el mismo filtro, para el "pagina 2 de 3".
 *
 * @param string $estado 'activos', 'inactivos' o 'todos'.
 */
function contarProductos(PDO $pdo, string $buscar = '', string $estado = 'activos'): int
{
    [$donde, $datos] = filtroProductos($buscar, $estado);

    $sql = 'SELECT COUNT(*)
            FROM productos p
            INNER JOIN categorias c ON c.id = p.categoria_id
            WHERE ' . $donde;

    $st = $pdo->prepare($sql);

    foreach ($datos as $marcador => $valor) {
        $st->bindValue($marcador, $valor, PDO::PARAM_STR);
    }

    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Un producto por su id, o null si no existe.
 *
 * @return array<string, mixed>|null
 */
function obtenerProducto(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT p.id, p.codigo, p.nombre, p.categoria_id, p.condicion, p.existencias,
                p.valor_alquiler, p.proveedor_id, p.descripcion, p.fecha_ingreso,
                p.activo, p.desactivado_en, p.desactivado_por,
                c.nombre AS categoria, pr.nombre AS proveedor
         FROM productos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         LEFT  JOIN proveedores pr ON pr.id = p.proveedor_id
         WHERE p.id = :id'
    );

    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    $fila = $st->fetch();

    return $fila === false ? null : $fila;
}

/**
 * Guarda un producto nuevo y devuelve su id.
 *
 * El INSERT es de una sentencia, con el orden de las columnas escrito en la
 * misma lista que el de los VALUES. Esa es la unica forma de no equivocarse: si
 * el orden de los VALUES se escribiera aparte, un dia se anade una columna y las
 * dos listas dejan de estar de acuerdo.
 *
 * @param array<string, mixed> $datos
 */
function crearProducto(PDO $pdo, array $datos): int
{
    $st = $pdo->prepare(
        'INSERT INTO productos
           (codigo, nombre, categoria_id, condicion, existencias,
            valor_alquiler, proveedor_id, descripcion, fecha_ingreso, activo)
         VALUES
           (:codigo, :nombre, :categoria_id, :condicion, :existencias,
            :valor_alquiler, :proveedor_id, :descripcion, :fecha_ingreso, 1)'
    );

    $st->bindValue(':codigo',         (string) $datos['codigo'],         PDO::PARAM_STR);
    $st->bindValue(':nombre',         (string) $datos['nombre'],         PDO::PARAM_STR);
    $st->bindValue(':categoria_id',   (int) $datos['categoria_id'],      PDO::PARAM_INT);
    $st->bindValue(':condicion',      (string) $datos['condicion'],      PDO::PARAM_STR);
    $st->bindValue(':existencias',    (int) $datos['existencias'],       PDO::PARAM_INT);
    $st->bindValue(':valor_alquiler', (float) $datos['valor_alquiler'],  PDO::PARAM_STR);
    $st->bindValue(':proveedor_id',   $datos['proveedor_id'],           PDO::PARAM_INT);
    $st->bindValue(':descripcion',    (string) $datos['descripcion'],    PDO::PARAM_STR);
    $st->bindValue(':fecha_ingreso',  (string) $datos['fecha_ingreso'],  PDO::PARAM_STR);

    $st->execute();

    return (int) $pdo->lastInsertId();
}

/**
 * Guarda los cambios de un producto que ya existe.
 *
 * Es un UPDATE con el id en el WHERE, no un DELETE y un INSERT: el id tiene que
 * seguir siendo el mismo, porque detalle_pedidos lo tiene guardado y los pedidos
 * de antes tienen que seguir apuntando a este producto.
 *
 * @param array<string, mixed> $datos
 */
function actualizarProducto(PDO $pdo, int $id, array $datos): void
{
    $st = $pdo->prepare(
        'UPDATE productos
         SET codigo         = :codigo,
             nombre         = :nombre,
             categoria_id   = :categoria_id,
             condicion      = :condicion,
             existencias    = :existencias,
             valor_alquiler = :valor_alquiler,
             proveedor_id   = :proveedor_id,
             descripcion    = :descripcion,
             fecha_ingreso  = :fecha_ingreso
         WHERE id = :id'
    );

    $st->bindValue(':codigo',         (string) $datos['codigo'],         PDO::PARAM_STR);
    $st->bindValue(':nombre',         (string) $datos['nombre'],         PDO::PARAM_STR);
    $st->bindValue(':categoria_id',   (int) $datos['categoria_id'],      PDO::PARAM_INT);
    $st->bindValue(':condicion',      (string) $datos['condicion'],      PDO::PARAM_STR);
    $st->bindValue(':existencias',    (int) $datos['existencias'],       PDO::PARAM_INT);
    $st->bindValue(':valor_alquiler', (float) $datos['valor_alquiler'],  PDO::PARAM_STR);
    $st->bindValue(':proveedor_id',   $datos['proveedor_id'],           PDO::PARAM_INT);
    $st->bindValue(':descripcion',    (string) $datos['descripcion'],    PDO::PARAM_STR);
    $st->bindValue(':fecha_ingreso',  (string) $datos['fecha_ingreso'],  PDO::PARAM_STR);
    $st->bindValue(':id', $id, PDO::PARAM_INT);

    $st->execute();
}

/**
 * Activa o desactiva un producto. Esta es la funcion del punto 4.
 *
 * "Desactivar" NO es borrar. El producto sigue en la tabla, con la misma fila y
 * el mismo id, y por eso los pedidos que lo llevaron siguen cuadran: el precio
 * esta copiado en detalle_pedidos y el nombre se saca del producto por id, y
 * esa fila sigue estando.
 *
 * Cuando se desactiva se guarda quien lo hizo y cuando, y cuando se reactiva
 * esas dos columnas se vuelven a vaciar: si se dejara la fecha antigua, un
 * producto reactivado aparecia con una fecha de desactivacion de la que nadie
 * se acuerda.
 *
 * @param int|null $usuarioId El usuario de la sesion; null deja la columna vacia.
 */
function cambiarEstadoProducto(PDO $pdo, int $id, bool $activo, ?int $usuarioId): void
{
    if ($activo) {
        $sql = 'UPDATE productos
                SET activo = 1, desactivado_en = NULL, desactivado_por = NULL
                WHERE id = :id';
    } else {
        $sql = 'UPDATE productos
                SET activo = 0,
                    desactivado_en  = NOW(),
                    desactivado_por = :usuario
                WHERE id = :id';
    }

    $st = $pdo->prepare($sql);

    $st->bindValue(':id', $id, PDO::PARAM_INT);

    if (!$activo) {
        $st->bindValue(':usuario', $usuarioId, PDO::PARAM_INT);
    }

    $st->execute();
}

/**
 * Dice si ese codigo de producto ya existe.
 *
 * Se pregunta antes de guardar y no se fia uno solo del indice UNIQUE: MySQL
 * avisaria con el error 1062, que es un error de base de datos, y el mensaje
 * que necesita la persona es "ese codigo ya lo usa otro producto". Ademas, al
 * editar hay que excluir el propio producto, o se estarian rechazando todos
 * los cambios porque el codigo ya existe... en el mismo producto.
 *
 * @param int|null $ignorarId El producto que se esta editando, si lo hay.
 */
function existeCodigoProducto(PDO $pdo, string $codigo, ?int $ignorarId = null): bool
{
    /* El "y el producto que se esta editando no cuenta" se escribe con DOS
       marcadores y no con uno repetido:

           AND (:ignorar_es_null = 1 OR id <> :ignorar_id)

       Porque con PDO::ATTR_EMULATE_PREPARES en false —que es lo que pone
       app/config/conexion.php— MySQL no recibe marcadores con nombre: recibe
       signos de pregunta, y un signo de pregunta no se puede repetir con el
       mismo nombre. La version con ":ignorar" dos veces, que fue la primera,
       daba

           SQLSTATE[HY093]: Invalid parameter number

       que es exactamente el error que ya explica el dia 9 en buscarProductos().
       La leccion es siempre la misma y por eso se repite aqui: con prepares
       nativos, un marcador, un uso. */
    $sql = 'SELECT COUNT(*)
            FROM productos
            WHERE codigo = :codigo
              AND (:ignorar_es_null = 1 OR id <> :ignorar_id)';

    $st = $pdo->prepare($sql);
    $st->bindValue(':codigo', $codigo, PDO::PARAM_STR);
    $st->bindValue(':ignorar_es_null', $ignorarId === null ? 1 : 0, PDO::PARAM_INT);
    $st->bindValue(':ignorar_id', $ignorarId, PDO::PARAM_INT);
    $st->execute();

    return (int) $st->fetchColumn() > 0;
}

/**
 * Las categorias, para el desplegable del formulario.
 *
 * Salen de la tabla y no estan escritas a mano en el HTML: el dia 13 la tabla
 * categorias es la unica fuente, y asi si manana se anade una quinta categoria
 * aparece sola en el formulario. Antes el desplegable tenia cuatro <option>
 * fijos y una categoria nueva en la base no se podia elegir.
 *
 * @return array<int, array<string, mixed>>
 */
function listarCategorias(PDO $pdo): array
{
    return $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
}

/**
 * Los proveedores, para el otro desplegable del formulario.
 *
 * @return array<int, array<string, mixed>>
 */
function listarProveedores(PDO $pdo): array
{
    return $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll();
}

/**
 * Traduce el id de una categoria al nombre de la tabla categorias.
 *
 * El formulario de alta mandaba las cuatro categorias como texto ("implemento",
 * "consumible") y la columna productos.categoria_id guarda un numero. Esta
 * funcion es el puente en los dos sentidos, y por eso vive en el modelo y no en
 * la pagina: los dos formularios, el de alta y el de edicion, la usan igual.
 *
 * @return array<string, int> texto => id
 */
function categoriasPorTexto(PDO $pdo): array
{
    $mapa = [];

    foreach (listarCategorias($pdo) as $fila) {
        /* La clave es el nombre de la categoria en minusculas y sin tildes,
           porque es como viene del formulario. La columna sigue guardandose
           en su sitio: la comparacion es solo para entender lo que el
           navegador mando. */
        $mapa[strtolower(normalizarTexto((string) $fila['nombre']))] = (int) $fila['id'];
    }

    return $mapa;
}

/**
 * Quita tildes y pasa a minusculas, para comparar texto escrito por una persona
 * con texto guardado en la base.
 */
function normalizarTexto(string $texto): string
{
    $texto = strtr($texto, 'áàäâãéèëêíìïîóòöôõúùüûñç', 'aaaaaeeeeiiiiooooouuuunc');
    $texto = strtr($texto, 'ÁÀÄÂÃÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇ', 'AAAAAEEEEIIIIOOOOOUUUUNC');

    return strtolower(trim($texto));
}

/* ===========================================================================
   LAS DOS FUNCIONES DEL DIA 9, QUE SE QUEDAN
   ---------------------------------------------------------------------------
   buscarProductos() y contarProductos() se escribieron el dia 9 y las explica la
   bitacora de ese dia con el detalle del error de "Invalid parameter number" que
   salia al repetir :texto tres veces con prepares nativos. Ese hallazgo sigue
   siendo cierto, asi que las dos funciones no se borran: ahora son un
   alias corto de las nuevas, y el mismo SQL esta escrito una sola vez.
   =========================================================================== */

/**
 * La funcion del dia 9, ahora encima de listarProductos().
 *
 * Antes hacia su propia consulta con su propio filtro; ahora son tres lineas
 * que digan "esto es lo mismo que el listado, con el limite que me digan".
 * Siempre con los activos: este es el buscador que escribe el pedido, y un
 * producto apagado no se puede pedir.
 *
 * @return array<int, array<string, mixed>>
 */
function buscarProductos(PDO $pdo, string $texto, int $limite = 20): array
{
    return listarProductos($pdo, $texto, 'nombre', 'asc', $limite, 0, 'activos');
}
