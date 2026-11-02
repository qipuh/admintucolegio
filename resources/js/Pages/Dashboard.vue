<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChartCanvas from '@/Components/ChartCanvas.vue';
import Icon from '@/Components/Icon.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import { ago, num, pct, ticket } from '@/utils/format';
import { bar, dayLabel, line, schoolColor, series, stackSegment, status } from '@/utils/charts';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    totals: { type: Object, required: true },
    schools: { type: Array, required: true },
    charts: { type: Array, required: true },
    alerts: { type: Array, required: true },
    tickets: { type: Object, required: true },
});

const syncing = ref(false);
const syncAll = () =>
    router.post(route('admin.schools.sync-all'), {}, {
        preserveScroll: true,
        onStart: () => (syncing.value = true),
        onFinish: () => (syncing.value = false),
    });

const color = (id) => schoolColor(props.schools, id);
const familyAdoption = computed(() => pct(props.totals.families_with_app, props.totals.families));
const lastSync = computed(() => props.schools.map((s) => s.synced_at).filter(Boolean).sort().at(-1) ?? null);

// Días con clase en al menos un colegio (descarta fines de semana y feriados).
const schoolDays = computed(() => {
    const days = new Set();
    for (const c of props.charts) {
        for (const d of c.attendance_daily) {
            if (d.asistio + d.tardanza + d.falta + d.justificada > 0) days.add(d.date);
        }
    }
    return [...days].sort();
});

const attendanceTrend = computed(() => {
    const datasets = props.charts.map((c) => {
        const byDate = Object.fromEntries(c.attendance_daily.map((d) => [d.date, d]));
        return line(
            c.name,
            schoolDays.value.map((day) => {
                const d = byDate[day];
                const total = d ? d.asistio + d.tardanza + d.falta + d.justificada : 0;
                return total ? pct(d.asistio + d.tardanza, total) : null;
            }),
            color(c.id),
            { spanGaps: true },
        );
    });
    // Eje ajustado al rango real (la asistencia suele moverse entre 80 y 100%).
    const values = datasets.flatMap((d) => d.data).filter((v) => v !== null);
    const min = values.length ? Math.max(0, Math.floor((Math.min(...values) - 5) / 10) * 10) : 0;
    return { labels: schoolDays.value.map(dayLabel), datasets, min };
});

const networkAttendance = computed(() => {
    let present = 0;
    let total = 0;
    for (const c of props.charts) {
        for (const d of c.attendance_daily) {
            present += d.asistio + d.tardanza;
            total += d.asistio + d.tardanza + d.falta + d.justificada;
        }
    }
    return total ? pct(present, total) : null;
});

const roleGroups = [
    { label: 'Familias', roles: ['Apoderado', 'Padre'] },
    { label: 'Estudiantes', roles: ['Estudiante'] },
    { label: 'Docentes', roles: ['Docente'] },
];
const usersByType = computed(() => {
    const groups = [
        ...roleGroups,
        { label: 'Personal y directivos', roles: null },
    ];
    const known = roleGroups.flatMap((g) => g.roles);
    return {
        labels: props.charts.map((c) => c.name),
        datasets: groups.map((g, i) =>
            stackSegment(
                g.label,
                props.charts.map((c) =>
                    Object.entries(c.roles)
                        .filter(([role]) => (g.roles ? g.roles.includes(role) : !known.includes(role)))
                        .reduce((s, [, n]) => s + n, 0),
                ),
                series[i],
                i === groups.length - 1,
                true,
            ),
        ),
    };
});

const adoptionBySchool = computed(() => ({
    labels: props.charts.map((c) => c.name),
    datasets: [
        { key: 'families', label: 'Familias' },
        { key: 'students', label: 'Estudiantes' },
        { key: 'teachers', label: 'Docentes' },
    ].map((g, i) =>
        bar(
            g.label,
            props.charts.map((c) => pct(c.adoption[g.key]?.with_app ?? 0, c.adoption[g.key]?.total ?? 0)),
            series[i],
            { borderSkipped: 'start', maxBarThickness: 16 },
        ),
    ),
}));

const sumDaily = (key, field) => {
    const acc = {};
    for (const c of props.charts) {
        for (const d of c[key]) acc[d.date] = (acc[d.date] ?? 0) + (d[field] ?? 0);
    }
    return acc;
};


const installs = computed(() => {
    const platforms = [
        { key: 'android', label: 'Android' },
        { key: 'ios', label: 'iOS' },
        { key: 'web', label: 'Push navegador' },
    ];
    const weeks = props.charts[0]?.installs_weekly.map((w) => w.week) ?? [];
    return {
        labels: weeks.map((w) => `Sem. ${dayLabel(w)}`),
        datasets: platforms.map((p, i) =>
            stackSegment(
                p.label,
                weeks.map((w) => props.charts.reduce((s, c) => s + (c.installs_weekly.find((x) => x.week === w)?.[p.key] ?? 0), 0)),
                series[i],
                i === platforms.length - 1,
            ),
        ),
    };
});

