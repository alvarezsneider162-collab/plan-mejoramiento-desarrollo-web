<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Pedidos: listado, detalle, alta y cambio de estado
   Archivo: pedidos.php
   Actividad de recuperacion, dia 13, punto 3

   Esta pagina hace las cuatro cosas del punto 3, y las hace de una forma que no
   se parece en nada a las otras dos pantallas del mismo dia, asi que conviene
   decir por que antes de nada.

       crear          cuatro lineas de detalle, con cliente y productos
       leer           el listado, con busqueda, orden y paginacion, y el detalle
                      de un pedido con ?ver=<id>
       actualizar     cambiar el estado del pedido
       buscar         ?buscar=<texto>, por cliente, documento, estado o numero

   NO hay borrar, y no es un olvido. Un pedido es un hecho que paso: salieron
   mesas, se cobraron, y el total esta en las facturas. Un producto se desactiva
   porque un producto es una oferta que puede dejar de existir; un pedido no, y
   por eso el modelo no tiene ninguna funcion para borrarlo. Si un pedido esta
   mal, se cambia su estado y se hace otro, que es lo que haria una persona con
   un talon de pedidos en la mano.

   ---------------------------------------------------------------------------
   LAS TRES DECISIONES QUE IMPORTAN DE ESTA PAGINA
   ---------------------------------------------------------------------------

   1. EL PRECIO NO VIENE DEL FORMULARIO.

      El formulario pregunta el producto y cuantas unidades, y nada mas. El
      precio de cada linea lo copia el modelo de productos.valor_alquiler en el
      momento de guardar, dentro de la misma transaccion que descuenta el stock.

      Preguntar tambien el precio en el formulario parece una cortesia, y en
      realidad es un agujero: si el precio lo escribe quien esta delante de la
      pantalla, alguien puede escribir lo que quiera, y el total del pedido lo
      calcularia la pagina con ese numero. La unica forma de que el total sea de
      fiar es que el precio no pase por el navegador. Por eso aqui no hay ni un
      campo de precio, y por eso el modelo devuelve PedidoRechazado si el
      producto no existe en vez de fiarse de lo que le digan.

   2. LAS CUATRO LINEAS SON FIJAS, Y NO HAY JAVASCRIPT.

      El enunciado pide cuatro lineas de detalle, y hay exactamente cuatro
      casillas de producto y cuatro de unidades, siempre visibles. Anadir lineas
      con JavaScript es una mejora, no un requisito, y el css/estilos.css lo
      tiene escrito desde el dia 12: "no hay ninguna linea oculta esperando a
      que un script la rellene".

      Asi el punto 5 se cumple sin esfuerzo: con el JavaScript desactivado la
      pagina hace exactamente lo mismo, porque no hay ningun JavaScript que
     desactivar. En las otras dos pantallas del dia la validacion se escribe
      dos veces, una en el navegador y otra en el servidor; aqui, al no haber
      script, solo hay que escribirla una vez, y no puede haber dos mensajes
      distintos para el mismo error.

   3. LO QUE DICE QUE HAY QUE ESCRIBIR ES EL MODELO, NO ESTA PAGINA.

      El stock se comprueba aqui antes de guardar, para poder responder "solo
      quedan 3" sin perder lo que la persona habia escrito. Pero la
      comprobacion que vale es la de dentro de la transaccion, con el bloqueo
      FOR UPDATE: entre que esta pagina lee las existencias y las escribe, otra
      persona puede haber vendido las mismas mesas. Por eso el mensaje que se
      ve en la pantalla es una cortesia y el que decide es crearPedidoConDetalle(),
      que devuelve el error 409 con la verdad. Si se quitara el analisis de
      existencias de esta pagina, el sistema seguiria siendo correcto: solo
      perderia solo la escolaridad del aviso.
   =========================================================================== */

/* El guardián va en la primera instrucción, antes de nada, porque header() y
   Set-Cookie tienen que salir antes del primer byte de la página. */
require_once __DIR__ . '/app/seguridad/guardia.php';

/* Los mismos dos roles que productos y clientes: quien da de alta inventario y
   clientes tiene que poder cerrar el alquiler. El consultor no, y el 403 lo
   contesta esta pagina, no el menu. */
exigirRol('administrador', 'vendedor');

require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/seguridad/salida.php';
require_once __DIR__ . '/app/seguridad/csrf.php';
require_once __DIR__ . '/app/seguridad/flash.php';
require_once __DIR__ . '/app/modelos/ClienteModelo.php';
require_once __DIR__ . '/app/modelos/ProductoModelo.php';
require_once __DIR__ . '/app/modelos/PedidoModelo.php';

/** Diez pedidos por página, como en las otras dos pantallas. */
const PEDIDOS_POR_PAGINA = 10;

/** Las cuatro lineas de detalle que pide el enunciado. */
const LINEAS_POR_PEDIDO = 4;

