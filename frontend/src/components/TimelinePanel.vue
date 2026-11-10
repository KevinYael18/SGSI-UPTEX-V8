<template>
  <div class="timeline">
    <h3 class="timeline__titulo"><AppIcon name="bitacora" :size="18" />Actividad</h3>
    <ol v-if="items.length" class="timeline__lista">
      <li v-for="(ev, i) in items" :key="i" class="timeline__item">
        <span class="timeline__punto" :class="`timeline__punto--${ev.accion}`" />
        <div>
          <p class="timeline__texto">
            <strong>{{ ev.usuario || 'Sistema' }}</strong>
            {{ etiquetaAccion(ev.accion) }}
          </p>
          <time class="timeline__fecha">{{ new Date(ev.fecha).toLocaleString('es-MX') }}</time>
        </div>
      </li>
    </ol>
    <p v-else class="timeline__vacio">Sin actividad registrada.</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import timelineApi from '../api/timeline';
import AppIcon from './ui/AppIcon.vue';

const props = defineProps({
    tipo: { type: String, required: true }, // activos | riesgos | incidentes | auditorias | documentos
    id: { type: [String, Number], required: true },
});

const items = ref([]);

function etiquetaAccion(accion) {
    return { create: 'creó el registro', update: 'actualizó el registro', delete: 'eliminó el registro', restore: 'restauró el registro' }[accion] || accion;
}

onMounted(async () => {
    const { data } = await timelineApi.obtener(props.tipo, props.id);
    items.value = data.data;
});
</script>

<style scoped>
.timeline__titulo { display: flex; align-items: center; gap: 8px; margin-bottom: var(--space-4); font-size: 1rem; }
.timeline__titulo :deep(svg) { color: var(--c-primary); }
.timeline__lista { list-style: none; margin: 0 0 0 7px; padding: 0; border-left: 2px solid var(--border); }
.timeline__item { position: relative; padding: 0 0 var(--space-4) var(--space-5); }
.timeline__item:last-child { padding-bottom: 0; }
.timeline__punto {
    position: absolute; left: -8px; top: 3px; width: 14px; height: 14px; border-radius: 50%;
    background: var(--c-signal); border: 3px solid var(--surface); box-shadow: 0 0 0 1px var(--c-signal);
}
.timeline__punto--create { background: var(--c-success); box-shadow: 0 0 0 1px var(--c-success); }
.timeline__punto--update { background: var(--c-gray-500); box-shadow: 0 0 0 1px var(--c-gray-500); }
.timeline__punto--delete { background: var(--c-risk); box-shadow: 0 0 0 1px var(--c-risk); }
.timeline__punto--restore { background: var(--c-lime); box-shadow: 0 0 0 1px var(--c-lime); }
.timeline__texto { margin: 0; font-size: var(--text-sm); }
.timeline__fecha { font-size: var(--text-xs); color: var(--text-muted); }
.timeline__vacio { color: var(--text-muted); font-size: var(--text-sm); margin: 0; }
</style>
