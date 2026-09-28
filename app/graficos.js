/* Graficos del tablero — Actividad de recuperacion, dia 14
   ---------------------------------------------------------------------------
   Punto 3 y 4 del enunciado: tres graficos de tipos distintos alimentados por
   las vistas del dia 14, y un filtro de fechas que los redibuja sin recargar.

   COMO SE REPARTE EL TRABAJO
   ---------------------------------------------------------------------------
   La pagina (dashboard.php) entrega el esqueleto: el formulario de fechas y
   tres <figure> vacios con la clase "grafico-<serie>". Este archivo es el que
   pregunta los datos y el que dibuja:

       api/graficos.php  ->  JSON con tres series  ->  este archivo  ->  SVG

   Ninguna consulta esta escrita aqui, y tampoco los nombres de las tablas ni
   de las vistas: este archivo solo trae el fetch, los numeros que la API ya
   agrupo y las cadenas del SVG. Quien quiera ver de donde salen los datos los
   encuentra en api/graficos.php; el navegador no necesita saberlo.

   POR QUE LOS GRAFICOS SON SVG ESCRITOS A MANO
   ---------------------------------------------------------------------------
   El proyecto no puede depender de una biblioteca que se descarga de internet:
   este salon se instala en oficinas donde el navegador puede estar aislado, y
   un <script src="https://cdn.jsdelivr.net/..."> que no llega deja el tablero
   sin graficos. Un SVG armado con cadenas no depende de nada: es texto, y
   funciona con la misma conexion que ya trajo la pagina.

   POR QUE SE DIBUJA EN JS Y NO SE PINTA EN EL HTML
   ---------------------------------------------------------------------------
   Porque los datos cambian con el rango de fechas del punto 4. Si el HTML
   viniera ya con los graficos pintados, cada cambio de fechas tendria que
   reescribir la pagina; asi el tablero pide el JSON, pinta, y al cambiar las
   fechas pide otro JSON y vuelve a pintar sin mover el documento.

   ---------------------------------------------------------------------------
   LAS TRES SERIES Y SUS TIPOS DE GRAFICO
   ---------------------------------------------------------------------------
     grafico-mes       linea          (del mes)              - tendencia
     grafico-categoria dona           (de la categoria)      - reparto
     grafico-stock     barras h       (del stock)            - inventario

   La linea muestra la tendencia mensual; la dona reparte las ventas entre
   categorias; y las barras horizontales comparan existencias por producto.
   ------------------------------------------------------------------------- */
