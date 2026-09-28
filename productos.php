<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Productos: listado, alta, edición y baja lógica
   Archivo: productos.php
   Actividad de recuperación, día 13, punto 1 (CRUD con búsqueda, paginación y
   ordenamiento) y punto 4 (borrado lógico)

   Esta página hace las cinco cosas del punto 1, y la sexta del punto 4:

       crear          el formulario de alta, que antes no guardaba nada
       leer           el listado, que era lo único que se podía hacer
       actualizar     la edición, que se entra por ?editar=<id>
       eliminar        desactivar (que NO es borrar: está en el punto 4)
       buscar         ?buscar=<texto>
       paginar        ?pagina=<n>, de diez en diez
       ordenar        ?orden=<columna>&dir=<asc|desc>

   ---------------------------------------------------------------------------
   CÓMO SE SABE SI HAY QUE CREAR, EDITAR O DESACTIVAR
   ---------------------------------------------------------------------------
   Todo llega por el POST en el campo oculto "accion". Es un campo más, y no un
   <button name="..."> distinto en cada caso, por una razón concreta: el
   listado y el formulario de alta están en la misma página, y el botón de la
   fila de cada producto es un formulario entero con su propio token. Un
   formulario no puede estar dentro de otro formulario, así que el formulario
   grande tiene que poder ser el mismo formulario en los tres casos, y lo que
   cambia se dice en un campo oculto.

   Cada acción termina igual, y esa parte es la importante:

       guardarFlash(...);
       redirigir(urlApp('productos.php') . '?...');

   Es el patrón Post/Redirect/Get, explicado en app/seguridad/flash.php. Con él,
   si alguien pulsa F5 después de guardar, el navegador repite un GET y no
   vuelve a guardar nada.

   ---------------------------------------------------------------------------
   LOS TRES MENSAJES DEL SERVIDOR, QUE ES LO QUE PIDE EL PUNTO 5
   ---------------------------------------------------------------------------
   El formulario lleva novalidate a propósito. Con novalidate el navegador no
   para el envío ni pinta su burbuja: el POST llega entero al servidor y es el
   servidor el que contesta. Con JavaScript desactivado la imagen es idéntica;
   con JavaScript encendido lo único que se añade es una revisión en el
   navegador, sin quitar nunca la del servidor.

   Los tres casos que hay que documentar:

     1. Campos vacíos
        "El código del producto es obligatorio."
        "El nombre del producto es obligatorio."
        "Las existencias son obligatorias."
        (una por campo, todas juntas en la misma lista)

     2. Precio negativo
        "El valor de alquiler tiene que ser mayor que cero: -5000 no es un precio."

     3. Texto en un campo numérico
        "Las existencias tienen que ser un número entero. Escribiste: «hola»."

   Y el formulario se vuelve a pintar con lo que se había escrito, para que no
   haya que teclear veinte letras otra vez por equivocarse en una.

   ---------------------------------------------------------------------------
   POR QUÉ DESACTIVAR NO ES BORRAR
   ---------------------------------------------------------------------------
   Está en app/modelos/ProductoModelo.php, en la cabecera: productos está en
   detalle_pedidos con ON DELETE RESTRICT, de modo que un producto que estuvo en
   un pedido no se puede borrar, y uno que no estuvo en ninguno se podría
   borrar y dejaría de existir para siempre. En los dos casos la baja lógica es
   lo que corresponde: la fila sigue ahí con su id, y el listado la esconde con
   WHERE activo = 1. El botón de "reactivar" es lo que permite volver atrás.
   =========================================================================== */

/* El guardián va en la primera instrucción, antes de nada, porque header() y
   Set-Cookie tienen que salir antes del primer byte de la página. */
require_once __DIR__ . '/app/seguridad/guardia.php';

/* El rol sale de app/config/menu.php: el menu deja esta entrada para
   administrador y vendedor, y el consultor no da de alta ni corrige
   inventario. Es la misma lista, escrita en los dos sitios, y por eso el menu y
   esta pagina no pueden contradecirse. */
exigirRol('administrador', 'vendedor');

require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/seguridad/salida.php';
require_once __DIR__ . '/app/seguridad/csrf.php';
require_once __DIR__ . '/app/seguridad/flash.php';
require_once __DIR__ . '/app/modelos/ProductoModelo.php';

/** Diez productos por página, como pide el punto 1. */
const PRODUCTOS_POR_PAGINA = 10;

$pdo = Conexion::obtener();

/* Las tres condiciones del ENUM de productos.condicion. Se comparan contra
   esta lista, y no contra un texto escrito en la comparación, para que el día
   que se añada una condición nueva solo haya que tocar un sitio. */
const CONDICIONES_PRODUCTO = ['Nuevo', 'Semi-nuevo', 'En reparación'];