const teacherDaily = computed(() => {
    const keys = [
        { key: 'presente', label: 'Puntual', color: status.good },
        { key: 'tardanza', label: 'Tardanza', color: status.warning },
        { key: 'falta', label: 'Falta', color: status.critical },
        { key: 'licencia', label: 'Licencia / permiso', color: status.neutral },
    ];
    const sums = Object.fromEntries(keys.map((k) => [k.key, sumDaily('teacher_daily', k.key)]));
    const dates = Object.keys(sums.presente)
        .filter((d) => keys.some((k) => sums[k.key][d] > 0))
        .sort();
    return {
        labels: dates.map(dayLabel),
        datasets: keys.map((k, i) => stackSegment(k.label, dates.map((d) => sums[k.key][d]), k.color, i === keys.length - 1)),
    };
});


const alertTone = {
    error: 'border-rose-200 bg-rose-50 text-rose-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    info: 'border-slate-200 bg-slate-50 text-slate-600',
};
</script>

<template>
    <Head title="Resumen" />

    <AuthenticatedLayout wide>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">Resumen de la red</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ totals.schools_synced }} de {{ totals.schools_active }} colegios activos con datos ·
                        última sincronización {{ ago(lastSync) }}
                    </p>
                </div>
                <SecondaryButton :disabled="syncing" @click="syncAll">
                    <Icon name="refresh" class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
                    {{ syncing ? 'Sincronizando…' : 'Sincronizar todo' }}
                </SecondaryButton>
            </div>
        </template>

        <!-- KPIs -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
            <StatCard label="Usuarios registrados" :value="totals.users" icon="users" />
            <StatCard label="Alumnos matriculados" :value="totals.enrolled" icon="school" tone="bg-violet-50 text-violet-600" />
            <StatCard label="Docentes activos" :value="totals.teachers" icon="user" tone="bg-sky-50 text-sky-600" />
            <StatCard
                label="Asistencia alumnos"
                :value="networkAttendance !== null ? `${networkAttendance}%` : null"
                icon="calendar"
                tone="bg-emerald-50 text-emerald-600"
                hint="Promedio últimos 30 días"
            />
            <StatCard
                label="Usuarios con la app"
                :value="totals.users_with_app"
                icon="phone"
                tone="bg-brand-50 text-brand-600"
                :hint="`${num(totals.app_active_7d)} activos en 7 días`"
            />
            <StatCard
                label="Familias con la app"
                :value="`${familyAdoption}%`"
                icon="chart"
                tone="bg-amber-50 text-amber-600"
                :hint="`${num(totals.families_with_app)} de ${num(totals.families)}`"
            />
        </div>

        <!-- Asistencia + alertas -->
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="card p-6 xl:col-span-2">
                <h2 class="font-display text-lg font-bold text-slate-900">Asistencia de alumnos por colegio</h2>
                <p class="text-sm text-slate-500">% de alumnos presentes (incluye tardanzas) por día de clases, últimos 30 días.</p>
                <div class="mt-4">
                    <ChartCanvas v-if="schoolDays.length" type="line" :labels="attendanceTrend.labels" :datasets="attendanceTrend.datasets" percent :y-min="attendanceTrend.min" :y-max="100" :height="300" :legend="true" />
                    <p v-else class="py-16 text-center text-sm text-slate-400">Sin registros de asistencia.</p>
                </div>
            </section>

            <section class="card flex flex-col p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold text-slate-900">Alertas</h2>
                    <span class="text-xs text-slate-400">{{ alerts.length }} activas</span>
                </div>
                <div v-if="!alerts.length" class="mt-6 flex items-center gap-3 text-sm text-emerald-700">
                    <Icon name="check-circle" class="h-5 w-5" /> Todo en orden.
                </div>
                <ul v-else class="mt-4 max-h-[300px] flex-1 space-y-2 overflow-y-auto pr-1">
                    <li v-for="(a, i) in alerts" :key="i">
                        <Link
                            :href="route('admin.schools.show', a.school_id)"
                            class="flex items-start gap-3 rounded-xl border px-3.5 py-2.5 text-sm transition hover:brightness-95"
                            :class="alertTone[a.level]"
                        >
                            <Icon :name="a.level === 'info' ? 'info' : 'warning'" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span><strong class="font-semibold">{{ a.school }}:</strong> {{ a.message }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Comunidad + adopción -->
        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="card p-6">
                <h2 class="font-display text-lg font-bold text-slate-900">Usuarios por tipo</h2>
                <p class="text-sm text-slate-500">Cuentas registradas en cada colegio según su rol.</p>
                <div class="mt-4">
                    <ChartCanvas :labels="usersByType.labels" :datasets="usersByType.datasets" horizontal stacked :height="Math.max(160, charts.length * 64 + 70)" />
                </div>
            </section>
            <section class="card p-6">
                <h2 class="font-display text-lg font-bold text-slate-900">Adopción de la app</h2>
                <p class="text-sm text-slate-500">% de cada grupo con la app instalada (Android o iOS).</p>
                <div class="mt-4">
                    <ChartCanvas :labels="adoptionBySchool.labels" :datasets="adoptionBySchool.datasets" horizontal percent :y-max="100" :height="Math.max(160, charts.length * 80 + 70)" />
                </div>
            </section>
        </div>

        <!-- Dispositivos + soporte -->
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="card p-6 xl:col-span-2">
                <h2 class="font-display text-lg font-bold text-slate-900">Dispositivos nuevos por semana</h2>
                <p class="text-sm text-slate-500">Registros de notificaciones por plataforma, últimas 12 semanas.</p>
                <div class="mt-4">
                    <ChartCanvas :labels="installs.labels" :datasets="installs.datasets" stacked :height="260" />
                </div>
            </section>

            <section class="card flex flex-col p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold text-slate-900">Soporte</h2>
                    <Link :href="route('admin.support.index')" class="text-sm font-medium text-brand-600 hover:text-brand-700">Ver todo</Link>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="font-display text-2xl font-bold text-slate-900">{{ tickets.open }}</p>
                        <p class="text-xs text-slate-500">Pendientes</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 p-3">
                        <p class="font-display text-2xl font-bold text-rose-700">{{ tickets.urgent }}</p>
                        <p class="text-xs text-rose-600">Alta / urgente</p>
                    </div>
                </div>
                <ul class="mt-4 flex-1 divide-y divide-slate-100">
                    <li v-for="t in tickets.recent" :key="t.id">
                        <Link :href="route('admin.support.show', t.id)" class="flex items-center gap-2.5 py-2.5 text-sm hover:text-brand-700">
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="ticket.priority[t.priority].dot" />
                            <span class="min-w-0 flex-1 truncate text-slate-700">{{ t.title }}</span>
                            <span class="shrink-0 text-xs text-slate-400">{{ t.school?.name ?? 'General' }}</span>
                        </Link>
                    </li>
                    <li v-if="!tickets.recent.length" class="py-3 text-sm text-slate-400">Sin tickets pendientes.</li>
                </ul>
                <Link
                    :href="route('admin.support.create')"
                    class="mt-3 inline-flex items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-2 text-sm font-medium text-slate-600 hover:border-brand-400 hover:text-brand-600"
                >
                    <Icon name="plus" class="h-4 w-4" /> Nuevo ticket
                </Link>
            </section>
        </div>

        <section class="card mt-6 p-6">
            <h2 class="font-display text-lg font-bold text-slate-900">Asistencia del personal docente</h2>
            <p class="text-sm text-slate-500">Marcaciones diarias de toda la red por resultado, últimos 30 días.</p>
            <div class="mt-4">
                <ChartCanvas :labels="teacherDaily.labels" :datasets="teacherDaily.datasets" stacked :height="240" />
            </div>
        </section>

        <!-- Tabla comparativa -->
        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-slate-900">Comparativo de colegios</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Colegio</th>
                            <th class="px-4 py-3 text-right">Usuarios</th>
                            <th class="px-4 py-3 text-right">Matriculados</th>
                            <th class="px-4 py-3 text-right">Docentes</th>
                            <th class="px-4 py-3 text-right">Con app</th>
                            <th class="px-4 py-3 text-right">Activos app 7 d</th>
                            <th class="px-4 py-3 text-right">Activos web 7 d</th>
                            <th class="px-4 py-3">Adopción familias</th>
                            <th class="px-6 py-3">Sincronización</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="s in schools"
                            :key="s.id"
                            class="cursor-pointer transition hover:bg-slate-50/70"
                            @click="router.visit(route('admin.schools.show', s.id))"
                        >
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ background: color(s.id) }" />
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ s.name }}</p>
                                        <p class="text-xs text-slate-400">{{ s.domain }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-right tabular-nums">{{ num(s.users) }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums">{{ num(s.enrolled) }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums">{{ num(s.teachers) }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums">{{ num(s.users_with_app) }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums">{{ num(s.app_active_7d) }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums">{{ num(s.web_active_7d) }}</td>
                            <td class="px-4 py-3.5">
                                <div v-if="s.family_adoption !== null" class="flex items-center gap-2">
                                    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${Math.min(100, s.family_adoption)}%` }" />
                                    </div>
                                    <span class="tabular-nums text-slate-600">{{ s.family_adoption }}%</span>
                                </div>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span v-if="!s.has_api_key" class="text-xs text-slate-400">Sin clave</span>
                                <span v-else-if="s.sync_status === 'error'" class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500" /> Error
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" /> {{ ago(s.synced_at) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
