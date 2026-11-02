<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Icon from '@/Components/Icon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ago, ticket } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
    tickets: { type: Object, required: true },
    filters: { type: Object, required: true },
    schools: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const f = reactive({
    status: props.filters.status ?? 'pendientes',
    priority: props.filters.priority ?? '',
    school_id: props.filters.school_id ?? '',
    q: props.filters.q ?? '',
});

let timer;
watch(f, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const query = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
        router.get(route('admin.support.index'), query, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const pending = ['abierto', 'en_progreso', 'esperando'].reduce((s, k) => s + (props.counts[k] ?? 0), 0);

const statusTabs = [
    { key: 'pendientes', label: 'Pendientes', count: pending },
    ...Object.entries(ticket.status).map(([key, v]) => ({ key, label: v.label, count: props.counts[key] ?? 0 })),
    { key: 'todos', label: 'Todos' },
];

const selectClass = 'rounded-xl border-slate-200 py-2 text-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15';
</script>

<template>
    <Head title="Soporte" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">Soporte</h1>
                    <p class="mt-1 text-sm text-slate-500">Incidencias, consultas y pedidos de los colegios.</p>
                </div>
                <Link :href="route('admin.support.create')">
                    <PrimaryButton><Icon name="plus" class="h-4 w-4" /> Nuevo ticket</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="mb-4 flex gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 text-sm">
            <button
                v-for="t in statusTabs"
                :key="t.key"
                class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 font-medium transition"
                :class="f.status === t.key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                @click="f.status = t.key"
            >
                {{ t.label }}
                <span v-if="t.count !== undefined" class="rounded-full bg-slate-200/70 px-1.5 text-[11px] text-slate-600">{{ t.count }}</span>
            </button>
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 md:flex-row">
                <div class="relative flex-1">
                    <Icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="f.q" type="search" placeholder="Buscar en título, descripción o reportante…" :class="selectClass" class="w-full pl-9" />
                </div>
                <select v-model="f.school_id" :class="selectClass">
                    <option value="">Todos los colegios</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="f.priority" :class="selectClass">
                    <option value="">Cualquier prioridad</option>
                    <option v-for="(p, k) in ticket.priority" :key="k" :value="k">{{ p.label }}</option>
                </select>
            </div>

            <ul class="divide-y divide-slate-100">
                <li v-for="t in tickets.data" :key="t.id">
                    <Link :href="route('admin.support.show', t.id)" class="flex flex-col gap-2 px-5 py-4 transition hover:bg-slate-50/70 sm:flex-row sm:items-center sm:gap-4">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="ticket.priority[t.priority].dot" :title="`Prioridad ${ticket.priority[t.priority].label}`" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">
                                    <span class="font-normal text-slate-400">#{{ t.id }}</span> {{ t.title }}
                                </p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                    <span>{{ t.school?.name ?? 'General' }}</span>
                                    <span>·</span>
                                    <span>{{ ticket.category[t.category] }}</span>
                                    <span v-if="t.assignee">· {{ t.assignee.name }}</span>
                                    <span v-if="t.comments_count" class="inline-flex items-center gap-0.5">· <Icon name="chat" class="h-3.5 w-3.5" /> {{ t.comments_count }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3 pl-5 sm:pl-0">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1" :class="ticket.status[t.status].tone">{{ ticket.status[t.status].label }}</span>
                            <span class="w-24 text-right text-xs text-slate-400">{{ ago(t.updated_at) }}</span>
                        </div>
                    </Link>
                </li>
                <li v-if="!tickets.data.length" class="flex flex-col items-center px-6 py-16 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <Icon name="lifebuoy" class="h-6 w-6" />
                    </span>
                    <p class="mt-4 font-medium text-slate-700">No hay tickets con estos filtros.</p>
                </li>
            </ul>

            <div v-if="tickets.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
                <span class="text-slate-500">{{ tickets.from }}–{{ tickets.to }} de {{ tickets.total }}</span>
                <div class="flex gap-1">
                    <template v-for="l in tickets.links" :key="l.label">
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            preserve-scroll
                            class="rounded-lg px-3 py-1.5"
                            :class="l.active ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            v-html="l.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
