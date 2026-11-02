<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="grid gap-6 md:grid-cols-3">
        <header>
            <h2 class="font-display text-lg font-bold text-rose-700">Eliminar cuenta</h2>
            <p class="mt-1 text-sm text-slate-500">Acción permanente. Perderás el acceso al panel.</p>
        </header>

        <div class="md:col-span-2">
            <p class="text-sm text-slate-600">
                Al eliminar tu cuenta se borrarán todos sus datos de forma permanente. Si eres el único
                administrador, tendrás que volver a sembrarlo desde el servidor.
            </p>
            <DangerButton class="mt-5" @click="confirmUserDeletion">Eliminar cuenta</DangerButton>
        </div>

        <Modal :show="confirmingUserDeletion" max-width="md" @close="closeModal">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <Icon name="warning" class="h-6 w-6" />
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">¿Eliminar tu cuenta?</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Esta acción no se puede deshacer. Escribe tu contraseña para confirmar.
                        </p>
                    </div>
                </div>

                <div class="mt-6">
                    <InputLabel for="password" value="Contraseña" class="sr-only" />
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="block w-full"
                        placeholder="Contraseña"
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>
                    <DangerButton :disabled="form.processing" @click="deleteUser">Eliminar cuenta</DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
