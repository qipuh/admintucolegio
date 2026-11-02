<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});

const highlights = [
    { icon: 'school', text: 'Registra y administra cada colegio de la red.' },
    { icon: 'phone', text: 'Controla qué colegios aparecen en la app móvil.' },
    { icon: 'shield', text: 'Acceso exclusivo para el super administrador.' },
];

const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <!-- Panel de marca (izquierda, solo escritorio) -->
        <aside
            class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-ink-900 p-12 text-white lg:flex xl:w-[55%]"
        >
            <div
                class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-brand-600/40 blur-3xl"
            />
            <div
                class="pointer-events-none absolute -bottom-40 right-0 h-[28rem] w-[28rem] rounded-full bg-indigo-500/30 blur-3xl"
            />
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 22px 22px"
            />

            <div class="relative">
                <ApplicationLogo class="w-48" />
            </div>

            <div class="relative max-w-lg">
                <h1 class="font-display text-4xl font-bold leading-tight xl:text-5xl">
                    Toda la red de colegios,
                    <span class="text-brand-300">en un solo lugar.</span>
                </h1>
                <p class="mt-5 text-base leading-relaxed text-slate-300">
                    Gestiona el directorio que alimenta la app TuColegio: dominios,
                    endpoints de API y visibilidad de cada institución.
                </p>

                <ul class="mt-10 space-y-4">
                    <li v-for="item in highlights" :key="item.icon" class="flex items-center gap-4">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/10"
                        >
                            <Icon :name="item.icon" class="h-5 w-5 text-brand-200" />
                        </span>
                        <span class="text-sm text-slate-200">{{ item.text }}</span>
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-slate-500">© {{ year }} TuColegio. Todos los derechos reservados.</p>
        </aside>

        <!-- Formulario (derecha) -->
        <main class="flex flex-1 items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-sm">
                <!-- En móvil no hay panel oscuro: el logo va sobre una placa oscura. -->
                <div class="mb-10 inline-flex rounded-2xl bg-ink-900 px-5 py-4 lg:hidden">
                    <ApplicationLogo class="w-36" />
                </div>

                <div v-if="title" class="mb-8">
                    <h2 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">{{ title }}</h2>
                    <p v-if="subtitle" class="mt-2 text-sm leading-relaxed text-slate-500">{{ subtitle }}</p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
