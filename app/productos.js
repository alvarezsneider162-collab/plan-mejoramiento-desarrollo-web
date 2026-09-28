/* ===========================================================================
   LA CARAMBOLA DORADA - Validacion del formulario de producto
   Archivo: app/productos.js
   Actividad de recuperacion, dia 8 punto 4, dia 9 y dia 13

   QUE CAMBIO EN EL DIA 13, Y POR QUE
   ---------------------------------------------------------------------------
   Este archivo hacia tres cosas distintas con el tiempo:

     dia 8   validaba los cuatro campos del enunciado y pintaba el mensaje
             junto al campo. Eso no dependia de donde salieran los datos.

     dia 9   la tabla paso a pintarla PHP desde MySQL, y con ella se fueron el
             filtrado mientras se escribia y los botones de editar y eliminar
             de JavaScript. El archivo se quedo solo con la validacion.

     dia 13  el formulario por fin guarda, con un INSERT o un UPDATE en el
             servidor. Y aqui esta el cambio de verdad de este dia:

                 ANTES   evento.preventDefault() SIEMPRE. Se cancelaba el envio
                         en todos los casos, porque no habia nada que enviar.

                 AHORA   solo se cancela cuando algo esta mal.

   Ese es todo el cambio de comportamiento, y es el que hace que este archivo
   deje de ser un obstaculo: si esta todo bien, no se toca nada y el navegador
   envia el POST, que es lo que espera. Si algo esta mal, se cancela, se avisa al
   lado del campo y el foco se queda en el primero que falla.

   ---------------------------------------------------------------------------
   POR QUE SE VALIDA IGUAL EN LOS DOS LUGARES, Y POR QUE NO ES DIFICULTAD
   ---------------------------------------------------------------------------
   Hay dos validaciones: esta, en el navegador, y la que hace
   validarProducto() dentro de productos.php, en el servidor. Se puede leer esto
   como que se repite trabajo, y en parte es: el navegador evita un viaje
   redondo cuando falta un asterisco.

   Pero la del servidor NO es opcional, por los tres motivos del enunciado:

     1. Con JavaScript desactivado, este archivo no se carga. Lo unico que
        queda es la validacion del servidor.

     2. Esto se puede esquivar en dos lineas. Quien tenga el teclado delante
        puede quitar el script, o llamar al POST con curl. Un navegador pide las
        cosas, un servidor las exige.

     3. Lo que se valida aqui es el CONTENIDO, y el servidor ademas comprueba lo
        que el navegador no puede saber: que el codigo no este repetido en otro
        producto, que la categoria exista de verdad en el catalogo y que el
        correo del cliente no se repita.

   Y por eso los mensajes de los dos lados dicen casi lo mismo, y no lo mismo a
   proposito: el del navegador esta en "Escribe el nombre", que es una
   instruccion, y el del servidor esta en "El nombre del producto es
   obligatorio", que es el hecho que se ha comprobado. Los dos casos del punto 5
   del enunciado (precio negativo y texto en un campo numerico) estan escritos
   igual en las dos capas, para que la captura con JavaScript y la captura sin
   JavaScript enseñen la misma frase.
   =========================================================================== */

