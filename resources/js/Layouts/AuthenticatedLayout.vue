<script setup>
import { computed, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Icon from '@/Components/Icon.vue';
import NavLink from '@/Components/NavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    // Pantallas de análisis (resumen, ficha de colegio) usan todo el ancho.
    wide: { type: Boolean, default: false },
    // Tablas grandes (matriz de asistencia): sin límite de ancho.
    full: { type: Boolean, default: false },
});

const page = usePage();
const sidebarOpen = ref(false);

const user = computed(() => page.props.auth.user);
const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join(''),
);

const nav = [
    { label: 'Resumen', icon: 'chart', route: 'dashboard', match: 'dashboard' },
    { label: 'Colegios', icon: 'school', route: 'admin.schools.index', match: 'admin.schools.*' },
    { label: 'Soporte', icon: 'lifebuoy', route: 'admin.support.index', match: 'admin.support.*', badge: 'openTickets' },
    { label: 'Mi perfil', icon: 'user', route: 'profile.edit', match: 'profile.*' },
];

// Cierra el menú móvil al navegar.
watch(() => page.url, () => (sidebarOpen.value = false));
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Fondo del menú móvil -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-ink-900 px-4 py-6 transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center justify-between px-2">
                <Link :href="route('dashboard')" class="block">
                    <ApplicationLogo class="w-36" />
                </Link>
                <button class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 lg:hidden" @click="sidebarOpen = false">
                    <Icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <nav class="mt-10 flex-1 space-y-1">
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Menú</p>
                <NavLink
                    v-for="item in nav"
                    :key="item.route"
                    :href="route(item.route)"
                    :active="route().current(item.match)"
                >
                    <Icon :name="item.icon" class="h-5 w-5" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span
                        v-if="item.badge && $page.props[item.badge]"
                        class="rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-bold text-white"
                    >
                        {{ $page.props[item.badge] }}
                    </span>
                </NavLink>
            </nav>

            <div class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10">
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white"
                    >
                        {{ initials }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">{{ user.name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ user.email }}</p>
                    </div>
                </div>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-white/5 px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white"
                >
                    <Icon name="logout" class="h-4 w-4" />
                    Cerrar sesión
                </Link>
            </div>
        </aside>

        <div class="lg:pl-64">
            <!-- Barra superior móvil -->
            <!-- Oscura como el sidebar: el logo tiene trazos blancos. -->
            <div class="sticky top-0 z-20 flex h-[57px] items-center gap-3 bg-ink-900 px-4 lg:hidden">
                <button class="rounded-lg p-1.5 text-slate-300 hover:bg-white/10" @click="sidebarOpen = true">
                    <Icon name="menu" class="h-6 w-6" />
                </button>
                <Link :href="route('dashboard')" class="block">
                    <ApplicationLogo class="w-24" />
                </Link>
            </div>

            <main
                class="mx-auto w-full px-4 py-8 sm:px-6 lg:px-8 lg:py-10"
                :class="full ? 'max-w-none' : wide ? 'max-w-[1480px]' : 'max-w-content'"
            >
                <header v-if="$slots.header" class="mb-8">
                    <slot name="header" />
                </header>

                <Transition
                    appear
                    enter-active-class="transition duration-300"
                    enter-from-class="translate-y-1 opacity-0"
                >
                    <div
                        v-if="$page.props.flash?.success"
                        :key="$page.props.flash.success"
                        class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
                    >
                        <Icon name="check-circle" class="h-5 w-5 shrink-0" />
                        {{ $page.props.flash.success }}
                    </div>
                </Transition>
                <div
                    v-if="$page.props.flash?.error"
                    class="mb-6 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
                >
                    <Icon name="warning" class="h-5 w-5 shrink-0" />
                    {{ $page.props.flash.error }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
