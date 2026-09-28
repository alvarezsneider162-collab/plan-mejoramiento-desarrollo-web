<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Clientes: listado, alta, edición y borrado
   Archivo: clientes.php
   Actividad de recuperación, día 13, punto 2

   Esta página hace las cinco cosas del punto 1 sobre clientes, con las dos
   reglas que el enunciado pide solo para este listado:

       crear          el formulario de alta, que antes no guardaba nada
       leer           el listado, con búsqueda, orden y paginación
       actualizar     la edición, que se entra por ?editar=<id>
       eliminar       el borrado de verdad, que aquí SÍ es borrar
       buscar         ?buscar=<texto>, por nombre, documento o correo
       ordenar        ?orden=<columna>&dir=<asc|desc>
       paginar        ?pagina=<n>, de diez en diez

   ---------------------------------------------------------------------------
   LAS DOS UNICIDADES DEL PUNTO 2
   ---------------------------------------------------------------------------
   Documento y correo son únicos, y son dos reglas distintas que no se pueden
   comprobar con una:

     documento   dos clientes con el mismo documento son el mismo cliente
                 escrito dos veces
     correo      el correo es la direccion a la que se manda la confirmacion,
                 asi que dos clientes con el mismo correo harian que una sola
                 respuesta llegase a los dos

   Las dos se comprueban en el servidor, antes de guardar, y no se espera a que
   reviente el INSERT con el error 1062 de MySQL: ese error es de la base de
   datos, y quien esta delante de la pagina necesita leer "ese documento ya lo
   tiene tal cliente".

   Y las dos estan tambien en un indice UNIQUE de MySQL, que es la garantia de
   que la base no deja pasar el duplicado aunque el codigo tenga un fallo. El
   indice es lo que no tiene errores.

   ---------------------------------------------------------------------------
   POR QUE AQUI SI SE BORRA Y EN PRODUCTOS NO
   ---------------------------------------------------------------------------
   El punto 4 pide borrado logico para los PRODUCTOS, y este listado lo
   respeta: un producto se desactiva y sigue estando en los pedidos que ya lo
   llevaron. Un cliente es distinto: un cliente sin pedidos no ha dejado nada
   atras, y un cliente con pedidos no se puede borrar ni querer, porque
   clientes esta en pedidos con ON DELETE RESTRICT.

   Asi que aqui el borrado es de verdad, y la base dice que no cuando no se
   puede: la pagina cuenta los pedidos de cada cliente antes de ofrecer el
   boton, y si alguien lo pulsa de todos modos, el error 1451 que devuelve la
   base se enseña como lo que es, un caso previsto, y no como una falla.
   =========================================================================== */

/* El guardián va en la primera instrucción, antes de nada, porque header() y
   Set-Cookie tienen que salir antes del primer byte de la página. */
require_once __DIR__ . '/app/seguridad/guardia.php';

/* El rol sale de app/config/menu.php: el menu deja esta entrada para
   administrador y vendedor. Quien solo consulta no da de alta clientes. */
exigirRol('administrador', 'vendedor');

require_once __DIR__ . '/app/config/conexion.php';
require_once __DIR__ . '/app/seguridad/salida.php';
require_once __DIR__ . '/app/seguridad/csrf.php';
require_once __DIR__ . '/app/seguridad/flash.php';
require_once __DIR__ . '/app/modelos/ClienteModelo.php';

/** Diez clientes por página, como en productos. */
const CLIENTES_POR_PAGINA = 10;

$pdo = Conexion::obtener();

/* ===========================================================================
   LO QUE LLEGA POR LA URL
   ---------------------------------------------------------------------------
   El orden y el estado pasan por la lista blanca del modelo, igual que en
   productos.php: lo que no esta en la lista no es un error que pare la pagina,
   es alguien escribiendo en la barra de direcciones.
   =========================================================================== */
$buscar     = trim((string) ($_GET['buscar'] ?? ''));
$orden      = (string) ($_GET['orden'] ?? 'nombre');
$direccion  = (string) ($_GET['dir'] ?? 'asc');
$pagina     = max(1, (int) ($_GET['pagina'] ?? 1));
$editandoId = (int) ($_GET['editar'] ?? 0);

$columnaValida = resolverOrdenCliente($orden);
$dirValida     = resolverDireccionCliente($direccion);

