<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ticket } from '@/utils/format';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    schools: { type: Array, required: true },
    users: { type: Array, required: true },
    defaults: { type: Object, required: true },
    options: { type: Object, required: true },
});

const form = useForm({
    school_id: props.defaults.school_id ?? '',
    title: '',
    description: '',
    category: 'incidencia',
    priority: 'media',
    assigned_to: usePage().props.auth.user.id,
    reporter_name: '',
    reporter_contact: '',
});

const submit = () =>
    form.transform((d) => ({ ...d, school_id: d.school_id || null, assigned_to: d.assigned_to || null }))
        .post(route('admin.support.store'));

const selectClass = 'mt-1.5 block w-full rounded-xl border-slate-200 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15';
</script>

<template>
    <Head title="Nuevo ticket" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('admin.support.index')" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
                <Icon name="arrow-left" class="h-4 w-4" /> Soporte
            </Link>
            <h1 class="mt-2 font-display text-2xl font-bold text-slate-900 sm:text-3xl">Nuevo ticket</h1>
            <p class="mt-1 text-sm text-slate-500">Registra una incidencia o pedido de un colegio.</p>
        </template>

        <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-3">
            <div class="card space-y-5 p-6 lg:col-span-2">
                <div>
                    <InputLabel for="title" value="Asunto" />
                    <TextInput id="title" v-model="form.title" class="mt-1.5 block w-full" placeholder="Ej. No llegan las notificaciones push" required autofocus />
                    <InputError class="mt-2" :message="form.errors.title" />
                </div>
                <div>
                    <InputLabel for="description" value="Descripción" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="8"
                        required
                        class="mt-1.5 block w-full rounded-xl border-slate-200 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15"
                        placeholder="Qué pasa, desde cuándo, a quiénes afecta, pasos para reproducirlo…"
                    />
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel for="reporter_name" value="Reportado por (opcional)" />
                        <TextInput id="reporter_name" v-model="form.reporter_name" class="mt-1.5 block w-full" placeholder="Nombre y cargo" />
                    </div>
                    <div>
                        <InputLabel for="reporter_contact" value="Contacto (opcional)" />
                        <TextInput id="reporter_contact" v-model="form.reporter_contact" class="mt-1.5 block w-full" placeholder="Celular o correo" />
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card space-y-5 p-6">
                    <div>
                        <InputLabel for="school_id" value="Colegio" />
                        <select id="school_id" v-model="form.school_id" :class="selectClass">
                            <option value="">General (toda la red)</option>
                            <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.school_id" />
                    </div>
                    <div>
                        <InputLabel for="category" value="Categoría" />
                        <select id="category" v-model="form.category" :class="selectClass">
                            <option v-for="c in options.categories" :key="c" :value="c">{{ ticket.category[c] }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Prioridad" />
                        <div class="mt-1.5 grid grid-cols-2 gap-2">
                            <button
                                v-for="p in options.priorities"
                                :key="p"
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition"
                                :class="form.priority === p ? 'border-brand-500 bg-brand-50 text-brand-700 ring-2 ring-brand-500/20' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                @click="form.priority = p"
                            >
                                <span class="h-2 w-2 rounded-full" :class="ticket.priority[p].dot" />
                                {{ ticket.priority[p].label }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <InputLabel for="assigned_to" value="Responsable" />
                        <select id="assigned_to" v-model="form.assigned_to" :class="selectClass">
                            <option value="">Sin asignar</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <PrimaryButton class="w-full py-3" :disabled="form.processing">Crear ticket</PrimaryButton>
                    <Link :href="route('admin.support.index')"><SecondaryButton class="w-full py-3">Cancelar</SecondaryButton></Link>
                </div>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
