<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    schools: {
        type: Array,
        required: true,
    },
});

const search = ref('');
const filter = ref('all');
const deleting = ref(null);

const stats = computed(() => {
    const active = props.schools.filter((s) => s.is_active).length;
    return [
        { label: 'Colegios', value: props.schools.length, icon: 'school', tone: 'bg-brand-50 text-brand-600' },
        { label: 'Visibles en la app', value: active, icon: 'phone', tone: 'bg-emerald-50 text-emerald-600' },
        { label: 'Ocultos', value: props.schools.length - active, icon: 'eye-off', tone: 'bg-amber-50 text-amber-600' },
    ];
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.schools.filter((s) => {
        if (filter.value === 'active' && !s.is_active) return false;
        if (filter.value === 'inactive' && s.is_active) return false;
        return !q || s.name.toLowerCase().includes(q) || s.domain.toLowerCase().includes(q);
    });
});

const initials = (name) =>
    name
        .replace(/^I\.E\.\s*/i, '')
        .split(/\s+/)
        .filter((w) => /^\p{L}/u.test(w))
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');

const toggleActive = (school) => {
    router.patch(route('admin.schools.toggle-active', school.id), {}, { preserveScroll: true });
};

const destroy = () => {
    router.delete(route('admin.schools.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
};
</script>

<template>
    <Head title="Colegios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">Colegios</h1>
                    <p class="mt-1 text-sm text-slate-500">Directorio de instituciones que consume la app TuColegio.</p>
                </div>
                <Link :href="route('admin.schools.create')">
                    <PrimaryButton>
                        <Icon name="plus" class="h-4 w-4" />
                        Nuevo colegio
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="stat in stats" :key="stat.label" class="card flex items-center gap-4 p-5">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl" :class="stat.tone">
                    <Icon :name="stat.icon" class="h-5 w-5" />
                </span>
                <div>
                    <p class="font-display text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                    <p class="text-sm text-slate-500">{{ stat.label }}</p>
                </div>
            </div>
        </div>

        <div class="card mt-6 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative sm:w-72">
                    <Icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar por nombre o dominio…"
                        class="w-full rounded-xl border-slate-200 py-2 pl-9 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15"
                    />
                </div>
                <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm">
                    <button
                        v-for="opt in [
                            { key: 'all', label: 'Todos' },
                            { key: 'active', label: 'Activos' },
                            { key: 'inactive', label: 'Inactivos' },
                        ]"
                        :key="opt.key"
                        class="rounded-lg px-3 py-1.5 font-medium transition"
                        :class="filter === opt.key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="filter = opt.key"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <div v-if="filtered.length === 0" class="flex flex-col items-center px-6 py-16 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <Icon name="school" class="h-6 w-6" />
                </span>
                <p class="mt-4 font-medium text-slate-700">
                    {{ schools.length === 0 ? 'Sin colegios registrados todavía.' : 'Ningún colegio coincide con la búsqueda.' }}
                </p>
            </div>

            <ul v-else class="divide-y divide-slate-100">
                <li
                    v-for="school in filtered"
                    :key="school.id"
                    class="flex flex-col gap-4 p-4 transition hover:bg-slate-50/60 sm:flex-row sm:items-center sm:px-5"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <img
                            v-if="school.logo_url"
                            :src="school.logo_url"
                            :alt="school.name"
                            class="h-11 w-11 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1"
                        />
                        <span
                            v-else
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 font-display text-sm font-bold text-brand-700"
                        >
                            {{ initials(school.name) }}
                        </span>
                        <div class="min-w-0">
                            <Link :href="route('admin.schools.show', school.id)" class="flex items-center gap-2 font-semibold text-slate-900 hover:text-brand-700">
                                <span class="truncate">{{ school.name }}</span>
                                <span
                                    v-if="school.has_api_key"
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :class="school.sync_status === 'error' ? 'bg-rose-500' : 'bg-emerald-500'"
                                    :title="school.sync_status === 'error' ? 'Error de sincronización' : 'Conectado'"
                                />
                            </Link>
                            <p class="mt-0.5 flex items-center gap-1.5 truncate text-sm text-slate-500">
                                <Icon name="globe" class="h-3.5 w-3.5 shrink-0" />
                                {{ school.domain }}
                            </p>
                            <p class="mt-0.5 truncate font-mono text-xs text-slate-400">{{ school.api_base_url }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <button
                            @click="toggleActive(school)"
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition"
                            :class="school.is_active
                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100'
                                : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200 hover:bg-slate-200'"
                            :title="school.is_active ? 'Clic para ocultar de la app' : 'Clic para mostrar en la app'"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="school.is_active ? 'bg-emerald-500' : 'bg-slate-400'" />
                            {{ school.is_active ? 'Activo' : 'Inactivo' }}
                        </button>

                        <Link
                            :href="route('admin.schools.edit', school.id)"
                            class="rounded-xl p-2 text-slate-500 transition hover:bg-brand-50 hover:text-brand-600"
                            title="Editar"
                        >
                            <Icon name="pencil" class="h-5 w-5" />
                        </Link>
                        <button
                            class="rounded-xl p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                            title="Eliminar"
                            @click="deleting = school"
                        >
                            <Icon name="trash" class="h-5 w-5" />
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <Modal :show="!!deleting" max-width="md" @close="deleting = null">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <Icon name="warning" class="h-6 w-6" />
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">¿Eliminar colegio?</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Se eliminará <strong class="text-slate-700">{{ deleting?.name }}</strong> y dejará de aparecer en la app.
                            Esta acción no se puede deshacer.
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="deleting = null">Cancelar</SecondaryButton>
                    <DangerButton @click="destroy">Eliminar</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
