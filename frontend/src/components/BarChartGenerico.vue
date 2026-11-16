<template>
  <div class="bar-chart-contenedor" :style="{ height: `${altura}px` }">
    <canvas ref="lienzo" role="img" :aria-label="etiquetaAccesible"></canvas>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue';
import Chart from 'chart.js/auto';
import {
    resolverColor, estiloBase, conAlfa, observarTema,
    animacionEntradaBarras, TRANSICIONES_ACTUALIZACION, alSerVisible,
} from './charts/temaGraficas';

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
let animacion = false;
let dejarDeObservar = () => {};
let cancelarVisibilidad = () => {};

// Los valores se toman TAL CUAL del backend; solo se calculan colores.
function datosGrafica() {
    const datosSeguro = props.datos ?? {};
    const etiquetas = Object.keys(datosSeguro);
    const valores = Object.values(datosSeguro);
    const colores = etiquetas.map((clave, i) =>
        resolverColor(props.coloresPorClave[clave] ?? props.colores[i % props.colores.length]),
    );
    return { etiquetas, valores, colores, maximo: Math.max(...valores, 1) };
}

function opciones(d) {
    const base = estiloBase();
    return {
        responsive: true,
        maintainAspectRatio: false,
        // Entrada: las barras crecen desde 0 hasta su valor real, escalonadas.
        animation: animacion,
        transitions: TRANSICIONES_ACTUALIZACION,
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
                suggestedMax: Math.ceil(d.maximo * 1.5) || 5,
                ticks: { stepSize: 1, color: base.texto, font: base.fuente, padding: 6 },
                grid: { color: base.rejilla, drawTicks: false },
                border: { display: false, dash: [4, 4] },
            },
        },
    };
}

function crear() {
    if (!lienzo.value) return;
    if (instancia) instancia.destroy();
    animacion = animacionEntradaBarras();
    const d = datosGrafica();

    instancia = new Chart(lienzo.value, {
        type: 'bar',
        data: {
            labels: d.etiquetas,
            datasets: [{
                data: d.valores,
                backgroundColor: d.colores,
                hoverBackgroundColor: d.colores.map((c) => conAlfa(c, 0.82)),
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                categoryPercentage: 0.7,
                barPercentage: 0.9,
            }],
        },
        options: opciones(d),
    });
}

/**
 * Cambio de tema o de datos: se actualiza la MISMA instancia. Chart.js
 * interpola colores y alturas desde su estado actual (sin destruir el
 * lienzo ni volver a crecer desde cero, y sin parpadeos).
 */
function actualizar() {
    if (!instancia) return;
    const d = datosGrafica();
    const conjunto = instancia.data.datasets[0];
    instancia.data.labels = d.etiquetas;
    conjunto.data = d.valores;
    conjunto.backgroundColor = d.colores;
    conjunto.hoverBackgroundColor = d.colores.map((c) => conAlfa(c, 0.82));
    instancia.options = opciones(d);
    instancia.update();
}

onMounted(() => {
    cancelarVisibilidad = alSerVisible(lienzo.value, crear);
    dejarDeObservar = observarTema(actualizar);
});
onBeforeUnmount(() => {
    cancelarVisibilidad();
    dejarDeObservar();
    if (instancia) instancia.destroy();
    instancia = null;
});
watch(() => props.datos, actualizar, { deep: true });
</script>

<style scoped>
.bar-chart-contenedor { position: relative; width: 100%; min-width: 0; overflow: hidden; }
@media (max-width: 640px) {
    .bar-chart-contenedor { height: 200px !important; }
}
</style>
