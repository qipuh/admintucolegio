<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    school: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => !!props.school);

const form = useForm({
    name: props.school?.name ?? '',
    domain: props.school?.domain ?? '',
    api_base_url: props.school?.api_base_url ?? '',
    logo_url: props.school?.logo_url ?? '',
    is_active: props.school?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.schools.update', props.school.id));
    } else {
        form.post(route('admin.schools.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar colegio' : 'Nuevo colegio'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ isEdit ? 'Editar colegio' : 'Nuevo colegio' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="name" value="Nombre" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="domain" value="Dominio (sin https://)" />
                            <TextInput id="domain" v-model="form.domain" type="text" class="mt-1 block w-full" placeholder="colegio.edu.pe" required />
                            <InputError class="mt-2" :message="form.errors.domain" />
                        </div>

                        <div>
                            <InputLabel for="api_base_url" value="API Base URL" />
                            <TextInput id="api_base_url" v-model="form.api_base_url" type="text" class="mt-1 block w-full" placeholder="https://colegio.edu.pe/api/v1" required />
                            <InputError class="mt-2" :message="form.errors.api_base_url" />
                        </div>

                        <div>
                            <InputLabel for="logo_url" value="Logo URL (opcional)" />
                            <TextInput id="logo_url" v-model="form.logo_url" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.logo_url" />
                        </div>

                        <div class="flex items-center">
                            <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" />
                            <label for="is_active" class="ms-2 text-sm text-gray-600 dark:text-gray-400">Activo (visible en la app)</label>
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">Guardar</PrimaryButton>
                            <a :href="route('admin.schools.index')" class="text-sm text-gray-600 underline dark:text-gray-400">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
