const nf = new Intl.NumberFormat('es-PE');

export const num = (v) => (v === null || v === undefined ? '—' : nf.format(v));

export const pct = (part, total) => (total ? Math.round((part * 1000) / total) / 10 : 0);

export const bytes = (b) => {
    if (!b) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    while (b >= 1024 && i < units.length - 1) {
        b /= 1024;
        i++;
    }
    return `${b.toFixed(i > 2 ? 1 : 0)} ${units[i]}`;
};

const rtf = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });

export const ago = (iso) => {
    if (!iso) return 'nunca';
    const diff = (new Date(iso).getTime() - Date.now()) / 1000;
    const steps = [
        [60, 'second'],
        [3600, 'minute'],
        [86400, 'hour'],
        [604800, 'day'],
        [2629800, 'week'],
        [31557600, 'month'],
        [Infinity, 'year'],
    ];
    const divisors = { second: 1, minute: 60, hour: 3600, day: 86400, week: 604800, month: 2629800, year: 31557600 };
    const [, unit] = steps.find(([limit]) => Math.abs(diff) < limit);
    return rtf.format(Math.round(diff / divisors[unit]), unit);
};

export const dateTime = (iso) =>
    iso
        ? new Date(iso).toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' })
        : '—';

// Etiquetas legibles para los códigos que devuelve la API de tucolegio.
export const statusLabel = (s) =>
    ({
        asistio: 'Asistió',
        presente: 'Presente',
        tardanza: 'Tardanza',
        tardanza_justificada: 'Tardanza justificada',
        inasistencia: 'Falta',
        inasistencia_justificada: 'Falta justificada',
        justificada: 'Justificada',
        permiso: 'Permiso',
        no_corresponde: 'No corresponde',
        licencia_goce: 'Licencia con goce',
        licencia_sin_goce: 'Licencia sin goce',
        sent: 'Enviados',
        failed: 'Fallidos',
        cancelled: 'Cancelados',
        android: 'Android',
        ios: 'iOS',
        web: 'Navegador (push web)',
    })[s] ?? s.replaceAll('_', ' ');

export const ticket = {
    status: {
        abierto: { label: 'Abierto', tone: 'bg-sky-50 text-sky-700 ring-sky-200' },
        en_progreso: { label: 'En progreso', tone: 'bg-violet-50 text-violet-700 ring-violet-200' },
        esperando: { label: 'Esperando respuesta', tone: 'bg-amber-50 text-amber-700 ring-amber-200' },
        resuelto: { label: 'Resuelto', tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
        cerrado: { label: 'Cerrado', tone: 'bg-slate-100 text-slate-600 ring-slate-200' },
    },
    priority: {
        baja: { label: 'Baja', tone: 'text-slate-500', dot: 'bg-slate-300' },
        media: { label: 'Media', tone: 'text-sky-700', dot: 'bg-sky-500' },
        alta: { label: 'Alta', tone: 'text-amber-700', dot: 'bg-amber-500' },
        urgente: { label: 'Urgente', tone: 'text-rose-700', dot: 'bg-rose-500' },
    },
    category: {
        incidencia: 'Incidencia',
        consulta: 'Consulta',
        mejora: 'Mejora',
        capacitacion: 'Capacitación',
        configuracion: 'Configuración',
    },
};