/* ===========================================================================
   LO QUE LLEGA POR LA URL
   ---------------------------------------------------------------------------
   Los tres parámetros del listado y el producto que se está editando. El orden
   pasa por lista blanca en el modelo: resolverOrdenProducto() devuelve la
   columna real o null, y null no llega nunca a la consulta. El estado pasa por
   la misma puerta: resolverEstadoProducto() deja solo 'activos', 'inactivos' y
   'todos', y un ?estado=inventado cae en 'activos' en vez de romper la pagina. */
$buscar     = trim((string) ($_GET['buscar'] ?? ''));
$estado     = resolverEstadoProducto((string) ($_GET['estado'] ?? 'activos'));
$orden      = (string) ($_GET['orden'] ?? 'nombre');
$direccion  = (string) ($_GET['dir'] ?? 'asc');
$pagina     = max(1, (int) ($_GET['pagina'] ?? 1));
$editandoId = (int) ($_GET['editar'] ?? 0);

$columnaValida = resolverOrdenProducto($orden);
$dirValida     = strtolower($direccion) === 'desc' ? 'desc' : 'asc';

/* Un orden que no está en la lista no es un error que pare la página: es alguien
   escribiendo a mano en la barra de direcciones. Lo razonable es mostrar el
   listado en el orden de siempre y avisar de que el campo no existe.

   El aviso va en una variable y no con avisarFlash() a proposito: tomarFlash()
   ya se ha leido cuando esto se decide, y un mensaje escrito en la sesion en
   este momento se veria en la pagina SIGUIENTE, no en esta. */
if ($columnaValida === null) {
    $avisoUrl = 'El campo por el que se pedía ordenar no existe. Se muestra el inventario por nombre.';
    $orden    = 'nombre';
} else {
    $avisoUrl = null;
}

/* ===========================================================================
   EL POST: LAS CUATRO ACCIONES
   =========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null;
    $accion = (string) ($_POST['accion'] ?? '');

    /* El token se comprueba antes de mirar qué se quería hacer: un POST sin
       token válido no cambia nada, ni siquiera para decir que el token no era
       válido. */
    if (!validarCsrf($token)) {
        http_response_code(HTTP_TOKEN_INVALIDO);
        exit('El formulario no vino de esta página. Recargue la página y vuelva a intentarlo.');
    }

    /* Cada formulario lleva la búsqueda y el filtro de estado en campos
       ocultos. Sin esto, desactivar un producto que se estaba buscando
       devolvería el listado entero, que no es lo que la persona tenía delante
       cuando pulsó el botón. La búsqueda se vuelve a pasar por la lista blanca
       del estado, igual que si venía por la URL. */
    $buscar     = trim((string) ($_POST['buscar'] ?? ''));
    $estado     = resolverEstadoProducto((string) ($_POST['estado'] ?? 'activos'));
    $volverA    = static function (array $extra = []) use ($buscar, $estado): string {
        $params = [];

        if ($buscar !== '') {
            $params['buscar'] = $buscar;
        }

        if ($estado !== 'activos') {
            $params['estado'] = $estado;
        }

        return urlApp('productos.php')
            . (($params + $extra) === [] ? '' : '?' . http_build_query($params + $extra));
    };

    /* ---- Desactivar y reactivar: el borrado lógico del punto 4 --------- */
    if ($accion === 'desactivar' || $accion === 'reactivar') {
        $id       = (int) ($_POST['id'] ?? 0);
        $producto = $id > 0 ? obtenerProducto($pdo, $id) : null;

        if ($producto === null) {
            avisarFlash('error', 'Ese producto ya no existe en el inventario.');
        } else {
            $quedaActivo = $accion === 'reactivar';

            cambiarEstadoProducto($pdo, $id, $quedaActivo, usuarioActual()['id'] ?? null);

            avisarFlash(
                'exito',
                $quedaActivo
                    ? '«' . $producto['nombre'] . '» vuelve a estar disponible.'
                    : '«' . $producto['nombre'] . '» se desactivó: desapareció del listado y de los pedidos nuevos, pero sigue en los pedidos que ya lo llevaron.'
            );
        }

        redirigir($volverA());
    }

    /* ---- Crear y editar ------------------------------------------------- */
    if ($accion === 'crear' || $accion === 'editar') {
        $id          = $accion === 'editar' ? (int) ($_POST['id'] ?? 0) : 0;
        $entrada     = leerEntradaProducto($_POST);
        $categorias  = listarCategorias($pdo);

        [$datos, $errores] = validarProducto($pdo, $entrada, $id, $categorias);

        if ($errores !== []) {
            /* Se guardan TODOS los errores, no solo el primero: si alguien
               dejó tres campos mal, tiene que ver los tres. Si no, tendría que
               enviar el formulario otras dos veces para enterarse del resto. */
            guardarFlash(
                'error',
                'No se guardó el producto. Revisa lo que está marcado en rojo.',
                $errores,
                $entrada,
                $id > 0 ? ['editando' => $id] : ['creando' => true]
            );

            redirigir($volverA($id > 0 ? ['editar' => $id] : ['crear' => 1]));
        }

        if ($accion === 'crear') {
            crearProducto($pdo, $datos);
            avisarFlash('exito', 'Se guardó «' . $datos['nombre'] . '» con el código ' . $datos['codigo'] . '.');
        } else {
            actualizarProducto($pdo, $id, $datos);
            avisarFlash('exito', 'Se guardaron los cambios de «' . $datos['nombre'] . '».');
        }

        redirigir($volverA());
    }
}

