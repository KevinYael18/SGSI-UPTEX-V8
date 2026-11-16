<template>
  <div v-if="visible" class="ia-seccion" :class="tono ? `ia-seccion--${tono}` : ''">
    <h4 class="ia-seccion__titulo">
      <AppIcon v-if="icono" :name="icono" :size="15" />
      <span>{{ titulo }}</span>
      <AppBadge v-if="prioridad" :tono="TONO_PRIORIDAD[prioridad] || 'neutral'">Prioridad {{ prioridad }}</AppBadge>
    </h4>
    <ul v-if="elementos?.length" class="ia-seccion__lista">
      <li v-for="(e, i) in elementos" :key="i">{{ e }}</li>
    </ul>
    <slot />
  </div>
</template>

<script setup>
import { computed, useSlots } from 'vue';
import AppIcon from '../ui/AppIcon.vue';
import AppBadge from '../ui/AppBadge.vue';

const props = defineProps({
    titulo: { type: String, required: true },
    elementos: { type: Array, default: null },
    icono: { type: String, default: '' },
    prioridad: { type: String, default: '' }, // alta | media | baja
    tono: { type: String, default: '' },       // riesgo | advertencia | exito
});
const slots = useSlots();
const TONO_PRIORIDAD = { alta: 'riesgo', media: 'advertencia', baja: 'signal' };
// Una sección vacía no se muestra (nada de tarjetas sin contenido).
const visible = computed(() => (props.elementos?.length ?? 0) > 0 || !!slots.default);
</script>

<style scoped>
.ia-seccion {
    display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; border-radius: var(--radius-md);
    background: var(--bg-subtle); border-left: 3px solid var(--c-primary);
}
.ia-seccion--riesgo { border-left-color: var(--c-risk); }
.ia-seccion--advertencia { border-left-color: var(--c-amber); }
.ia-seccion--exito { border-left-color: var(--c-success); }
.ia-seccion__titulo { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin: 0; font-size: var(--text-sm); color: var(--text); }
.ia-seccion__titulo :deep(svg) { color: var(--c-primary); }
.ia-seccion__lista { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 4px; font-size: var(--text-sm); color: var(--text); line-height: 1.5; }
.ia-seccion :deep(p) { margin: 0; font-size: var(--text-sm); color: var(--text); line-height: 1.55; }
</style>
