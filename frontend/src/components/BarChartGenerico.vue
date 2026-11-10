<template>
  <div class="bar-chart-contenedor" :style="{ height: `${altura}px` }">
    <canvas ref="lienzo" role="img" :aria-label="etiquetaAccesible"></canvas>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue';
import Chart from 'chart.js/auto';
import { resolverColor, estiloBase, conAlfa, observarTema } from './charts/temaGraficas';

const props = defineProps({
    datos: { type: Object, default: () => ({}) },
    // Acepta colores literales o tokens del sistema de diseño ('--c-primary').
    colores: { type: Array, default: () => ['--chart-1', '--chart-2', '--chart-3', '--chart-4', '--chart-5'] },
    // Opcional: color fijo por etiqueta (p. ej. { critico: '--c-risk' }) para
    // que cada estado conserve su significado sin importar el orden.
    coloresPorClave: { type: Object, default: () => ({}) },
    etiquetaAccesible: { type: String, default: 'Gráfica de barras' },
    altura: { type: Number, default: 220 },
});
const lienzo = ref(null);
let instancia = null;
let dejarDeObservar = () => {};

function construir() {
    if (instancia) instancia.destroy();
    if (!lienzo.value) return;

    const datosSeguro = props.datos ?? {};
    const etiquetas = Object.keys(datosSeguro);
    const valores = Object.values(datosSeguro);
    const maximo = Math.max(...valores, 1);

    const base = estiloBase();
    const colores = etiquetas.map((clave, i) =>
        resolverColor(props.coloresPorClave[clave] ?? props.colores[i % props.colores.length]),
    );

    instancia = new Chart(lienzo.value, {
        type: 'bar',
        data: {
            labels: etiquetas,
            datasets: [{
                data: valores,
                backgroundColor: colores,
                hoverBackgroundColor: colores.map((c) => conAlfa(c, 0.82)),
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                categoryPercentage: 0.7,
                barPercentage: 0.9,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 650, easing: 'easeOutQuart' },
            layout: { padding: { top: 4 } },
            plugins: { legend: { display: false }, tooltip: base.tooltip },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: base.texto, font: base.fuente, maxRotation: 40, autoSkip: true },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: Math.ceil(maximo * 1.5) || 5,
                    ticks: { stepSize: 1, color: base.texto, font: base.fuente, padding: 6 },
                    grid: { color: base.rejilla, drawTicks: false },
                    border: { display: false, dash: [4, 4] },
                },
            },
        },
    });
}

onMounted(() => {
    construir();
    dejarDeObservar = observarTema(construir);
});
onBeforeUnmount(() => {
    dejarDeObservar();
    if (instancia) instancia.destroy();
});
watch(() => props.datos, construir, { deep: true });
</script>

<style scoped>
.bar-chart-contenedor { position: relative; width: 100%; min-width: 0; overflow: hidden; }
@media (max-width: 640px) {
    .bar-chart-contenedor { height: 200px !important; }
}
</style>
