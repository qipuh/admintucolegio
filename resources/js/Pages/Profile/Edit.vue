<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Mi perfil" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">Mi perfil</h1>
            <p class="mt-1 text-sm text-slate-500">Administra los datos y la seguridad de tu cuenta.</p>
        </template>

        <div class="space-y-6">
            <div class="card p-6 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <div class="card p-6 sm:p-8">
                <UpdatePasswordForm />
            </div>

            <div class="card border-rose-100 p-6 sm:p-8">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
