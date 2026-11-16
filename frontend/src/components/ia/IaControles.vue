<template>
  <div v-if="controles?.length || descartados" class="ia-controles">
    <h4 class="ia-controles__titulo"><AppIcon name="cumplimiento" :size="15" />{{ titulo }}</h4>
    <ul class="ia-controles__lista">
      <li v-for="c in controles" :key="c.codigo">
        <AppBadge tono="signal">{{ c.codigo }}</AppBadge>
        <div>
          <strong>{{ c.nombre }}</strong>
          <span v-if="c.estado_implementacion" class="ia-controles__estado">Estado en GSI UPTex: {{ c.estado_implementacion.replace('_', ' ') }}</span>
          <p v-if="c.justificacion">{{ c.justificacion }}</p>
        </div>
      </li>
    </ul>
    <p v-if="descartados > 0" class="ia-controles__descartados">
      Se descartaron {{ descartados }} referencia(s) que no existen en el catálogo ISO/IEC 27001:2022 del sistema.
    </p>
  </div>
</template>

<script setup>
import AppIcon from '../ui/AppIcon.vue';
import AppBadge from '../ui/AppBadge.vue';

defineProps({
    controles: { type: Array, default: () => [] },
    descartados: { type: Number, default: 0 },
    titulo: { type: String, default: 'Controles del Anexo A recomendados' },
});
</script>

<style scoped>
.ia-controles { display: flex; flex-direction: column; gap: 8px; }
.ia-controles__titulo { display: flex; align-items: center; gap: 6px; margin: 0; font-size: var(--text-sm); color: var(--text); }
.ia-controles__titulo :deep(svg) { color: var(--c-primary); }
.ia-controles__lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.ia-controles__lista li { display: flex; gap: 8px; align-items: flex-start; }
.ia-controles__lista strong { font-size: var(--text-sm); color: var(--text); }
.ia-controles__estado { display: block; font-size: 0.7rem; color: var(--text-subtle); text-transform: capitalize; }
.ia-controles__lista p { margin: 2px 0 0; font-size: var(--text-xs); color: var(--text-muted); line-height: 1.5; }
.ia-controles__descartados { margin: 0; font-size: var(--text-xs); color: var(--text-muted); }
</style>