/** Los tres estados que el modelo acepta, en el orden en que avanza un alquiler. */
const ESTADOS_PEDIDO = ['Pendiente', 'Confirmado', 'Entregado'];

$pdo = Conexion::obtener();

/* ===========================================================================
   LO QUE LLEGA POR LA URL
   ---------------------------------------------------------------------------
   ver    el id del pedido cuyo detalle se quiere ver
   buscar texto de busqueda
   orden  columna, por la lista blanca del modelo
   dir    asc o desc
   pagina la pagina del listado
   =========================================================================== */
$buscar  = trim((string) ($_GET['buscar'] ?? ''));
$orden   = (string) ($_GET['orden'] ?? 'fecha');
$pagina  = max(1, (int) ($_GET['pagina'] ?? 1));
$verId   = (int) ($_GET['ver'] ?? 0);

/* El listado de pedidos va por defecto del mas reciente al mas antiguo, que es
   como se lee un talon de pedidos, y no como en productos y clientes, donde el
   nombre es lo natural. */
$dirValida = resolverDireccionPedido((string) ($_GET['dir'] ?? 'desc'));

/* Los avisos de la URL van en una variable y no con avisarFlash(), porque
   tomarFlash() ya se ha leido cuando se decide esto, y un mensaje escrito en la
   sesion en este momento se veria en la pagina siguiente. */
$avisoUrl = null;

if (resolverOrdenPedido($orden) === null) {
    $avisoUrl = 'El campo por el que se pedía ordenar no existe. Se muestran los pedidos del más reciente al más antiguo.';
    $orden    = 'fecha';
}

/* ===========================================================================
   EL POST: LAS DOS ACCIONES
   ---------------------------------------------------------------------------
   crear  un pedido con sus lineas
   estado el cambio de estado de uno que ya existe

   Las dos van por POST por lo mismo que en productos y clientes: un enlace que
   cambia datos se puede abrir con el boton derecho en otra pestana, y ahi el GET
   habria hecho el cambio sin que nadie lo pidiera. Y las dos llevan token CSRF,
   porque tener sesion no demuestra que el boton sea de esta pagina.
   =========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null;
    $accion = (string) ($_POST['accion'] ?? '');

    if (!validarCsrf($token)) {
        http_response_code(HTTP_TOKEN_INVALIDO);
        exit('El formulario no vino de esta página. Recargue la página y vuelva a intentarlo.');
    }

    $buscar = trim((string) ($_POST['buscar'] ?? ''));

    $volverA = static function (array $extra = []) use ($buscar): string {
        $params = $buscar === '' ? [] : ['buscar' => $buscar];

        return urlApp('pedidos.php') . (($params + $extra) === [] ? '' : '?' . http_build_query($params + $extra));
    };

    /* ---- Crear el pedido ------------------------------------------------- */
    if ($accion === 'crear') {
        $entrada = leerEntradaPedido($_POST);

        [$clienteId, $lineas, $errores] = validarPedido($pdo, $entrada, productosParaPedido($pdo));

        if ($errores !== []) {
            guardarFlash(
                'error',
                'No se guardó el pedido. Revisa lo que está marcado en rojo.',
                $errores,
                $entrada,
                ['creando' => true]
            );

            redirigir($volverA(['crear' => 1]));
        }

        /* El modelo es el que dice que si o que no. Su excepcion ya trae el
           mensaje escrito para la persona que lo va a leer, asi que no hay que
           traducirla ni construirla aqui: se enseña tal cual. */
        try {
            $nuevoId = crearPedidoConDetalle($pdo, $clienteId, $lineas);
        } catch (PedidoRechazado $e) {
            /* Vuelve el formulario con lo que se habia escrito, y con este
               mensaje como el grande. Los errores por campo ya no sirven: aqui
               el problema no es un campo concreto, son las existencias, que
               pueden haber cambiado entre la lectura y la escritura. */
            guardarFlash('error', $e->getMessage(), [], $entrada, ['creando' => true]);

            redirigir($volverA(['crear' => 1]));
        }

        /* El total lo calcula el modelo, asi que no se puede inventar aqui el
           mensaje de "pedido creado" sin mirarlo. */
        $total = obtenerPedido($pdo, $nuevoId);

        avisarFlash(
            'exito',
            'Se guardó el pedido #' . $nuevoId . ' para «' . $total['cliente'] . '», por $ '
            . number_format((float) $total['total'], 0, ',', '.') . '.'
        );

        redirigir($volverA(['ver' => $nuevoId]));
    }

    /* ---- Cambiar el estado ----------------------------------------------- */
    if ($accion === 'estado') {
        $id     = (int) ($_POST['id'] ?? 0);
        $estado = (string) ($_POST['estado'] ?? '');
        $pedido = $id > 0 ? obtenerPedido($pdo, $id) : null;

        if ($pedido === null) {
            avisarFlash('error', 'Ese pedido no existe.');
        } elseif (!in_array($estado, ESTADOS_PEDIDO, true)) {
            /* Un estado que no esta en la lista no se le pasa al modelo: el
               modelo tambien lo comprueba y devuelve false, pero aqui se puede
               decir cual era el malo, que es mas util que un "no se pudo". */
            avisarFlash('error', '«' . $estado . '» no es un estado de pedido. Los que hay son: ' . implode(', ', ESTADOS_PEDIDO) . '.');
        } elseif ((string) $pedido['estado'] === $estado) {
            avisarFlash('aviso', 'El pedido #' . $id . ' ya estaba en «' . $estado . '». No se cambie nada.');
        } elseif (!cambiarEstadoPedido($pdo, $id, $estado)) {
            avisarFlash('error', 'La base no dejó cambiar el estado del pedido #' . $id . '.');
        } else {
            avisarFlash('exito', 'El pedido #' . $id . ' pasó de «' . $pedido['estado'] . '» a «' . $estado . '».');
        }

        redirigir($volverA($verId > 0 && $verId === $id ? ['ver' => $id] : []));
    }
}

