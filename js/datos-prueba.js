/* Datos de prueba — Actividad de recuperación, día 7
   Cada objeto tiene la forma que tendrá su tabla en la base de datos: son
   los mismos campos que ya usa productos.html, con los mismos nombres. */

const productos = [
  { codigo: "TAC-001", nombre: "Taco de pool 1,45 m",           categoria: "Implemento de juego", condicion: "Nuevo",         existencias:  8, valor_alquiler: 12000 },
  { codigo: "BOL-004", nombre: "Juego de bolas de billar",      categoria: "Implemento de juego", condicion: "Nuevo",         existencias:  3, valor_alquiler: 45000 },
  { codigo: "TRI-002", nombre: "Triángulo para bolas",          categoria: "Implemento de juego", condicion: "Nuevo",         existencias:  5, valor_alquiler:  8000 },
  { codigo: "EXT-005", nombre: "Extensión de taco",             categoria: "Implemento de juego", condicion: "En reparación", existencias:  1, valor_alquiler: 10000 },
  { codigo: "PUN-006", nombre: "Puntería profesional",          categoria: "Implemento de juego", condicion: "Nuevo",         existencias:  4, valor_alquiler: 18000 },
  { codigo: "SOP-016", nombre: "Soporete para taco",            categoria: "Implemento de juego", condicion: "Nuevo",         existencias: 15, valor_alquiler:  5000 },
  { codigo: "TIZ-010", nombre: "Tiza de billar",                categoria: "Consumible",          condicion: "Nuevo",         existencias: 24, valor_alquiler:  2000 },
  { codigo: "GUA-003", nombre: "Guantes de fibra",              categoria: "Consumible",          condicion: "Semi-nuevo",     existencias:  6, valor_alquiler: 15000 },
  { codigo: "GUA-007", nombre: "Guantes de cuero sintético",   categoria: "Consumible",          condicion: "Semi-nuevo",     existencias:  7, valor_alquiler: 22000 },
  { codigo: "AGT-014", nombre: "Agente de limpieza para mesas", categoria: "Consumible",          condicion: "Nuevo",         existencias: 12, valor_alquiler:  7500 },
  { codigo: "TIZ-015", nombre: "Tiza de Tournament",            categoria: "Consumible",          condicion: "Nuevo",         existencias: 20, valor_alquiler:  4500 },
  { codigo: "REP-011", nombre: "Repuesto de punta de taco",     categoria: "Repuesto",            condicion: "Nuevo",         existencias: 18, valor_alquiler:  3500 },
  { codigo: "REP-012", nombre: "Banda de goma para pocket",     categoria: "Repuesto",            condicion: "Nuevo",         existencias:  9, valor_alquiler:  6000 },
  { codigo: "CON-013", nombre: "Conector de extensión",         categoria: "Repuesto",            condicion: "En reparación", existencias:  2, valor_alquiler:  9000 },
  { codigo: "MES-008", nombre: "Mesa de billar 8 pies",         categoria: "Mesa de billar",      condicion: "Nuevo",         existencias:  2, valor_alquiler: 60000 },
  { codigo: "MES-009", nombre: "Mesa de billar 9 pies",         categoria: "Mesa de billar",      condicion: "Nuevo",         existencias:  1, valor_alquiler: 85000 }
];

const pedidos = [
  {
    id: "PED-001", cliente: "Club de Billar La 8", fecha: "2026-09-27", estado: "Confirmado",
    items: [
      { codigo: "MES-008", cantidad: 1 },
      { codigo: "TAC-001", cantidad: 4 }
    ],
    total: 108000
  },
  {
    id: "PED-002", cliente: "Federación Antioqueña de Pool", fecha: "2026-09-28", estado: "Confirmado",
    items: [
      { codigo: "MES-009", cantidad: 1 },
      { codigo: "BOL-004", cantidad: 2 },
      { codigo: "PUN-006", cantidad: 4 }
    ],
    total: 247000
  },
  {
    id: "PED-003", cliente: "Cafeterías del Centro", fecha: "2026-09-28", estado: "Pendiente",
    items: [
      { codigo: "TIZ-010", cantidad: 6 },
      { codigo: "GUA-003", cantidad: 2 }
    ],
    total: 42000
  },
  {
    id: "PED-004", cliente: "Universidad de Antioquia", fecha: "2026-09-29", estado: "Entregado",
    items: [
      { codigo: "SOP-016", cantidad: 10 },
      { codigo: "TRI-002", cantidad: 2 },
      { codigo: "REP-011", cantidad: 4 }
    ],
    total: 80000
  },
  {
    id: "PED-005", cliente: "Club de Billar La 8", fecha: "2026-09-29", estado: "Confirmado",
    items: [
      { codigo: "MES-008", cantidad: 1 },
      { codigo: "MES-009", cantidad: 1 },
      { codigo: "BOL-004", cantidad: 1 }
    ],
    total: 190000
  },
  {
    id: "PED-006", cliente: "Hotel Boutique del Río", fecha: "2026-09-30", estado: "Pendiente",
    items: [
      { codigo: "GUA-007", cantidad: 4 },
      { codigo: "EXT-005", cantidad: 1 }
    ],
    total: 98000
  },
  {
    id: "PED-007", cliente: "Torneo Municipal de Pool", fecha: "2026-10-01", estado: "Confirmado",
    items: [
      { codigo: "MES-009", cantidad: 1 },
      { codigo: "MES-008", cantidad: 1 },
      { codigo: "PUN-006", cantidad: 2 },
      { codigo: "TIZ-015", cantidad: 8 }
    ],
    total: 217000
  },
  {
    id: "PED-008", cliente: "Cafetería La Curva", fecha: "2026-10-02", estado: "Entregado",
    items: [
      { codigo: "TIZ-010", cantidad: 4 },
      { codigo: "AGT-014", cantidad: 1 },
      { codigo: "REP-012", cantidad: 2 }
    ],
    total: 27500
  }
];
