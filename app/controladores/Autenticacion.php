<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/conexion.php';

/* ===========================================================================
   LA CARAMBOLA DORADA - Usuarios, intentos y bloqueo
   Archivo: app/controladores/Autenticacion.php
   Actividad de recuperacion, dia 10, puntos 2, 3 y 4

   Aqui esta todo lo que se hace con las tablas usuarios e intentos_acceso:

     - crearUsuario()     guarda un usuario con password_hash()
     - usuarioPorCorreo() busca uno por correo, con la clave hasheada
     - registrarIntento() anota cada intento, salga bien o mal
     - fallosEnVentana()  cuenta los fallos de un correo en 15 minutos
     - aplicarBloqueo()   bloquea la cuenta cuando son cinco

   ---------------------------------------------------------------------------
   LO QUE NO HACE NINGUNA DE ESTAS FUNCIONES
   ---------------------------------------------------------------------------
   - No guarda contrasenas. Nunca. Lo que se guarda es el hash.
   - No usa md5(), ni sha1(), ni sha256(), ni comparable de cadenas para las
     contrasenas. password_hash() y password_verify() son la unica via.
   - No compara dos hashes con == ni con ===. La comparacion la hace
     password_verify(), que ademas compara en tiempo constante.
   - No hace ninguna consulta con el texto pegado dentro: todo va por prepare()
     con marcadores, como en el dia 9.

   ---------------------------------------------------------------------------
   POR QUE password_hash() Y NO UN HASH PROPIO
   ---------------------------------------------------------------------------
   Un hash del dia 10 tiene que cumplir tres cosas:

     1. No se puede volver atras. De un hash no sale la contrasena. Un md5 se
        rompe en segundos con una tabla de valores ya calculados.
     2. La misma contrasena tiene que dar hashes DISTINTOS cada vez, y aun asi
        los dos tienen que servir para entrar. Eso se llama sal. Sin sal, dos
        usuarios con la misma contrasena tendrian el mismo hash en la base, y
        con solo mirar la tabla se sabria que usan la misma clave.
     3. El coste de comprobacion se puede subir con los anos. md5 se puede
        calcular millones de veces por segundo, asi que un atacante prueba
        millones de claves por segundo. bcrypt, que es lo que hace
        PASSWORD_DEFAULT, va a ratos a proposito.

   password_hash() con PASSWORD_DEFAULT hoy produce bcrypt, que empieza por
   $2y$ y ocupa 60 caracteres. La columna es VARCHAR(255) para que el dia que
   el algoritmo por defecto sea argon2, que ocupa mas, los hashes viejos sigan
   cabiendo.
   =========================================================================== */

/** Ventana de bloqueo, en minutos. El enunciado pide cinco fallos en quince. */
const MINUTOS_VENTANA = 15;

/** Cuantos fallos hay que meter en esa ventana para bloquear. */
const MAX_INTENTOS = 5;

/**
 * Un hash de mentira, solo para gastar el mismo tiempo que un hash de verdad.
 *
 * Cuando el correo no existe, password_verify() no se puede llamar con la clave
 * del visitante, porque no hay hash con que compararla. Si en ese caso se
 * devolviera el error de inmediato, la respuesta seria mas rapida que cuando el
 * correo si existe, y ese hueco de unos milisegundos acaba diciendo que correos
 * estan registrados. Por eso se verifica contra este hash, que no corresponde a
 * ninguna clave.
 */
const HASH_SENUELO = '$2y$10$e0NRxbA0MBx7T/YBLcOOOZeve1U0/KdzOWaQX8DlLtdLLbFSpNS1a';

/**
 * Crea un usuario y devuelve su id y el hash que guardo.
 *
 * $nombre, $correo, $rol van tal cual a la base. $clave NO: lo que se guarda es
 * su hash, y a la columna caracteres solo va cuantos caracteres tenia.
 *
 * Se devuelve el hash guardado y no uno nuevo, a proposito. Si se volviera a
 * hashear aqui la clave para ensenarsela a la pagina, saldria un hash DISTINTO
 * al de la base, porque cada llamada a password_hash() le pone una sal nueva.
 * La pagina tiene que ensenar el hash que de verdad quedo en la tabla, que es el
 * mismo que se va a comprobar en el punto 4.
 *
 * El nombre se guarda tal cual, sin escapar ni limpiar. Escribirlo con
 * htmlspecialchars() antes de guardarlo estaria MAL: el dato en la base tiene
 * que ser el nombre de verdad, con su <script> si lo tiene. Quien lo escapa es
 * la pagina, al imprimirlo. Esa separacion es la que hace que el punto 5 sea
 * una prueba de verdad y no un detalle de adorno.
 *
 * @return array{id: int, clave_hash: string}
 */