/* ===========================================================================
   EL AVISO DE LA PETICIÓN ANTERIOR
   =========================================================================== */
$flash   = tomarFlash();
$errores = $flash['errores'] ?? [];

$repintar = ($flash['entrada'] ?? []) !== [];

/* ===========================================================================
   LOS DATOS QUE PINTAR
   ---------------------------------------------------------------------------
   Se piden antes del HTML, y todos antes de decidir nada, para que las tres
   zonas de la pagina (el detalle, el listado y el formulario) se pinten con la
   misma informacion del mismo viaje a la base.
   =========================================================================== */
$productos = productosParaPedido($pdo);

/* El desplegable de clientes necesita TODOS los clientes, no una pagina. Se
   piden todos con el total como limite, que es el unico sitio donde el numero
   correcto ya esta calculado y no hay que escribir un 500 a ojo que un dia
   se queda corto. */
$clientes = listarClientes($pdo, '', 'nombre', 'asc', max(1, contarClientes($pdo)), 0);

$total       = contarPedidos($pdo, $buscar);
$paginas     = max(1, (int) ceil($total / PEDIDOS_POR_PAGINA));
$pagina      = min($pagina, $paginas);
$primeraFila = $total === 0 ? 0 : ($pagina - 1) * PEDIDOS_POR_PAGINA + 1;
$ultimaFila  = min($total, $pagina * PEDIDOS_POR_PAGINA);

$pedidos = listarPedidos(
    $pdo,
    $buscar,
    $orden,
    $dirValida,
    PEDIDOS_POR_PAGINA,
    ($pagina - 1) * PEDIDOS_POR_PAGINA
);

$columnasConEnlace = [
    'id'      => 'N.º',
    'fecha'   => 'Fecha',
    'cliente' => 'Cliente',
    'estado'  => 'Estado',
    'lineas'  => 'Líneas',
    'piezas'  => 'Piezas',
    'total'   => 'Total',
];

/* El detalle del pedido que se quiere ver, si se pidio alguno. */
$viendo     = $verId > 0 ? obtenerPedido($pdo, $verId) : null;
$viendoDetalle = $viendo === null ? [] : listarDetalle($pdo, $verId);

/* Si se pidió ver un pedido que no existe, no es un fallo de MySQL: es un enlace
   viejo. Se avisa y se sigue con el listado. */
if ($verId > 0 && $viendo === null) {
    $avisoUrl = $avisoUrl === null
        ? 'El pedido que se quiso ver no existe.'
        : $avisoUrl . ' El pedido que se quiso ver tampoco existe.';
}

/* Un mapa de id => existencias y nombre, para pintar el aviso de las lineas sin
   tener que buscar el producto otra vez por cada fila del formulario. */
$porId = [];

foreach ($productos as $producto) {
    $porId[(int) $producto['id']] = $producto;
}

/* El valor de un campo del formulario, de donde venga. */
$campo = static function (string $ruta, string $porDefecto = '') use ($flash, $repintar): string {
    if (!$repintar) {
        return $porDefecto;
    }

    /* Se recorre la ruta con nombres, porque la entrada de un pedido tiene
       arreglos dentro de arreglos: linea -> 1 -> producto_id. Con la ruta
       'linea.1.cantidad' esta funcion hace lo mismo que productos.php con un
       campo simple, y sin tener dos versiones de la misma idea. */
    $valor = $flash['entrada'];

    foreach (explode('.', $ruta) as $paso) {
        if (!is_array($valor) || !array_key_exists($paso, $valor)) {
            return $porDefecto;
        }

        $valor = $valor[$paso];
    }

    return is_scalar($valor) ? (string) $valor : $porDefecto;
};

/* El error de un campo, o cadena vacía si no tiene. Que devuelva "" y no false
   es lo que permite escribirlo en el HTML sin inventarse una condición. */
