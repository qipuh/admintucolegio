<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
    api_key: '',
    remove_api_key: false,
    is_active: props.school?.is_active ?? true,
});

const showKey = ref(false);

// Clave aleatoria para pegar también en INTEGRADOR_API_KEY del colegio.
const generateKey = () => {
    const bytes = crypto.getRandomValues(new Uint8Array(24));
    form.api_key = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    form.remove_api_key = false;
    showKey.value = true;
};

// Al crear, sugiere la API a partir del dominio mientras el usuario no la toque.
const apiTouched = ref(isEdit.value);
watch(
    () => form.domain,
    (domain) => {
        if (!apiTouched.value) {
            form.api_base_url = domain ? `https://${domain.trim()}/api/v1` : '';
        }
    },
);

const logoBroken = ref(false);
watch(() => form.logo_url, () => (logoBroken.value = false));

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
            <Link
                :href="route('admin.schools.index')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800"
            >
                <Icon name="arrow-left" class="h-4 w-4" />
                Colegios
            </Link>
            <h1 class="mt-2 font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                {{ isEdit ? 'Editar colegio' : 'Nuevo colegio' }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ isEdit ? school.name : 'Registra una institución para que aparezca en la app.' }}
            </p>
        </template>

        <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-3">
            <div class="card space-y-6 p-6 lg:col-span-2">
                <div>
                    <InputLabel for="name" value="Nombre" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1.5 block w-full"
                        placeholder="I.E. San Martín"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="domain" value="Dominio" />
                    <div class="mt-1.5 flex rounded-xl shadow-sm">
                        <span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">
                            https://
                        </span>
                        <TextInput
                            id="domain"
                            v-model="form.domain"
                            type="text"
                            class="block w-full rounded-l-none shadow-none"
                            placeholder="colegio.edu.pe"
                            required
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.domain" />
                </div>

                <div>
                    <InputLabel for="api_base_url" value="API Base URL" />
                    <TextInput
                        id="api_base_url"
                        v-model="form.api_base_url"
                        type="text"
                        class="mt-1.5 block w-full font-mono"
                        placeholder="https://colegio.edu.pe/api/v1"
                        required
                        @input="apiTouched = true"
                    />
                    <p class="mt-1.5 text-xs text-slate-400">Endpoint que usará la app móvil para este colegio.</p>
                    <InputError class="mt-2" :message="form.errors.api_base_url" />
                </div>

                <div>
                    <InputLabel for="logo_url" value="URL del logo (opcional)" />
                    <TextInput
                        id="logo_url"
                        v-model="form.logo_url"
                        type="text"
                        class="mt-1.5 block w-full"
                        placeholder="https://colegio.edu.pe/logo.png"
                    />
                    <InputError class="mt-2" :message="form.errors.logo_url" />
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
                    <div class="flex items-start gap-3">
                        <Icon name="key" class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                        <div class="min-w-0 flex-1">
                            <InputLabel for="api_key" value="Clave de integrador" />
                            <p class="mt-0.5 text-xs text-slate-500">
                                Debe coincidir con <code class="rounded bg-white px-1">INTEGRADOR_API_KEY</code> en el
                                <code class="rounded bg-white px-1">.env</code> del colegio. Permite traer estadísticas y diagnóstico.
                            </p>
                            <p v-if="school?.has_api_key && !form.api_key && !form.remove_api_key" class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                <Icon name="check-circle" class="h-4 w-4" /> Clave configurada (déjalo vacío para conservarla).
                            </p>
                            <div class="mt-3 flex gap-2">
                                <div class="relative flex-1">
                                    <TextInput
                                        id="api_key"
                                        v-model="form.api_key"
                                        :type="showKey ? 'text' : 'password'"
                                        class="block w-full pr-10 font-mono"
                                        :placeholder="school?.has_api_key ? '••••••••••••' : 'Pega o genera una clave'"
                                        autocomplete="off"
                                    />
                                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600" @click="showKey = !showKey">
                                        <Icon :name="showKey ? 'eye-off' : 'eye'" class="h-4 w-4" />
                                    </button>
                                </div>
                                <SecondaryButton @click="generateKey">Generar</SecondaryButton>
                            </div>
                            <InputError class="mt-2" :message="form.errors.api_key" />
                            <label v-if="school?.has_api_key" class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                <input v-model="form.remove_api_key" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500/30" />
                                Quitar la clave (desconectar este colegio)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <p class="text-sm font-semibold text-slate-900">Vista previa</p>
                    <div class="mt-4 flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <img
                            v-if="form.logo_url && !logoBroken"
                            :src="form.logo_url"
                            alt=""
                            class="h-11 w-11 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1"
                            @error="logoBroken = true"
                        />
                        <span
                            v-else
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"
                        >
                            <Icon name="school" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ form.name || 'Nombre del colegio' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ form.domain || 'dominio.edu.pe' }}</p>
                        </div>
                    </div>

                    <label class="mt-5 flex cursor-pointer items-start justify-between gap-4">
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Visible en la app</span>
                            <span class="block text-xs text-slate-500">Si está apagado, el colegio no aparece en el selector.</span>
                        </span>
                        <span class="relative mt-0.5 inline-flex shrink-0">
                            <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
                            <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-brand-600 peer-focus-visible:ring-4 peer-focus-visible:ring-brand-500/30" />
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5" />
                        </span>
                    </label>
                </div>

                <div class="flex flex-col gap-3">
                    <PrimaryButton class="w-full py-3" :disabled="form.processing">
                        {{ isEdit ? 'Guardar cambios' : 'Crear colegio' }}
                    </PrimaryButton>
                    <Link :href="isEdit ? route('admin.schools.show', school.id) : route('admin.schools.index')">
                        <SecondaryButton class="w-full py-3">Cancelar</SecondaryButton>
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
