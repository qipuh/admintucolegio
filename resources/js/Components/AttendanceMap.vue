<script setup>
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Mapa con los puntos de marcación de entrada (verde) y salida (rojo).
const props = defineProps({
    points: { type: Array, required: true }, // [{ lat, lng, color, label }]
});

const el = ref(null);
let map = null;

const pin = (color) =>
    L.divIcon({
        className: '',
        html: `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="42" viewBox="0 0 30 42"><path d="M15 0C6.7 0 0 6.7 0 15c0 11 15 27 15 27s15-16 15-27C30 6.7 23.3 0 15 0z" fill="${color}" stroke="white" stroke-width="1.5"/><circle cx="15" cy="15" r="5.5" fill="white"/></svg>`,
        iconSize: [30, 42],
        iconAnchor: [15, 42],
        popupAnchor: [0, -38],
    });

onMounted(() => {
    map = L.map(el.value);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    const coords = props.points.map((p) => [Number(p.lat), Number(p.lng)]);
    props.points.forEach((p, i) => L.marker(coords[i], { icon: pin(p.color) }).addTo(map).bindPopup(p.label));

    if (coords.length === 1) {
        map.setView(coords[0], 17);
    } else {
        map.fitBounds(coords, { padding: [50, 50], maxZoom: 17 });
    }
    // El modal anima su apertura; recalcula el tamaño al terminar.
    setTimeout(() => map?.invalidateSize(), 350);
});

onBeforeUnmount(() => map?.remove());
</script>

<template>
    <div ref="el" class="h-[420px] w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100" />
</template>
