/* Menú lateral — Actividad de recuperación, día 8
   ---------------------------------------------------------------------------
   Punto 3 del enunciado: conectar el botón del menú y que también responda
   con la tecla Enter.

   POR QUÉ HACE FALTA ESTE ARCHIVO
   En el día 6 el botón del menú era un <label for="interruptor-menu">. Un
   <label> no es un control: no recibe el foco, no se puede tabular hasta él y
   la tecla Enter no lo activa. Por eso el menú se abría con el ratón y no con
   el teclado. Aquí el <label> se cambia por un <button> de verdad (el cambio
   está en el HTML de las tres páginas) y este archivo lo conecta.

   POR QUÉ NO HAY NINGÚN keydown AQUÍ
   Un <button> ya emite un click cuando se presiona Enter. Si además se
   escuchara keydown, la tecla contaría dos veces: una por el keydown y otra
   por el click, y el menú se abriría y cerraría en el mismo instante. Por eso
   este archivo solo escucha "click" y la tecla Enter funciona sola.

   POR QUÉ EL CHECKBOX SIGUE SIENDO EL ESTADO
   El CSS del día 6 abre el menú con el hermano general "~" a partir del
   checkbox:  .interruptor-menu:checked ~ .panel__menu  y
               .interruptor-menu:checked ~ .menu-velo
   Ese selector no se ha tocado, así que el botón real no reescribe nada: solo
   marca y desmarca la misma casilla que marcaba el <label>. Así el menú sigue
   funcionando aunque este archivo no cargue.
   --------------------------------------------------------------------- */
(function () {
  "use strict";

  const interruptor = document.getElementById("interruptor-menu");
  const boton = document.querySelector(".boton-menu");
  const cerrar = document.querySelector(".menu-cerrar");
  const panel = document.getElementById("menu-lateral");

  /* login.php no tiene menú lateral, así que aquí no hay nada que hacer.
     Sin esta salida, boton sería null y boton.addEventListener(...) lanzaría
     un TypeError que pararía todo el archivo. */
  if (!interruptor || !boton) return;

  /* Una sola función para dejar de acuerdo los tres indicadores de estado:
     la casilla, el aria-expanded que anuncia el estado a los lectores de
     pantalla, y la clase .abierto que pide el enunciado. */
  function sincronizar() {
    const abierto = interruptor.checked;

    boton.setAttribute("aria-expanded", abierto ? "true" : "false");
    if (panel) panel.classList.toggle("abierto", abierto);
  }

  function alternar() {
    interruptor.checked = !interruptor.checked;
    sincronizar();
  }

  /* El botón de abrir. Con Enter y con Espacio el <button> emite click, que
     es justo lo que pasa aquí. */
  boton.addEventListener("click", alternar);

  /* El botón de cerrar. También era un <label> el día 6, y por el mismo
     motivo no responda al teclado. Con el <button> ya responde. */
  if (cerrar) cerrar.addEventListener("click", alternar);

  /* Si la casilla se cambia por otra vía —el teclado, o el <label> del velo,
     que sigue siendo un label— el evento change avisa y se sincroniza. */
  interruptor.addEventListener("change", sincronizar);

  /* Al cargar, el estado puede venir ya marcado (por ejemplo, en la
     navegación con hash), así que se pone en punto una vez. */
  sincronizar();
})();
