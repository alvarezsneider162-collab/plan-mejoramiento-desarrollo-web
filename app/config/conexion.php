<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - Conexion a la base de datos
   Archivo: app/config/conexion.php
   Actividad de recuperacion, dia 9, punto 3

   Este archivo se escribe una vez y todo el proyecto lo reutiliza. La clase
   guarda la conexion en una variable estatica, asi que aunque veinte archivos
   la pidan, se abre una sola conexion y no veinte.

   Sigue el patron que da el enunciado. Lo unico que se le anade es un manejo
   de error que no se traga la excepcion: si MySQL no esta arriba, la pagina
   dice cual fue el problema en vez de mostrar una pantalla en blanco.
   =========================================================================== */

final class Conexion
{
    /** La conexion viva. null todavia no se ha abierto. */
    private static ?PDO $pdo = null;

    /** No se instancia: todos los metodos son estaticos. */
    private function __construct()
    {
    }

    /**
     * Devuelve la conexion, abriendola la primera vez que se llama.
     */
    public static function obtener(): PDO
    {
        if (self::$pdo === null) {
            /* El archivo de credenciales esta en el .gitignore. El que se
               sube al repositorio es credenciales.example.php, que tiene la
               misma forma pero con valores de mentira. */
            $cfg = require __DIR__ . '/credenciales.php';

            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['bd']};charset=utf8mb4";

            try {
                self::$pdo = new PDO($dsn, $cfg['usuario'], $cfg['clave'], [
                    /* Que los errores de MySQL se lancer como excepciones de PHP
                       y no como avisos que se pueden pasar por alto. */
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                    /* Cada fila viene como arreglo con nombre, no con indice
                       duplicado. */
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    /* ESTA ES LA LINEA MAS IMPORTANTE DEL ARCHIVO. Con la
                       emulacion en true, cuando se escribe

                           $st->bindValue(':limite', $limite, PDO::PARAM_INT);

                       PHP le pasa el numero ya convertido en texto a MySQL, MySQL
                       lo envuelve entre comillas y acaba ejecutando

                           WHERE existencias <= '5'

                       es decir, el marcador se vuelve TEXTO y la comparacion de
                       numeros se resuelve comparando cadenas, con los resultados
                       raros que de ahi salen. Con la emulacion en false el numero
                       llega a MySQL como numero, y por eso en el punto 5 el
                       intento de DROP ni siquiera llega a armarse como SQL. */
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $error) {
                /* Si MySQL esta apagado, o la base no existe, o la clave esta
                   mal, la pagina no debe quedar en blanco: se dice que paso. El
                   mensaje va con getCode() porque MySQL manda alli su numero de
                   error, que es mas util que el texto generico de PHP. */
                throw new RuntimeException(
                    'No se pudo conectar con la base de datos. Revisa que MySQL '
                    . 'este encendido y que app/config/credenciales.php tenga la '
                    . 'base y la clave correctas. Detalle: ' . $error->getMessage(),
                    0,
                    $error
                );
            }
        }

        return self::$pdo;
    }
}
