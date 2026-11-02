// Paleta de gráficos (instancia de referencia validada; ver skill dataviz).
// Categórica: orden fijo, nunca ciclar. Estado: solo cuando el color significa algo.
export const series = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

export const status = {
    good: '#0ca30c',
    warning: '#fab219',
    serious: '#ec835a',
    critical: '#d03b3b',
    neutral: '#b8b6ad',
};

export const ink = {
    primary: '#0b0b0b',
    secondary: '#52514e',
    muted: '#898781',
    grid: '#e1e0d9',
    axis: '#c3c2b7',
    surface: '#ffffff',
};

// Color estable por colegio: sigue a la entidad (su id), no a su posición.
export const schoolColor = (schools, id) => {
    const order = [...schools].map((s) => s.id).sort((a, b) => a - b);
    return series[order.indexOf(id) % series.length];
};

const shortDate = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short' });
const monthFmt = new Intl.DateTimeFormat('es-PE', { month: 'short', year: '2-digit' });

export const dayLabel = (iso) => shortDate.format(new Date(`${iso}T12:00:00`));
export const monthLabel = (ym) => monthFmt.format(new Date(`${ym}-15T12:00:00`));

/** Barras: extremo de datos redondeado (4px) y anclado a la base. */
export const bar = (label, data, color, extra = {}) => ({
    type: 'bar',
    label,
    data,
    backgroundColor: color,
    hoverBackgroundColor: color,
    borderRadius: 4,
    borderSkipped: 'start',
    maxBarThickness: 28,
    ...extra,
});

/** Segmento de barra apilada: 2px de separación con la superficie; el último lleva el extremo redondeado. */
export const stackSegment = (label, data, color, isTop = false, horizontal = false) => ({
    type: 'bar',
    label,
    data,
    backgroundColor: color,
    hoverBackgroundColor: color,
    borderColor: ink.surface,
    borderWidth: horizontal ? { right: 2 } : { top: 2 },
    borderRadius: isTop ? (horizontal ? { topRight: 4, bottomRight: 4 } : { topLeft: 4, topRight: 4 }) : 0,
    borderSkipped: false,
    maxBarThickness: 28,
    stack: 's',
});

export const line = (label, data, color, extra = {}) => ({
    type: 'line',
    label,
    data,
    borderColor: color,
    backgroundColor: color,
    borderWidth: 2,
    pointRadius: 0,
    pointHoverRadius: 5,
    pointHoverBorderWidth: 2,
    pointHoverBorderColor: ink.surface,
    tension: 0.3,
    ...extra,
});
