/* Ejercicios de arreglos — Actividad de recuperación, día 7
   Los cuatro cálculos del punto 2, resueltos con métodos de arreglo y sin
   una sola línea de for. Cargar este archivo DESPUÉS de datos-prueba.js. */

/* Punto 3 — la moneda.
   El peso colombiano no tiene subdivision en uso everyday, así que los
   precios se formatean sin decimales. El promedio sí cae en centavos, así
   que para él hace falta un segundo formateador: redondearlo al peso
   entero estaría报告显示 un número que el cálculo no dio. */
const pesos = new Intl.NumberFormat("es-CO", {
  style: "currency",
  currency: "COP",
  maximumFractionDigits: 0
});

const pesosConCentavos = new Intl.NumberFormat("es-CO", {
  style: "currency",
  currency: "COP",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});

/* Cálculo 1 — el producto más caro.
   Un reduce que va guardando el ganador; el ternario decide si el
   producto que está pasando supera al que ya tenía. */
const productoMasCaro = productos.reduce((masCaro, producto) =>
  producto.valor_alquiler > masCaro.valor_alquiler ? producto : masCaro
);

/* Cálculo 2 — total de unidades por categoría.
   El reduce usa un objeto vacío como acumulador. La primera vez que aparece
   una categoría no existe todavía en el objeto, y ?? 0 la crea en cero. */
const unidadesPorCategoria = productos.reduce((acumulado, producto) => {
  acumulado[producto.categoria] =
    (acumulado[producto.categoria] ?? 0) + producto.existencias;
  return acumulado;
}, {});

/* Cálculo 3 — productos con existencias menores a cinco.
   filter y sort encadenados: primero se queda con los que cumplen, después
   los ordena de menos a más existencias para que el más crítico salga primero. */
const stockBajo = productos
  .filter(producto => producto.existencias < 5)
  .sort((a, b) => a.existencias - b.existencias);

/* Cálculo 4 — promedio de precio de alquiler.
   map extrae los precios, reduce los suma, y el resultado se divide entre
   la cantidad de productos. */
const promedioPrecio = productos
  .map(producto => producto.valor_alquiler)
  .reduce((suma, valor) => suma + valor, 0) / productos.length;

/* ===== Salida en consola ================================================= */

console.log("%cActividad día 7 — los cuatro cálculos con métodos de arreglo",
  "font-weight:700; font-size:14px; color:#0A3A24");
console.log(
  productos.length + " productos y " + pedidos.length +
  " pedidos cargados desde datos-prueba.js"
);

console.log("");
console.log("%c1. Producto más caro", "font-weight:700; color:#0A3A24");
console.table([{
  "Código": productoMasCaro.codigo,
  "Producto": productoMasCaro.nombre,
  "Categoría": productoMasCaro.categoria,
  "Condición": productoMasCaro.condicion,
  "Existencias": productoMasCaro.existencias,
  "Valor de alquiler": pesos.format(productoMasCaro.valor_alquiler)
}]);

console.log("");
console.log("%c2. Total de unidades por categoría", "font-weight:700; color:#0A3A24");
console.table(Object.entries(unidadesPorCategoria).map(([categoria, unidades]) => ({
  "Categoría": categoria,
  "Unidades": unidades
})));

console.log("");
console.log("%c3. Productos con existencias menores a cinco", "font-weight:700; color:#0A3A24");
console.table(stockBajo.map(producto => ({
  "Código": producto.codigo,
  "Producto": producto.nombre,
  "Categoría": producto.categoria,
  "Condición": producto.condicion,
  "Existencias": producto.existencias,
  "Valor de alquiler": pesos.format(producto.valor_alquiler)
})));

console.log("");
console.log("%c4. Promedio de precio de alquiler", "font-weight:700; color:#0A3A24");
console.log("  " + pesosConCentavos.format(promedioPrecio));

console.log("");
console.log("%cLos ocho pedidos de prueba (punto 1)", "font-weight:700; color:#0A3A24");
console.table(pedidos.map(pedido => ({
  "Pedido": pedido.id,
  "Cliente": pedido.cliente,
  "Fecha": pedido.fecha,
  "Estado": pedido.estado,
  "Ítems pedidos": pedido.items
    .map(item => item.codigo + " ×" + item.cantidad)
    .join(", "),
  "Total": pesos.format(pedido.total)
})));