/* ===========================================================================
   EL AVISO DE LA PETICIÓN ANTERIOR
   ---------------------------------------------------------------------------
   tomarFlash() borra lo que devuelve, así que el mensaje se ve una sola vez. Se
   lee antes de pintar nada porque el formulario necesita saber si se está
   repintando con lo que se escribió o si va a salir de la base.
   =========================================================================== */
$flash   = tomarFlash();
$errores = $flash['errores'] ?? [];

/* Lo que se escribió la última vez. Si no hay flash, el formulario se pinta con
   el producto de la base cuando se está editando, y vacío cuando es un alta. */
$repintar = ($flash['entrada'] ?? []) !== [];
$editando = $editandoId > 0 ? obtenerProducto($pdo, $editandoId) : null;

/* Si se pidió editar algo que no existe, no es un fallo de MySQL: es un enlace
   viejo. Se avisa y se sigue con el alta en blanco.

   El aviso va en la variable y no con avisarFlash() por el mismo motivo que en
   clientes.php: a esta altura tomarFlash() ya se ha leido, y un mensaje escrito
   en la sesion aqui no se veria hasta la pagina siguiente, que es justo cuando
   ya no viene de la URL. */
if ($editandoId > 0 && $editando === null) {
    $avisoUrl   = $avisoUrl === null
        ? 'El producto que se quiso editar no existe. Se muestra el formulario de alta.'
        : $avisoUrl . ' El producto que se quiso editar tampoco existe, y se muestra el formulario de alta.';
    $editando = null;
    $editandoId = 0;
}

$campo = static function (string $nombre, string $porDefecto = '') use ($flash, $repintar, $editando): string {
    if ($repintar) {
        return (string) ($flash['entrada'][$nombre] ?? $porDefecto);
    }

    if ($editando !== null) {
        return (string) ($editando[$nombre] ?? $porDefecto);
    }

    return $porDefecto;
};

/* El error de un campo, o cadena vacía si no tiene. Que devuelva "" y no false
   es lo que permite escribirlo en el HTML sin inventarse una condición. */
$errorDe = static function (string $nombre) use ($errores): string {
    return (string) ($errores[$nombre] ?? '');
};

/* La clase que se le pone al input cuando tiene error: la misma .es-invalid que
   ya usa la guia de estilo del dia 8, y no una clase nueva. Asi el borde rojo
   sale de las mismas variables de color que el resto del sistema, y un <p> de
   error con la clase de siempre se pinta con el mismo margen en las tres
   pantallas. */
$claseError = static function (string $nombre) use ($errores): string {
    return isset($errores[$nombre]) && $errores[$nombre] !== '' ? ' es-invalid' : '';
};

/* ===========================================================================
   EL LISTADO
   =========================================================================== */
$total          = contarProductos($pdo, $buscar, $estado);
$paginas        = max(1, (int) ceil($total / PRODUCTOS_POR_PAGINA));
$pagina         = min($pagina, $paginas);
$primeraFila    = $total === 0 ? 0 : ($pagina - 1) * PRODUCTOS_POR_PAGINA + 1;
$ultimaFila     = min($total, $pagina * PRODUCTOS_POR_PAGINA);

$productos = listarProductos(
    $pdo,
    $buscar,
    $orden,
    $dirValida,
    PRODUCTOS_POR_PAGINA,
    ($pagina - 1) * PRODUCTOS_POR_PAGINA,
    $estado
);

/* Las columnas que se pueden pulsar, en el orden en que salen en la tabla. Las
   dos que no se pintan aquí (estado y categoría) también se pueden ordenar, y
   desde la URL, porque están en la lista blanca del modelo. */
$columnasConEnlace = [
    'codigo'      => 'Código',
    'nombre'      => 'Nombre',
    'condicion'   => 'Condición',
    'existencias' => 'Existencias',
    'valor'       => 'Valor de alquiler',
];

/* Un enlace de orden o de página tiene que llevar todo lo demás de la URL, o se
   pierde la búsqueda en el primer clic. Esta función es la única que arma esos
   enlaces, y por eso ninguno se escribe a mano en el HTML.

   El nombre de la columna vuelve a pasar por la lista blanca antes de entrar en
   la URL: si no, el enlace que genera esta función para una columna válida
   seguiría siendo válido, pero un ?orden= inventado en la barra de direcciones
   aparecería en todos los enlaces siguientes. */
