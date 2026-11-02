<script setup>
import Icon from '@/Components/Icon.vue';
import { num } from '@/utils/format';

defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: null },
    icon: { type: String, required: true },
    tone: { type: String, default: 'bg-brand-50 text-brand-600' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div class="card flex items-start gap-4 p-5">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="tone">
            <Icon :name="icon" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="font-display text-2xl font-bold tabular-nums text-slate-900">
                {{ typeof value === 'number' ? num(value) : (value ?? '—') }}
            </p>
            <p class="text-sm text-slate-500">{{ label }}</p>
            <p v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>
        </div>
    </div>
</template>
