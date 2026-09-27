<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Modelo de usuario
   Archivo: app/modelos/UsuarioModelo.php
   Actividad de recuperacion, dia 11, punto 4

   Consulta que lee la lista de usuarios para usuarios.php. Es el hermano de
   ProductoModelo.php y sigue su mismo reparto: aqui vive el SQL, y en la pagina
   solo queda el HTML.

   ---------------------------------------------------------------------------
   POR QUE NO HAY UNA CLASE CON UN CONSTRUCTOR
   ---------------------------------------------------------------------------
   ProductoModelo.php no tiene clase: son funciones sueltas que reciben el PDO
   como primer argumento. Eso es lo que hace el proyecto, asi que este archivo
   hace lo mismo. La razon tecnica es que conexion.php ya abre una sola conexion
   y la comparte por require_once, de modo que el PDO no hay que pasarlo de un
   sitio a otro guardandolo en objetos: con que la pagina lo pida, ya es la
   misma.

   ---------------------------------------------------------------------------
   LO QUE ESTA CONSULTA NO TRAE, Y POR QUE
   ---------------------------------------------------------------------------
   La tabla usuarios tiene nueve columnas, y esta consulta pide seis:

       id, nombre, correo, rol, activo, creado_en

   Quedan fuera tres, y las tres se quedan fuera a proposito:

     - clave_hash. Es el hash de la contrasena. No hace falta para pintar una
       lista, y si se pidiera, el dia en que alguien acerque este listado a una
       pantalla, a una copia de seguridad o a un log, el hash se va con el. Un
       hash no es una contrasena, pero es material que sirve para atacar, asi
       que la regla es no pedir lo que no se usa.

     - bloqueado_hasta. Sirve para el login, que es donde se lee. En una lista
       de usuarios no se muestra.

     - caracteres. Es el numero de letras de la contrasena. No aporta nada a
       quien mira la lista.

   Ademas, el SELECT dice las columnas por su nombre y en el orden que se
   quieren. No se hace SELECT *: si manana alguien anade una columna con un
   secreto, un SELECT * la traeria en silencio y esta pagina ni se enteraria.
   ------------------------------------------------------------------------== */

/**
 * Devuelve todos los usuarios, del id mas bajo al mas alto.
 *
 * No lleva WHERE porque la idea es verlos a todos, y lo que decide quien puede
 * ver la lista no es la consulta: es exigirRol('administrador') en la pagina.
 * Que la consulta traiga a todos no significa que cualquiera la vea; la
 * proteccion va antes, en la pagina, y por eso no se filtra aqui.
 *
 * El nombre se escapa en la pagina, no aqui. Que el dato salga crudo del modelo
 * es lo correcto: el modelo devuelve datos, y lo que se haga con ellos en la
 * capa que los dibuja es otra historia. Escapar en las dos partes molesta:
 * esc() dos veces, y el texto sale con &amp;lt; en pantalla.
 *
 * @return array<int, array<string, mixed>>
 */
function listarUsuarios(PDO $pdo): array
{
    $st = $pdo->prepare(
        'SELECT id, nombre, correo, rol, activo, creado_en
         FROM usuarios
         ORDER BY id'
    );

    $st->execute();

    return $st->fetchAll();
}

/**
 * Cuenta cuantos usuarios hay, para el rotulo del titulo.
 *
 * Es un COUNT aparte y no un count() sobre la lista a proposito. Con la lista ya
 * en memoria, count($filas) daria el mismo numero sin tocar la base. Pero este
 * archivo tambien lo usa una pagina que solo quiere el total y no la lista, y
 * asi las dos cosas son el mismo SQL en vez de dos caminos distintos que pueden
 * dar numeros distintos si alguien cambia uno y se olvida del otro.
 */
function contarUsuarios(PDO $pdo): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios');
    $st->execute();

    return (int) $st->fetchColumn();
}
