<script setup>
import { Chart, registerables } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { ink } from '@/utils/charts';

Chart.register(...registerables);

Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = ink.secondary;

// Línea vertical que sigue al puntero en gráficos de línea (crosshair).
const crosshair = {
    id: 'crosshair',
    afterDatasetsDraw(chart) {
        const active = chart.tooltip?.getActiveElements?.() ?? [];
        if (!active.length || chart.config.options.crosshair === false) return;
        const { ctx, chartArea } = chart;
        const x = active[0].element.x;
        ctx.save();
        ctx.strokeStyle = ink.axis;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();
        ctx.restore();
    },
};

const props = defineProps({
    type: { type: String, default: 'bar' },
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
    height: { type: Number, default: 260 },
    horizontal: { type: Boolean, default: false },
    stacked: { type: Boolean, default: false },
    percent: { type: Boolean, default: false },
    legend: { type: Boolean, default: null },
    yMax: { type: Number, default: null },
    yMin: { type: Number, default: null },
    valueSuffix: { type: String, default: '' },
});

const canvas = ref(null);
const chart = shallowRef(null);
const nf = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 1 });
const fmt = (v) => `${nf.format(v)}${props.percent ? '%' : props.valueSuffix}`;

const buildOptions = () => {
    const valueAxis = {
        stacked: props.stacked,
        beginAtZero: true,
        max: props.yMax ?? undefined,
        min: props.yMin ?? undefined,
        grid: { color: ink.grid, drawTicks: false },
        border: { display: false },
        ticks: { color: ink.muted, padding: 8, maxTicksLimit: 6, callback: (v) => fmt(v) },
    };
    const categoryAxis = {
        stacked: props.stacked,
        grid: { display: false },
        border: { color: ink.axis },
        ticks: { color: ink.muted, maxRotation: 0, autoSkipPadding: 12 },
    };
    const showLegend = props.legend ?? props.datasets.length > 1;

    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: props.horizontal ? 'y' : 'x',
        animation: { duration: 400 },
        interaction: { mode: 'index', intersect: false, axis: props.horizontal ? 'y' : 'x' },
        crosshair: props.type === 'line',
        layout: { padding: { top: 4 } },
        scales: props.horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
        plugins: {
            legend: {
                display: showLegend,
                position: 'top',
                align: 'start',
                labels: {
                    usePointStyle: true,
                    pointStyle: props.type === 'line' ? 'line' : 'rectRounded',
                    boxWidth: 10,
                    boxHeight: 10,
                    padding: 14,
                    color: ink.secondary,
                },
            },
            tooltip: {
                backgroundColor: '#ffffff',
                borderColor: 'rgba(11,11,11,0.10)',
                borderWidth: 1,
                titleColor: ink.secondary,
                titleFont: { weight: '500' },
                bodyColor: ink.primary,
                bodyFont: { weight: '600' },
                padding: 10,
                boxPadding: 4,
                usePointStyle: true,
                callbacks: {
                    labelPointStyle: () => ({ pointStyle: 'line', rotation: 0 }),
                    // El borde de las barras es blanco (separador): la clave usa el relleno.
                    labelColor: (ctx) => {
                        const c = ctx.dataset.backgroundColor ?? ctx.dataset.borderColor;
                        return { borderColor: c, backgroundColor: c, borderWidth: 3 };
                    },
                    label: (ctx) => ` ${fmt(ctx.parsed[props.horizontal ? 'x' : 'y'])}  ${ctx.dataset.label}`,
                },
            },
        },
    };
};

const render = () => {
    chart.value?.destroy();
    chart.value = new Chart(canvas.value, {
        type: props.type,
        data: { labels: props.labels, datasets: props.datasets },
        options: buildOptions(),
        plugins: [crosshair],
    });
};

onMounted(render);
onBeforeUnmount(() => chart.value?.destroy());

watch(
    () => [props.labels, props.datasets],
    () => {
        if (!chart.value) return;
        chart.value.data.labels = props.labels;
        chart.value.data.datasets = props.datasets;
        chart.value.update();
    },
    { deep: true },
);
</script>

<template>
    <div class="relative" :style="{ height: `${height}px` }">
        <canvas ref="canvas" role="img" :aria-label="datasets.map((d) => d.label).join(', ')" />
    </div>
</template>
