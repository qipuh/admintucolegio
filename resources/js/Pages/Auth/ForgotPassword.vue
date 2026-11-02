<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout
        title="Recuperar contraseña"
        subtitle="Escribe tu correo y te enviaremos un enlace para crear una nueva contraseña."
    >
        <Head title="Recuperar contraseña" />

        <div
            v-if="status"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Correo electrónico" />
                <div class="relative mt-1.5">
                    <Icon name="mail" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pl-11"
                        v-model="form.email"
                        placeholder="tu@correo.com"
                        required
                        autofocus
                        autocomplete="username"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <PrimaryButton class="w-full py-3" :disabled="form.processing">
                Enviar enlace de recuperación
            </PrimaryButton>

            <Link
                :href="route('login')"
                class="flex items-center justify-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-800"
            >
                <Icon name="arrow-left" class="h-4 w-4" />
                Volver a iniciar sesión
            </Link>
        </form>
    </GuestLayout>
</template>
