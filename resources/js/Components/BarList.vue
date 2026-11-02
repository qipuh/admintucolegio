<script setup>
import { computed } from 'vue';
import { num, pct } from '@/utils/format';

// Lista de barras horizontales: [{ label, value, tone? }]
const props = defineProps({
    items: { type: Array, required: true },
    showPercent: { type: Boolean, default: false },
});

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
const total = computed(() => props.items.reduce((s, i) => s + i.value, 0));
</script>

<template>
    <ul class="space-y-3">
        <li v-for="item in items" :key="item.label">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="truncate text-slate-600">{{ item.label }}</span>
                <span class="shrink-0 font-semibold tabular-nums text-slate-900">
                    {{ num(item.value) }}
                    <span v-if="showPercent" class="ml-1 text-xs font-normal text-slate-400">{{ pct(item.value, total) }}%</span>
                </span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="item.tone ?? 'bg-brand-500'"
                    :style="{ width: `${(item.value / max) * 100}%` }"
                />
            </div>
        </li>
        <li v-if="!items.length" class="text-sm text-slate-400">Sin datos.</li>
    </ul>
</template>
