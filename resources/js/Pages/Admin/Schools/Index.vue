<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    schools: {
        type: Array,
        required: true,
    },
});

const toggleActive = (school) => {
    router.patch(route('admin.schools.toggle-active', school.id), {}, { preserveScroll: true });
};

const destroy = (school) => {
    if (!confirm(`¿Eliminar "${school.name}"? Esto lo quita también de la app.`)) return;
    router.delete(route('admin.schools.destroy', school.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Colegios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Colegios
                </h2>
                <Link :href="route('admin.schools.create')">
                    <PrimaryButton>Nuevo colegio</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-100 p-4 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                    {{ $page.props.flash.success }}
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Dominio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">API Base URL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Activo</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr v-if="schools.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Sin colegios registrados todavía.
                                </td>
                            </tr>
                            <tr v-for="school in schools" :key="school.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ school.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ school.domain }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ school.api_base_url }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <button
                                        @click="toggleActive(school)"
                                        :class="school.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                    >
                                        {{ school.is_active ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('admin.schools.edit', school.id)" class="mr-3">
                                        <SecondaryButton>Editar</SecondaryButton>
                                    </Link>
                                    <DangerButton @click="destroy(school)">Eliminar</DangerButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
