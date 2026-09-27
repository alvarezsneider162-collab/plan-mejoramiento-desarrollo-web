/* Prototipo interactivo de productos — Actividad de recuperación, día 8
   ---------------------------------------------------------------------------
   Los cuatro puntos del enunciado, en el orden en que están:

     1. La tabla se pinta desde js/datos-prueba.js. En el HTML no queda ni una
        sola fila escrita a mano: el <tbody> está vacío.
     2. Buscador en vivo: un input y filter, sin botón de buscar.
     3. El botón del menú lo conecta app/menu.js (está en otro archivo porque
        ese también lo necesitan dashboard.html y componentes.html).
     4. Validación del formulario, con el mensaje junto al campo. Ningún alert.

   Decisiones que conviene tener presentes al leer el código:

   · POR QUÉ createElement Y NO innerHTML.
     La plantilla se podría escribir con innerHTML y quedaría más corta, pero el
     formulario deja añadir productos: el nombre que escriba el usuario acaba
     dentro de una celda. Con innerHTML, un nombre con "<img onerror=...>" se
     ejecutaría. Por eso cada celda se crea con createElement y su texto se
     pone con textContent, que siempre trata el contenido como texto.

   · POR QUÉ data-codigo Y NO data-id.
     El ejemplo guía usa data-id y Number(id). Los datos del día 7 no tienen
     un id numérico: su clave natural es el código ("TAC-001"). Por eso los
     botones llevan data-codigo y se comparan como texto.

   · POR QUÉ EXISTENCIAS Y NO STOCK, Y VALOR_ALQUILER Y NO PRECIO.
     El enunciado dice "precio" y "stock", pero el proyecto ya los tenía
     nombrados valor_alquiler y cantidad desde el día 2, y el archivo de datos
     usa esos mismos nombres. Se mantienen los del proyecto para que el
     formulario, la tabla y los datos hablen el mismo idioma.
   --------------------------------------------------------------------- */