(function () {
  "use strict";

  const formulario = document.getElementById("filtro-graficos");
  const estado = document.getElementById("estado-graficos");

  /* Si este archivo se carga en una pagina que no tiene la seccion (login.php,
     por ejemplo) no hay nada que hacer y no hay que tocar el DOM. */
  if (!formulario || !estado) return;

  const lienzos = {
    mes: document.getElementById("grafico-mes"),
    categoria: document.getElementById("grafico-categoria"),
    stock: document.getElementById("grafico-stock"),
  };

  /* -------------------------------------------------------------------------
     PEQUENAS AYUDAS
     ------------------------------------------------------------------------- */

  /* Los valores llegan ya como numeros. Un peso se escribe con punto de miles
     y sin decimales, que es como se lee en Colombia: 755500 -> "$755.500". */
  function pesos(valor) {
    return "$" + Math.round(valor).toLocaleString("es-CO");
  }

  /* "2026-09" es el mes que manda la vista. Para el eje de la linea y para el
     resumen que lee el lector de pantalla se quiere "sep 2026". */
  const MESES = ["", "ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];

  function mesLargo(etiqueta) {
    const p = etiqueta.split("-");
    const nombres = ["", "enero", "febrero", "marzo", "abril", "mayo", "junio",
                     "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
    const n = parseInt(p[1], 10);
    return (nombres[n] || etiqueta) + " " + p[0];
  }

  function mesCorto(etiqueta) {
    const p = etiqueta.split("-");
    return (MESES[parseInt(p[1], 10)] || etiqueta) + " " + p[0].slice(2);
  }

  /* Todo lo que llega por el JSON sale de la base de datos, y se imprime
     dentro de un SVG con innerHTML. Un nombre que un empleado escribiera
     con "<" romperia el grafico, asi que antes de entrar al SVG se escapa
     como si fuera HTML, que es lo que es. */
  function escTexto(texto) {
    return String(texto)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  /* Un SVG para cuando no hay datos en el rango: una linea centrada que dice
     la razon, en vez de dejar la caja vacia o dibujar una escala con un cero. */
  function vacio(contenedor, mensaje) {
    contenedor.innerHTML =
      '<svg role="img" aria-label="Sin datos" viewBox="0 0 520 120" ' +
      'xmlns="http://www.w3.org/2000/svg">' +
      '<text x="260" y="62" text-anchor="middle" font-size="15" fill="currentColor">' +
      escTexto(mensaje) +
      "</text></svg>";
  }

  /* -------------------------------------------------------------------------
     LA LINEA DE VENTAS POR MES
     ------------------------------------------------------------------------- */
  function dibujarMes(serie) {
    const lienzo = lienzos.mes;
    if (serie.length === 0) { vacio(lienzo, "No hay ventas en este rango."); return; }

    const W = 520, H = 270, IZQ = 64, DER = 16, ARRIBA = 20, ABAJO = 42;
    const ancho = W - IZQ - DER;
    const alto = H - ARRIBA - ABAJO;
    const max = Math.max.apply(null, serie.map(function (d) { return d.valor; }));

    const x = function (i) {
      if (serie.length === 1) return IZQ + ancho / 2;
      return IZQ + (i * ancho) / (serie.length - 1);
    };
    const y = function (v) {
      return ARRIBA + alto - (v / max) * alto;
    };

    let out = "";
    out += '<svg viewBox="0 0 ' + W + " " + H + '" xmlns="http://www.w3.org/2000/svg">';

    /* La rejilla: cuatro lineas horizontales y su valor. Se dibujan primero
       para que la linea y los puntos les pasen por encima. */
    for (let k = 0; k <= 4; k++) {
      const yy = ARRIBA + alto - (alto * k) / 4;
      out += '<line x1="' + IZQ + '" y1="' + yy + '" x2="' + (W - DER) +
             '" y2="' + yy + '" stroke="#D6DBD2" stroke-width="1" />';
      out += '<text x="' + (IZQ - 8) + '" y="' + (yy + 4) + '" text-anchor="end" ' +
             'font-size="12" fill="#4E5F56">' + pesos(max * (k / 4)) + "</text>";
    }

    /* La linea. Un SVG solo hace falta que pase por los datos y no por los
       ejes, asi que se recogen los puntos y se unen con un polyline. */
    let puntos = "";
    let puntosGordos = "";
    for (let i = 0; i < serie.length; i++) {
      puntos += (i === 0 ? "" : " ") + x(i) + "," + y(serie[i].valor);
    }

    out += '<polyline points="' + puntos + '" fill="none" stroke="#0F5132" ' +
           'stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />';

    /* Cada mes: un punto, su etiqueta en el eje y el valor dentro de un
       <title>, que es la burbuja que sale al pasar el raton por encima. */
    for (let i = 0; i < serie.length; i++) {
      const descripcion = mesLargo(serie[i].etiqueta) + ": " + pesos(serie[i].valor);
      out += '<circle cx="' + x(i) + '" cy="' + y(serie[i].valor) + '" r="4" ' +
             'fill="#8A6100" stroke="#FFFFFF" stroke-width="1.5">' +
             "<title>" + escTexto(descripcion) + "</title></circle>";
      out += '<text x="' + x(i) + '" y="' + (H - ABAJO + 24) + '" text-anchor="middle" ' +
             'font-size="12" fill="#14201A">' + mesCorto(serie[i].etiqueta) + "</text>";
    }

    out += "</svg>";

    /* El resumen que ve el lector de pantalla. Es texto plano, no SVG. */
    const resumen = serie.map(function (d) {
      return mesLargo(d.etiqueta) + " " + pesos(d.valor);
    }).join(", ");
    lienzo.setAttribute("aria-label", "Ventas por mes: " + resumen);
    lienzo.innerHTML = out;
  }

  /* -------------------------------------------------------------------------
     LA DONA DE VENTAS POR CATEGORIA
     ------------------------------------------------------------------------- */
  function dibujarCategoria(serie) {
    const lienzo = lienzos.categoria;
    if (serie.length === 0) { vacio(lienzo, "No hay ventas por categoria en este rango."); return; }

    const W = 520, FILA = 38, H = Math.max(220, 32 + serie.length * FILA);
    const cx = 132, cy = H / 2, radio = 76;
    const circunferencia = 2 * Math.PI * radio;
    const total = serie.reduce(function (suma, d) { return suma + d.valor; }, 0);
    if (total <= 0) { vacio(lienzo, "No hay ventas por categoria en este rango."); return; }
    const colores = ["#0F5132", "#C68A00", "#176B87", "#B3261E", "#6A7D36", "#8A4B08"];

    let out = "";
    out += '<svg viewBox="0 0 ' + W + " " + H + '" xmlns="http://www.w3.org/2000/svg">';
    let recorrido = 0;
    for (let i = 0; i < serie.length; i++) {
      const longitud = (serie[i].valor / total) * circunferencia;
      const color = colores[i % colores.length];
      const descripcion = serie[i].etiqueta + ": " + pesos(serie[i].valor);
      out += '<circle cx="' + cx + '" cy="' + cy + '" r="' + radio + '" fill="none" ' +
        'stroke="' + color + '" stroke-width="34" stroke-dasharray="' + longitud +
        ' ' + (circunferencia - longitud) + '" stroke-dashoffset="' + (-recorrido) +
        '" transform="rotate(-90 ' + cx + ' ' + cy + ')"><title>' +
        escTexto(descripcion) + '</title></circle>';
      const y = 32 + i * FILA;
      out += '<rect x="270" y="' + (y - 10) + '" width="12" height="12" rx="2" fill="' +
        color + '" />';
      out += '<text x="290" y="' + y + '" font-size="12" fill="#14201A">' +
        escTexto(serie[i].etiqueta) + '</text>';
      out += '<text x="504" y="' + y + '" text-anchor="end" font-size="12" fill="#4E5F56">' +
        pesos(serie[i].valor) + '</text>';
      recorrido += longitud;
    }

    out += "</svg>";
    lienzo.setAttribute("aria-label",
      "Ventas por categoria: " + serie.map(function (d) {
        return d.etiqueta + " " + pesos(d.valor);
      }).join(", "));
    lienzo.innerHTML = out;
  }

  /* -------------------------------------------------------------------------
     LAS BARRAS HORIZONTALES DEL STOCK CRITICO
     ------------------------------------------------------------------------- */
  function dibujarStock(serie) {
    const lienzo = lienzos.stock;
    if (serie.length === 0) { vacio(lienzo, "Ningun producto esta en stock critico."); return; }

    /* El alto del dibujo depende de cuantos productos haya. Fila de 34 px
       mas el pie, y un minimo para que la caja no quede chica con uno solo. */
    const W = 520, IZQ = 14, DER = 96, FILA = 34, CABEZA = 10;
    const max = Math.max(1, Math.max.apply(null, serie.map(function (d) { return d.valor; })));
    const H = Math.max(120, CABEZA + serie.length * FILA + 10);
    const ancho = W - IZQ - DER;

    let out = "";
    out += '<svg viewBox="0 0 ' + W + " " + H + '" xmlns="http://www.w3.org/2000/svg">';
    out += '<line x1="' + IZQ + '" y1="0" x2="' + IZQ + '" y2="' + (H - 6) +
           '" stroke="#7E8D81" stroke-width="1" />';

    for (let i = 0; i < serie.length; i++) {
      const y0 = CABEZA + i * FILA;
      const unidades = serie[i].valor;
      const largo = (unidades / max) * ancho;

      /* Del rojo de critico a un tono intermedio segun lo que quede: con una
         unidad el color alerta de verdad, poniendose menos urgente al subir.
         Del #B3261E al #D6DBD2 no se puede, asi que se elige por umbrales. */
      const color = unidades <= 1 ? "#B3261E" : (unidades <= 3 ? "#8A5300" : "#8A6100");

      const descripcion = serie[i].extra + " (" + serie[i].etiqueta + "): " +
        unidades + " de " + serie[i].limite + " existencias";
      out += '<text x="' + IZQ + '" y="' + (y0 + 16) + '" font-size="12" fill="#14201A">' +
             escTexto(serie[i].etiqueta + "  ") + "</text>";
      out += '<rect x="' + IZQ + '" y="' + (y0 + 20) + '" width="' + largo +
             '" height="' + (FILA - 26) + '" rx="2" fill="' + color + '">' +
             "<title>" + escTexto(descripcion) + "</title></rect>";
      out += '<text x="' + (W - DER + 8) + '" y="' + (y0 + 34) + '" font-size="12" ' +
             'fill="#4E5F56">' + unidades + " de " + serie[i].limite + "</text>";
    }

    out += "</svg>";
    lienzo.setAttribute("aria-label",
      "Stock critico: " + serie.map(function (d) {
        return d.etiqueta + " " + d.valor + " existencias";
      }).join(", "));
    lienzo.innerHTML = out;
  }

  /* -------------------------------------------------------------------------
     EL QUE REPARTE: cada serie a su figura, por el prefijo del id.
     ------------------------------------------------------------------------- */
  function pintar(series) {
    dibujarMes(series.mes || []);
    dibujarCategoria(series.categoria || []);
    dibujarStock(series.stock || []);
  }

  /* -------------------------------------------------------------------------
     LA CONSULTA A LA API Y EL FILTRO DEL PUNTO 4
     -------------------------------------------------------------------------
     Al abrir la pagina no se manda ninguna fecha: la API devuelve el rango
     completo del historico y contesta tambien cual es ese rango, que se pone
     en los dos campos. Asi el filtro siempre dice la verdad sobre lo que esta
     dibujado, y lo unico que el usuario toca es un rango mas estrecho.

     Al enviar el formulario se cancela el recargo del navegador (evento
     submit y preventDefault) y se vuelve a llamar a la misma funcion de
     siempre: la pagina no se mueve, y ademas se puede ver en network inspector
     que la peticion es un GET normal a api/graficos.php con dos parametros y
     que contesta un JSON, sin consulta SQL dentro. */
  function buscar() {
    const parametros = new URLSearchParams();
    if (formulario.desde.value) parametros.set("desde", formulario.desde.value);
    if (formulario.hasta.value) parametros.set("hasta", formulario.hasta.value);
    const cadena = parametros.toString();

    estado.textContent = "Cargando...";

    fetch("api/graficos.php" + (cadena ? "?" + cadena : ""))
      .then(function (respuesta) {
        if (!respuesta.ok) throw new Error("HTTP " + respuesta.status);
        return respuesta.json();
      })
      .then(function (datos) {
        /* Si la sesion se cerro a mitad de la tarde, api/graficos.php contesta
           un 302 con el cuerpo vacio y fetch lo convierte en un JSON que
           revienta. Lo que llega aqui ya es JSON o nada, y el if de abajo
           tambien caza un {"ok": false} si la API lo llegara a mandar. */
        if (!datos || datos.ok !== true) {
          throw new Error(datos && datos.error ? datos.error : "La API no devolvio datos.");
        }

        formulario.desde.value = datos.rango.desde;
        formulario.hasta.value = datos.rango.hasta;
        pintar(datos.series);
        estado.textContent = "Rango " + datos.rango.desde + " a " + datos.rango.hasta + ".";
      })
      .catch(function (error) {
        /* Con la sesion vencida los tres lienzos dicen lo mismo, para que el
           error no quede en silencio. Si fuera otro error (MySQL apagado, por
           ejemplo) tambien se ve, y el detalle tecnico queda en consola. */
        vacio(lienzos.mes, "No hay datos. " + error.message);
        vacio(lienzos.categoria, "No hay datos. " + error.message);
        vacio(lienzos.stock, "No hay datos. " + error.message);
        estado.textContent = "Error al cargar: " + error.message;
        console.error("api/graficos.php ->", error);
      });
  }

  formulario.addEventListener("submit", function (evento) {
    evento.preventDefault();
    buscar();
  });

  buscar();
})();