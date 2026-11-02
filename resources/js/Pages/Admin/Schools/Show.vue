<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChartCanvas from '@/Components/ChartCanvas.vue';
import Icon from '@/Components/Icon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import { ago, bytes, dateTime, num, pct, ticket } from '@/utils/format';
import { bar, dayLabel, line, monthLabel, series, stackSegment, status } from '@/utils/charts';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    school: { type: Object, required: true },
    tickets: { type: Array, required: true },
    openTickets: { type: Number, required: true },
});

const tab = ref('stats');
const syncing = ref(false);

const st = computed(() => props.school.stats ?? null);
const hl = computed(() => props.school.health ?? null);

const sync = () =>
    router.post(route('admin.schools.sync', props.school.id), {}, {
        preserveScroll: true,
        onStart: () => (syncing.value = true),
        onFinish: () => (syncing.value = false),
    });

const attendanceRate = computed(() => {
    const a = st.value?.activity?.student_attendance_30d ?? {};
    const total = Object.values(a).reduce((s, v) => s + v, 0);
    return total ? pct((a.asistio ?? 0) + (a.tardanza ?? 0) + (a.tardanza_justificada ?? 0), total) : null;
});

const diskUsed = computed(() => {
    const d = hl.value?.disk;
    return d?.total_bytes ? pct(d.total_bytes - d.free_bytes, d.total_bytes) : null;
});

const checks = computed(() => {
    const h = hl.value;
    if (!h) return [];
    return [
        { label: 'Base de datos', ok: h.services.database },
        { label: 'Almacenamiento escribible', ok: h.services.storage_writable },
        { label: 'Modo debug apagado', ok: !(h.app.debug && h.app.env === 'production'), note: h.app.debug ? 'APP_DEBUG=true' : '' },
        { label: 'Cola sin fallos (24 h)', ok: h.queue.failed_24h === 0, note: h.queue.failed_24h ? `${h.queue.failed_24h} fallidos` : '' },
        { label: 'Espacio en disco', ok: diskUsed.value === null || diskUsed.value < 90, note: diskUsed.value !== null ? `${diskUsed.value}% usado` : '' },
    ];
});

// Agrupa trabajos fallidos con el mismo job + error (suelen repetirse).
const failedGroups = computed(() => {
    const groups = new Map();
    for (const j of hl.value?.failed_jobs ?? []) {
        const key = `${j.job}|${j.error}`;
        const g = groups.get(key);
        if (g) {
            g.count++;
        } else {
            groups.set(key, { ...j, count: 1 });
        }
    }
    return [...groups.values()];
});

// ── Gráficos de la pestaña Estadísticas ─────────────────────────────
const sr = computed(() => st.value?.series ?? {});

const attendanceKeys = [
    { key: 'asistio', label: 'Asistió', color: status.good },
    { key: 'tardanza', label: 'Tardanza', color: status.warning, dark: true },
    { key: 'falta', label: 'Falta', color: status.critical },
    { key: 'justificada', label: 'Falta justificada', color: status.neutral, dark: true },
];

const classDays = (rows, keys) => (rows ?? []).filter((d) => keys.some((k) => d[k.key] > 0));

const studentDaily = computed(() => {
    const rows = classDays(sr.value.student_attendance_daily, attendanceKeys);
    return {
        labels: rows.map((d) => dayLabel(d.date)),
        datasets: attendanceKeys.map((k, i) => stackSegment(k.label, rows.map((d) => d[k.key]), k.color, i === attendanceKeys.length - 1)),
    };
});

const attendanceMix = computed(() => {
    const rows = sr.value.student_attendance_daily ?? [];
    const totals = attendanceKeys.map((k) => ({ ...k, value: rows.reduce((s, d) => s + (d[k.key] ?? 0), 0) }));
    const all = totals.reduce((s, t) => s + t.value, 0);
    return totals.map((t) => ({ ...t, share: pct(t.value, all) }));
});

const ordinal = { PRIMERO: '1°', SEGUNDO: '2°', TERCERO: '3°', CUARTO: '4°', QUINTO: '5°', SEXTO: '6°' };
const gradeLabel = (level, grade) => `${ordinal[grade] ?? grade} ${level?.slice(0, 3) ?? ''}`.trim();
const levelColor = (level, levels) => series[levels.indexOf(level) % series.length];

