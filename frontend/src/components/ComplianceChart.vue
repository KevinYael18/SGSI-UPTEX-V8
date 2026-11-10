<template>
  <div class="compliance-chart">
    <canvas ref="lienzo" role="img" aria-label="Cumplimiento por categoría ISO 27001"></canvas>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue';
import Chart from 'chart.js/auto';
import { resolverColor, estiloBase, observarTema } from './charts/temaGraficas';

const props = defineProps({ datos: { type: Object, required: true } }); // { organizacional, personas, fisico, tecnologico }
const lienzo = ref(null);
let instancia = null;
let dejarDeObservar = () => {};

const etiquetas = { organizacional: 'Organizacional', personas: 'Personas', fisico: 'Físico', tecnologico: 'Tecnológico' };

function construir() {
  if (instancia) instancia.destroy();
  if (!lienzo.value) return;

  const base = estiloBase();
  const verde = resolverColor('--c-primary');
  const pista = resolverColor('--bg-subtle');

  instancia = new Chart(lienzo.value, {
    type: 'bar',
    data: {
      labels: Object.keys(props.datos).map((k) => etiquetas[k] ?? k),
      datasets: [{
        label: '% Implementado',
        data: Object.values(props.datos),
        backgroundColor: verde,
        borderRadius: 6,
        borderSkipped: false,
        // Grosor proporcional al alto disponible (con tope) para que las
        // barras no se vean como hilos cuando la tarjeta es más alta.
        categoryPercentage: 0.62,
        barPercentage: 1,
        maxBarThickness: 30,
      }],
    },
    options: {
      // Barras horizontales: las categorías se leen completas incluso en
      // pantallas angostas (antes las etiquetas quedaban rotadas).
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 700, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { ...base.tooltip, callbacks: { label: (ctx) => ` ${ctx.parsed.x}% implementado` } },
      },
      scales: {
        x: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: (v) => `${v}%`, color: base.texto, font: base.fuente, stepSize: 25 },
          grid: { color: base.rejilla, drawTicks: false },
          border: { display: false },
        },
        y: {
          ticks: { color: base.texto, font: { ...base.fuente, weight: '500' } },
          grid: { display: false },
          border: { display: false },
        },
      },
    },
    plugins: [{
      // Pista gris de fondo (0–100 %) detrás de cada barra: ayuda a leer
      // el avance real sin añadir datos a la gráfica.
      id: 'pistaCumplimiento',
      beforeDatasetsDraw(chart) {
        const { ctx, chartArea, scales } = chart;
        const meta = chart.getDatasetMeta(0);
        ctx.save();
        ctx.fillStyle = pista;
        meta.data.forEach((barra) => {
          const alto = barra.height;
          const y = barra.y - alto / 2;
          const x0 = scales.x.getPixelForValue(0);
          const ancho = chartArea.right - x0;
          const r = Math.min(6, alto / 2);
          ctx.beginPath();
          if (ctx.roundRect) ctx.roundRect(x0, y, ancho, alto, r);
          else ctx.rect(x0, y, ancho, alto);
          ctx.fill();
        });
        ctx.restore();
      },
      // Porcentaje al final de cada barra: dentro (blanco) si la barra es
      // larga, o justo después (color de texto) si es corta.
      afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const meta = chart.getDatasetMeta(0);
        const valores = chart.data.datasets[0].data;
        ctx.save();
        ctx.font = `600 12px ${base.fuente?.family || 'Inter, system-ui, sans-serif'}`;
        ctx.textBaseline = 'middle';
        meta.data.forEach((barra, i) => {
          const v = Number(valores[i]) || 0;
          const texto = `${Math.round(v * 10) / 10}%`;
          const ancho = ctx.measureText(texto).width;
          const dentro = barra.x - barra.base > ancho + 16;
          ctx.fillStyle = dentro ? '#fff' : base.texto;
          ctx.textAlign = dentro ? 'right' : 'left';
          ctx.fillText(texto, dentro ? barra.x - 8 : barra.x + 6, barra.y);
        });
        ctx.restore();
      },
    }],
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
.compliance-chart { position: relative; width: 100%; height: 220px; min-width: 0; }
</style>