function crearUsuario(
    PDO $pdo,
    string $nombre,
    string $correo,
    string $clave,
    string $rol
): array {
    /* PASSWORD_DEFAULT es lo que se pide: el algoritmo mas seguro que tenga
       esta version de PHP, hoy bcrypt. Escribir bcrypt a mano seria dejar de
       aprovechar las mejoras de las versiones siguientes. */
    $hash = password_hash($clave, PASSWORD_DEFAULT);

    $st = $pdo->prepare(
        'INSERT INTO usuarios (caracteres, rol, nombre, correo, clave_hash)
         VALUES (:caracteres, :rol, :nombre, :correo, :clave_hash)'
    );
    $st->bindValue(':caracteres', strlen($clave), PDO::PARAM_INT);
    $st->bindValue(':rol',        $rol,              PDO::PARAM_STR);
    $st->bindValue(':nombre',     $nombre,           PDO::PARAM_STR);
    $st->bindValue(':correo',     $correo,           PDO::PARAM_STR);
    $st->bindValue(':clave_hash', $hash,             PDO::PARAM_STR);
    $st->execute();

    return ['id' => (int) $pdo->lastInsertId(), 'clave_hash' => $hash];
}

/**
 * Busca un usuario por correo. Devuelve null si no existe.
 *
 * Trae clave_hash y bloqueado_hasta, que son los dos datos que hacen falta
 * para decidir, pero el nombre de la columna deja claro que es un hash y no la
 * contrasena.
 */
