<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ago, dateTime, ticket as T } from '@/utils/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    users: { type: Array, required: true },
    options: { type: Object, required: true },
});

const comment = useForm({ body: '' });
const sendComment = () =>
    comment.post(route('admin.support.comment', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => comment.reset(),
    });

const update = (field, value) =>
    router.patch(route('admin.support.update', props.ticket.id), { [field]: value }, { preserveScroll: true });

const confirmingDelete = ref(false);
const destroy = () => router.delete(route('admin.support.destroy', props.ticket.id));

const initials = (name) => (name ?? '?').split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const selectClass = 'mt-1.5 block w-full rounded-xl border-slate-200 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15';
</script>

<template>
    <Head :title="`#${ticket.id} ${ticket.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('admin.support.index')" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
                <Icon name="arrow-left" class="h-4 w-4" /> Soporte
            </Link>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="font-display text-2xl font-bold text-slate-900">
                    <span class="text-slate-400">#{{ ticket.id }}</span> {{ ticket.title }}
                </h1>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1" :class="T.status[ticket.status].tone">{{ T.status[ticket.status].label }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Creado {{ ago(ticket.created_at) }}<span v-if="ticket.creator"> por {{ ticket.creator.name }}</span>
                <span v-if="ticket.resolved_at"> · resuelto {{ ago(ticket.resolved_at) }}</span>
            </p>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="card p-6">
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ ticket.description }}</p>
                    <div v-if="ticket.reporter_name || ticket.reporter_contact" class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4 text-sm text-slate-500">
                        <Icon name="user" class="h-4 w-4" />
                        Reportado por <strong class="text-slate-700">{{ ticket.reporter_name || '—' }}</strong>
                        <span v-if="ticket.reporter_contact">· {{ ticket.reporter_contact }}</span>
                    </div>
                </section>

                <section class="card p-6">
                    <h2 class="font-display text-base font-bold text-slate-900">Seguimiento</h2>
                    <ol class="mt-5 space-y-5">
                        <li v-for="c in ticket.comments" :key="c.id" class="flex gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-700">
                                {{ initials(c.user?.name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-slate-400">
                                    <strong class="font-semibold text-slate-700">{{ c.user?.name ?? 'Sistema' }}</strong> · {{ dateTime(c.created_at) }}
                                </p>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ c.body }}</p>
                            </div>
                        </li>
                        <li v-if="!ticket.comments.length" class="text-sm text-slate-400">Sin comentarios todavía.</li>
                    </ol>

                    <form @submit.prevent="sendComment" class="mt-6 border-t border-slate-100 pt-5">
                        <textarea
                            v-model="comment.body"
                            rows="3"
                            required
                            class="block w-full rounded-xl border-slate-200 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15"
                            placeholder="Agrega una nota de seguimiento, lo que se revisó o la solución aplicada…"
                        />
                        <InputError class="mt-2" :message="comment.errors.body" />
                        <div class="mt-3 flex justify-end">
                            <PrimaryButton :disabled="comment.processing || !comment.body.trim()">
                                <Icon name="chat" class="h-4 w-4" /> Comentar
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card space-y-5 p-6">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Estado</label>
                        <select :value="ticket.status" :class="selectClass" @change="update('status', $event.target.value)">
                            <option v-for="s in options.statuses" :key="s" :value="s">{{ T.status[s].label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Prioridad</label>
                        <select :value="ticket.priority" :class="selectClass" @change="update('priority', $event.target.value)">
                            <option v-for="p in options.priorities" :key="p" :value="p">{{ T.priority[p].label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Responsable</label>
                        <select :value="ticket.assigned_to ?? ''" :class="selectClass" @change="update('assigned_to', $event.target.value || null)">
                            <option value="">Sin asignar</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <dl class="space-y-2 border-t border-slate-100 pt-4 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Categoría</dt>
                            <dd class="font-medium text-slate-800">{{ T.category[ticket.category] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Actualizado</dt>
                            <dd class="text-slate-800">{{ ago(ticket.updated_at) }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="ticket.school" class="card p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Colegio</p>
                    <p class="mt-2 font-semibold text-slate-900">{{ ticket.school.name }}</p>
                    <p class="text-sm text-slate-500">{{ ticket.school.domain }}</p>
                    <Link
                        :href="route('admin.schools.show', ticket.school.id)"
                        class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700"
                    >
                        Ver estado técnico <Icon name="arrow-right" class="h-4 w-4" />
                    </Link>
                </section>

                <button class="flex w-full items-center justify-center gap-2 text-sm font-medium text-slate-400 hover:text-rose-600" @click="confirmingDelete = true">
                    <Icon name="trash" class="h-4 w-4" /> Eliminar ticket
                </button>
            </aside>
        </div>

        <Modal :show="confirmingDelete" max-width="md" @close="confirmingDelete = false">
            <div class="p-6">
                <h2 class="font-display text-lg font-bold text-slate-900">¿Eliminar este ticket?</h2>
                <p class="mt-1 text-sm text-slate-500">Se borrará junto con su historial. Si ya se resolvió, mejor márcalo como cerrado.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="confirmingDelete = false">Cancelar</SecondaryButton>
                    <DangerButton @click="destroy">Eliminar</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
