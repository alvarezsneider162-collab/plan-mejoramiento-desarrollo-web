<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Mensajes de una sola vez
   Archivo: app/seguridad/flash.php
   Actividad de recuperacion, dia 13, puntos 1, 2, 3 y 5

   Este archivo resuelve un problema que aparece en cuanto hay formularios de
   verdad: en PHP, guardar y avisar son dos peticiones distintas, y avisar en la
   misma que guarda tiene dos efectos malos.

   ---------------------------------------------------------------------------
   EL PROBLEMA CONCRETO
   ---------------------------------------------------------------------------
   Guardar un producto y acontinuacion redirigir con

       header('Location: productos.php');  exit;

   es lo que se hace siempre, y se hace por una razon buena: si el visitante
   recarga la pagina con F5, el navegador vuelve a enviar el POST y se guarda
   OTRO producto. El patron se llama PRG, de Post/Redirect/Get, y por eso el
   POST acaba en un 302 y nunca en un 200.

   La consecuencia es que el mensaje "Producto creado" ya no puede ir en el
   cuerpo de la respuesta del POST, porque ese cuerpo se tira con la
   redireccion. Va en la sesion, y la pagina que se carga al final lo lee y lo
   borra. Eso es un mensaje flash.

   ---------------------------------------------------------------------------
   POR QUE ADEMAS SE GUARDA LO QUE ESCRIBIO EL USUARIO
   ---------------------------------------------------------------------------
   Un formulario con errores que ademas se vacia es un formulario inutil: si
   alguien escribio veinte lineas de un pedido y le equivoco en el correo del
   cliente, perder todo es la peor respuesta posible. Con el patron PRG los
   valores anteriores tambien viajan en la sesion, y el formulario se vuelve a
   pintar con lo que habia escrito, con el error marcado al lado de su campo.

   Eso tiene una ventaja extra que el dia 13 aprovecha: como los errores estan
   en la sesion y no en el HTML del POST, cualquier carga de la pagina los
   muestra. Eso es lo que permite la prueba del punto 5 con el JavaScript
   desactivado: el mensaje que se ve en la captura lo ha escrito el SERVIDOR,
   no un script del navegador.

   ---------------------------------------------------------------------------
   LO QUE SE GUARDA, EN UNA SOLA LLAVE
   ---------------------------------------------------------------------------
   $_SESSION['dia13_flash'] = [
       'tipo'    => 'exito' | 'error' | 'aviso' | 'info',
       'mensaje' => 'Texto de una linea para el aviso grande',
       'errores' => ['campo' => 'Lo que no cuadra de ese campo', ...],
       'entrada' => ['nombre' => 'lo que habia escrito', ...],
       'extra'   => ['lo que haga falta', ...],
   ];

   Las tres primeras llaves son obvias. 'entrada' es lo que se relee en los
   value="" del formulario, y 'extra' es para lo que no es un campo del
   formulario: el id del producto recien creado, el id del pedido, el detalle
   que se quiere ensear en un aviso largo.

   ---------------------------------------------------------------------------
   POR QUE LAS DOS LECTURAS BORRAN
   ---------------------------------------------------------------------------
   leerFlash() y leerEntrada() vacian lo que devuelven. Un mensaje que se
   queda guardado se volveria a ver en cada recarga de la pagina durante toda
   la sesion, y "Producto creado" repetido veinte veces parece un fallo. Que
   se lea una vez y se borre es justo lo que significa flash.

   Y las dos lecturas van juntas en una sola funcion, tomarFlash(), porque si el
   mensaje se leyera en un sitio y los errores en otro, con el codigo
   intermedio, un error podria quedar sin su mensaje o al reves.
   =========================================================================== */

/**
 * Guarda un aviso para la proxima peticion.
 *
 * @param array<string, string> $errores Mensajes por campo del formulario.
 * @param array<string, mixed>  $entrada Lo que habia escrito, para repintarlo.
 * @param array<string, mixed>  $extra   Datos que la pagina necesite aparte.
 */
function guardarFlash(
    string $tipo,
    string $mensaje,
    array $errores = [],
    array $entrada = [],
    array $extra = []
): void {
    /* $_SESSION['dia13_flash'] se reescribe entero, no se mezcla con lo que
       hubiera. Si en una peticion se guardan dos avisos, el segundo tiene que
       sustituir al primero, no apilarse debajo: un formulario que ademas de
       fallar avisa de otra cosa imprimiria dos avisos a la vez. */
    $_SESSION['dia13_flash'] = [
        'tipo'    => $tipo,
        'mensaje' => $mensaje,
        'errores' => $errores,
        'entrada' => $entrada,
        'extra'   => $extra,
    ];
}

/**
 * Lee el aviso de la peticion anterior y lo borra.
 *
 * Devuelve null si no habia nada, que es el caso normal de la primera visita a
 * una pagina.
 *
 * @return array{tipo: string, mensaje: string, errores: array<string, string>, entrada: array<string, mixed>, extra: array<string, mixed>}|null
 */
function tomarFlash(): ?array
{
    if (!isset($_SESSION['dia13_flash']) || !is_array($_SESSION['dia13_flash'])) {
        return null;
    }

    $flash = $_SESSION['dia13_flash'];

    unset($_SESSION['dia13_flash']);

    /* El type() no es por capricho: los tres bloques llegan de $_POST, que es
       un arreglo de valores que puede ser de cualquier tipo si alguien manda
       algo raro. Con return type y sin comprobar, una entrada con un array
       dentro haria que la pagina que lo pinta reviente con un error en vez de
       pintar el aviso. */
    return [
        'tipo'    => (string) ($flash['tipo'] ?? 'info'),
        'mensaje' => (string) ($flash['mensaje'] ?? ''),
        'errores' => is_array($flash['errores'] ?? null) ? $flash['errores'] : [],
        'entrada' => is_array($flash['entrada'] ?? null) ? $flash['entrada'] : [],
        'extra'   => is_array($flash['extra'] ?? null) ? $flash['extra'] : [],
    ];
}

/**
 * Un atajo para cuando solo hay que avisar y no hay formulario que repintar.
 */
function avisarFlash(string $tipo, string $mensaje): void
{
    guardarFlash($tipo, $mensaje);
}