$errorDe = static function (string $nombre) use ($errores): string {
    return (string) ($errores[$nombre] ?? '');
};

/* La misma clase .es-invalid que usan productos.php y clientes.php: el borde
   rojo sale de las mismas variables de color en las tres pantallas. */
$claseError = static function (string $nombre) use ($errores): string {
    return isset($errores[$nombre]) && $errores[$nombre] !== '' ? ' es-invalid' : '';
};

/* Los enlaces de orden, de pagina y de detalle se arman en un solo sitio, y
   por eso ninguno se escribe a mano en el HTML. Todos se llevan por delante la
   busqueda, para que ir cambiando de pagina no la borre. */
$enlace = static function (array $cambios) use ($buscar, $orden, $dirValida, $pagina): string {
    $params = [
        'buscar' => $buscar,
        'orden'  => resolverOrdenPedido($orden) === null ? 'fecha' : $orden,
        'dir'    => $dirValida,
        'pagina' => (string) $pagina,
    ];

    foreach ($cambios as $clave => $valor) {
        if ($valor === null || $valor === '') {
            unset($params[$clave]);
            continue;
        }

        $params[$clave] = (string) $valor;
    }

    if (($params['buscar'] ?? '') === '') {
        unset($params['buscar']);
    }

    return urlApp('pedidos.php') . '?' . http_build_query($params);
};

$token = tokenCsrf();

/* ===========================================================================
   LAS TRES FUNCIONES DE ESTA PAGINA QUE NO SON DE PINTAR
   ---------------------------------------------------------------------------
   están aquí y no en el modelo porque no tocan la base de datos: dos leen el
   POST y una decide si lo leído sirve.
   =========================================================================== */

/**
 * Lee el formulario y deja solo las claves que existen, todas como texto.
 *
 * Las cuatro lineas se guardan en un arreglo, con los numeros de linea como
 * claves. El numero de linea va en la clave y no en un campo oculto porque los
 * campos ocultos se pueden mandar a mano, y con las claves el bucle del HTML y
 * el bucle de la validacion miran la misma lista y no se pueden desincronizar.
 *
 * @param array<string, mixed> $post
 * @return array{cliente: string, linea: array<int, array{producto_id: string, cantidad: string}>}
 */
function leerEntradaPedido(array $post): array
{
    $texto = static function (mixed $valor): string {
        return is_string($valor) ? trim($valor) : '';
    };

    $lineas = [];
    $entrada = $_POST['linea'] ?? [];

    for ($n = 1; $n <= LINEAS_POR_PEDIDO; $n++) {
        /* Lo que llega en linea[n] se lee como un arreglo solo si lo es. Si
           alguien manda "linea" con un texto, el is_array lo para aqui y la
           pagina no revienta con un warning a media pagina. */
        $linea = isset($entrada[$n]) && is_array($entrada[$n]) ? $entrada[$n] : [];

        $lineas[$n] = [
            'producto_id' => $texto($linea['producto_id'] ?? ''),
            'cantidad'    => $texto($linea['cantidad'] ?? ''),
        ];
    }

    return [
        'cliente' => $texto($post['cliente'] ?? ''),
        'linea'   => $lineas,
    ];
}

/**
 * Valida la entrada y devuelve el cliente, las lineas limpias y los errores.
 *
 * Se devuelven las lineas ya limpias, y no el arreglo entero, porque lo que
 * necesita el modelo es un arreglo de ['producto_id' => int, 'cantidad' => int]
 * y el modelo no tiene por que saber que el formulario tiene cuatro casillas y
 * que la cuarta a menudo va vacía.
 *
 * El precio NO se valida aquí porque no viene del formulario. Lo único que se
 * mira del producto es que siga en el catalogo de disponibles, y eso es una
 * comprobación de comfort: el modelo vuelve a mirar las existencias dentro de
 * la transaccion, y su respuesta es la que vale.
 *
 * @param array<int, array<string, mixed>> $disponibles Productos que se pueden pedir.
 * @return array{0: int, 1: array<int, array{producto_id: int, cantidad: int}>, 2: array<string, string>}
 */
