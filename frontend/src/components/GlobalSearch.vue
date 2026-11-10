<template>
  <div ref="raiz" class="gs">
    <AppIcon name="buscar" :size="18" class="gs__lupa" />
    <input
      v-model="q"
      class="gs__input"
      type="search"
      placeholder="Buscar activos, riesgos, incidentes, documentos…"
      aria-label="Búsqueda global"
      @input="buscarDebounced"
      @focus="abierto = true"
    />
    <Transition name="gs-lista">
      <ul v-if="abierto && resultados.length" class="gs__resultados">
        <li v-for="r in resultados" :key="`${r.modulo}-${r.id}`" @mousedown="ir(r)">
          <span class="gs__modulo">{{ r.modulo }}</span>
          <span class="gs__titulo">{{ r.titulo }}</span>
          <AppIcon name="flecha" :size="15" class="gs__ir" />
        </li>
      </ul>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import busquedaApi from '../api/busqueda';
import AppIcon from './ui/AppIcon.vue';

const q = ref('');
const resultados = ref([]);
const abierto = ref(false);
const router = useRouter();
let timeoutId = null;

function buscarDebounced() {
    clearTimeout(timeoutId);
    if (q.value.trim().length < 2) { resultados.value = []; return; }
    timeoutId = setTimeout(async () => {
        const { data } = await busquedaApi.buscar(q.value.trim());
        resultados.value = data.data;
    }, 300);
}

const rutasPorModulo = {
    activo: (id) => ({ name: 'activos.detalle', params: { uuid: id } }),
    incidente: (id) => ({ name: 'incidentes.detalle', params: { uuid: id } }),
    documento: (id) => ({ name: 'documentos.detalle', params: { uuid: id } }),
    auditoria: (id) => ({ name: 'auditorias.detalle', params: { id } }),
    riesgo: () => ({ name: 'riesgos.lista' }),
    control_iso: () => ({ name: 'cumplimiento' }),
};

function ir(r) {
    abierto.value = false;
    q.value = '';
    const destino = rutasPorModulo[r.modulo]?.(r.id);
    if (destino) router.push(destino);
}

// Solo presentación: cerrar la lista de resultados al hacer clic fuera.
const raiz = ref(null);
function alClicFuera(e) {
    if (raiz.value && !raiz.value.contains(e.target)) abierto.value = false;
}
onMounted(() => document.addEventListener('mousedown', alClicFuera));
onBeforeUnmount(() => document.removeEventListener('mousedown', alClicFuera));
</script>

<style scoped>
.gs { position: relative; width: 100%; }
.gs__lupa { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-subtle); pointer-events: none; transition: color var(--transition-fast); }
.gs:focus-within .gs__lupa { color: var(--c-primary); }
.gs__input {
    width: 100%; min-height: 42px; padding: 9px 16px 9px 42px; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-subtle); color: var(--text); font-size: var(--text-sm);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
}
.gs__input:hover { border-color: var(--border-strong); }
.gs__input:focus { outline: none; background: var(--surface); border-color: var(--c-primary); box-shadow: var(--focus-ring); }
.gs__resultados {
    position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: var(--surface-raised);
    border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
    list-style: none; margin: 0; padding: 6px; max-height: min(360px, 60vh); overflow-y: auto; z-index: 50;
}
.gs__resultados li {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px; min-height: 44px;
    border-radius: var(--radius-sm); font-size: var(--text-sm); cursor: pointer; color: var(--text);
}
.gs__resultados li:hover { background: var(--c-primary-soft); }
.gs__modulo {
    flex-shrink: 0; font-size: 0.64rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
    color: var(--c-primary); background: var(--c-primary-soft); padding: 3px 8px; border-radius: 999px;
}
.gs__titulo { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gs__ir { color: var(--text-subtle); opacity: 0; transition: opacity var(--transition-fast), transform var(--transition-fast); }
.gs__resultados li:hover .gs__ir { opacity: 1; transform: translateX(2px); color: var(--c-primary); }

.gs-lista-enter-active, .gs-lista-leave-active { transition: opacity 160ms var(--ease), transform 160ms var(--ease); }
.gs-lista-enter-from, .gs-lista-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
