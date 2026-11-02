<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout
        title="Verifica tu correo"
        subtitle="Antes de continuar, confirma tu correo con el enlace que te enviamos. Si no lo recibiste, podemos enviarte otro."
    >
        <Head title="Verificación de correo" />

        <div
            v-if="verificationLinkSent"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            Te enviamos un nuevo enlace de verificación a tu correo.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <PrimaryButton class="w-full py-3" :disabled="form.processing">
                Reenviar correo de verificación
            </PrimaryButton>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="w-full text-center text-sm font-medium text-slate-500 hover:text-slate-800"
            >
                Cerrar sesión
            </Link>
        </form>
    </GuestLayout>
</template>
