<template>
  <div class="stat-card" :class="tono ? `stat-card--${tono}` : 'stat-card--neutral'">
    <div class="stat-card__top">
      <span class="stat-card__label">{{ label }}</span>
      <span v-if="icono" class="stat-card__icono" aria-hidden="true"><AppIcon :name="icono" :size="19" /></span>
    </div>
    <strong class="stat-card__valor" :class="tono ? `stat-card__valor--${tono}` : ''">
      <!-- Lectores de pantalla: siempre el valor real; el conteo es solo visual. -->
      <span class="visually-hidden">{{ valor }}</span>
      <span aria-hidden="true">{{ mostrado }}</span>
    </strong>
    <div v-if="progreso !== null" class="stat-card__barra" role="progressbar" :aria-valuenow="progresoSeguro" aria-valuemin="0" aria-valuemax="100">
      <span :style="{ width: `${barraVisible ? progresoSeguro : 0}%` }" />
    </div>
    <span v-if="detalle" class="stat-card__detalle">{{ detalle }}</span>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import AppIcon from './AppIcon.vue';
import { useContadorAnimado } from '../../composables/useContadorAnimado';

const props = defineProps({
    label: String,
    valor: [String, Number],
    detalle: { type: String, default: '' },
    tono: { type: String, default: '' }, // '' | riesgo | advertencia | exito
    icono: { type: String, default: '' }, // opcional
    progreso: { type: Number, default: null }, // opcional: 0–100, dibuja una barra de avance
});

const progresoSeguro = computed(() => Math.max(0, Math.min(100, Number(props.progreso) || 0)));

// ---- Solo presentación ----
// El número sube progresivamente hasta el valor real y termina mostrando
// exactamente el mismo texto recibido (ver useContadorAnimado).
const { mostrado } = useContadorAnimado(() => props.valor);
// La barra de avance arranca en 0 y se llena hasta el porcentaje real
// (usa la transición de `width` que ya tenía el componente).
const barraVisible = ref(false);
onMounted(() => {
    if (typeof requestAnimationFrame === 'function') requestAnimationFrame(() => requestAnimationFrame(() => { barraVisible.value = true; }));
    else barraVisible.value = true;
});
</script>

<style scoped>
.stat-card {
    --acento: var(--c-gray-500);
    --acento-suave: var(--bg-subtle);
    position: relative; overflow: hidden; min-width: 0;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: var(--space-5); display: flex; flex-direction: column; gap: 6px; box-shadow: var(--shadow-sm);
    transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
}
.stat-card::after {
    content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--acento); opacity: 0.85;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--border-strong); }
.stat-card--exito { --acento: var(--c-success); --acento-suave: var(--c-success-soft); }
.stat-card--riesgo { --acento: var(--c-risk); --acento-suave: var(--c-risk-soft); }
.stat-card--advertencia { --acento: var(--c-amber); --acento-suave: var(--c-amber-soft); }

.stat-card__top { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-3); }
.stat-card__label {
    font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;
    line-height: 1.35; padding-top: 2px;
}
.stat-card__icono {
    display: grid; place-items: center; width: 38px; height: 38px; border-radius: var(--radius-md); flex-shrink: 0;
    background: var(--acento-suave); color: var(--acento);
}
.stat-card__valor {
    font-family: var(--font-display); font-size: clamp(1.6rem, 1.2rem + 1vw, 2.1rem); font-weight: 700;
    color: var(--text); line-height: 1.1; letter-spacing: -0.02em; margin-top: 2px;
}
.stat-card__valor--riesgo { color: var(--c-risk); }
.stat-card__valor--advertencia { color: var(--c-amber); }
.stat-card__valor--exito { color: var(--c-success); }
.stat-card__barra { height: 6px; border-radius: 999px; background: var(--bg-subtle); overflow: hidden; margin-top: 4px; }
.stat-card__barra span {
    display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--c-primary), var(--c-lime));
    transition: width 900ms var(--ease);
}
.stat-card__detalle { font-size: var(--text-xs); color: var(--text-muted); }

@media (max-width: 640px) {
    .stat-card { padding: var(--space-4); }
    .stat-card__icono { width: 32px; height: 32px; }
}
</style>
