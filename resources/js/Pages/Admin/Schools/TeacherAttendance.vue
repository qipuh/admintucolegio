<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AttendanceMap from '@/Components/AttendanceMap.vue';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    school: { type: Object, required: true },
    filters: { type: Object, required: true },
    data: { type: Object, default: null },
    error: { type: String, default: null },
});

const f = reactive({
    level: props.filters.level ?? '',
    teacher_id: props.filters.teacher_id ?? '',
    mode: props.filters.mode ?? props.data?.mode ?? 'week',
    date: props.filters.date ?? props.data?.from ?? '',
    month: props.filters.month ?? props.data?.from?.slice(0, 7) ?? '',
    date_from: props.filters.date_from ?? props.data?.from ?? '',
    date_to: props.filters.date_to ?? props.data?.to ?? '',
    attendance: props.filters.attendance ?? '',
});

const loading = ref(false);

// Solo se envían los campos que corresponden al modo elegido.
const query = () => {
    const q = { mode: f.mode, level: f.level, teacher_id: f.teacher_id, attendance: f.attendance };
    if (f.mode === 'day' || f.mode === 'week') q.date = f.date;
    if (f.mode === 'month') q.month = f.month;
    if (f.mode === 'range') Object.assign(q, { date_from: f.date_from, date_to: f.date_to });
    return Object.fromEntries(Object.entries(q).filter(([, v]) => v !== '' && v !== null));
};

const search = () =>
    router.get(route('admin.schools.teacher-attendance', props.school.id), query(), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });

const clear = () => router.get(route('admin.schools.teacher-attendance', props.school.id));
const hasFilters = computed(() => Object.keys(props.filters).length > 0);
const exportUrl = computed(() => route('admin.schools.teacher-attendance.export', { school: props.school.id, ...query() }));

const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const fmtDay = (iso) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}`;
const fmtFull = (iso) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}/${iso.slice(0, 4)}`;

// Mismos colores de estado que la pantalla de tucolegio.
const statusClass = {
    presente: 'bg-green-100 text-green-700 border-green-300',
    justificada: 'bg-gray-200 text-gray-700 border-gray-400',
    permiso: 'bg-blue-100 text-blue-700 border-blue-300',
    tardanza: 'bg-amber-100 text-amber-700 border-amber-300',
    inasistencia: 'bg-red-100 text-red-700 border-red-300',
    licencia_goce: 'bg-indigo-100 text-indigo-700 border-indigo-300',
    licencia_sin_goce: 'bg-purple-100 text-purple-700 border-purple-300',
    no_corresponde: 'bg-slate-100 text-slate-600 border-slate-300',
};

const levelClass = {
    Inicial: 'bg-emerald-100 text-emerald-700',
    Primaria: 'bg-sky-100 text-sky-700',
    Secundaria: 'bg-violet-100 text-violet-700',
};

const summary = computed(() => {
    const t = props.data?.teachers ?? [];
    let marked = 0;
    let absent = 0;
    let late = 0;
    for (const row of t) {
        for (const c of Object.values(row.cells)) {
            if (c.type === 'marked') marked++;
            if (c.type === 'absent') absent++;
            if (c.type === 'marked' && c.diff_minutes > 0) late++;
        }
    }
    return { teachers: t.length, marked, absent, late, complete: t.filter((r) => r.scheduled > 0 && r.marked === r.scheduled).length };
});

const detail = ref(null);
const detailPoints = computed(() => {
    const d = detail.value;
    if (!d) return [];
    const pts = [];
    if (d.lat && d.lng) pts.push({ lat: d.lat, lng: d.lng, color: '#16a34a', label: `Entrada ${d.check_in ?? ''}` });
    if (d.lat2 && d.lng2) pts.push({ lat: d.lat2, lng: d.lng2, color: '#dc2626', label: `Salida ${d.check_out ?? ''}` });
    return pts;
});

const openDetail = (teacher, date, cell) => (detail.value = { ...cell, name: teacher.name, date: fmtFull(date) });

// Barra de scroll horizontal superior (fija arriba al bajar), espejo de la de la tabla.
// Al bajar, la misma franja muestra una copia de la cabecera de días.
const topBar = ref(null);
const tableWrap = ref(null);
const theadEl = ref(null);
const tableWidth = ref(0);
const colWidths = ref([]);
const scrollX = ref(0);
const overflowing = ref(false);
const headerHidden = ref(false);
let syncing = false;
let observer = null;
let headerObserver = null;