function validarPedido(PDO $pdo, array $entrada, array $disponibles): array
{
    $errores = [];

    /* Un mapa para no buscar el producto en la lista por cada linea. */
    $porId = [];

    foreach ($disponibles as $producto) {
        $porId[(int) $producto['id']] = $producto;
    }

    /* ---- El cliente ---- */
    $clienteId = (int) ($entrada['cliente'] ?? 0);

    if ($clienteId <= 0) {
        $errores['cliente'] = 'Hay que elegir a quién es el pedido.';
    } elseif (obtenerCliente($pdo, $clienteId) === null) {
        /* Un id que no existe no es un cliente sin nombre, es un id inventado.
           La diferencia importa: uno es un formulario sin elegir, y el otro es
           alguien jugando con la URL. */
        $errores['cliente'] = 'Ese cliente no existe. Vuelve a elegirlo en la lista.';
    }

    /* ---- Las cuatro lineas ---- */
    $lineas = [];

    for ($n = 1; $n <= LINEAS_POR_PEDIDO; $n++) {
        $productoId = (int) ($entrada['linea'][$n]['producto_id'] ?? 0);
        $cantidad   = $entrada['linea'][$n]['cantidad'] ?? '';

        /* Una linea con las dos casillas vacias no es un error: es una linea sin
           usar. De cuatro lineas, las que esten vacias no cuentan, y eso se
           comprueba al final del bucle. */
        if ($productoId === 0 && $cantidad === '') {
            continue;
        }

        if ($productoId === 0) {
            $errores['linea' . $n] = 'Escribiste cuántas unidades pero no elegiste el producto.';
            continue;
        }

        if (!isset($porId[$productoId])) {
            /* O no existe, o esta desactivado, o se quedo sin existencias. Las
               tres cosas se responden igual a proposito: distinguirlas
               ayudaria a quien esta probando, no a quien esta de verdad. */
            $errores['linea' . $n] = 'Ese producto ya no está disponible para pedidos.';
            continue;
        }

        if ($cantidad === '') {
            $errores['linea' . $n] = 'Faltan las unidades de «' . $porId[$productoId]['nombre'] . '».';
            continue;
        }

        /* Solo se miran los digitos y un guion delante. Se acepta el guion para
           poder contestar "al menos una" a un -3, que es un mensaje mas util que
           "sin signos" para quien escribio -3 queriendo decir 3. */
        if (!preg_match('/^-?[0-9]+$/', $cantidad)) {
            $errores['linea' . $n] = 'Las unidades tienen que ser un número entero, sin letras ni signos.';
            continue;
        }

        $cantidad = (int) $cantidad;

        if ($cantidad < 1) {
            $errores['linea' . $n] = 'Las unidades tienen que ser al menos una.';
            continue;
        }

        /* La comprobacion de existencias. El mensaje es el del modelo, para que
           el mismo fallo se lea igual aqui y alli. */
        if ($cantidad > (int) $porId[$productoId]['existencias']) {
            $errores['linea' . $n] = '«' . $porId[$productoId]['nombre'] . '» solo tiene '
                . (int) $porId[$productoId]['existencias']
                . ' unidad(es) y el pedido pide ' . $cantidad . '.';

            continue;
        }

        $lineas[] = ['producto_id' => $productoId, 'cantidad' => $cantidad];
    }

    /* No quedo ninguna linea buena. Si ademas no hay ningun error de linea, es
       que las cuatro estaban vacias, y el aviso tiene que decir eso: si el
       problema es que las cuatro estan en blanco, el mensaje util es "pon
       alguna", no repetir el error de una linea que no esta mal.

       El error se busca con array_filter() y NO con isset() sobre las cuatro
       claves: isset($a, $b, $c, $d) es true solo si las CUATRO existen, asi que
       preguntar con isset() si hay un error de linea daria la respuesta al reves
       y este mensaje pisaria el error de la linea que si estaba mal. */
    $clavesDeLinea = array_filter(
        array_keys($errores),
        static fn (string $clave): bool => str_starts_with($clave, 'linea')
    );

    if ($lineas === [] && $clavesDeLinea === []) {
        $errores['linea1'] = 'Un pedido tiene que llevar al menos un producto con sus unidades.';
    }

    return [$clienteId, $lineas, $errores];
}

$tituloPagina = 'Pedidos';
$itemActual   = 'pedidos';

