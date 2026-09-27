<?php
declare(strict_types=1);

require_once __DIR__ . '/Autenticacion.php';

/* ===========================================================================
   LA CARAMBOLA DORADA - Alta de usuarios
   Archivo: app/controladores/registro.php
   Actividad de recuperacion, dia 10, punto 2

   Lo que hace este archivo es el punto 2 del enunciado: tomar la contrasena que
   escribio una persona, convertirla en hash con password_hash() y guardar en
   la base SOLO ese hash.

   Lo que devuelve la funcion es un arreglo con dos cosas:

     'errores'  lista de mensajes, vacia si todo fue bien
     'usuario'  los datos del usuario creado, para que la pagina los muestre

   ---------------------------------------------------------------------------
   LOS TRES USUARIOS DEL ENUNCIADO
   ---------------------------------------------------------------------------
   El enunciado pide un administrador, un vendedor y un consultor. Los tres se
   crean pasando por aqui, escribiendo en el formulario, no con un INSERT escrito
   a mano. La razon es que la contrasena llega en el POST, se hashea en PHP y se
   guarda unicamente el hash. Si el usuario se creara por SQL, su contrasena
   quedaria escrita en un archivo de texto plano, que es justo lo que el
   enunciado prohibe.
   =========================================================================== */

/** Los tres roles que admite la tabla. */
const ROLES = ['administrador', 'vendedor', 'consultor'];

/** Longitud minima de la contrasena. La misma que exige el CHECK de la tabla. */
const CLAVE_MINIMA = 8;

/**
 * Valida los datos del formulario y, si todo esta bien, crea el usuario.
 *
 * @param array<string, string> $datos  lo que vino en el POST
 * @return array{errores: string[], usuario: ?array}
 */
function procesarRegistro(PDO $pdo, array $datos): array
{
    $errores = [];

    $nombre = trim($datos['nombre'] ?? '');
    $correo = trim($datos['correo'] ?? '');
    $clave  = (string) ($datos['clave'] ?? '');
    $rol    = trim($datos['rol'] ?? '');

    /* --- El nombre ------------------------------------------------------ */
    if ($nombre === '') {
        $errores[] = 'Escribe el nombre de la persona.';
    } elseif (mb_strlen($nombre) > 80) {
        /* mb_strlen y no strlen: strlen cuenta BYTES, y en UTF-8 un acento son
           dos. Con strlen, un nombre de 41 letras con tildes pasaria y uno de
           42 no, que es un limite que depende de como se escriba. */
        $errores[] = 'El nombre no puede pasar de 80 caracteres.';
    }

    /* --- El correo ------------------------------------------------------ */
    if ($correo === '') {
        $errores[] = 'Escribe el correo electrónico.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ese correo no tiene un formato válido.';
    } elseif (mb_strlen($correo) > 190) {
        $errores[] = 'El correo no puede pasar de 190 caracteres.';
    } elseif (correoExiste($pdo, $correo)) {
        $errores[] = 'Ese correo ya está registrado.';
    }

    /* --- La contrasena -------------------------------------------------- */
    if ($clave === '') {
        $errores[] = 'Escribe la contraseña.';
    } elseif (strlen($clave) < CLAVE_MINIMA) {
        $errores[] = 'La contraseña necesita al menos ' . CLAVE_MINIMA . ' caracteres.';
    }

    /* --- El rol --------------------------------------------------------- */
    if (!in_array($rol, ROLES, true)) {
        $errores[] = 'Elija uno de los roles de la lista.';
    }

    if ($errores !== []) {
        return ['errores' => $errores, 'usuario' => null];
    }

    /* A partir de aqui ya no hay nada que pueda salir mal de la validacion, y
       la contrasena solo existe en la memoria de PHP. Cuando este archivo termine
       se borra sola, y en la base quedo unicamente su hash. */
    $creado = crearUsuario($pdo, $nombre, $correo, $clave, $rol);

    return [
        'errores' => [],
        'usuario' => [
            'id'         => $creado['id'],
            'nombre'     => $nombre,
            'correo'     => $correo,
            'rol'        => $rol,
            'caracteres' => strlen($clave),
            /* El hash que quedo en la base, el mismo que se comprueba en el
               punto 4 en phpMyAdmin. NUNCA la contrasena. */
            'clave_hash' => $creado['clave_hash'],
        ],
    ];
}