const gradeAttendance = computed(() => {
    const rows = sr.value.attendance_by_grade_30d ?? [];
    const values = rows.map((r) => (r.total ? pct(r.asistio + r.tardanza, r.total) : 0));
    const avg = attendanceRate.value ?? 0;
    const min = Math.max(0, Math.floor((Math.min(...values, avg) - 5) / 5) * 5);
    return {
        labels: rows.map((r) => gradeLabel(r.level, r.grade)),
        min,
        datasets: [
            bar('Presentes', values, series[0], { maxBarThickness: 26 }),
            line('Promedio del colegio', rows.map(() => avg), status.neutral, { borderWidth: 1.5, tension: 0, pointHoverRadius: 0 }),
        ],
    };
});

const enrollment = computed(() => {
    const rows = st.value?.community?.enrolled_by_grade ?? [];
    const levels = [...new Set(rows.map((r) => r.level))];
    // Una serie por nivel (identidad), valores solo en sus grados para que cada barra tome su color.
    return {
        labels: rows.map((r) => gradeLabel(r.level, r.grade)),
        datasets: levels.map((lv) =>
            bar(lv, rows.map((r) => (r.level === lv ? r.total : null)), levelColor(lv, levels), { stack: 's', maxBarThickness: 26 }),
        ),
    };
});

const adoptionChart = computed(() => {
    const a = st.value?.usage?.adoption ?? {};
    const groups = [
        { key: 'families', label: 'Apoderados' },
        { key: 'students', label: 'Estudiantes' },
        { key: 'teachers', label: 'Docentes' },
    ];
    return {
        labels: groups.map((g) => g.label),
        // 100% apilado: la parte con app se lee aunque el grupo sea grande.
        datasets: [
            stackSegment('Con app', groups.map((g) => pct(a[g.key]?.with_app ?? 0, a[g.key]?.total ?? 0)), series[0], false, true),
            stackSegment('Sin app', groups.map((g) => (a[g.key]?.total ? 100 - pct(a[g.key].with_app, a[g.key].total) : 0)), '#dcdad3', true, true),
        ].map((d) => ({ ...d, maxBarThickness: 26 })),
    };
});

const rolesChart = computed(() => {
    const entries = Object.entries(st.value?.community?.users_by_role ?? {}).sort((a, b) => b[1] - a[1]);
    return {
        labels: entries.map(([r]) => r),
        datasets: [bar('Usuarios', entries.map(([, n]) => n), series[0], { maxBarThickness: 18 })],
    };
});

const installsChart = computed(() => {
    const weeks = sr.value.installs_weekly ?? [];
    const platforms = [
        { key: 'android', label: 'Android' },
        { key: 'ios', label: 'iOS' },
        { key: 'web', label: 'Push navegador' },
    ];
    return {
        labels: weeks.map((w) => dayLabel(w.week)),
        datasets: platforms.map((p, i) => stackSegment(p.label, weeks.map((w) => w[p.key]), series[i], i === platforms.length - 1)),
    };
});

const newUsersChart = computed(() => {
    const rows = sr.value.new_users_monthly ?? [];
    return {
        labels: rows.map((r) => monthLabel(r.month)),
        datasets: [bar('Cuentas nuevas', rows.map((r) => r.total), series[0])],
    };
});

const teacherKeys = [
    { key: 'presente', label: 'Puntual', color: status.good },
    { key: 'tardanza', label: 'Tardanza', color: status.warning },
    { key: 'falta', label: 'Falta', color: status.critical },
    { key: 'licencia', label: 'Licencia / permiso', color: status.neutral },
];

const teacherDaily = computed(() => {
    const rows = classDays(sr.value.teacher_attendance_daily, teacherKeys);
    return {
        labels: rows.map((d) => dayLabel(d.date)),
        datasets: teacherKeys.map((k, i) => stackSegment(k.label, rows.map((d) => d[k.key]), k.color, i === teacherKeys.length - 1)),
    };
});



const tabs = [
    { key: 'stats', label: 'Estadísticas', icon: 'chart' },
    { key: 'health', label: 'Estado técnico', icon: 'server' },
    { key: 'support', label: 'Soporte', icon: 'lifebuoy' },
];
</script>