require __DIR__ . '/app/parciales/cabecera.php';
require __DIR__ . '/app/parciales/menu.php';
?>
<main class="panel__contenido">

  <h1>Pedidos</h1>
  <p class="sub">Un alquiler de mesas, con sus líneas de detalle. El precio lo pone el inventario, no el formulario.</p>

  <?php /* Los mensajes del servidor. Se pintan aqui y no dentro del formulario
          porque valen para las dos acciones: un alta con errores y un cambio de
          estado son el mismo <ul>. La clase de cada uno sale del tipo de flash,
          y avisarFlash() ya la puso. */ ?>
  <?php if ($flash !== null): ?>
    <ul class="lista-alertas">
      <li class="alerta alerta--<?= esc((string) $flash['tipo']) ?>">
        <strong><?= $flash['tipo'] === 'error' ? 'No se pudo hacer:' : ($flash['tipo'] === 'aviso' ? 'Ojo:' : 'Listo:') ?></strong>
        <?= esc((string) $flash['mensaje']) ?>
      </li>
    </ul>
  <?php endif; ?>

  <?php if ($avisoUrl !== null): ?>
    <ul class="lista-alertas">
      <li class="alerta alerta--advertencia"><?= esc($avisoUrl) ?></li>
    </ul>
  <?php endif; ?>

  <?php /* ------------------------------------------------------------------
          EL DETALLE DE UN PEDIDO
          Va antes que el listado y que el formulario, porque es lo que se fue a
          mirar: se entra con ?ver=<id> y lo primero que aparece es ese pedido.
          La cabecera va en una <dl> de dos columnas, que es lo que es una ficha
          de datos, y las lineas en una tabla, que es lo que es una lista de
          cosas con columnas.

          El total va aparte y en grande, con su propia clase, porque es lo
          unico de todo el pedido que se mira de verdad.
          ------------------------------------------------------------------ */ ?>
  <?php if ($viendo !== null): ?>
    <section class="tarjeta" id="pedido-<?= (int) $viendo['id'] ?>">
      <h2>Pedido #<?= (int) $viendo['id'] ?></h2>

      <dl class="pedido-cabecera">
        <dt>Cliente</dt>
        <dd>
          <?= esc((string) $viendo['cliente']) ?>
          <span class="mono">(<?= esc((string) $viendo['documento']) ?>)</span>
        </dd>

        <dt>Fecha</dt>
        <dd><?= esc((string) $viendo['fecha']) ?></dd>

        <dt>Estado</dt>
        <dd><?= esc((string) $viendo['estado']) ?></dd>

        <dt>Teléfono</dt>
        <dd class="mono"><?= esc((string) ($viendo['telefono'] ?? '—')) ?></dd>

        <dt>Correo</dt>
        <dd class="mono"><?= esc((string) ($viendo['email'] ?? '—')) ?></dd>
      </dl>

      <div class="tabla-scroll">
        <table class="tabla-productos">
          <caption>Líneas del pedido #<?= (int) $viendo['id'] ?>.</caption>
          <thead>
            <tr>
              <th scope="col">Producto</th>
              <th scope="col">Unidades</th>
              <th scope="col">Precio</th>
              <th scope="col">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($viendoDetalle === []): ?>
              <tr>
                <td colspan="4" class="sub">Este pedido no tiene líneas. No debería pasar.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($viendoDetalle as $linea): ?>
              <?php /* El producto puede estar desactivado ahora, y se dice. Un
                      producto desactivado sigue estando en el detalle del
                      pedido: el día del alquiler estaba disponible, y reescribir
                      el histórico para que cuadre con hoy sería mentir. */ ?>
              <tr>
                <td>
                  <?= esc((string) $linea['producto']) ?>
                  <?php if ((int) $linea['activo'] !== 1): ?>
                    <span class="sub"> (desactivado ahora)</span>
                  <?php endif; ?>
                </td>
                <td><?= (int) $linea['cantidad'] ?></td>
                <td class="mono">$ <?= number_format((float) $linea['precio_unitario'], 0, ',', '.') ?></td>
                <td class="mono">$ <?= number_format((float) $linea['subtotal'], 0, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="pedido-total">
        <span>Total del pedido</span>
        <span class="pedido-total__numero">$ <?= number_format((float) $viendo['total'], 0, ',', '.') ?></span>
      </p>

      <?php /* ---- Cambiar el estado ---------------------------------------
              Es la unica Actualizacion que admite un pedido, y va por POST con
              el boton al lado. El <select> con los tres estados y un boton
              "Guardar" funciona entero con el teclado y sin JavaScript, que es
              lo que hace falta para que el cambio de estado se pueda demostrar
              en la prueba del punto 5.

              El valor que ya tiene sale marcado, y aun asi se pueden las tres
              opciones: se puede corregir un estado que se puso por error, y
              para eso tiene que haber algo que elegir. */ ?>
      <form class="formulario" method="post" action="pedidos.php" novalidate>
        <input type="hidden" name="csrf" value="<?= esc($token) ?>">
        <input type="hidden" name="accion" value="estado">
        <input type="hidden" name="id" value="<?= (int) $viendo['id'] ?>">
        <?php if ($buscar !== ''): ?>
          <input type="hidden" name="buscar" value="<?= esc($buscar) ?>">
        <?php endif; ?>

        <div class="formulario__acciones">
          <label for="estado">Estado del pedido</label>
          <select class="campo" id="estado" name="estado">
            <?php foreach (ESTADOS_PEDIDO as $estado): ?>
              <option value="<?= esc($estado) ?>"<?= (string) $viendo['estado'] === $estado ? ' selected' : '' ?>>
                <?= esc($estado) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="boton" type="submit">Guardar el estado</button>
          <a class="boton boton--secundario" href="<?= esc($enlace(['ver' => ''])) ?>">Cerrar el detalle</a>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <?php /* ------------------------------------------------------------------
          EL BUSCADOR
          Un formulario con method="get" y sin nada de JavaScript: el buscador
          funciona entero con el teclado. Busca por cliente, por documento, por
          estado y por numero de pedido, que son las cuatro cosas por las que
          alguien busca un pedido.
          ------------------------------------------------------------------ */ ?>
  <form class="buscador" method="get" action="pedidos.php" role="search">
    <div class="buscador__fila">
      <label class="rotulo-demo" for="buscar">Buscar pedido</label>
      <input class="campo" type="search" id="buscar" name="buscar"
             value="<?= esc($buscar) ?>" placeholder="Cliente, documento, estado o n.º">
      <?php /* Se esconde el resto de la URL, para que buscar no borre el
              ordenamiento ni vuelva a la primera pagina. Sin esto, escribir en
              el buscador y buscar devuelve la lista del primer dia. */ ?>
      <input type="hidden" name="orden" value="<?= esc($orden) ?>">
      <input type="hidden" name="dir" value="<?= esc($dirValida) ?>">
      <button class="boton" type="submit">Buscar</button>
      <?php if ($buscar !== ''): ?>
        <a class="enlace" href="<?= esc($enlace(['buscar' => '', 'pagina' => ''])) ?>">Quitar la búsqueda</a>
      <?php endif; ?>
    </div>
    <p class="buscador__conteo">
      <?php if ($total === 0): ?>
        No hay pedidos que cumplan la búsqueda.
      <?php else: ?>
        Mostrando del <?= $primeraFila ?> al <?= $ultimaFila ?> de <?= $total ?>
        <?= $total === 1 ? 'pedido' : 'pedidos' ?>, en <?= $paginas ?>
        <?= $paginas === 1 ? 'página' : 'páginas' ?>.
      <?php endif; ?>
    </p>
  </form>

  <?php /* ------------------------------------------------------------------
          EL LISTADO
          La clase del <table> es .tabla-productos y no .tabla-pedidos, y no es
          una errata: en este proyecto ese nombre es el de la tabla del sistema
          y la usan igual componentes.php, usuarios.php, dashboard.php y
          clientes.php.
          ------------------------------------------------------------------ */ ?>
  <div class="tabla-scroll">
    <table class="tabla-productos" id="tabla-pedidos">
      <caption>Pedidos de la casa. Los títulos de columna son enlaces para ordenar.</caption>
      <thead>
        <tr>
          <?php foreach ($columnasConEnlace as $clave => $titulo): ?>
            <?php /* La flechita va en la columna por la que se ordena ahora, y
                    solo si la columna es la que esta activa. Al pulsar otra
                    columna se invierte el sentido, salvo que ya se estaba
                    ordenando por ella, que entonces se da la vuelta. */ ?>
            <?php $esActiva = $orden === $clave; ?>
            <th scope="col" aria-sort="<?= $esActiva ? ($dirValida === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
              <a href="<?= esc($enlace(['orden' => $clave, 'dir' => $esActiva && $dirValida === 'desc' ? 'asc' : 'desc', 'pagina' => ''])) ?>">
                <?= esc($titulo) ?><?= $esActiva ? ($dirValida === 'asc' ? ' ↑' : ' ↓') : '' ?>
              </a>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($pedidos === []): ?>
          <tr>
            <td colspan="<?= count($columnasConEnlace) ?>" class="sub">
              <?php if ($buscar !== ''): ?>
                Ningún pedido cumple la búsqueda «<?= esc($buscar) ?>».
              <?php else: ?>
                No hay pedidos todavía. Crea el primero con el formulario de abajo.
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($pedidos as $pedido): ?>
          <tr>
            <td class="mono">
              <a href="<?= esc($enlace(['ver' => (string) $pedido['id'], 'pagina' => ''])) ?>">#<?= (int) $pedido['id'] ?></a>
            </td>
            <td class="mono"><?= esc((string) $pedido['fecha']) ?></td>
            <td><?= esc((string) $pedido['cliente']) ?></td>
            <td><?= esc((string) $pedido['estado']) ?></td>
            <td><?= (int) $pedido['lineas'] ?></td>
            <td><?= (int) $pedido['piezas'] ?></td>
            <td class="mono">$ <?= number_format((float) $pedido['total'], 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php /* ------------------------------------------------------------------
          LA PAGINACIÓN
          Solo aparece si hay mas de una pagina. El numero de paginas sale del
          total con el mismo filtro que la tabla (contarPedidos), que es la
          unica forma de que "pagina 2 de 3" sea verdad.
          ------------------------------------------------------------------ */ ?>
  <?php if ($paginas > 1): ?>
    <nav class="paginacion" aria-label="Páginas del listado">
      <?php if ($pagina > 1): ?>
        <a class="paginacion__enlace" rel="prev"
           href="<?= esc($enlace(['pagina' => (string) ($pagina - 1)])) ?>">Anterior</a>
      <?php endif; ?>

      <?php for ($n = 1; $n <= $paginas; $n++): ?>
        <?php if ($n === $pagina): ?>
          <span class="paginacion__enlace paginacion__enlace--actual" aria-current="page"><?= $n ?></span>
        <?php else: ?>
          <a class="paginacion__enlace" href="<?= esc($enlace(['pagina' => (string) $n])) ?>"><?= $n ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($pagina < $paginas): ?>
        <a class="paginacion__enlace" rel="next"
           href="<?= esc($enlace(['pagina' => (string) ($pagina + 1)])) ?>">Siguiente</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

  <?php /* ------------------------------------------------------------------
          EL FORMULARIO DE ALTA
          Cuatro lineas fijas, un desplegable de cliente y ningun campo de
          precio. El desplegable de productos sale de productosParaPedido(), que
          ya viene filtrado: solo los activos y solo los que tienen unidades, y
          con las unidades a la vista para no prometer lo que no hay.

          El precio de cada linea se enseña al lado, en el <option> del
          producto, pero NO se envía: el select solo manda el id, y el modelo
          copia el precio de productos.valor_alquiler al guardar. Por eso el
          option lleva el precio como texto y no como value.

          novalidate: es la linea que hace que el punto 5 se pueda demostrar con
          el JavaScript desactivado. Sin ella, el navegador no dejaria enviar el
          formulario vacio y no habria mensaje del servidor que enseñar. Por eso
          esta pagina NO lleva un archivo de JavaScript propio.
          ------------------------------------------------------------------ */ ?>
  <section class="tarjeta">
    <h2>Nuevo pedido</h2>

    <form class="formulario" id="form-pedido" method="post" action="pedidos.php" novalidate>
      <input type="hidden" name="csrf" value="<?= esc($token) ?>">
      <input type="hidden" name="accion" value="crear">
      <?php if ($buscar !== ''): ?>
        <input type="hidden" name="buscar" value="<?= esc($buscar) ?>">
      <?php endif; ?>

      <div class="campo-grupo">
        <label for="cliente">Cliente</label>
        <select class="campo<?= $claseError('cliente') ?>"
                id="cliente" name="cliente"
                <?= $errorDe('cliente') !== '' ? 'aria-invalid="true" aria-describedby="error-cliente"' : '' ?>>
          <option value="">— Elige un cliente —</option>
          <?php foreach ($clientes as $cliente): ?>
            <option value="<?= (int) $cliente['id'] ?>"<?= (int) $campo('cliente') === (int) $cliente['id'] ? ' selected' : '' ?>>
              <?= esc((string) $cliente['nombre']) ?> (<?= esc((string) $cliente['documento']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <p class="campo-error" role="alert" id="error-cliente"><?= esc($errorDe('cliente')) ?></p>
      </div>

      <?php /* ---- Las cuatro lineas ---------------------------------------- */
      if ($productos === []): ?>
        <p class="sub">No hay ningún producto disponible para pedidos, así que no se puede crear ninguno. Hay que tener inventario con unidades.</p>
      <?php else: ?>
        <div class="tabla-scroll">
          <table class="tabla-productos lineas-pedido">
            <caption>Líneas del pedido. Las que se dejen vacías se cuentan como cero.</caption>
            <thead>
              <tr>
                <th scope="col">Producto</th>
                <th scope="col">Unidades</th>
              </tr>
            </thead>
            <tbody>
              <?php for ($n = 1; $n <= LINEAS_POR_PEDIDO; $n++): ?>
                <tr>
                  <td>
                    <label class="rotulo-demo" for="producto-<?= $n ?>">Línea <?= $n ?></label>
                    <select class="campo<?= $claseError('linea' . $n) ?>"
                            id="producto-<?= $n ?>" name="linea[<?= $n ?>][producto_id]">
                      <option value="">— Ninguno —</option>
                      <?php $elegido = (int) $campo('linea.' . $n . '.producto_id'); ?>
                      <?php foreach ($productos as $producto): ?>
                        <option value="<?= (int) $producto['id'] ?>"<?= $elegido === (int) $producto['id'] ? ' selected' : '' ?>>
                          <?= esc((string) $producto['codigo']) ?> — <?= esc((string) $producto['nombre']) ?>
                          ($ <?= number_format((float) $producto['valor_alquiler'], 0, ',', '.') ?>, quedan <?= (int) $producto['existencias'] ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <label class="rotulo-demo" for="cantidad-<?= $n ?>">Unidades de la línea <?= $n ?></label>
                    <input class="campo<?= $claseError('linea' . $n) ?>"
                           type="text" id="cantidad-<?= $n ?>" name="linea[<?= $n ?>][cantidad]"
                           inputmode="numeric" maxlength="4" placeholder="1"
                           value="<?= esc($campo('linea.' . $n . '.cantidad')) ?>"
                           <?= $errorDe('linea' . $n) !== '' ? 'aria-invalid="true" aria-describedby="error-linea-' . $n . '"' : '' ?>>
                    <p class="campo-error" role="alert" id="error-linea-<?= $n ?>"><?= esc($errorDe('linea' . $n)) ?></p>
                  </td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="formulario__acciones">
        <button class="boton" type="submit">Guardar el pedido</button>
      </div>
    </form>
  </section>

</main>
<?php
require __DIR__ . '/app/parciales/pie.php';