function usuarioPorCorreo(PDO $pdo, string $correo): ?array
{
    /* La columna "bloqueado" no existe en la tabla: se calcula en la propia
       consulta. Lo hace MySQL y no PHP a proposito, y la razon es que comparar
       una fecha en PHP es comparar contra el reloj equivocado. En esta maqueta
       PHP corre en Europe/Berlin y MySQL en America/Bogota, siete horas
       distintas, asi que un strtotime() sobre la fecha que escribio MySQL la
       entendia en hora de Berlin y el bloqueo de quince minutos parecia haber
       expirado nada mas empezar. Si la pregunta la hace la base, compara
       bloqueado_hasta contra su propio NOW(), que es la misma hora con la que
       lo escribio, y las dos horas no tienen por que coincidir. */
    $st = $pdo->prepare(
        'SELECT id, nombre, correo, rol, clave_hash, activo, bloqueado_hasta,
                (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado
           FROM usuarios
          WHERE correo = :correo
          LIMIT 1'
    );
    $st->bindValue(':correo', $correo, PDO::PARAM_STR);
    $st->execute();

    $fila = $st->fetch();

    return $fila === false ? null : $fila;
}

/** Dice si el correo ya esta registrado. */
function correoExiste(PDO $pdo, string $correo): bool
{
    $st = $pdo->prepare('SELECT 1 FROM usuarios WHERE correo = :correo LIMIT 1');
    $st->bindValue(':correo', $correo, PDO::PARAM_STR);
    $st->execute();

    return $st->fetchColumn() !== false;
}

/**
 * Anota un intento de acceso en la tabla intentos_acceso.
 *
 * Se anota TAMBIEN el intento con correo inexistente, a proposito. La tabla
 * guarda el correo, no la contrasena: ni la que fue, ni la que se escribio. Asi
 * el historico de intentos no es un archivo de contrasenas.
 */
function registrarIntento(PDO $pdo, string $correo, bool $exito, ?string $ip = null): void
{
    $st = $pdo->prepare(
        'INSERT INTO intentos_acceso (correo, exito, ip)
         VALUES (:correo, :exito, :ip)'
    );
    $st->bindValue(':correo', $correo,   PDO::PARAM_STR);
    $st->bindValue(':exito',  $exito ? 1 : 0, PDO::PARAM_INT);
    $st->bindValue(':ip',     $ip,      $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->execute();
}

/**
 * Cuenta los intentos fallidos de un correo en los ultimos 15 minutos.
 *
 * El calculo de la ventana lo hace la propia base de datos con
 * DATE_SUB(NOW(), INTERVAL 15 MINUTE), no PHP. Asi la ventana se cuenta con la
 * hora del servidor de la base, que es la misma para todos, y no con la hora
 * de cada pagina.
 *
 * Solo cuenta exito = 0. Un ingreso correcto no se suma a los fallos, y los
 * fallos viejos se van quedando fuera solos cuando pasan los 15 minutos: por
 * eso esta consulta no borra nada y no hay nada que limpiar.
 *
 * El 15 va pegado dentro de la cadena porque MySQL no acepta un marcador de
 * prepared en la parte de INTERVAL. Eso NO es una consulta con texto del
 * visitante pegado dentro: es la constante MINUTOS_VENTANA, declarada mas arriba
 * en este mismo archivo, que es un entero fijo y que nadie de fuera puede
 * cambiar. El correo, que si viene de quien escribe en el formulario, va con su
 * marcador.
 */
function fallosEnVentana(PDO $pdo, string $correo): int
{
    $st = $pdo->prepare(
        'SELECT COUNT(*)
           FROM intentos_acceso
          WHERE correo     = :correo
            AND exito      = 0
            AND creado_en >= DATE_SUB(NOW(), INTERVAL ' . MINUTOS_VENTANA . ' MINUTE)'
    );
    $st->bindValue(':correo', $correo, PDO::PARAM_STR);
    $st->execute();

    return (int) $st->fetchColumn();
}

/**
 * Dice si un usuario esta bloqueado ahora mismo.
 *
 * La respuesta no se calcula aqui: viene en $usuario['bloqueado'], que MySQL
 * calculo en la consulta de usuarioPorCorreo() comparando bloqueado_hasta
 * contra su propio NOW(). Esta funcion solo lee ese 1 o 0.
 *
 * Antes comparaba las fechas en PHP, con strtotime() y time(). Funcionaba solo
 * si PHP y MySQL tuvieran la misma hora, y en esta maqueta no la tienen: PHP
 * corre en Europe/Berlin y MySQL en America/Bogota. Con siete horas de
 * diferencia, un bloqueo puesto para dentro de quince minutos se leia como
 * vencido y la cuenta entraba con la contrasena correcta. La cuenta de fallos,
 * fallosEnVentana(), ya lo hacia bien, preguntando a la base; esto se dejo
 * igual de esthetico.
 */
function estaBloqueado(array $usuario): bool
{
    return (int) $usuario['bloqueado'] === 1;
}

/**
 * Bloquea la cuenta hasta dentro de 15 minutos.
 *
 * Se llama en el quinto fallo. El campo queda con la fecha en vez de un SI/NO
 * porque asi el bloqueo se levanta solo solo: en quince minutos, bloqueado_hasta
 * ya es un pasado y la cuenta vuelve a estar usable sin que nadie la desbloquee
 * a mano.
 */
function aplicarBloqueo(PDO $pdo, int $usuarioId): void
{
    $st = $pdo->prepare(
        'UPDATE usuarios
            SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . MINUTOS_VENTANA . ' MINUTE)
          WHERE id = :id'
    );
    $st->bindValue(':id', $usuarioId, PDO::PARAM_INT);
    $st->execute();
}

/** Quita el bloqueo de una cuenta. */
function levantarBloqueo(PDO $pdo, int $usuarioId): void
{
    $st = $pdo->prepare('UPDATE usuarios SET bloqueado_hasta = NULL WHERE id = :id');
    $st->bindValue(':id', $usuarioId, PDO::PARAM_INT);
    $st->execute();
}

/**
 * Revisa el hash con la clave que escribio el visitante.
 *
 * Si el hash se hizo con un algoritmo que ya no es el que se usa ahora, se
 * vuelve a hashear la clave y se guarda el hash nuevo. Asi, sin hacer nada, las
 * cuentas viejas van modernizandose solas la primera vez que su
 * dueno entra.
 */
function verificarClave(PDO $pdo, array $usuario, string $clave): bool
{
    if (!password_verify($clave, (string) $usuario['clave_hash'])) {
        return false;
    }

    if (password_needs_rehash((string) $usuario['clave_hash'], PASSWORD_DEFAULT)) {
        $st = $pdo->prepare('UPDATE usuarios SET clave_hash = :nuevo WHERE id = :id');
        $st->bindValue(':nuevo', password_hash($clave, PASSWORD_DEFAULT), PDO::PARAM_STR);
        $st->bindValue(':id', (int) $usuario['id'], PDO::PARAM_INT);
        $st->execute();
    }

    return true;
}
