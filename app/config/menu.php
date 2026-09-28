<?php
declare(strict_types=1);

/* ===========================================================================
   LA CARAMBOLA DORADA - El menu, escrito como datos
   Archivo: app/config/menu.php
   Actividad de recuperacion, dia 12, punto 2

   El menu lateral estaba escrito a mano en dashboard.html, productos.html,
   usuarios.html y componentes.html: las mismas seis lineas repetidas cuatro
   veces, con el "activo" marcado en un archivo y no en el otro. Cuatro copias
   de lo mismo no se pueden mantener: al entrar un rol nuevo hay que acordarse
   de los cuatro archivos, y basta con olvidar uno para que ese menu se quede
   viejo.

   Este archivo es el unico sitio donde vive la lista. Se carga con un
   require_once y lo lee app/parciales/menu.php, que es quien lo pinta.

   ---------------------------------------------------------------------------
   QUE TIENE CADA ENTRADA
   ---------------------------------------------------------------------------
     clave  el identificador de la entrada. NO es el texto ni la ruta: es un
            nombre corto y fijo, y es lo que la pagina pasa para decir "esta
            soy yo". Por eso el marcado del activo no depende de comparar
            cadenas de rutas, que se rompen en cuanto alguien anade un
            parametro o escribe la ruta de otra forma.
     texto  lo que se ve. Va escapado al pintarlo, no aqui: este archivo es
            configuracion, no HTML, y no tiene que saber nada de htmlspecialchars.
     ruta   el href. Relativa a la carpeta de la aplicacion, y con la base ya
            puesta por urlApp() al pintar, para que el proyecto se pueda mover
            de carpeta sin tocar este archivo.
     roles  QUIEN VE ESTA ENTRADA. Es la parte que hace que el menu del punto 5
            sea distinto para el administrador, el vendedor y el consultor.

   ---------------------------------------------------------------------------
   EL ROL, TAL CUAL VIENE DE LA BASE
   ---------------------------------------------------------------------------
   Los tres roles son los del ENUM de la tabla usuarios: administrador, vendedor
   y consultor. Se escriben tal cual, en minusculas y sin tildes, porque asi es
   como los guarda Autenticacion.php y como los compara puede() con in_array y
   comparador estricto. Si aqui se escribiera "Administrador" con mayuscula, la
   comparacion daria false y la entrada no se veria nunca. Un in_array con
   comparador estricto no perdona ni una tilde.

   ---------------------------------------------------------------------------
   POR QUE ROLES VA DENTRO Y NO EN UNA TABLA DE LA BASE
   ---------------------------------------------------------------------------
   Porque un menu no es informacion: es la lista de pantallas del sistema, y
   cambia con cada pantala nueva, no con cada usuario. Meterlo en MySQL
   obligaria aUPDATEar filas cada vez que se anade una seccion, y hoy no hay ni
   una consulta mas de las que ya tiene el proyecto.

   Lo que si esta en la base es el ROL de cada persona, y eso es lo que decide
   que entradas de este arreglo se pintan.

   ---------------------------------------------------------------------------
   ESTO NO ES SEGURIDAD
   ---------------------------------------------------------------------------
   Y hay que decirlo claro, porque es la confusion mas comun con un menu por
   roles: esconder una entrada NO impide entrar por la URL. Si el consultor no
   ve "Usuarios" en su menu, escribiendo usuarios.php a mano le llega igual: por
   eso usuarios.php vuelve a llamar a exigirRol('administrador') en su primera
   linea, y el 403 lo contesta la pagina, no el menu.

    Entonces para que sirve el filtro de roles? Para que no haya que leer. Un
    menu de seis entradas del que un vendedor solo puede usar cuatro es ruido:
    la persona tiene que adivinar cuales le sirven. Y el punto 5 lo pide como eso:
    COMPARAR EL MENU VISIBLE, que es una cuestion de navegacion, no de acceso.

   ---------------------------------------------------------------------------
   LO QUE SE HA QUITADO, Y POR QUE
   ---------------------------------------------------------------------------
   El menu viejo tenia seis entradas: Tablero, Productos, Componentes, Usuarios,
   Ingreso y Cerrar sesion. "Ingreso" desaparece, y no es una perdida: esa
   entrada solo tenia sentido sin sesion abierta, y el menu lateral solo se
   pinta en paginas privadas, que son las que ya exigieron entrar. A alguien que
   esta dentro, "Ingreso" es un enlace a la pantalla de entrada, que es
   justamente donde ya esta. El boton de cerrar sesion sigue ahi, y ahora con
   su formulario POST, que es lo que pide salir.php.

   Y "Componentes" y "Entorno" se escriben con mayuscula inicial, como las
   demas, porque hasta el dia 11 el menu de usuarios.php traia "Usuarios" con
   mayuscula y el de dashboard.php traia "Cerrar sesi&oacute;n" con la entidad
   HTML escrita a mano. Las dos cosas estan en un solo archivo ahora.
   =========================================================================== */

return [
    /* El <h2> del menu. Se lee de ahi y no de la pagina, para que las cinco
       pantallas del panel no traigan cada una su titulo escrito, y ademas no
       se llamaban todos igual. */
    'titulo' => 'Menú principal',

    /* El identificador de la <nav>. Va con id porque el <nav> lo apunta con
       aria-labelledby, y sin id el lector de pantalla no tendria con que
       anunciar el nombre de la region. */
    'idNavegacion' => 'menu-titulo-lateral',

    'entradas' => [

        /* ------------------------------------------------------------------
           LO QUE VE TODO EL QUE ESTA DENTRO
           ------------------------------------------------------------------ */

        [
            'clave' => 'tablero',
            'texto' => 'Tablero',
            'ruta'  => 'dashboard.php',
            'roles' => ['administrador', 'vendedor', 'consultor'],
        ],

        [
            'clave' => 'productos',
            'texto' => 'Productos',
            'ruta'  => 'productos.php',
            'roles' => ['administrador', 'vendedor'],
        ],

        [
            'clave' => 'componentes',
            'texto' => 'Componentes',
            'ruta'  => 'componentes.php',
            'roles' => ['administrador', 'vendedor', 'consultor'],
        ],

        /* ------------------------------------------------------------------
           LO QUE SOLO VE EL ADMINISTRADOR
           ------------------------------------------------------------------ */

        [
            'clave' => 'usuarios',
            'texto' => 'Usuarios',
            'ruta'  => 'usuarios.php',
            'roles' => ['administrador'],
        ],

        [
            'clave' => 'entorno',
            'texto' => 'Entorno',
            'ruta'  => 'phpinfo.php',
            'roles' => ['administrador'],
        ],

        /* ------------------------------------------------------------------
           LO QUE VE CUALQUIERA, INCLUIDO UN VISITANTE SIN SESION
           ------------------------------------------------------------------ */

        [
            'clave' => 'salir',
            'texto' => 'Cerrar sesión',
            'ruta'  => 'salir.php',
            'roles' => ['administrador', 'vendedor', 'consultor', 'visitante'],
        ],
    ],
];