<template>
    <Head :title="school.name" />

    <AuthenticatedLayout wide>
        <template #header>
            <Link :href="route('admin.schools.index')" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
                <Icon name="arrow-left" class="h-4 w-4" /> Colegios
            </Link>
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <img v-if="school.logo_url" :src="school.logo_url" alt="" class="h-14 w-14 shrink-0 rounded-2xl border border-slate-200 bg-white object-contain p-1.5" />
                    <span v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                        <Icon name="school" class="h-7 w-7" />
                    </span>
                    <div class="min-w-0">
                        <h1 class="truncate font-display text-2xl font-bold text-slate-900">{{ school.name }}</h1>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                            <a :href="`https://${school.domain}`" target="_blank" rel="noopener" class="inline-flex items-center gap-1 hover:text-brand-600">
                                {{ school.domain }} <Icon name="external" class="h-3.5 w-3.5" />
                            </a>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full" :class="school.is_active ? 'bg-emerald-500' : 'bg-slate-400'" />
                                {{ school.is_active ? 'Visible en la app' : 'Oculto en la app' }}
                            </span>
                            <span v-if="school.synced_at" class="inline-flex items-center gap-1">
                                <Icon name="clock" class="h-3.5 w-3.5" /> Datos {{ ago(school.synced_at) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <Link v-if="school.has_api_key" :href="route('admin.schools.teacher-attendance', school.id)">
                        <SecondaryButton><Icon name="table" class="h-4 w-4" /> Asistencia docente</SecondaryButton>
                    </Link>
                    <Link :href="route('admin.schools.edit', school.id)">
                        <SecondaryButton><Icon name="pencil" class="h-4 w-4" /> Editar</SecondaryButton>
                    </Link>
                    <PrimaryButton v-if="school.has_api_key" :disabled="syncing" @click="sync">
                        <Icon name="refresh" class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
                        {{ syncing ? 'Sincronizando…' : 'Sincronizar' }}
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <!-- Configuración pendiente -->
        <div v-if="!school.has_api_key" class="card mb-6 border-amber-200 bg-amber-50/60 p-6">
            <div class="flex gap-4">
                <Icon name="key" class="h-6 w-6 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                    <p class="font-semibold">Este colegio aún no está conectado.</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-amber-800">
                        <li>En el servidor del colegio, agrega <code class="rounded bg-amber-100 px-1">INTEGRADOR_API_KEY=&lt;clave larga&gt;</code> al <code>.env</code> y ejecuta <code class="rounded bg-amber-100 px-1">php artisan config:cache</code>.</li>
                        <li>Pega la misma clave en <Link :href="route('admin.schools.edit', school.id)" class="font-semibold underline">Editar colegio</Link> y guarda.</li>
                    </ol>
                </div>
            </div>
        </div>

        <div v-else-if="school.sync_status === 'error'" class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <Icon name="warning" class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="font-semibold">Falló la última sincronización.</p>
                <p class="mt-0.5 break-all">{{ school.sync_error }}</p>
                <p v-if="school.synced_at" class="mt-1 text-xs text-rose-600">Se muestran los datos de {{ dateTime(school.synced_at) }}.</p>
            </div>
        </div>

        <!-- Pestañas -->
        <div class="mb-6 flex gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1">
            <button
                v-for="t in tabs"
                :key="t.key"
                class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-medium transition"
                :class="tab === t.key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                @click="tab = t.key"
            >
                <Icon :name="t.icon" class="h-4 w-4" />
                {{ t.label }}
                <span v-if="t.key === 'support' && openTickets" class="rounded-full bg-brand-500 px-1.5 text-[11px] font-bold text-white">{{ openTickets }}</span>
            </button>
        </div>

        <!-- ESTADÍSTICAS -->
        <template v-if="tab === 'stats'">
            <div v-if="!st" class="card p-10 text-center text-sm text-slate-500">Todavía no hay estadísticas de este colegio.</div>
            <div v-else class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                    <StatCard label="Usuarios" :value="st.community.users" icon="users" />
                    <StatCard
                        label="Matriculados"
                        :value="st.community.enrolled"
                        icon="school"
                        tone="bg-violet-50 text-violet-600"
                        :hint="st.school_year ? `Año ${st.school_year.name} · ${st.community.sections} secciones` : ''"
                    />
                    <StatCard label="Docentes activos" :value="st.community.teachers_active" icon="user" tone="bg-sky-50 text-sky-600" />
                    <StatCard label="Familias" :value="st.community.families" icon="users" tone="bg-amber-50 text-amber-600" />
                    <StatCard
                        label="Asistencia alumnos"
                        :value="attendanceRate !== null ? `${attendanceRate}%` : null"
                        icon="calendar"
                        tone="bg-emerald-50 text-emerald-600"
                        :hint="`${num(st.activity.student_attendance_today)} registros hoy`"
                    />
                    <StatCard
                        label="Usuarios con la app"
                        :value="st.usage.users_with_app"
                        icon="phone"
                        tone="bg-brand-50 text-brand-600"
                        :hint="`${num(st.usage.app_active_7d)} activos en 7 días`"
                    />
                </div>

                <!-- Asistencia diaria + composición -->
                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="card p-6 xl:col-span-2">
                        <h2 class="font-display text-lg font-bold text-slate-900">Asistencia diaria de alumnos</h2>
                        <p class="text-sm text-slate-500">Registros por día de clases y resultado, últimos 30 días.</p>
                        <div class="mt-4">
                            <ChartCanvas :labels="studentDaily.labels" :datasets="studentDaily.datasets" stacked :height="290" />
                        </div>
                    </section>
                    <section class="card p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">Composición del mes</h2>
                        <p class="text-sm text-slate-500">Total de registros de asistencia en 30 días.</p>
                        <div class="mt-6 flex h-16 overflow-hidden rounded-lg">
                            <div
                                v-for="seg in attendanceMix"
                                :key="seg.label"
                                class="flex items-center justify-center border-r-2 border-white text-xs font-semibold last:border-r-0"
                                :class="seg.dark ? 'text-slate-900' : 'text-white'"
                                :style="{ width: `${seg.share}%`, background: seg.color }"
                                :title="`${seg.label}: ${num(seg.value)} (${seg.share}%)`"
                            >
                                <span v-if="seg.share >= 8">{{ seg.share }}%</span>
                            </div>
                        </div>
                        <ul class="mt-5 space-y-2.5">
                            <li v-for="seg in attendanceMix" :key="seg.label" class="flex items-center gap-3 text-sm">
                                <span class="h-2.5 w-2.5 rounded-sm" :style="{ background: seg.color }" />
                                <span class="flex-1 text-slate-600">{{ seg.label }}</span>
                                <span class="font-semibold tabular-nums text-slate-900">{{ num(seg.value) }}</span>
                                <span class="w-12 text-right text-xs tabular-nums text-slate-400">{{ seg.share }}%</span>
                            </li>
                        </ul>
                    </section>
                </div>

                <!-- Por grado -->
                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="card p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">Asistencia por grado</h2>
                        <p class="text-sm text-slate-500">% de presentes (incluye tardanzas) en 30 días. La línea gris marca el promedio del colegio.</p>
                        <div class="mt-4">
                            <ChartCanvas :labels="gradeAttendance.labels" :datasets="gradeAttendance.datasets" percent :y-min="gradeAttendance.min" :y-max="100" :height="280" />
                        </div>
                    </section>
                    <section class="card p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">Matrícula por grado</h2>
                        <p class="text-sm text-slate-500">Alumnos matriculados en el año activo.</p>
                        <div class="mt-4">
                            <ChartCanvas :labels="enrollment.labels" :datasets="enrollment.datasets" stacked :height="280" />
                        </div>
                    </section>
                </div>

                <!-- Comunidad y app -->
                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="card p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">Adopción de la app</h2>
                        <p class="text-sm text-slate-500">Cuántos de cada grupo tienen la app instalada (Android o iOS).</p>
                        <div class="mt-4">
                            <ChartCanvas :labels="adoptionChart.labels" :datasets="adoptionChart.datasets" horizontal stacked percent :y-max="100" :height="200" />
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3 text-center text-xs text-slate-500">
                            <p v-for="g in [
                                { k: 'families', l: 'Apoderados' },
                                { k: 'students', l: 'Estudiantes' },
                                { k: 'teachers', l: 'Docentes' },
                            ]" :key="g.k">
                                <strong class="text-slate-900">{{ num(st.usage.adoption[g.k]?.with_app ?? 0) }}</strong>
                                de {{ num(st.usage.adoption[g.k]?.total ?? 0) }} {{ g.l.toLowerCase() }}
                            </p>
                        </div>
                        <div class="mt-5 grid grid-cols-3 gap-3 border-t border-slate-100 pt-5 text-center">
                            <div v-for="m in [
                                { l: 'Activos hoy', v: st.usage.app_active_today },
                                { l: 'Activos 7 días', v: st.usage.app_active_7d },
                                { l: 'Activos 30 días', v: st.usage.app_active_30d },
                            ]" :key="m.l">
                                <p class="font-display text-xl font-bold text-slate-900">{{ num(m.v) }}</p>
                                <p class="text-xs text-slate-500">{{ m.l }}</p>
                            </div>
                        </div>
                        <p class="mt-4 text-xs text-slate-400">
                            Web: {{ num(st.usage.web_active_today) }} usuarios hoy · {{ num(st.usage.web_active_7d) }} en 7 días ·
                            {{ num(st.usage.users_with_web_push) }} con push en navegador.
                        </p>
                    </section>
                    <section class="card p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">Usuarios por rol</h2>
                        <p class="text-sm text-slate-500">Cuentas registradas en el colegio.</p>
                        <div class="mt-4">
                            <ChartCanvas :labels="rolesChart.labels" :datasets="rolesChart.datasets" horizontal :height="Math.max(200, rolesChart.labels.length * 30 + 40)" />
                        </div>
                    </section>
                </div>

                <!-- Crecimiento -->
                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="card p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">Dispositivos nuevos por semana</h2>
                        <p class="text-sm text-slate-500">Registros de notificaciones por plataforma, últimas 12 semanas.</p>
                        <div class="mt-4">
                            <ChartCanvas :labels="installsChart.labels" :datasets="installsChart.datasets" stacked :height="250" />
                        </div>
                    </section>
                    <section class="card p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">Cuentas nuevas por mes</h2>
                        <p class="text-sm text-slate-500">Usuarios creados en los últimos 12 meses.</p>
                        <div class="mt-4">
                            <ChartCanvas :labels="newUsersChart.labels" :datasets="newUsersChart.datasets" :height="250" />
                        </div>
                    </section>
                </div>

                <!-- Personal -->
                <div class="grid gap-6">
                    <section class="card p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="font-display text-lg font-bold text-slate-900">Asistencia docente</h2>
                                <p class="text-sm text-slate-500">Marcaciones diarias por resultado, últimos 30 días.</p>
                            </div>
                            <Link :href="route('admin.schools.teacher-attendance', school.id)" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700">
                                Ver detalle por docente <Icon name="arrow-right" class="h-4 w-4" />
                            </Link>
                        </div>
                        <div class="mt-4">
                            <ChartCanvas :labels="teacherDaily.labels" :datasets="teacherDaily.datasets" stacked :height="250" />
                        </div>
                    </section>
                </div>

            </div>
        </template>

        <!-- ESTADO TÉCNICO -->
        <template v-if="tab === 'health'">
            <div v-if="!hl" class="card p-10 text-center text-sm text-slate-500">Sin diagnóstico disponible.</div>
            <div v-else class="space-y-6">
                <div class="grid gap-6 lg:grid-cols-3">
                    <section class="card p-6">
                        <h2 class="font-display text-base font-bold text-slate-900">Chequeos</h2>
                        <ul class="mt-4 space-y-3">
                            <li v-for="c in checks" :key="c.label" class="flex items-center gap-3 text-sm">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full" :class="c.ok ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                                    <Icon :name="c.ok ? 'check-circle' : 'warning'" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 text-slate-700">{{ c.label }}</span>
                                <span v-if="c.note" class="text-xs text-slate-400">{{ c.note }}</span>
                            </li>
                        </ul>
                    </section>

                    <section class="card p-6">
                        <h2 class="font-display text-base font-bold text-slate-900">Aplicación</h2>
                        <dl class="mt-4 space-y-2.5 text-sm">
                            <div v-for="row in [
                                ['Versión', hl.app.commit ? `${hl.app.commit.branch ?? '—'} @ ${hl.app.commit.hash ?? '—'}` : '—'],
                                ['Entorno', hl.app.env],
                                ['PHP / Laravel', `${hl.app.php} / ${hl.app.laravel}`],
                                ['Zona horaria', hl.app.timezone],
                                ['Cola / caché', `${hl.services.queue_driver} / ${hl.services.cache_driver}`],
                            ]" :key="row[0]" class="flex justify-between gap-4">
                                <dt class="text-slate-500">{{ row[0] }}</dt>
                                <dd class="truncate font-mono text-xs text-slate-800">{{ row[1] }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="card p-6">
                        <h2 class="font-display text-base font-bold text-slate-900">Recursos</h2>
                        <p class="mt-4 text-sm text-slate-600">Disco</p>
                        <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" :class="diskUsed > 90 ? 'bg-rose-500' : diskUsed > 75 ? 'bg-amber-400' : 'bg-brand-500'" :style="{ width: `${diskUsed ?? 0}%` }" />
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ bytes(hl.disk.free_bytes) }} libres de {{ bytes(hl.disk.total_bytes) }}</p>
                        <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-slate-50 p-2.5">
                                <dt class="text-[11px] text-slate-500">En cola</dt>
                                <dd class="font-display text-lg font-bold">{{ hl.queue.pending }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-2.5">
                                <dt class="text-[11px] text-slate-500">Fallidos</dt>
                                <dd class="font-display text-lg font-bold">{{ hl.queue.failed }}</dd>
                            </div>
                            <div class="rounded-xl p-2.5" :class="hl.queue.failed_24h ? 'bg-rose-50 text-rose-700' : 'bg-slate-50'">
                                <dt class="text-[11px]">Fallidos 24 h</dt>
                                <dd class="font-display text-lg font-bold">{{ hl.queue.failed_24h }}</dd>
                            </div>
                        </dl>
                        <p v-if="hl.queue.oldest_pending_at" class="mt-2 text-xs text-amber-600">Trabajo más antiguo en cola: {{ ago(hl.queue.oldest_pending_at) }}</p>
                    </section>
                </div>

                <section class="card overflow-hidden">
                    <h2 class="border-b border-slate-100 px-6 py-4 font-display text-base font-bold text-slate-900">Trabajos fallidos recientes</h2>
                    <ul class="divide-y divide-slate-100">
                        <li v-for="j in failedGroups" :key="j.id" class="px-6 py-3">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-semibold text-slate-700">{{ j.job ?? 'Job' }}</span>
                                    <span v-if="j.count > 1" class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">×{{ j.count }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-slate-400">Último: {{ dateTime(j.failed_at) }}</span>
                            </div>
                            <p class="mt-1 break-all font-mono text-xs text-rose-700">{{ j.error }}</p>
                        </li>
                        <li v-if="!failedGroups.length" class="px-6 py-4 text-sm text-slate-400">Sin trabajos fallidos.</li>
                    </ul>
                </section>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="card overflow-hidden">
                        <h2 class="border-b border-slate-100 px-6 py-4 font-display text-base font-bold text-slate-900">Errores del log</h2>
                        <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                            <li v-for="(e, i) in hl.log_errors" :key="i" class="px-6 py-3">
                                <p class="text-xs text-slate-400">{{ e.at }} · <span class="font-semibold text-rose-600">{{ e.level }}</span></p>
                                <p class="mt-0.5 break-all font-mono text-xs text-slate-700">{{ e.message }}</p>
                            </li>
                            <li v-if="!hl.log_errors.length" class="px-6 py-4 text-sm text-slate-400">Sin errores recientes.</li>
                        </ul>
                    </section>
                    <section class="card overflow-hidden">
                        <h2 class="border-b border-slate-100 px-6 py-4 font-display text-base font-bold text-slate-900">WhatsApp: errores (7 días)</h2>
                        <ul class="divide-y divide-slate-100">
                            <li v-for="(e, i) in hl.whatsapp_errors_7d" :key="i" class="flex items-start justify-between gap-3 px-6 py-3">
                                <p class="break-all font-mono text-xs text-slate-700">{{ e.error || '(sin mensaje)' }}</p>
                                <span class="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">{{ num(e.total) }}</span>
                            </li>
                            <li v-if="!hl.whatsapp_errors_7d.length" class="px-6 py-4 text-sm text-slate-400">Sin errores de WhatsApp.</li>
                        </ul>
                    </section>
                </div>
                <p class="text-xs text-slate-400">Diagnóstico generado {{ dateTime(hl.generated_at) }}.</p>
            </div>
        </template>

        <!-- SOPORTE -->
        <template v-if="tab === 'support'">
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="font-display text-base font-bold text-slate-900">Tickets del colegio</h2>
                    <Link :href="route('admin.support.create', { school_id: school.id })">
                        <PrimaryButton><Icon name="plus" class="h-4 w-4" /> Nuevo ticket</PrimaryButton>
                    </Link>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="t in tickets" :key="t.id">
                        <Link :href="route('admin.support.show', t.id)" class="flex items-center gap-3 px-6 py-3.5 text-sm hover:bg-slate-50">
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="ticket.priority[t.priority].dot" />
                            <span class="min-w-0 flex-1 truncate font-medium text-slate-800">#{{ t.id }} {{ t.title }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1" :class="ticket.status[t.status].tone">{{ ticket.status[t.status].label }}</span>
                            <span class="hidden shrink-0 text-xs text-slate-400 sm:inline">{{ ago(t.created_at) }}</span>
                        </Link>
                    </li>
                    <li v-if="!tickets.length" class="px-6 py-8 text-center text-sm text-slate-400">Sin tickets registrados.</li>
                </ul>
            </section>
        </template>
    </AuthenticatedLayout>
</template>