const measure = () => {
    const el = tableWrap.value;
    if (!el) return;
    tableWidth.value = el.scrollWidth;
    overflowing.value = el.scrollWidth > el.clientWidth + 1;
    colWidths.value = [...(theadEl.value?.querySelectorAll('th') ?? [])].map((th) => th.getBoundingClientRect().width);
};

// Mueve una barra cuando se mueve la otra, sin rebotar entre ambas.
const mirror = (from, to) => {
    if (syncing || !from || !to) return;
    syncing = true;
    to.scrollLeft = from.scrollLeft;
    requestAnimationFrame(() => (syncing = false));
};
const onTopScroll = () => mirror(topBar.value, tableWrap.value);
const onTableScroll = () => {
    scrollX.value = tableWrap.value?.scrollLeft ?? 0;
    mirror(tableWrap.value, topBar.value);
};

const observe = (el) => {
    observer?.disconnect();
    headerObserver?.disconnect();
    if (!el) return;
    observer.observe(el);
    if (el.firstElementChild) observer.observe(el.firstElementChild);
    if (theadEl.value) headerObserver.observe(theadEl.value);
    measure();
};

onMounted(() => {
    observer = new ResizeObserver(measure);
    // La cabecera real salió por arriba de la pantalla → mostrar la copia fija.
    headerObserver = new IntersectionObserver(([entry]) => {
        headerHidden.value = !entry.isIntersecting && entry.boundingClientRect.top < 0;
    });
    observe(tableWrap.value);
});
// La tabla puede aparecer o reemplazarse al filtrar (p. ej. tras un error).
watch(tableWrap, (el) => observer && observe(el));
onBeforeUnmount(() => {
    observer?.disconnect();
    headerObserver?.disconnect();
});

const inputClass ='rounded-lg border-slate-200 py-1.5 text-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15';
</script>