(function () {
  "use strict";

  const cuerpo = document.getElementById("tabla-productos-body");
  const buscador = document.getElementById("buscar");
  const conteo = document.getElementById("buscador-conteo");
  const sinResultados = document.getElementById("tabla-vacia");
  const form = document.getElementById("form-producto");
  const botonGuardar = document.getElementById("boton-guardar");
  const estado = document.getElementById("estado-form");

  /* Si falta el <tbody> o no llegó el archivo de datos, no hay nada que
     pintar y las siguientes instrucciones darían error. "typeof" en vez de un
     try porque datos-prueba.js es un script clásico: cuando carga, declara
     "const productos" en el ámbito global y desde aquí se ve sin más. */
  if (!cuerpo || !form || typeof productos === "undefined") return;

  /*      El mismo formato de moneda que se usó el día 7. Vive aquí y no en otro
     archivo compartido porque hasta el día 8 no había dos cosas que lo
     necesitaran en la misma página. */
  const dinero = new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });

  /* Copia de trabajo. datos-prueba.js es el origen y no se modifica: aquí se
     trabaja sobre una copia para poder añadir, editar y borrar sin tocarlo. */
  let lista = productos.slice();

  /* Código del producto que se está editando, o null si el formulario está
     para registrar uno nuevo. */
  let editando = null;

  /* Los mensajes de error no salen mientras el usuario está escribiendo por
     primera vez, sino a partir del primer intento de envío. Con esta bandera
     el formulario es más tranquilo y aun así corrige en vivo después. */
  let mostrarErrores = false;

  /* Del dato legible ("Semi-nuevo") al value del radio ("semi_nuevo"). El
     enunciado guarda el estado como texto y el formulario lo guarda como
     value, así que hay que traducir en los dos sentidos. */
  const VALOR_CONDICION = {
    "Nuevo": "nuevo",
    "Semi-nuevo": "semi_nuevo",
    "En reparación": "reparacion"
  };

  /* Quita tildes y pasa a minúsculas, para que escribir "categoria" sin tilde
     encuentre "Categoría" y "CUBIERTA" encuentre "cubierta". */
  function normalizar(texto) {
    return String(texto)
      .normalize("NFD")
      .replace(/\p{Diacritic}/gu, "")
      .toLowerCase()
      .trim();
  }

  /* --------------------------------------------------------------------
     Punto 1 — Pintar la tabla
     ------------------------------------------------------------------ */

  /* Un botón de acción. Se construye aparte porque los dos son idénticos
     menos el texto, la acción y la variante de color. */
  function botonAccion(texto, accion, codigo, peligro) {
    const b = document.createElement("button");
    b.type = "button";
    b.className = peligro ? "boton-mini boton-peligro" : "boton-mini";
    b.dataset.accion = accion;
    b.dataset.codigo = codigo;
    b.textContent = texto;
    return b;
  }

  /* Una celda de la tabla. El data-label no es decorativo: en móvil la hoja
     de estilos convierte la celda en "etiqueta: valor" con
     content: attr(data-label), así que sin este atributo las celdas saldrían
     sin su nombre en el celular. Por eso se pone uno por celda, siempre. */
  function celda(etiqueta, texto) {
    const td = document.createElement("td");
    td.dataset.label = etiqueta;
    td.textContent = texto;
    return td;
  }

  function plantilla(producto) {
    const fila = document.createElement("tr");

    /* El código va en un <th scope="row"> porque es lo que titula la tarjeta en
       móvil, igual que las siete filas que había escritas a mano. */
    const titulo = document.createElement("th");
    titulo.scope = "row";
    titulo.textContent = producto.codigo;
    fila.append(titulo);

    fila.append(
      celda("Producto", producto.nombre),
      celda("Categoría", producto.categoria),
      celda("Condición", producto.condicion),
      celda("Existencias", String(producto.existencias)),
      celda("Valor de alquiler", dinero.format(producto.valor_alquiler))
    );

    const acciones = document.createElement("td");
    acciones.dataset.label = "Acciones";
    const caja = document.createElement("div");
    caja.className = "acciones-fila";
    caja.append(
      botonAccion("Editar", "editar", producto.codigo, false),
      botonAccion("Eliminar", "eliminar", producto.codigo, true)
    );
    acciones.append(caja);
    fila.append(acciones);

    return fila;
  }

  /* --------------------------------------------------------------------
     Punto 2 — Buscador en vivo
     ------------------------------------------------------------------ */

  /* Devuelve la lista ya filtrada. Con el buscador vacío devuelve todo, sin
     tocar la lista original. */
  function visibles() {
    const texto = normalizar(buscador.value);
    if (!texto) return lista;

    return lista.filter((producto) =>
      normalizar(producto.nombre).includes(texto) ||
      normalizar(producto.categoria).includes(texto) ||
      normalizar(producto.codigo).includes(texto)
    );
  }

  function pintar() {
    const filtrados = visibles();

    /* Un solo replaceChildren con el resultado del map: se borra lo que había
       y se pone lo nuevo, sin acumular filas. */
    cuerpo.replaceChildren(...filtrados.map(plantilla));

    const total = lista.length;
    const coincide = filtrados.length === 1 ? "coincide" : "coinciden";
    const sustantivo = total === 1 ? "producto" : "productos";

    conteo.textContent = filtrados.length === total
      ? total + " " + sustantivo + " en el inventario."
      : filtrados.length + " de " + total + " " + sustantivo + " " + coincide + " con la búsqueda.";

    const vacio = filtrados.length === 0;
    sinResultados.hidden = !vacio;
    if (vacio) {
      sinResultados.textContent =
        "Ningún producto coincide con esa búsqueda. Prueba con otra palabra o con el código.";
    }
  }

  /* El evento "input" salta en cada tecla y también al pegar y al borrar, que
     es justo lo que hace falta para que el filtrado sea en vivo. No hay
     ningún botón de "buscar": filtrar y escribir son la misma cosa. */
  buscador.addEventListener("input", pintar);

  /* --------------------------------------------------------------------
     Delegación de eventos sobre la tabla.
     Un solo escucha para todas las filas. No se pone un addEventListener en
     cada botón al pintar: las filas se borran y se recrean en cada búsqueda,
     y así sus escuchas morirían con ellas. El tbody no cambia nunca, así que
     un solo escucha suyo sirve para toda la tabla, sin importar cuántas filas
     tenga.
     ------------------------------------------------------------------ */

  function abrirFormulario(codigo) {
    const producto = lista.find((p) => p.codigo === codigo);
    if (!producto) return;

    editando = codigo;
    form.elements.codigo.value = producto.codigo;
    /* El código es la clave con la que se localiza la fila, así que mientras
       se edita se deja bloqueado. Si se pudiera cambiar, el producto perdería
       su clave y el botón Editar dejaría de encontrarlo. */
    form.elements.codigo.readOnly = true;
    form.elements.nombre.value = producto.nombre;
    form.elements.cantidad.value = String(producto.existencias);
    form.elements.valor_alquiler.value = String(producto.valor_alquiler);

    /* La categoría se busca por su texto visible, no por su value: el value
       es "implemento" y el texto es "Implemento de juego", y los datos guardan
       el texto. El <select> es la lista de la fuente, así que no hay que
       escribir el mapeo a mano ni se puede desincronizar. */
    const opcion = Array.from(form.elements.categoria.options)
      .find((o) => o.textContent.trim() === producto.categoria);
    form.elements.categoria.value = opcion ? opcion.value : "";

    form.elements.condicion.value = VALOR_CONDICION[producto.condicion] || "nuevo";

    botonGuardar.textContent = "Guardar cambios";
    estado.textContent =
      'Editando "' + producto.nombre + '". Cambia lo que necesites y guarda.';

    form.scrollIntoView({ behavior: "smooth", block: "start" });
    form.elements.nombre.focus();
  }

  function borrar(codigo) {
    const producto = lista.find((p) => p.codigo === codigo);
    if (!producto) return;

    lista = lista.filter((p) => p.codigo !== codigo);

    /* Si el producto que se estaba editando es justo el que se borra, el
       formulario se limpia, porque si no se quedaría editando algo que ya no
       está en la lista. */
    if (editando === codigo) {
      form.reset();
      form.elements.codigo.readOnly = false;
      editando = null;
      botonGuardar.textContent = "Registrar producto";
    }

    pintar();
    estado.textContent = 'Se eliminó "' + producto.nombre + '" de la lista.';
  }

  cuerpo.addEventListener("click", (evento) => {
    const boton = evento.target.closest("button[data-accion]");
    if (!boton) return;

    const accion = boton.dataset.accion;
    const codigo = boton.dataset.codigo;

    if (accion === "editar") abrirFormulario(codigo);
    else if (accion === "eliminar") borrar(codigo);
  });

  /* --------------------------------------------------------------------
     Punto 4 — Validación con el mensaje junto al campo
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
     Guardar
     ------------------------------------------------------------------ */

  function hayOtroConEseCodigo(codigo) {
    return lista.some((p) => p.codigo === codigo && p.codigo !== editando);
  }

  function guardar() {
    const codigo = form.elements.codigo.value.trim().toUpperCase();

    /* El código es la clave. Si se repite, el producto nuevo no se podría
       volver a localizar con Editar ni con Eliminar, así que se avisa en vez de
       guardarlo. No es una quinta regla del enunciado: es una guarda para que
       los datos no queden rotos. */
    if (hayOtroConEseCodigo(codigo)) {
      estado.textContent = 'Ya existe un producto con el código "' + codigo + '".';
      form.elements.codigo.setAttribute("aria-invalid", "true");
      form.elements.codigo.focus();
      return;
    }

    const producto = {
      codigo: codigo,
      nombre: form.elements.nombre.value.trim(),
      /* La categoría se vuelve a leer del texto de la opción elegida, que es
         como está en los datos. */
      categoria: form.elements.categoria.selectedOptions[0].textContent.trim(),
      condicion: Object.keys(VALOR_CONDICION)
        .find((texto) => VALOR_CONDICION[texto] === form.elements.condicion.value),
      existencias: Number(form.elements.cantidad.value),
      valor_alquiler: Number(form.elements.valor_alquiler.value)
    };

    if (editando) {
      lista = lista.map((p) => (p.codigo === editando ? producto : p));
      estado.textContent = 'Se actualizó "' + producto.nombre + '".';
    } else {
      lista.push(producto);
      estado.textContent = 'Se registró "' + producto.nombre + '".';
    }

    form.reset();
    form.elements.codigo.removeAttribute("aria-invalid");
    form.elements.codigo.readOnly = false;
    editando = null;
    mostrarErrores = false;
    botonGuardar.textContent = "Registrar producto";
    REGLAS.forEach((regla) => pintarError(regla, ""));

    pintar();
  }

  form.addEventListener("submit", (evento) => {
    /* Siempre se cancela el envío: es un prototipo y productos.php no existe.
       Cancelarlo también impide que el navegador actúe su propio envío. */
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

    guardar();
  });

  /* Antes del primer envío no se molesta con mensajes. Después, cada tecla
     vuelve a revisar para que el error se borre solo en cuanto se corrige. */
  const corregir = () => { if (mostrarErrores) validarTodo(); };
  form.addEventListener("input", corregir);
  form.addEventListener("change", corregir);

  /* Primer pintado: sin buscador, salen los 16 productos del archivo de datos. */
  pintar();
})();