/* Los avisos de lo que llega por la URL se guardan aqui y no con avisarFlash().
   La razon es el orden de las dos cosas: tomarFlash() ya se ha leido cuando esto
   se decide, asi que un mensaje escrito en este momento se quedaria en la
   sesion y no se veria hasta la pagina SIGUIENTE, que es justo cuando ya no
   viene de la URL y el aviso habria perdido su sentido. Con una variable
   normal se pinta en esta peticion, que es cuando el aviso importa. */
$avisoUrl = null;

if ($columnaValida === null) {
    $avisoUrl = 'El campo por el que se pedía ordenar no existe. Se muestra la lista por nombre.';
    $orden    = 'nombre';
}

/* ===========================================================================
   EL POST: LAS TRES ACCIONES
   ---------------------------------------------------------------------------
   Todo llega en el campo oculto "accion", por el mismo motivo que en
   productos.php: el listado y el formulario comparten pagina, y un <form> no
   puede estar dentro de otro <form>, asi que el boton de la fila tiene que ser
   un formulario propio y el grande tiene que servir para las tres acciones.

   Cada una termina con guardarFlash() y redirigir(), que es el patron
   Post/Redirect/Get: pulsar F5 despues de guardar repite un GET y no vuelve a
   guardar nada.
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

    /* La búsqueda viaja en un campo oculto para que al volver se vea la misma
       lista, y no la lista entera. */
    $buscar  = trim((string) ($_POST['buscar'] ?? ''));
    $volverA = static function (array $extra = []) use ($buscar): string {
        $params = $buscar === '' ? [] : ['buscar' => $buscar];

        return urlApp('clientes.php')
            . (($params + $extra) === [] ? '' : '?' . http_build_query($params + $extra));
    };

    /* ---- Borrar ---------------------------------------------------------- */
    if ($accion === 'eliminar') {
        $id      = (int) ($_POST['id'] ?? 0);
        $cliente = $id > 0 ? obtenerCliente($pdo, $id) : null;

        if ($cliente === null) {
            avisarFlash('error', 'Ese cliente ya no existe.');
        } elseif (pedidosDeCliente($pdo, $id) > 0) {
            /* Esto no es un error de la base adivinando: se comprueba antes, y
               el boton ni siquiera se pinta en este caso. Si se llega aqui es
               porque el pedido se creo entre el listado y el clic. */
            avisarFlash(
                'error',
                '«' . $cliente['nombre'] . '» no se puede borrar porque ya tiene pedidos. Un cliente con pedidos se queda: los pedidos son historico.'
            );
        } elseif (!eliminarCliente($pdo, $id)) {
            /* Aqui si se ha llegado al DELETE y la base lo ha rechazado, que es
               el error 1451. Se cuenta como caso previsto y no como una falla. */
            avisarFlash('error', 'La base no dejó borrar a ese cliente. Puede que tenga pedidos.');
        } else {
            avisarFlash('exito', 'Se borró «' . $cliente['nombre'] . '».');
        }

        redirigir($volverA());
    }

    /* ---- Crear y editar ------------------------------------------------- */
    if ($accion === 'crear' || $accion === 'editar') {
        $id      = $accion === 'editar' ? (int) ($_POST['id'] ?? 0) : 0;
        $entrada = leerEntradaCliente($_POST);

        [$datos, $errores] = validarCliente($pdo, $entrada, $id);

        if ($errores !== []) {
            /* Se guardan TODOS los errores, no solo el primero: si alguien dejó
               dos campos mal, tiene que ver los dos. */
            guardarFlash(
                'error',
                'No se guardó el cliente. Revisa lo que está marcado en rojo.',
                $errores,
                $entrada,
                $id > 0 ? ['editando' => $id] : ['creando' => true]
            );

            redirigir($volverA($id > 0 ? ['editar' => $id] : ['crear' => 1]));
        }

        if ($accion === 'crear') {
            crearCliente($pdo, $datos);
            avisarFlash('exito', 'Se guardó «' . $datos['nombre'] . '», documento ' . $datos['documento'] . '.');
        } else {
            actualizarCliente($pdo, $id, $datos);
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

$repintar = ($flash['entrada'] ?? []) !== [];
$editando = $editandoId > 0 ? obtenerCliente($pdo, $editandoId) : null;

/* Si se pidió editar algo que no existe, no es un fallo de MySQL: es un enlace
   viejo. Se avisa y se sigue con el alta en blanco. El aviso va en la variable,
   por el mismo motivo que el del orden: a esta altura tomarFlash() ya se ha
   leido, y un avisarFlash() aqui se veria en la pagina siguiente. */
if ($editandoId > 0 && $editando === null) {
    $avisoUrl   = $avisoUrl === null
        ? 'El cliente que se quiso editar no existe. Se muestra el formulario de alta.'
        : $avisoUrl . ' El cliente que se quiso editar tampoco existe, y se muestra el formulario de alta.';
    $editando   = null;
    $editandoId = 0;
}

/* El valor de un campo del formulario, de donde venga.

   El segundo parametro es el nombre de la columna, y hace falta para uno solo:
   el correo. En la base la columna se llama email, que viene del enunciado, y en
   el formulario el campo se llama correo, que es la palabra que ve la persona.
   Son dos idiomas para la misma casilla, y el que las junta es este sitio: ni la
   tabla se renombra (no es nuestra) ni el campo se llama email (se veria raro al
   lado de la etiqueta "Correo").

   Sin ese parametro, editar un cliente con correo lo teaches vacio y, al
   guardar, se perderia el correo sin que nadie lo note. */
$campo = static function (string $nombre, ?string $columna = null, string $porDefecto = '') use ($flash, $repintar, $editando): string {
    if ($repintar) {
        return (string) ($flash['entrada'][$nombre] ?? $porDefecto);
    }

    if ($editando !== null) {
        return (string) ($editando[$columna ?? $nombre] ?? $porDefecto);
    }

    return $porDefecto;
};

/* El error de un campo, o cadena vacía si no tiene. Que devuelva "" y no false
   es lo que permite escribirlo en el HTML sin inventarse una condición. */
$errorDe = static function (string $nombre) use ($errores): string {
    return (string) ($errores[$nombre] ?? '');
};

/* La misma clase .es-invalid que usa productos.php: el borde rojo sale de las
   mismas variables de color en las dos pantallas. */
$claseError = static function (string $nombre) use ($errores): string {
    return isset($errores[$nombre]) && $errores[$nombre] !== '' ? ' es-invalid' : '';
};

/* ===========================================================================
   EL LISTADO
   =========================================================================== */
$total       = contarClientes($pdo, $buscar);
$paginas     = max(1, (int) ceil($total / CLIENTES_POR_PAGINA));
$pagina      = min($pagina, $paginas);
$primeraFila = $total === 0 ? 0 : ($pagina - 1) * CLIENTES_POR_PAGINA + 1;
$ultimaFila  = min($total, $pagina * CLIENTES_POR_PAGINA);

$clientes = listarClientes(
    $pdo,
    $buscar,
    $orden,
    $dirValida,
    CLIENTES_POR_PAGINA,
    ($pagina - 1) * CLIENTES_POR_PAGINA
);

$columnasConEnlace = [
    'nombre'    => 'Nombre',
    'documento' => 'Documento',
    'correo'    => 'Correo',
    'pedidos'   => 'Pedidos',
];

/* Un enlace de orden o de página tiene que llevar todo lo demás de la URL, o se
   pierde la búsqueda en el primer clic. Esta función es la única que arma esos
   enlaces, y por eso ninguno se escribe a mano en el HTML.

   El nombre de la columna vuelve a pasar por la lista blanca antes de entrar en
   la URL: si no, un ?orden= inventado aparecería en todos los enlaces
   siguientes. */
$enlace = static function (array $cambios) use ($buscar, $orden, $dirValida, $pagina): string {
    $params = [
        'buscar' => $buscar,
        'orden'  => resolverOrdenCliente($orden) === null ? 'nombre' : $orden,
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

    /* Una busqueda vacia no va en la URL: "?buscar=&orden=nombre" funciona
       igual, pero se ve feo en la barra de direcciones y en las capturas de la
       bitacora. */
    if (($params['buscar'] ?? '') === '') {
        unset($params['buscar']);
    }

    return urlApp('clientes.php') . '?' . http_build_query($params);
};

$token = tokenCsrf();

/* ===========================================================================
   LAS DOS FUNCIONES DE ESTA PÁGINA QUE NO SON DE PINTAR
   ---------------------------------------------------------------------------
   están aquí y no en el modelo porque no tocan la base de datos: una lee el
   POST y la otra decide si lo leído sirve.
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
function leerEntradaCliente(array $post): array
{
    $claves = ['nombre', 'documento', 'telefono', 'correo'];

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
 * Correo y telefono son opcionales, y aqui no se comprueba que no esten: eso lo
 * hace textoONulo() dentro del modelo, justo antes del INSERT, porque quien sabe
 * que la columna es nullable y que encima tiene un indice UNIQUE es el modelo.
 *
 * @return array{0: array<string, mixed>, 1: array<string, string>}
 */
function validarCliente(PDO $pdo, array $entrada, int $idCliente): array
{
    $errores = [];
    $datos   = [];

    /* ---- El nombre ---- */
    $nombre = $entrada['nombre'] ?? '';

    if ($nombre === '') {
        $errores['nombre'] = 'El nombre del cliente es obligatorio.';
    } elseif (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 60) {
        $errores['nombre'] = 'El nombre tiene que tener entre 3 y 60 letras.';
    } else {
        $datos['nombre'] = $nombre;
    }

    /* ---- El documento: obligatorio y único ---- */
    $documento = $entrada['documento'] ?? '';

    if ($documento === '') {
        $errores['documento'] = 'El documento es obligatorio.';
    } elseif (!preg_match('/^[0-9]{6,13}(-[0-9])?$/', $documento)) {
        /* El formato es el que YA USA LA BASE, y no uno inventado aqui: en
           sql/datos.sql los diez clientes vienen como cedula o NIT, con guion,
           900123456-1 y 890904353-4. Si esta comprobacion aceptara solo digitos,
           no se podria guardar ni uno de esos clientes: al abrir su edicion el
           campo vendria con el guion puesto y el guardado se rechazaria a si
           mismo. Un formulario que no puede editar lo que ya esta en la base
           esta roto, por muy bien hecha que este la validacion.

           El guion es opcional porque un NIT tambien se escribe sin el ultimo
           digito, y se guarda tal cual se escribe. Eso tiene una consecuencia
           que conviene saber: el indice UNIQUE compara el TEXTO, de modo que
           900123456-1 y 900123456 son dos documentos distintos para la base. No
           se normaliza a un solo formato porque el ultimo digito no se puede
           inventar: en los datos de ejemplo es un digito de control y no hay
           ningun algoritmo en el proyecto que lo calcule. */
        $errores['documento'] = 'El documento tiene que ser el número de la cédula o del NIT, con guion opcional. Ejemplo: 900123456-1';
    } elseif (existeDocumentoCliente($pdo, $documento, $idCliente > 0 ? $idCliente : null)) {
        $otro = nombreDuplicadoCliente($pdo, 'documento', $documento, $idCliente > 0 ? $idCliente : null);
        $errores['documento'] = $otro === null
            ? 'Ese documento ya lo tiene otro cliente.'
            : 'Ese documento ya lo tiene «' . $otro . '». Un documento es una sola persona.';
    } else {
        $datos['documento'] = $documento;
    }

    /* ---- El teléfono: opcional, y solo números ---- */
    $telefono = $entrada['telefono'] ?? '';

    if ($telefono === '') {
        $datos['telefono'] = null;
    } elseif (!preg_match('/^[0-9+\s()-]{7,20}$/', $telefono)) {
        $errores['telefono'] = 'El teléfono solo puede tener números, espacios, el + y el guion. Escribiste: «' . $telefono . '».';
    } else {
        $datos['telefono'] = $telefono;
    }

    /* ---- El correo: opcional, con forma de correo y único ---- */
    $correo = $entrada['correo'] ?? '';

    if ($correo === '') {
        $datos['correo'] = null;
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Eso no parece un correo. Ejemplo: persona@correo.com';
    } elseif (mb_strlen($correo) > 120) {
        $errores['correo'] = 'El correo es demasiado largo.';
    } elseif (existeCorreoCliente($pdo, $correo, $idCliente > 0 ? $idCliente : null)) {
        $otro = nombreDuplicadoCliente($pdo, 'email', $correo, $idCliente > 0 ? $idCliente : null);
        $errores['correo'] = $otro === null
            ? 'Ese correo ya lo tiene otro cliente.'
            : 'Ese correo ya lo tiene «' . $otro . '». Al correo se le manda la confirmación del pedido, y solo a una dirección.';
    } else {
        $datos['correo'] = $correo;
    }

    return [$datos, $errores];
}

$tituloPagina = 'Clientes';
$itemActual   = 'clientes';

require __DIR__ . '/app/parciales/cabecera.php';
require __DIR__ . '/app/parciales/menu.php';
?>
<main class="panel__contenido">

  <h1>Clientes</h1>
  <p class="sub">Alta, edición, búsqueda y borrado. Documento y correo no se repiten.</p>

  <?php /* Los mensajes del servidor. Se pintan aqui y no dentro del formulario
          porque valen para las tres acciones: un alta con errores y un borrado
          exitoso son el mismo <ul>. La clase de cada uno sale del tipo de
          flash, y avisarFlash() ya la puso. */ ?>
  <?php if ($flash !== null): ?>
    <ul class="lista-alertas">
      <li class="alerta alerta--<?= esc((string) $flash['tipo']) ?>">
        <strong><?= $flash['tipo'] === 'error' ? 'No se pudo hacer:' : ($flash['tipo'] === 'aviso' ? 'Ojo:' : 'Listo:') ?></strong>
        <?= esc((string) $flash['mensaje']) ?>
      </li>
    </ul>
  <?php endif; ?>

  <?php /* El aviso de la URL, y no del POST. Los dos van aquí y no en un solo
          <ul> porque pueden darse a la vez: se puede editar un id que no existe
          EN una pagina que además llega con un orden inventado, y con un solo
          bloque solo se vería uno de los dos. */ ?>
  <?php if ($avisoUrl !== null): ?>
    <ul class="lista-alertas">
      <li class="alerta alerta--advertencia"><?= esc($avisoUrl) ?></li>
    </ul>
  <?php endif; ?>

  <?php /* ------------------------------------------------------------------
          EL BUSCADOR
          Un formulario con method="get" y sin nada de JavaScript: el buscador
          funciona entero con el teclado. El campo se llama "buscar" porque es el
          nombre que espera el modelo, y el boton dice "Buscar" para que se sepa
          que hay que pulsarlo.
          ------------------------------------------------------------------ */ ?>
  <form class="buscador" method="get" action="clientes.php" role="search">
    <div class="buscador__fila">
      <label class="rotulo-demo" for="buscar">Buscar cliente</label>
      <input class="campo" type="search" id="buscar" name="buscar"
             value="<?= esc($buscar) ?>" placeholder="Nombre, documento o correo">
      <?php /* Se esconde el resto de la URL, para que buscar no borre el
              ordenamiento ni vuelva a la primera pagina. Sin esto, escribir en
              el buscador y buscar devuelve la lista por nombre. */ ?>
      <input type="hidden" name="orden" value="<?= esc($orden) ?>">
      <input type="hidden" name="dir" value="<?= esc($dirValida) ?>">
      <button class="boton" type="submit">Buscar</button>
      <?php if ($buscar !== ''): ?>
        <a class="enlace" href="<?= esc($enlace(['buscar' => '', 'pagina' => ''])) ?>">Quitar la búsqueda</a>
      <?php endif; ?>
    </div>
    <p class="buscador__conteo">
      <?php if ($total === 0): ?>
        No hay clientes que cumplan la búsqueda.
      <?php else: ?>
        Mostrando del <?= $primeraFila ?> al <?= $ultimaFila ?> de <?= $total ?>
        <?= $total === 1 ? 'cliente' : 'clientes' ?>, en <?= $paginas ?>
        <?= $paginas === 1 ? 'página' : 'páginas' ?>.
      <?php endif; ?>
    </p>
  </form>

  <?php /* ------------------------------------------------------------------
          EL LISTADO
          El <caption> no se ve en la tabla, pero es lo que lee un lector de
          pantalla para saber de qué es la tabla: no se ve, pero no sobra. Y la
          tabla va dentro de un div con overflow, que es lo que hace que en el
          teléfono se deslice en horizontal en vez de romper la rejilla.
          La clase del <table> es .tabla-productos y no .tabla-clientes, y no es
          una errata: en este proyecto ese nombre es el de la tabla del sistema,
          y la usan igual componentes.php, usuarios.php y dashboard.php. Una
          clase nueva por pantalla seria copiar los treinta estilos de la tabla
          otra vez, y acabaria haviendo cuatro tablas que se ven igual y divergen
          al primer cambio.
          ------------------------------------------------------------------ */ ?>
  <div class="tabla-scroll">
          <table class="tabla-productos" id="tabla-clientes">
      <caption>Clientes de la casa. Los títulos de columna son enlaces para ordenar.</caption>
      <thead>
        <tr>
          <?php foreach ($columnasConEnlace as $clave => $titulo): ?>
            <?php /* La flechita va en la columna por la que se ordena ahora, y
                    solo si la columna es la que esta activa. Al pulsar otra
                    columna se invierte el sentido, salvo que ya se estaba
                    ordenando por ella, que entonces se da la vuelta. */ ?>
            <?php $esActiva = $orden === $clave; ?>
            <th scope="col" aria-sort="<?= $esActiva ? ($dirValida === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
              <a href="<?= esc($enlace(['orden' => $clave, 'dir' => $esActiva && $dirValida === 'asc' ? 'desc' : 'asc', 'pagina' => ''])) ?>">
                <?= esc($titulo) ?><?= $esActiva ? ($dirValida === 'asc' ? ' ↑' : ' ↓') : '' ?>
              </a>
            </th>
          <?php endforeach; ?>
          <th scope="col">Teléfono</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($clientes === []): ?>
          <tr>
            <td colspan="<?= count($columnasConEnlace) + 2 ?>" class="sub">
              <?php if ($buscar !== ''): ?>
                Ningún cliente cumple la búsqueda «<?= esc($buscar) ?>».
              <?php else: ?>
                No hay clientes. Si es la primera vez que entras, crea el primero con el formulario de abajo.
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($clientes as $cliente): ?>
          <?php $pedidos = (int) $cliente['pedidos']; ?>
          <tr>
            <td><?= esc((string) $cliente['nombre']) ?></td>
            <td class="mono"><?= esc((string) $cliente['documento']) ?></td>
            <td class="mono"><?= esc((string) ($cliente['email'] ?? '—')) ?></td>
            <td><?= $pedidos ?></td>
            <td class="mono"><?= esc((string) ($cliente['telefono'] ?? '—')) ?></td>
            <td class="acciones-fila">
              <a class="boton-mini"
                 href="<?= esc($enlace(['editar' => (string) $cliente['id'], 'pagina' => ''])) ?>">Editar</a>

              <?php /* Borrar va por POST y no por enlace, por el mismo motivo que
                      en productos: un enlace que cambia datos se puede activar
                      con un clic derecho y "abrir en una pestana nueva", y ahi el
                      GET habria borrado al cliente sin que nadie lo pidiera.
                      Ademas lleva token CSRF, porque la sesion por si sola no
                      demuestra que el boton sea de esta pagina. */
              if ($pedidos === 0): ?>
                <form method="post" action="clientes.php" class="acciones-fila">
                  <input type="hidden" name="csrf" value="<?= esc($token) ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                  <?php if ($buscar !== ''): ?>
                    <input type="hidden" name="buscar" value="<?= esc($buscar) ?>">
                  <?php endif; ?>
                  <button class="boton-mini boton-peligro" type="submit">Borrar</button>
                </form>
              <?php else: ?>
                <?php /* Aqui no hay boton de borrar, y el motivo esta escrito al
                        lado. Un boton que se sabe que va a fallar es peor que
                        ningun boton: la persona lo pulsa, pierde el trabajo de
                        escrever el motivo y no entiende nada. La regla esta en el
                        app/modelos/ClienteModelo.php: un cliente con pedidos no
                        se borra, porque los pedidos son historico. */ ?>
                <span class="sub" title="Un cliente con pedidos no se borra: los pedidos son histórico.">Con pedidos</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php /* ------------------------------------------------------------------
          LA PAGINACIÓN
          Solo aparece si hay mas de una pagina. El numero de paginas sale del
          total con el mismo filtro que la tabla (contarClientes), que es la
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
          EL FORMULARIO DE ALTA Y DE EDICIÓN
          Es el mismo formulario para las dos cosas, y el campo oculto "accion"
          dice cual es. Vuelve entero despues de un error, con lo que se
          escribio, gracias al flash.

          novalidate: es la linea que hace que el punto 5 se pueda demostrar
          con JavaScript desactivado. Sin ella, el navegador no dejaria enviar
          el formulario vacio y no habria mensaje del servidor que enseñar. Por
          eso esta pagina NO lleva un archivo de JavaScript propio: los mensajes
          del navegador y los del servidor tienen que decir lo mismo, y la
          forma de lograrlo es que aqui solo haya una validacion, la del
          servidor.
          ------------------------------------------------------------------ */ ?>
  <?php $esEdicion = $editando !== null; ?>
  <section class="tarjeta">
    <h2><?= $esEdicion ? 'Editar cliente' : 'Nuevo cliente' ?></h2>

    <?php if ($esEdicion): ?>
      <p class="sub">Está editando a «<?= esc((string) $editando['nombre']) ?>». Los cambios se guardan con el mismo botón.</p>
    <?php endif; ?>

    <?php /* La lista de errores del campo que toca, escrita una sola vez por
            campo. Se pinta debajo del input, no al lado: el mensaje se
            asocia con el campo con el <p id="error-x"> y el aria-describedby
            del input, que es lo que lee el lector de pantalla. Por eso el id
            se compone y no se escribe a mano. */ ?>

    <form class="formulario" id="form-cliente" method="post" action="clientes.php" novalidate>
      <input type="hidden" name="csrf" value="<?= esc($token) ?>">
      <input type="hidden" name="accion" value="<?= $esEdicion ? 'editar' : 'crear' ?>">
      <?php if ($esEdicion): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <?php if ($buscar !== ''): ?>
        <input type="hidden" name="buscar" value="<?= esc($buscar) ?>">
      <?php endif; ?>

      <div class="formulario__rejilla">

        <div class="campo-grupo">
          <label for="nombre">Nombre</label>
          <input class="campo<?= $claseError('nombre') ?>"
                 type="text" id="nombre" name="nombre"
                 value="<?= esc($campo('nombre')) ?>" maxlength="60" placeholder="Bar El Rinconcito"
                 <?= $errorDe('nombre') !== '' ? 'aria-invalid="true" aria-describedby="error-nombre"' : '' ?>>
          <p class="campo-error" role="alert" id="error-nombre"><?= esc($errorDe('nombre')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="documento">Documento</label>
          <?php /* El ejemplo del marcador es uno REAL de la base, copiado de
                  sql/datos.sql, y no uno inventado: 900123456-1 es el Club de
                  Billar La 8. Poner un ejemplo que no existe en la base
                  confunde, y el que se pone de ejemplo suele acabar siendo el
                  primero que se escribe. El maxlength da 15 porque es lo que
                  mide el documento mas largo que cabe: trece digitos, guion y
                  un digito. */ ?>
          <input class="campo<?= $claseError('documento') ?>"
                 type="text" id="documento" name="documento" inputmode="numeric"
                 value="<?= esc($campo('documento')) ?>" maxlength="15" placeholder="900123456-1"
                 <?= $errorDe('documento') !== '' ? 'aria-invalid="true" aria-describedby="error-documento"' : '' ?>>
          <p class="campo-error" role="alert" id="error-documento"><?= esc($errorDe('documento')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="telefono">Teléfono (opcional)</label>
          <input class="campo<?= $claseError('telefono') ?>"
                 type="text" id="telefono" name="telefono" inputmode="tel"
                 value="<?= esc($campo('telefono')) ?>" maxlength="20" placeholder="3105551234"
                 <?= $errorDe('telefono') !== '' ? 'aria-invalid="true" aria-describedby="error-telefono"' : '' ?>>
          <p class="campo-error" role="alert" id="error-telefono"><?= esc($errorDe('telefono')) ?></p>
        </div>

        <div class="campo-grupo">
          <label for="correo">Correo (opcional)</label>
          <input class="campo<?= $claseError('correo') ?>"
                 type="text" id="correo" name="correo"
                 value="<?= esc($campo('correo', 'email')) ?>" maxlength="120" placeholder="persona@correo.com"
                 <?= $errorDe('correo') !== '' ? 'aria-invalid="true" aria-describedby="error-correo"' : '' ?>>
          <p class="campo-error" role="alert" id="error-correo"><?= esc($errorDe('correo')) ?></p>
        </div>

      </div>

      <div class="formulario__acciones">
        <button class="boton" type="submit"><?= $esEdicion ? 'Guardar cambios' : 'Crear cliente' ?></button>
        <?php if ($esEdicion): ?>
          <a class="boton boton--secundario" href="<?= esc(urlApp('clientes.php')) ?>">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

</main>
<?php
require __DIR__ . '/app/parciales/pie.php';