$enlace = static function (array $cambios) use ($buscar, $orden, $dirValida, $pagina, $estado): string {
    $params = [
        'buscar' => $buscar,
        'estado' => $estado === 'activos' ? null : $estado,
        'orden'  => resolverOrdenProducto($orden) === null ? 'nombre' : $orden,
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

    /* Una busqueda vacia no va en la URL. "?buscar=&orden=nombre" funciona
       igual, pero se ve feo en la barra de direcciones, en las capturas de la
       bitacora y en el video del dia 13, y un enlace con dos parametros donde
       solo importa uno se copia mal mas adelante. */
    if (($params['buscar'] ?? '') === '') {
        unset($params['buscar']);
    }

    return urlApp('productos.php') . '?' . http_build_query($params);
};

$categorias  = listarCategorias($pdo);
$proveedores = listarProveedores($pdo);
$token = tokenCsrf();

/* ===========================================================================
   LAS DOS FUNCIONES DE ESTA PÁGINA QUE NO SON DE PINTAR
   ---------------------------------------------------------------------------
   están aquí y no en el modelo porque no tocan la base de datos: una lee el
   POST y la otra decide si lo leído sirve. Si el código está repetido o la
   categoría no existe, eso sí lo decide el modelo, que es quien tiene la base.
   =========================================================================== */

/**
 * Lee el formulario y deja solo las claves que existen en la tabla.
 *
 * Sin esto, cada comprobación tendría que preguntar primero si la clave viene
 * en el POST, y con strict_types cualquier valor raro reventaría con un
 * TypeError en vez de con un mensaje que se pueda leer.
 *
 * @param array<string, mixed> $post
 * @return array<string, string>
 */
function leerEntradaProducto(array $post): array
{
    $claves = ['codigo', 'nombre', 'categoria_id', 'condicion', 'existencias', 'valor_alquiler', 'proveedor_id', 'descripcion', 'fecha_ingreso'];

    $entrada = [];

    foreach ($claves as $clave) {
        $valor = $post[$clave] ?? '';
        $entrada[$clave] = is_string($valor) ? trim($valor) : '';
    }

    return $entrada;
}

/**
 * Valida la entrada y devuelve los datos limpios y los errores.
 *
 * El orden de las comprobaciones es el del formulario, de arriba abajo, y así
 * los errores salen en el mismo sitio que los campos.
 *
 * Hay tres reglas de forma y el resto son de contenido:
 *
 *   - vacío:     trim() === ''
 *   - numérico:  is_numeric(), que es lo que separa "12" de "hola"
 *   - entero:    además, sin decimales
 *
 * Y la conversión a número se hace DESPUÉS de comprobar que es un número. Si se
 * hiciera antes, "12abc" se convertiría en 12 y pasaría la comprobación, que es
 * justo el fallo que hay que evitar.
 *
 * @param array<int, array<string, mixed>> $categorias
 * @return array{0: array<string, mixed>, 1: array<string, string>}
 */
function validarProducto(PDO $pdo, array $entrada, int $idProducto, array $categorias): array
{
    $errores = [];
    $datos   = [];

    /* ---- El código ---- */
    $codigo = strtoupper($entrada['codigo'] ?? '');

    if ($codigo === '') {
        $errores['codigo'] = 'El código del producto es obligatorio.';
    } elseif (!preg_match('/^[A-Z]{3}-[0-9]{3}$/', $codigo)) {
        $errores['codigo'] = 'El código tiene que ser tres letras en mayúscula, un guion y tres dígitos. Ejemplo: TAC-001';
    } elseif (existeCodigoProducto($pdo, $codigo, $idProducto > 0 ? $idProducto : null)) {
        $errores['codigo'] = 'Ese código ya lo usa otro producto. Cada producto necesita uno distinto.';
    } else {
        $datos['codigo'] = $codigo;
    }

    /* ---- El nombre ---- */
    $nombre = $entrada['nombre'] ?? '';

    if ($nombre === '') {
        $errores['nombre'] = 'El nombre del producto es obligatorio.';
    } elseif (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 60) {
        $errores['nombre'] = 'El nombre tiene que tener entre 3 y 60 letras.';
    } else {
        $datos['nombre'] = $nombre;
    }

    /* ---- La categoría ---- */
    $categoriaId  = (int) ($entrada['categoria_id'] ?? 0);
    $categoriasOk = array_column($categorias, 'id');

    if ($categoriaId <= 0) {
        $errores['categoria_id'] = 'Hay que elegir una categoría.';
    } elseif (!in_array($categoriaId, $categoriasOk, true)) {
        $errores['categoria_id'] = 'Esa categoría no existe en el catálogo.';
    } else {
        $datos['categoria_id'] = $categoriaId;
    }

    /* ---- La condición ---- */
    $condicion = $entrada['condicion'] ?? '';

    if (!in_array($condicion, CONDICIONES_PRODUCTO, true)) {
        $errores['condicion'] = 'La condición tiene que ser una de las tres del inventario.';
    } else {
        $datos['condicion'] = $condicion;
    }

    /* ---- Las existencias: aquí está el caso 3 del punto 5 ---- */
    $existencias = $entrada['existencias'] ?? '';

    if ($existencias === '') {
        $errores['existencias'] = 'Las existencias son obligatorias.';
    } elseif (!is_numeric($existencias)) {
        /* El mensaje repite lo que se escribió, entre comillas: quien lo lee
           tiene que poder comparar lo que puso con lo que le devolvieron. */
        $errores['existencias'] = 'Las existencias tienen que ser un número entero. Escribiste: «' . $existencias . '».';
    } elseif ((float) $existencias != (int) $existencias) {
        $errores['existencias'] = 'Las existencias tienen que ser un número entero, sin decimales.';
    } elseif ((int) $existencias < 0) {
        $errores['existencias'] = 'Las existencias no pueden ser negativas: eso quiere decir que hay más de las que hay.';
    } else {
        $datos['existencias'] = (int) $existencias;
    }

    /* ---- El valor de alquiler: aquí está el caso 2 del punto 5 ---- */
    $valor = $entrada['valor_alquiler'] ?? '';

    if ($valor === '') {
        $errores['valor_alquiler'] = 'El valor de alquiler es obligatorio.';
    } elseif (!is_numeric($valor)) {
        $errores['valor_alquiler'] = 'El valor de alquiler tiene que ser una cantidad de pesos. Escribiste: «' . $valor . '».';
    } elseif ((float) $valor <= 0) {
        $errores['valor_alquiler'] = 'El valor de alquiler tiene que ser mayor que cero: ' . $valor . ' no es un precio.';
    } else {
        $datos['valor_alquiler'] = (float) $valor;
    }

    /* ---- La fecha de ingreso ---- */
    $fecha = $entrada['fecha_ingreso'] ?? '';

    if ($fecha === '') {
        $errores['fecha_ingreso'] = 'La fecha de ingreso es obligatoria.';
    } else {
        /* Decide checkdate() y no un "> date('Y-m-d')": un formulario puede ser
           un día de PXE, y un producto puede venir de un inventario antiguo. Solo
           se pide que la fecha exista de verdad, que es lo que impide el
           "2026-13-45". */
        $partes = explode('-', $fecha);

        if (count($partes) !== 3 || !checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0])) {
            $errores['fecha_ingreso'] = 'Esa fecha no existe en el calendario.';
        } else {
            $datos['fecha_ingreso'] = $fecha;
        }
    }

    /* ---- El proveedor, opcional ---- */
    $proveedorId = (int) ($entrada['proveedor_id'] ?? 0);

    $datos['proveedor_id'] = $proveedorId > 0 ? $proveedorId : null;

    /* ---- La descripción, opcional ---- */
    $datos['descripcion'] = mb_substr($entrada['descripcion'] ?? '', 0, 200);

    return [$datos, $errores];
}

