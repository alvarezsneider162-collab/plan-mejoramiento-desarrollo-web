<?php
declare(strict_types=1);

require_once __DIR__ . '/Autenticacion.php';

/* ===========================================================================
   LA CARAMBOLA DORADA - Ingreso
   Archivo: app/controladores/login.php
   Actividad de recuperacion, dia 10, punto 3

   Este archivo es el que decide si alguien entra o no. Se apoya en
   Autenticacion.php, que es donde estan las consultas y el password_verify().

   ---------------------------------------------------------------------------
   EL MENSAJE GENERICO, Y POR QUE ES LO UNICO QUE SE MUESTRA
   ---------------------------------------------------------------------------
   El enunciado pide que el mensaje de error sea IGUAL para correo inexistente
   y para contrasena equivocada, y eso es lo que hace $mensajeGenerico mas
   abajo. La razon de fondo:

   Si el texto fuera distinto en cada caso, la pagina misma serviria para
   preguntar que correos estan registrados: se prueban, se mira si el error
   cambia y se anotan los que dicen "usuario no encontrado". Con un solo
   mensaje, el atacante no puede distinguir los dos casos y solo puede probar
   correos a ciegas.

   Por la misma razon, el caso de la cuenta BLOQUEADA tambien dice lo mismo. Es
   tentador poner ahi "cuenta bloqueada, intente mas tarde" porque queda mas
   claro para el que se equivoca cinco veces, pero ese texto solo aparece si el
   correo EXISTE, y vuelve a decir cual existe y cual no. El bloqueo se
   demuestra en la tabla usuarios, en la columna bloqueado_hasta, que es donde
   de verdad esta el dato.

   ---------------------------------------------------------------------------
   EL ORDEN DE LAS COMPROBACIONES
   ---------------------------------------------------------------------------
   1. Token CSRF. Si no esta bien, se corta con un HTTP_TOKEN_INVALIDO y no se
      mira nada mas.
   2. Formato del correo y largo de la contrasena. Si estan mal, es el mismo
      mensaje generico: aunque el formato este mal, no se le dice a nadie si ese
      correo existe.
   3. Se busca el usuario.
   4. Si esta bloqueado, se anota el intento y se corta con el mismo mensaje.
   5. password_verify(). Aqui, y solo aqui, se sabe si la contrasena es la
      correcta. Nunca se compara el hash con ==: eso lo hace password_verify(),
      que ademas tarda lo mismo haya acierto o no.
   6. Fallos en la ventana. Si son cinco, se bloquea.
   =========================================================================== */

/**
 * UN texto, y solo uno, para todos los fallos.
 *
 * No lleva el correo ni el motivo dentro. Si llevara el motivo, volveria a
 * servir para averiguar que correos estan dados de alta.
 */
const MENSAJE_GENERICO = 'Correo o contraseña incorrectos.';

/**
 * Intenta iniciar sesion.
 *
 * @param array<string, mixed> $post  el $_POST tal cual
 * @return array{error: string, usuario: ?array, codigo: int}
 *         codigo es el estado HTTP que deberia devolver la pagina: 200 con el
 *         formulario y un error, o el codigo de HTTP_TOKEN_INVALIDO cuando el token
         no valia.
 */
function procesarLogin(PDO $pdo, array $post): array
{
    $fallo = static fn(): array => ['error' => MENSAJE_GENERICO, 'usuario' => null, 'codigo' => 200];

    /* --- 1. Token CSRF -------------------------------------------------- */
    if (!validarCsrf(isset($post['csrf']) && is_string($post['csrf']) ? $post['csrf'] : null)) {
        /* El codigo sale de la constante de app/seguridad/csrf.php. Ahi esta
           escrito por que se usa 403 y no el 419 del ejemplo del enunciado. */
        return [
            'error'   => 'La sesión del formulario no es válida. Recargue la página e intente de nuevo.',
            'usuario' => null,
            'codigo'  => HTTP_TOKEN_INVALIDO,
        ];
    }

    $correo = trim((string) ($post['correo'] ?? ''));
    $clave  = (string) ($post['clave'] ?? '');
    $ip     = ipVisitante();

    /* --- 2. Formato ----------------------------------------------------- */
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($clave) < 8) {
        /* Se anota el intento igual. Si alguien manda cien correos con formato
           invalido, queda registrado que se intento cien veces. */
        registrarIntento($pdo, $correo, false, $ip);

        return $fallo();
    }

    /* --- 3. Buscar el usuario ------------------------------------------- */
    $usuario = usuarioPorCorreo($pdo, $correo);

    if ($usuario === null) {
        /* El correo no existe. Aun asi se gasta el mismo tiempo que si
           existiera, verificando la clave contra un hash de mentira. Sin esto,
           esta respuesta seria mas rapida que la de un correo que si existe, y
           ese hueco de milisegundos acaba delatando que correos estan
           registrados. */
        password_verify($clave, HASH_SENUELO);

        registrarIntento($pdo, $correo, false, $ip);

        return $fallo();
    }

    /* --- 4. Bloqueado --------------------------------------------------- */
    if (estaBloqueado($usuario) || (int) $usuario['activo'] !== 1) {
        /* Cuenta dada de baja y cuenta bloqueada se tratan igual, y con el
           mismo mensaje, por el mismo motivo de no destapar que correos
           existen. El POST de una cuenta bloqueada SI se anota como fallo, para
           que se vea que se sigue insistiendo. */
        registrarIntento($pdo, $correo, false, $ip);

        return $fallo();
    }

    /* --- 5. La contrasena ----------------------------------------------- */
    if (!verificarClave($pdo, $usuario, $clave)) {
        registrarIntento($pdo, $correo, false, $ip);

        /* --- 6. Van cinco fallos en quince minutos, se bloquea ---------- */
        if (fallosEnVentana($pdo, $correo) >= MAX_INTENTOS) {
            aplicarBloqueo($pdo, (int) $usuario['id']);
        }

        return $fallo();
    }

    /* --- Entra ---------------------------------------------------------- */
    registrarIntento($pdo, $correo, true, $ip);

    return ['error' => '', 'usuario' => $usuario, 'codigo' => 200];
}

/**
 * La IP de quien esta entrando, o null si el servidor no la da.
 *
 * El punto 3 no la pide, pero sin IP el bloqueo se puede tumbar desde cualquier
 * parte: el conteo de fallos es por correo, asi que con cinco intentos desde
 * cinco maquinas distintas se bloquea la cuenta de otra persona. Guardarla deja
 * la traza de quien fue. Detras de un proxy sale la del proxy, que tampoco es
 * la solucion definitiva, pero algo es algo.
 */
function ipVisitante(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!is_string($ip) || $ip === '') {
        return null;
    }

    /* 45 caracteres es lo que cabe un IPv6 completo, que es lo que dice la
       columna. Si viene algo mas largo, es basura y no se guarda. */
    return strlen($ip) <= 45 ? $ip : null;
}
