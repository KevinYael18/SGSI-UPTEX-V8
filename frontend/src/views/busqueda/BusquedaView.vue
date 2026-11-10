<template>
  <div>
    <h1>Búsqueda global</h1>
    <div class="buscar-caja">
      <AppIcon name="buscar" :size="20" class="buscar-caja__icono" />
      <input v-model="q" class="buscar" placeholder="Buscar…" @input="buscarDebounced" autofocus />
    </div>
    <ul class="resultados">
      <li v-for="r in resultados" :key="`${r.modulo}-${r.id}`">
        <AppBadge tono="signal">{{ r.modulo }}</AppBadge> <span class="resultados__titulo">{{ r.titulo }}</span>
      </li>
    </ul>
    <EmptyState v-if="buscado && !resultados.length" texto="Sin resultados para tu búsqueda." />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import busquedaApi from '../../api/busqueda';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const q = ref('');
const resultados = ref([]);
const buscado = ref(false);
let timeoutId = null;

function buscarDebounced() {
    clearTimeout(timeoutId);
    if (q.value.trim().length < 2) { resultados.value = []; buscado.value = false; return; }
    timeoutId = setTimeout(async () => {
        const { data } = await busquedaApi.buscar(q.value.trim());
        resultados.value = data.data;
        buscado.value = true;
    }, 300);
}
</script>

<style scoped>
.buscar-caja { position: relative; max-width: 560px; margin-bottom: var(--space-5); }
.buscar-caja__icono { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-subtle); pointer-events: none; }
.buscar {
    width: 100%; min-height: 50px; padding: 12px 18px 12px 48px; border-radius: 999px; border: 1px solid var(--border-strong);
    background: var(--surface); color: var(--text); font-size: var(--text-base); box-shadow: var(--shadow-sm);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.buscar:focus { outline: none; border-color: var(--c-primary); box-shadow: var(--focus-ring); }
.buscar-caja:focus-within .buscar-caja__icono { color: var(--c-primary); }
.resultados { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--space-2); }
.resultados li {
    display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-4); min-height: 56px;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); font-size: var(--text-sm); box-shadow: var(--shadow-sm);
}
.resultados__titulo { min-width: 0; overflow-wrap: anywhere; }
</style>
