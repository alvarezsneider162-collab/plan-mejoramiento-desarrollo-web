/* ===========================================================================
   LA CARAMBOLA DORADA - Validacion del formulario de producto
   Archivo: app/productos.js
   Actividad de recuperacion, dia 8, punto 4, y dia 9

   QUE CAMBIO EN EL DIA 9, Y POR QUE:

   Este archivo antes hacia tres cosas: pintaba la tabla, filtraba mientras se
   escribia y manejaba los botones Editar y Eliminar. Las tres dependian del
   arreglo `productos` que venia de js/datos-prueba.js.

   El dia 9 ese archivo desaparece, porque la tabla ahora la lee MySQL desde
   productos.php. Asi que las tres cosas que quedaban sin datos de origen se
   fueron con el: la tabla la pinta PHP, la busqueda la hace la consulta
   preparada, y no hay UPDATE ni DELETE porque el dia 9 no los pide.

   Lo que SI se queda, y no se toca, es la validacion del punto 4 del dia 8: los
   cuatro mensajes junto al campo. Es la parte del dia 8 que no dependia de los
   datos de prueba, asi que sigue funcionando igual.
   =========================================================================== */

(function () {
  "use strict";

  const form = document.getElementById("form-producto");
  if (!form) return;

  const estado = document.getElementById("estado-form");
  let mostrarErrores = false;

  /* --------------------------------------------------------------------
     Punto 4 del dia 8 - Validación con el mensaje junto al campo
     Cada regla trae dos mensajes: uno para el campo vacío y otro para el campo
     lleno pero con un valor que no vale. La diferencia importa: decir
     "el valor debe ser mayor que cero" a un campo que está vacío confunde, y
     la diferencia son dos cadenas.
     El HTML ya lleva los atributos que valen sin JavaScript (required, minlength,
     min, step). Lo que añade este código es el texto que se lee.
     ------------------------------------------------------------------ */

  const REGLAS = [
    {
      campo: "nombre",
      vacio: "Escribe el nombre del producto.",
      mensaje: "El nombre necesita al menos tres caracteres."
    },
    {
      campo: "valor_alquiler",
      vacio: "Escribe el valor de alquiler.",
      mensaje: "El valor de alquiler debe ser mayor que cero."
    },
    {
      campo: "cantidad",
      vacio: "Escribe cuántas existencias hay.",
      mensaje: "Las existencias deben ser un entero que no sea negativo, o sea cero o más."
    },
    {
      campo: "categoria",
      vacio: "Selecciona una categoría.",
      mensaje: "Selecciona una categoría."
    }
  ];

  function revisarCampo(regla) {
    const valor = form.elements[regla.campo].value;
    if (valor === "") return regla.vacio;

    if (regla.campo === "nombre") {
      return valor.trim().length >= 3 ? "" : regla.mensaje;
    }
    if (regla.campo === "valor_alquiler") {
      return Number(valor) > 0 ? "" : regla.mensaje;
    }
    if (regla.campo === "cantidad") {
      /* Un entero que no sea negativo es solo de dígitos: por eso se prueba
         con una expresión regular y no con Number(). Number("3.5") es 3.5,
         que no es un entero, y Number(" ") es 0, que si lo sería. */
      return /^\d+$/.test(valor.trim()) ? "" : regla.mensaje;
    }
    /* La categoría es un <select> con una opción vacía de salida, así que
       solo hay que comprobar que no siga en esa opción. */
    return valor === "" ? regla.mensaje : "";
  }

  function pintarError(regla, mensaje) {
    const control = form.elements[regla.campo];
    const caja = document.getElementById("error-" + regla.campo);

    caja.textContent = mensaje;

    if (mensaje) {
      control.setAttribute("aria-invalid", "true");
      control.setAttribute("aria-describedby", caja.id);
    } else {
      control.removeAttribute("aria-invalid");
      control.removeAttribute("aria-describedby");
    }

    /* setCustomValidity deja la regla también en el propio control, y no solo
       como texto en la página. El formulario lleva novalidate, así que el
       navegador no pinta su burbuja: el aviso que se ve es el de al lado. */
    control.setCustomValidity(mensaje);
  }

  /* Revisa las cuatro reglas y devuelve el primer campo que falla, para poder
     dejar el foco ahí. */
  function validarTodo() {
    let primero = null;

    REGLAS.forEach((regla) => {
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
    /* Siempre se cancela el envío. El dia 9 todavia no hay INSERT: el enunciado
       no pide guardar, solo leer, asi que el boton revisa los cuatro campos y
       avisa que el guardado todavia no esta conectado. Cancelar tambien impide
       que el navegador actué su propio envío y recargue la pagina. */
    evento.preventDefault();

    mostrarErrores = true;
    const primero = validarTodo();

    if (primero) {
      primero.focus();
      estado.textContent = "Revisa los campos marcados antes de guardar.";
      return;
    }

    /* Las cuatro reglas del enunciado ya pasaron. Quedan los otros
       obligatorios del formulario —el código y la fecha—, que el enunciado no
       pide validar. Se usa :invalid para señalar el primero y se le da el
       foco; no se llama a reportValidity() para que no aparezca ninguna
       burbuja del navegador, ya que el enunciado pide los avisos junto al
       campo. */
    if (!form.checkValidity()) {
      const culpable = form.querySelector(":invalid");
      if (culpable) culpable.focus();
      estado.textContent = "Falta el código o la fecha de ingreso, que también son obligatorios.";
      return;
    }

    estado.textContent = "Los campos están correctos. El día 9 solo lee de la "
      + "base de datos: guardar un producto es un INSERT y todavía no está hecho.";
  });

  /* Antes del primer envío no se molesta con mensajes. Después, cada tecla
     vuelve a revisar para que el error se borre solo en cuanto se corrige. */
  const corregir = function () { if (mostrarErrores) validarTodo(); };
  form.addEventListener("input", corregir);
  form.addEventListener("change", corregir);
})();