/* Los valores por defecto del alta. La fecha de hoy se pide al servidor y no a
   JavaScript, porque sin JavaScript no hay fecha: el campo se rellena con la
   del servidor, que además es la misma que usa la base. */
$fechaDeHoy = date('Y-m-d');

$tituloPagina = 'Productos';
$itemActual   = 'productos';

require __DIR__ . '/app/parciales/cabecera.php';
require __DIR__ . '/app/parciales/menu.php';
?>
<main class="panel__contenido">

  <h1>Productos</h1>
  <p class="sub">Inventario de la casa: alta, edición, búsqueda, orden y baja lógica.</p>

  <?php /* Los mensajes del servidor. Se pintan aqui y no dentro del formulario
          porque valen para las cuatro acciones: un alta con errores y una baja
          exitosa son el mismo <ul>. La clase de cada uno sale del tipo de
          flash, y avisarFlash() ya la puso. */ ?>
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
      <li class="alerta alerta--advertencia"><?= esc($avisoOrden) ?></li>
    </ul>
  <?php endif; ?>

  <?php /* ------------------------------------------------------------------
          EL BUSCADOR
          Un formulario con method="get" y sin nada de JavaScript: el buscador
          funciona entero con el teclado. El campo se llama "buscar" porque es
          el nombre que espera el modelo, y el boton dice "Buscar" para que se
          sepa que hay que pulsarlo.
          ------------------------------------------------------------------ */ ?>
  <form class="buscador" method="get" action="productos.php" role="search">
    <div class="buscador__fila">
      <label class="rotulo-demo" for="buscar">Buscar producto</label>
      <input class="campo" type="search" id="buscar" name="buscar"
             value="<?= esc($buscar) ?>" placeholder="Nombre, código o categoría">
      <?php /* Se esconde el resto de la URL, para que buscar no borre el
              ordenamiento ni vuelva a la primera pagina. Sin esto, escribir
              en el buscador y buscar devuelve el listado por nombre. */ ?>
      <input type="hidden" name="orden" value="<?= esc($orden) ?>">
      <input type="hidden" name="dir" value="<?= esc($dirValida) ?>">
      <?php /* El filtro de estado tambien viaja escondido, por el mismo
               motivo: buscar no debe cambiar de vista a la que se estaba. */ ?>
      <?php if ($estado !== 'activos'): ?>
        <input type="hidden" name="estado" value="<?= esc($estado) ?>">
      <?php endif; ?>
      <button class="boton" type="submit">Buscar</button>
      <?php if ($buscar !== ''): ?>
        <a class="enlace" href="<?= esc($enlace(['buscar' => '', 'pagina' => ''])) ?>">Quitar la búsqueda</a>
      <?php endif; ?>
    </div>
    <p class="buscador__conteo">
      <?php if ($total === 0): ?>
        No hay productos que cumplan la búsqueda.
      <?php else: ?>
        Mostrando del <?= $primeraFila ?> al <?= $ultimaFila ?> de <?= $total ?>
        <?= $total === 1 ? 'producto' : 'productos' ?>, en <?= $paginas ?>
        <?= $paginas === 1 ? 'página' : 'páginas' ?>.
      <?php endif; ?>
    </p>
  </form>

  <?php /* ------------------------------------------------------------------
          EL FILTRO DE ESTADO
          Sin esto, desactivar un producto lo hace desaparecer y no queda
          forma de volver a esa pantalla para reactivarlo: el boton de
          "Reactivar" solo se ve en la fila de un producto apagado, y si la
          lista no lo enseña, ese boton no existe. Son enlaces, no un
          <select> con JavaScript, para que cambiar de vista funcione entero
          sin JavaScript y con el teclado.
          ------------------------------------------------------------------ */ ?>
  <nav class="filtros" aria-label="Filtrar por estado">
    <span class="filtros__titulo">Ver:</span>
    <?php foreach (['activos' => 'Activos', 'inactivos' => 'Desactivados', 'todos' => 'Todos'] as $clave => $titulo): ?>
      <a class="filtros__enlace<?= $estado === $clave ? ' filtros__enlace--activo' : '' ?>"
         href="<?= esc($enlace(['estado' => $clave === 'activos' ? null : $clave, 'pagina' => ''])) ?>"
         <?= $estado === $clave ? 'aria-current="page"' : '' ?>><?= esc($titulo) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php /* ------------------------------------------------------------------
          EL LISTADO
          El <caption> no se ve en la tabla, pero es lo que lee un lector de
          pantalla para saber de qué es la tabla: no se ve, pero no sobra. Y la
          tabla va dentro de un
          div con overflow, que es lo que hace que en el teléfono se deslice en
          horizontal en vez de romper la rejilla.
          ------------------------------------------------------------------ */ ?>
  <div class="tabla-scroll">
    <table class="tabla-productos">
      <caption>Inventario de productos. Los títulos de columna son enlaces para ordenar.</caption>
      <thead>
        <tr>
          <?php foreach ($columnasConEnlace as $clave => $titulo): ?>
            <?php /* La flechita va en la columna por la que se ordena ahora, y
                    solo si la columna es la que esta activa. Al pulsar otra
                    columna se invierte el sentido, salvo que ya se estaba
                    ordenando por ella, que entonces se da la vuelta: es el
                    comportamiento de cualquier tabla ordenable. */ ?>
            <?php $esActiva = $orden === $clave; ?>
            <th scope="col" aria-sort="<?= $esActiva ? ($dirValida === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
              <a href="<?= esc($enlace(['orden' => $clave, 'dir' => $esActiva && $dirValida === 'asc' ? 'desc' : 'asc', 'pagina' => ''])) ?>">
                <?= esc($titulo) ?><?= $esActiva ? ($dirValida === 'asc' ? ' ↑' : ' ↓') : '' ?>
              </a>
            </th>
          <?php endforeach; ?>
          <th scope="col">Estado</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($productos === []): ?>
          <?php /* El mensaje cambia segun por que este vacio. Decir "crea el
                   primero" mientras se mira la lista de desactivados, que
                   justamente es la de los que ya existen, manda a la persona a
                   crear un producto que ya tiene. */ ?>
          <tr>
            <td colspan="<?= count($columnasConEnlace) + 2 ?>" class="sub">
              <?php if ($buscar !== ''): ?>
                Ningún producto cumple la búsqueda «<?= esc($buscar) ?>».
              <?php elseif ($estado === 'inactivos'): ?>
                No hay ningún producto desactivado. Los que se desactiven se quedan aquí, con su botón de Reactivar.
              <?php else: ?>
                No hay productos. Si es la primera vez que entras, crea el primero con el formulario de abajo.
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($productos as $producto): ?>
          <tr>
            <td><?= esc((string) $producto['codigo']) ?></td>
            <td><?= esc((string) $producto['nombre']) ?></td>
            <td><?= esc((string) $producto['condicion']) ?></td>
            <td><?= (int) $producto['existencias'] ?></td>
            <td>$ <?= number_format((float) $producto['valor_alquiler'], 0, ',', '.') ?></td>
            <td>
              <?php /* El estado es el punto 4 en una celda: lo que se ve es que
                      el producto esta activo o no, y el motivo esta en el
                      title del badge para quien lo quiera leer entero. */ ?>
              <span class="badge <?= (int) $producto['activo'] === 1 ? 'badge--exito' : 'badge--neutro' ?>"
                    title="<?= (int) $producto['activo'] === 1 ? 'Visible en el listado y disponible para pedidos' : 'Desactivado: oculto del listado y no se puede pedir' ?>">
                <?= (int) $producto['activo'] === 1 ? 'Activo' : 'Desactivado' ?>
              </span>
            </td>
            <td class="acciones-fila">
              <a class="boton-mini"
                 href="<?= esc($enlace(['editar' => (string) $producto['id'], 'pagina' => ''])) ?>">Editar</a>

              <?php /* Desactivar y reactivar van por POST y no por enlace, por
                      el mismo motivo que logout.php: un enlace que cambia datos
                      se puede activar con un clic derecho y "abrir en una
                      pestana nueva", y ahi el GET habria desactivado el
                      producto sin que nadie lo pidiera. Ademas lleva token
                      CSRF, porque la sesion por si sola no demuestra que el
                      boton sea de esta pagina. */ ?>
              <form method="post" action="productos.php" class="acciones-fila">
                <input type="hidden" name="csrf" value="<?= esc($token) ?>">
                <input type="hidden" name="accion" value="<?= (int) $producto['activo'] === 1 ? 'desactivar' : 'reactivar' ?>">
                <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                <?php if ($buscar !== ''): ?>
                  <input type="hidden" name="buscar" value="<?= esc($buscar) ?>">
                <?php endif; ?>
                <?php /* Al volver se mira la misma vista en la que se pulsó. Con
                         el filtro en "desactivados", reactivar tiene que sacar
                         la fila de la lista, y no devolverla a los activos. */ ?>
                <input type="hidden" name="estado" value="<?= esc($estado) ?>">
                <button class="boton-mini <?= (int) $producto['activo'] === 1 ? 'boton-peligro' : '' ?>" type="submit">
                  <?= (int) $producto['activo'] === 1 ? 'Desactivar' : 'Reactivar' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php /* ------------------------------------------------------------------
          LA PAGINACIÓN
          Solo aparece si hay mas de una pagina. El numero de paginas sale del
          total con el mismo filtro que la tabla (contarProductos), que es la
          unica forma de que "pagina 2 de 3" sea verdad: un COUNT sobre las
          diez filas de la pagina contaria diez siempre.
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
          EL FORMULARIO DE ALTA Y DE EDICIÓN
          Es el mismo formulario para las dos cosas, y el campo oculto "accion"
          dice cual es. Vuelve entero despues de un error, con lo que se
          escribio, gracias al flash.

          novalidate: es la linea que hace que el punto 5 se pueda demostrar
          con JavaScript desactivado. Sin ella, el navegador no dejaria enviar
          el formulario vacio y no habria mensaje del servidor que enseñar.
          ------------------------------------------------------------------ */ ?>
  <?php $esEdicion = $editando !== null; ?>
  <section class="tarjeta">
    <h2><?= $esEdicion ? 'Editar producto' : 'Nuevo producto' ?></h2>

    <?php if ($esEdicion): ?>
      <p class="sub">Está editando «<?= esc((string) $editando['nombre']) ?>». Los cambios se guardan con el mismo botón.</p>
    <?php endif; ?>

    <?php /* La lista de errores del campo que toca, escrita una sola vez por
            campo. Se pinta debajo del input, no al lado: el mensaje se
            associate con el campo con el <p id="error-x"> y el aria-describedby
            del input, que es lo que lee el lector de pantalla. Por eso el id
            se compone y no se escribe a mano. */ ?>

    <form class="formulario" id="form-producto" method="post" action="productos.php" novalidate>
      <input type="hidden" name="csrf" value="<?= esc($token) ?>">
      <input type="hidden" name="accion" value="<?= $esEdicion ? 'editar' : 'crear' ?>">
      <?php if ($esEdicion): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <?php if ($buscar !== ''): ?>
        <input type="hidden" name="buscar" value="<?= esc($buscar) ?>">
      <?php endif; ?>
      <input type="hidden" name="estado" value="<?= esc($estado) ?>">

      <div class="formulario__rejilla">

        <div class="campo-grupo">
          <label for="codigo">Código</label>
          <input class="campo<?= $claseError('codigo') ?>"
 type="text" id="codigo" name="codigo"
                 value="<?= esc($campo('codigo')) ?>" maxlength="7" placeholder="TAC-001"
                 pattern="[A-Za-z]{3}-[0-9]{3}"
                 <?= $errorDe('codigo') !== '' ? 'aria-invalid="true" aria-describedby="error-codigo"' : '' ?>>
          <p class="campo-error" role="alert" id="error-codigo"><?= esc($errorDe('codigo')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="nombre">Nombre</label>
          <input class="campo<?= $claseError('nombre') ?>"
 type="text" id="nombre" name="nombre"
                 value="<?= esc($campo('nombre')) ?>" maxlength="60" placeholder="Extensión de taco"
                 <?= $errorDe('nombre') !== '' ? 'aria-invalid="true" aria-describedby="error-nombre"' : '' ?>>
          <p class="campo-error" role="alert" id="error-nombre"><?= esc($errorDe('nombre')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="categoria_id">Categoría</label>
          <?php /* El desplegable se arma con listarCategorias() y no con
                  <option> escritos a mano: el dia 9 habia cuatro fijos, y una
                  categoria nueva en la base no se podia elegir desde el
                  formulario. Lo que no se puede elegir no se puede vender. */ ?>
          <select class="campo<?= $claseError('categoria_id') ?>"
 id="categoria_id" name="categoria_id"
                  <?= $errorDe('categoria_id') !== '' ? 'aria-invalid="true" aria-describedby="error-categoria_id"' : '' ?>>
            <option value="">Elige una categoría</option>
            <?php foreach ($categorias as $categoria): ?>
              <?php $valor = (int) $categoria['id']; ?>
              <option value="<?= $valor ?>" <?= (int) $campo('categoria_id', '0') === $valor ? 'selected' : '' ?>>
                <?= esc((string) $categoria['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="campo-error" role="alert" id="error-categoria_id"><?= esc($errorDe('categoria_id')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="condicion">Condición</label>
          <select class="campo" id="condicion" name="condicion">
            <?php foreach (CONDICIONES_PRODUCTO as $opcion): ?>
              <option value="<?= esc($opcion) ?>" <?= $campo('condicion', 'Nuevo') === $opcion ? 'selected' : '' ?>>
                <?= esc($opcion) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo-grupo">
          <label for="existencias">Existencias</label>
          <input class="campo<?= $claseError('existencias') ?>"
 type="text" id="existencias" name="existencias"
                 value="<?= esc($campo('existencias')) ?>" inputmode="numeric" placeholder="5"
                 <?= $errorDe('existencias') !== '' ? 'aria-invalid="true" aria-describedby="error-existencias"' : '' ?>>
          <p class="campo-error" role="alert" id="error-existencias"><?= esc($errorDe('existencias')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="valor_alquiler">Valor de alquiler (en pesos)</label>
          <input class="campo<?= $claseError('valor_alquiler') ?>"
 type="text" id="valor_alquiler" name="valor_alquiler"
                 value="<?= esc($campo('valor_alquiler')) ?>" inputmode="numeric" placeholder="25000"
                 <?= $errorDe('valor_alquiler') !== '' ? 'aria-invalid="true" aria-describedby="error-valor_alquiler"' : '' ?>>
          <p class="campo-error" role="alert" id="error-valor_alquiler"><?= esc($errorDe('valor_alquiler')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="fecha_ingreso">Fecha de ingreso</label>
          <input class="campo<?= $claseError('fecha_ingreso') ?>"
 type="date" id="fecha_ingreso" name="fecha_ingreso"
                 value="<?= esc($campo('fecha_ingreso', $fechaDeHoy)) ?>"
                 <?= $errorDe('fecha_ingreso') !== '' ? 'aria-invalid="true" aria-describedby="error-fecha_ingreso"' : '' ?>>
          <p class="campo-error" role="alert" id="error-fecha_ingreso"><?= esc($errorDe('fecha_ingreso')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="proveedor_id">Proveedor (opcional)</label>
          <select class="campo" id="proveedor_id" name="proveedor_id">
            <option value="">Sin proveedor</option>
            <?php foreach ($proveedores as $proveedor): ?>
              <?php $valor = (int) $proveedor['id']; ?>
              <option value="<?= $valor ?>" <?= (int) $campo('proveedor_id', '0') === $valor ? 'selected' : '' ?>>
                <?= esc((string) $proveedor['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo-grupo campo-grupo--ancho">
          <label for="descripcion">Descripción (opcional)</label>
          <textarea class="campo" id="descripcion" name="descripcion" rows="3" maxlength="200"
                    placeholder="Algo que sirva para distinguirlo en el mostrador"><?= esc($campo('descripcion')) ?></textarea>
        </div>

      </div>

      <div class="formulario__acciones">
        <button class="boton" type="submit"><?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?></button>
        <?php if ($esEdicion): ?>
          <a class="boton boton--secundario" href="<?= esc(urlApp('productos.php')) ?>">Cancelar</a>
        <?php endif; ?>
      </div>

      <?php /* El estado del envio, para el navegador. Es un <p> y no una ventana
              alert() porque el enunciado del dia 8 pide los mensajes al lado del
              campo, y porque con JavaScript desactivado este <p> no aparece: lo
              que se ve en ese caso es el mensaje del servidor. */ ?>
      <p class="sub" id="estado-form" role="status" aria-live="polite"></p>
    </form>
  </section>

</main>
<?php
$scriptsExtra = '<script src="app/productos.js"></script>';
require __DIR__ . '/app/parciales/pie.php';