(function () {
  "use strict";

  const form = document.getElementById("form-producto");
  if (!form) return;

  const estado = document.getElementById("estado-form");
  let mostrarErrores = false;

  /* --------------------------------------------------------------------
     LAS REGLAS
     Cada regla trae dos mensajes: uno para el campo vacío y otro para el campo
     lleno pero con un valor que no vale. La diferencia importa: decir "el valor
     debe ser mayor que cero" a un campo que está vacío confunde, y la
     diferencia son dos cadenas.

     El HTML ya lleva novalidate y los atributos que valen sin JavaScript
     (required, pattern, minlength). Lo que añade este código es el texto que
     se lee al lado del campo, que es lo que pide el punto 4 del día 8.

     Los nombres de campo son los del formulario de productos.php, y no los del
     día 8. El día 8 el formulario tenía "cantidad" y "categoria"; ahora se
     llaman "existencias" y "categoria_id", que son los nombres de las columnas.
     ------------------------------------------------------------------ */

  const REGLAS = [
    {
      campo: "codigo",
      vacio: "Escribe el código del producto.",
      /* El patrón se comprueba con la misma expresión que el servidor, así que
         "tac-1" falla en los dos lados por la misma razón y no uno sí y el
         otro no. */
      mensaje: "El código tiene que ser tres letras en mayúscula, un guion y tres dígitos. Ejemplo: TAC-001",
      revisar: function (valor) {
        return /^[A-Z]{3}-[0-9]{3}$/.test(valor.toUpperCase()) ? "" : this.mensaje;
      }
    },
    {
      campo: "nombre",
      vacio: "Escribe el nombre del producto.",
      mensaje: "El nombre necesita al menos tres caracteres.",
      revisar: function (valor) {
        return valor.trim().length >= 3 ? "" : this.mensaje;
      }
    },
    {
      campo: "categoria_id",
      vacio: "Elige una categoría.",
      mensaje: "Elige una categoría.",
      /* El <select> lleva una opción vacía de salida, así que solo hay que
         comprobar que no siga en ella. */
      revisar: function () { return ""; }
    },
    {
      campo: "existencias",
      vacio: "Escribe cuántas existencias hay.",
      /* El caso 3 del punto 5: texto en un campo numérico. El mensaje repite
         lo que hay en la caja, entre comillas, para que se pueda comparar. */
      mensaje: "Las existencias tienen que ser un número entero. Escribiste: \u00AB",
      revisar: function (valor) {
        return /^\d+$/.test(valor.trim()) ? "" : this.mensaje + valor + "\u00BB.";
      }
    },
    {
      campo: "valor_alquiler",
      vacio: "Escribe el valor de alquiler.",
      /* El caso 2 del punto 5: un precio negativo. */
      mensaje: "El valor de alquiler tiene que ser mayor que cero: ",
      revisar: function (valor) {
        return Number(valor) > 0 ? "" : this.mensaje + valor + " no es un precio.";
      }
    },
    {
      campo: "fecha_ingreso",
      vacio: "Escribe la fecha de ingreso.",
      /* El type="date" ya no deja escribir un "2026-13-45" en un navegador
         moderno: el teclado solo ofrece un calendario. Esta regla esta para
         navegadores viejos y para cuando el campo viene vacio por un POST
         manipulado. */
      mensaje: "Esa fecha no existe en el calendario.",
      revisar: function (valor) {
        if (valor === "") return this.vacio;
        const partes = valor.split("-");
        if (partes.length !== 3) return this.mensaje;
        const mes = Number(partes[1]);
        const dia = Number(partes[2]);
        const anio = Number(partes[0]);
        const diasDelMes = new Date(anio, mes, 0).getDate();
        return mes >= 1 && mes <= 12 && dia >= 1 && dia <= diasDelMes ? "" : this.mensaje;
      }
    }
  ];

  /* Cada regla sabe revisarse. La categoria es el unico caso en el que el
     mensaje de "vacio" y el de "lleno" son el mismo, asi que se resuelve aqui y
     no dentro de revisar(): si esta vacia, se avisa, y si tiene algo ya no hay
     nada que objetar, porque las opciones las puso la base. */
  function revisarCampo(regla) {
    const control = form.elements[regla.campo];
    if (!control) return "";

    const valor = String(control.value || "").trim();

    if (valor === "") return regla.vacio;

    return regla.revisar(valor);
  }

  function pintarError(regla, mensaje) {
    const control = form.elements[regla.campo];
    const caja = document.getElementById("error-" + regla.campo);
    if (!control || !caja) return;

    caja.textContent = mensaje;

    if (mensaje) {
      control.setAttribute("aria-invalid", "true");
      control.setAttribute("aria-describedby", caja.id);
      control.classList.add("es-invalid");
    } else {
      control.removeAttribute("aria-invalid");
      control.removeAttribute("aria-describedby");
      control.classList.remove("es-invalid");
    }

    /* setCustomValidity deja la regla tambien en el propio control, y no solo
       como texto en la pagina. El formulario lleva novalidate, asi que el
       navegador no pinta su burbuja: el aviso que se ve es el de al lado. */
    control.setCustomValidity(mensaje);
  }

  /* Revisa todas las reglas y devuelve el primer campo que falla, para poder
     dejar el foco ahi. */
  function validarTodo() {
    let primero = null;

    REGLAS.forEach(function (regla) {
      const mensaje = revisarCampo(regla);
      pintarError(regla, mensaje);
      if (mensaje && !primero) primero = form.elements[regla.campo];
    });

    return primero;
  }

  /* --------------------------------------------------------------------
     Envío
     ------------------------------------------------------------------ */

  form.addEventListener("submit", function (evento) {
    mostrarErrores = true;
    const primero = validarTodo();

    /* Aqui esta el cambio del dia 13. Antes se llamaba preventDefault() antes
       de mirar nada, y el envio se cancelaba siempre. Ahora solo se cancela si
       hay un campo que este mal: */

    if (primero) {
      evento.preventDefault();
      primero.focus();
      if (estado) {
        estado.textContent = "Revisa los campos marcados antes de guardar.";
      }
      return;
    }

    /* Todo esta bien: no se toca nada y el POST sale. El servidor vuelve a
       comprobarlo todo, y si encuentra algo que aqui no se puede ver (un codigo
       repetido, una categoria que no existe) vuelve con el formulario
       repintado y el error al lado del campo. */
    if (estado) {
      estado.textContent = "Guardando\u2026";
    }
  });

  /* Antes del primer envío no se molesta con mensajes. Despues, cada tecla
     vuelve a revisar para que el error se borre solo en cuanto se corrige. */
  const corregir = function () { if (mostrarErrores) validarTodo(); };
  form.addEventListener("input", corregir);
  form.addEventListener("change", corregir);
})();
