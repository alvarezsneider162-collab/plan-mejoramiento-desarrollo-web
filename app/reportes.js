(function () {
  "use strict";

  const datosNodo = document.getElementById("datos-grafico-reporte");
  const lienzo = document.getElementById("grafico-reporte");
  const botonImprimir = document.getElementById("imprimir-reporte");

  if (botonImprimir) {
    botonImprimir.addEventListener("click", function () {
      window.print();
    });
  }

  if (!datosNodo || !lienzo || typeof Chart === "undefined") return;

  const datos = JSON.parse(datosNodo.textContent);
  const colores = ["#0F5132", "#C68A00", "#176B87", "#B3261E", "#6A7D36", "#8A4B08"];
  const configuracion = {
    type: datos.tipo,
    data: {
      labels: datos.etiquetas,
      datasets: [{
        label: datos.tipo === "doughnut" ? "Ingresos" : "Valor registrado",
        data: datos.valores,
        backgroundColor: datos.etiquetas.map(function (_, indice) {
          return colores[indice % colores.length];
        }),
        borderColor: "#FFFFFF",
        borderWidth: 2,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      indexAxis: datos.tipo === "bar" ? "y" : "x",
      plugins: {
        legend: { display: datos.tipo === "doughnut", position: "bottom" },
        tooltip: {
          callbacks: {
            label: function (contexto) {
              const valor = Number(contexto.raw || 0).toLocaleString("es-CO");
              return (contexto.label ? contexto.label + ": " : "") + "$" + valor;
            },
          },
        },
      },
      scales: datos.tipo === "bar" ? {
        x: { beginAtZero: true, ticks: { callback: function (valor) {
          return "$" + Number(valor).toLocaleString("es-CO");
        } } },
        y: { ticks: { autoSkip: false } },
      } : {},
    },
  };

  new Chart(lienzo, configuracion);
})();