<template>
    <Head :title="`Asistencia docente · ${school.name}`" />

    <AuthenticatedLayout full>
        <template #header>
            <Link :href="route('admin.schools.show', school.id)" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
                <Icon name="arrow-left" class="h-4 w-4" /> {{ school.name }}
            </Link>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">Asistencia de docentes</h1>
                    <p class="mt-1 text-sm text-slate-500">Entradas y salidas por docente y día, consultadas en vivo desde el colegio.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                    <Icon name="eye" class="h-3.5 w-3.5" /> Solo lectura
                </span>
            </div>
        </template>

        <!-- Filtros -->
        <form class="card mb-4 flex flex-wrap items-end gap-2 p-3" @submit.prevent="search">
            <div>
                <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Nivel</label>
                <select v-model="f.level" :class="inputClass" @change="search">
                    <option value="">Todos</option>
                    <option v-for="l in data?.levels ?? []" :key="l" :value="l">{{ l }}</option>
                </select>
            </div>
            <div>
                <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Docente</label>
                <select v-model="f.teacher_id" :class="inputClass" class="max-w-[230px]" @change="search">
                    <option value="">Todos</option>
                    <option v-for="t in data?.all_teachers ?? []" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Periodo</label>
                <select v-model="f.mode" :class="inputClass">
                    <option value="day">Día</option>
                    <option value="week">Semana</option>
                    <option value="month">Mes</option>
                    <option value="range">Rango</option>
                </select>
            </div>
            <div v-if="f.mode === 'day' || f.mode === 'week'">
                <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">{{ f.mode === 'day' ? 'Fecha' : 'Semana de' }}</label>
                <input v-model="f.date" type="date" :class="inputClass" />
            </div>
            <div v-if="f.mode === 'month'">
                <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Mes</label>
                <input v-model="f.month" type="month" :class="inputClass" />
            </div>
            <template v-if="f.mode === 'range'">
                <div>
                    <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Desde</label>
                    <input v-model="f.date_from" type="date" :class="inputClass" />
                </div>
                <div>
                    <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Hasta</label>
                    <input v-model="f.date_to" type="date" :class="inputClass" />
                </div>
            </template>
            <div>
                <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Asistencia</label>
                <select v-model="f.attendance" :class="inputClass" @change="search">
                    <option value="">Todos</option>
                    <option value="asistieron">Asistieron</option>
                    <option value="faltaron">Con faltas</option>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700" :disabled="loading">
                <Icon name="search" class="h-4 w-4" /> Buscar
            </button>
            <button v-if="hasFilters" type="button" class="rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-50" title="Limpiar filtros" @click="clear">
                <Icon name="close" class="h-4 w-4" />
            </button>
            <a
                v-if="data"
                :href="exportUrl"
                class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-green-700"
            >
                <Icon name="download" class="h-4 w-4" /> Excel
            </a>
        </form>

        <div v-if="error" class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <Icon name="warning" class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="font-semibold">No se pudo consultar la asistencia del colegio.</p>
                <p class="mt-0.5 break-all">{{ error }}</p>
            </div>
        </div>

        <template v-else-if="data">
            <!-- Resumen del periodo -->
            <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="s in [
                    { l: 'Docentes', v: summary.teachers, c: 'text-slate-900' },
                    { l: 'Con asistencia completa', v: summary.complete, c: 'text-emerald-700' },
                    { l: 'Marcaciones', v: summary.marked, c: 'text-slate-900' },
                    { l: 'Llegadas tarde', v: summary.late, c: 'text-amber-700' },
                    { l: 'Sin marcar', v: summary.absent, c: 'text-rose-700' },
                ]" :key="s.l" class="card px-4 py-3">
                    <p class="font-display text-2xl font-bold tabular-nums" :class="s.c">{{ s.v }}</p>
                    <p class="text-xs text-slate-500">{{ s.l }}</p>
                </div>
            </div>

            <!-- Matriz docentes × días -->
            <div class="card transition-opacity" :class="{ 'opacity-50': loading }">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/60 px-5 py-3">
                    <h3 class="font-semibold text-slate-700">
                        Registro
                        <span class="ml-2 text-xs font-normal text-slate-500">
                            {{ fmtFull(data.from) }}<template v-if="data.from !== data.to"> — {{ fmtFull(data.to) }}</template>
                        </span>
                    </h3>
                    <div class="flex flex-wrap items-center gap-4 text-[11px] text-slate-500">
                        <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-green-400 bg-green-100" /> Marcó</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-red-300 bg-red-50" /> No marcó</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-blue-300 bg-blue-100" /> Permiso</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-gray-400 bg-gray-200" /> Justificada</span>
                        <span class="italic text-slate-400">(clic en una marcación para ver el detalle y el mapa)</span>
                    </div>
                </div>

                <!-- Franja fija arriba al bajar: copia de la cabecera de días + scroll horizontal superior. -->
                <div
                    v-show="overflowing || headerHidden"
                    class="sticky top-[57px] z-30 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur lg:top-0"
                >
                    <div v-if="headerHidden && colWidths.length" class="relative overflow-hidden bg-ink-900 text-white">
                        <div class="flex" :style="{ transform: `translateX(-${scrollX}px)`, width: `${tableWidth}px` }">
                            <div
                                v-for="(w, i) in colWidths"
                                :key="i"
                                class="shrink-0 border-l border-white/10 px-2 py-1.5 text-center text-xs font-semibold first:border-l-0"
                                :class="data.days[i - 1]?.is_today ? 'bg-brand-700' : ''"
                                :style="{ width: `${w}px` }"
                            >
                                <template v-if="i > 0 && i <= data.days.length">
                                    <span class="mr-1 text-[10px] uppercase opacity-70">{{ dayNames[data.days[i - 1].dow] }}</span>{{ fmtDay(data.days[i - 1].date) }}
                                </template>
                                <template v-else-if="i > data.days.length">Asist.</template>
                            </div>
                        </div>
                        <div class="absolute inset-y-0 left-0 flex items-center bg-ink-900 px-4 text-sm font-semibold" :style="{ width: `${colWidths[0]}px` }">
                            Docente
                        </div>
                    </div>
                    <div v-show="overflowing" ref="topBar" class="top-scroll overflow-x-auto overflow-y-hidden" @scroll="onTopScroll">
                        <div class="h-px" :style="{ width: `${tableWidth}px` }" />
                    </div>
                </div>

                <!-- Sin scroll vertical propio: la página completa baja. Solo scroll horizontal en periodos largos. -->
                <div ref="tableWrap" class="overflow-x-auto rounded-b-2xl" @scroll="onTableScroll">
                    <table class="w-full border-collapse text-left">
                        <thead ref="theadEl" class="bg-ink-900 text-white">
                            <tr>
                                <th class="sticky left-0 z-30 min-w-[240px] bg-ink-900 px-4 py-3 text-sm font-semibold">Docente</th>
                                <th
                                    v-for="d in data.days"
                                    :key="d.date"
                                    class="min-w-[104px] border-l border-white/10 px-2 py-2 text-center text-xs font-semibold"
                                    :class="d.is_today ? 'bg-brand-700' : ''"
                                >
                                    <div class="text-[10px] uppercase opacity-70">{{ dayNames[d.dow] }}</div>
                                    <div>{{ fmtDay(d.date) }}</div>
                                </th>
                                <th class="min-w-[70px] border-l border-white/10 px-3 py-3 text-center text-xs font-semibold">Asist.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="t in data.teachers" :key="t.id" class="hover:bg-slate-50/60">
                                <td class="sticky left-0 z-10 border-r border-slate-100 bg-white px-4 py-2">
                                    <p class="text-sm font-medium leading-tight text-slate-900">{{ t.name }}</p>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                        <span class="text-[10px] text-slate-400">{{ t.dni }}</span>
                                        <span
                                            v-if="t.late_minutes > 0"
                                            class="rounded bg-amber-100 px-1 py-0.5 text-[9px] font-bold uppercase text-amber-700"
                                            title="Minutos de tardanza acumulados en el periodo (entrada tarde + salida anticipada)"
                                        >⏱ {{ t.late_minutes }} min</span>
                                        <span
                                            v-for="lv in t.levels"
                                            :key="lv"
                                            class="rounded px-1 py-0.5 text-[9px] font-black uppercase"
                                            :class="levelClass[lv] ?? 'bg-gray-100 text-gray-500'"
                                        >{{ lv.slice(0, 3) }}</span>
                                    </div>
                                </td>

                                <template v-for="d in data.days" :key="d.date">
                                    <td
                                        v-if="t.cells[d.date]?.type === 'marked'"
                                        class="border-l border-slate-100 bg-green-50/50 px-1.5 py-1.5 text-center align-middle"
                                    >
                                        <button
                                            type="button"
                                            class="block w-full rounded py-0.5 text-[11px] leading-snug transition hover:bg-green-100"
                                            title="Ver detalle y mapa"
                                            @click="openDetail(t, d.date, t.cells[d.date])"
                                        >
                                            <span class="block font-semibold text-green-700">E {{ t.cells[d.date].check_in }}</span>
                                            <span
                                                v-if="t.cells[d.date].diff"
                                                class="block text-[9px] font-semibold"
                                                :class="t.cells[d.date].diff_minutes > 0 ? 'text-amber-600' : 'text-slate-400'"
                                            >{{ t.cells[d.date].diff }}</span>
                                            <template v-if="t.cells[d.date].check_out">
                                                <span class="block font-semibold text-blue-700">S {{ t.cells[d.date].check_out }}</span>
                                                <span class="block text-[9px] text-slate-400">{{ t.cells[d.date].worked }}</span>
                                                <span
                                                    v-if="t.cells[d.date].expected"
                                                    class="block text-[8px]"
                                                    :class="t.cells[d.date].worked_minutes < t.cells[d.date].expected_minutes ? 'text-red-500' : 'text-green-600'"
                                                >/ {{ t.cells[d.date].expected }} lect.</span>
                                            </template>
                                            <span v-else class="block text-slate-400">S —</span>
                                        </button>
                                        <span
                                            v-if="t.cells[d.date].status"
                                            class="mt-0.5 block rounded border px-1 text-[8px] font-bold uppercase"
                                            :class="statusClass[t.cells[d.date].status]"
                                        >{{ t.cells[d.date].status_label }}</span>
                                    </td>
                                    <td
                                        v-else-if="t.cells[d.date]?.type === 'status'"
                                        class="border-l border-slate-100 px-1.5 py-2 text-center align-middle text-[10px] font-bold uppercase leading-snug"
                                        :class="statusClass[t.cells[d.date].status]"
                                        :title="t.cells[d.date].status_note ?? ''"
                                    >{{ t.cells[d.date].status_label }}</td>
                                    <td v-else-if="t.cells[d.date]?.type === 'no_schedule'" class="border-l border-slate-100 px-1.5 py-1.5 text-center align-middle text-xs text-slate-300" title="Sin horario programado este día">—</td>
                                    <td v-else-if="t.cells[d.date]?.type === 'pending'" class="border-l border-slate-100 px-1.5 py-1.5 text-center align-middle text-xs text-amber-400" title="Su turno de hoy todavía no termina">⏳</td>
                                    <td v-else-if="t.cells[d.date]?.type === 'future'" class="border-l border-slate-100 px-1.5 py-1.5 text-center align-middle text-xs text-slate-300">·</td>
                                    <td v-else class="border-l border-slate-100 bg-red-50/60 px-1.5 py-1.5 text-center align-middle text-xs font-semibold text-red-500" title="No marcó">✗</td>
                                </template>

                                <td class="border-l border-slate-100 px-2 py-2 text-center">
                                    <span
                                        class="text-xs font-bold"
                                        :class="t.scheduled > 0 && t.marked === t.scheduled ? 'text-green-600' : t.marked === 0 ? 'text-red-500' : 'text-amber-600'"
                                    >{{ t.marked }}/{{ t.scheduled }}</span>
                                </td>
                            </tr>
                            <tr v-if="!data.teachers.length">
                                <td :colspan="data.days.length + 2" class="px-6 py-12 text-center text-sm text-slate-500">
                                    No hay docentes para los filtros seleccionados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Detalle de marcación (solo lectura) -->
        <Modal :show="!!detail" max-width="2xl" @close="detail = null">
            <div v-if="detail" class="p-6">
                <h3 class="font-display text-lg font-bold text-slate-900">Registro de asistencia — {{ detail.name }}</h3>
                <p class="mb-4 text-sm text-slate-500">{{ detail.date }}</p>

                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-green-50 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-green-600">Entrada</p>
                        <p class="text-lg font-bold text-green-700">{{ detail.check_in ?? '—' }}</p>
                        <p v-if="detail.diff" class="text-[10px] text-green-600">{{ detail.diff }}</p>
                    </div>
                    <div class="rounded-xl bg-blue-50 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-blue-600">Salida</p>
                        <p class="text-lg font-bold text-blue-700">{{ detail.check_out ?? '—' }}</p>
                        <p v-if="detail.worked" class="text-[10px] text-blue-600">{{ detail.worked }}</p>
                        <p v-if="detail.expected" class="text-[9px] text-slate-400">Lectivas: {{ detail.expected }}</p>
                    </div>
                    <div class="col-span-2 rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-slate-500">IP / Dispositivo</p>
                        <p class="break-all text-xs text-slate-700">{{ detail.ip ?? '—' }}<template v-if="detail.device"> · {{ detail.device }}</template></p>
                    </div>
                </div>

                <p v-if="detail.status_label" class="mb-4 text-sm text-slate-600">
                    Estado: <span class="rounded border px-1.5 py-0.5 text-xs font-bold uppercase" :class="statusClass[detail.status]">{{ detail.status_label }}</span>
                    <span v-if="detail.status_note" class="ml-1 text-slate-500">— {{ detail.status_note }}</span>
                </p>

                <template v-if="detailPoints.length">
                    <div class="mb-2 flex flex-wrap items-center gap-4 text-xs text-slate-600">
                        <span v-if="detail.lat && detail.lng" class="flex items-center gap-1">
                            <span class="inline-block h-3 w-3 rounded-full border-2 border-white bg-green-600 shadow" />
                            Entrada <strong>{{ detail.check_in }}</strong>
                            <span v-if="detail.acc" class="text-slate-400">±{{ Math.round(detail.acc) }} m</span>
                        </span>
                        <span v-if="detail.lat2 && detail.lng2" class="flex items-center gap-1">
                            <span class="inline-block h-3 w-3 rounded-full border-2 border-white bg-red-600 shadow" />
                            Salida <strong>{{ detail.check_out }}</strong>
                            <span v-if="detail.acc2" class="text-slate-400">±{{ Math.round(detail.acc2) }} m</span>
                        </span>
                        <span v-if="detail.check_out && !(detail.lat2 && detail.lng2)" class="italic text-slate-400">(salida sin ubicación capturada)</span>
                    </div>
                    <AttendanceMap :points="detailPoints" />
                </template>
                <div v-else class="rounded-xl border border-dashed border-slate-200 bg-slate-50 py-8 text-center text-sm text-slate-400">
                    Ubicación no capturada en este registro
                </div>

                <div class="mt-5 flex justify-end">
                    <SecondaryButton @click="detail = null">Cerrar</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Barra superior siempre visible (algunos sistemas ocultan las barras de scroll). */
.top-scroll {
    scrollbar-width: auto;
    scrollbar-color: #94a3b8 #f1f5f9;
}
.top-scroll::-webkit-scrollbar {
    height: 12px;
}
.top-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 9999px;
}
.top-scroll::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 9999px;
    border: 2px solid #f1f5f9;
}
.top-scroll::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}
</style>
