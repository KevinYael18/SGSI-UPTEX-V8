<template>
  <div class="app-table-wrap">
    <div class="app-table-scroll">
      <table class="app-table" :class="{ 'app-table--clicable': clicable }">
        <thead>
          <tr>
            <th v-for="col in columnas" :key="col.key" :style="{ width: col.width }">{{ col.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando" class="app-table__fila-estado">
            <td :colspan="columnas.length" class="app-table__estado">
              <span class="app-table__esqueleto" aria-hidden="true">
                <span v-for="n in 3" :key="n" class="skeleton app-table__esqueleto-linea" :style="{ '--i': n }" />
              </span>
              <span class="app-table__cargando"><span class="app-table__spinner" aria-hidden="true" />Cargando…</span>
            </td>
          </tr>
          <tr v-else-if="filas.length === 0" class="app-table__fila-estado">
            <td :colspan="columnas.length" class="app-table__estado">
              <slot name="vacio">Sin resultados.</slot>
            </td>
          </tr>
          <template v-else>
            <tr
              v-for="(fila, i) in filas"
              :key="fila.id ?? fila.uuid ?? i"
              class="entrada-escalonada"
              :style="{ '--i': i }"
              :class="{ 'app-table__fila--clic': clicable }"
              @click="clicable && $emit('fila-click', fila)"
            >
              <td v-for="col in columnas" :key="col.key" :data-label="col.label">
                <slot :name="`col-${col.key}`" :fila="fila">{{ fila[col.key] }}</slot>
              </td>
              <td v-if="clicable" class="app-table__flecha" aria-hidden="true"><AppIcon name="chevron-der" :size="18" /></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Paginación real: consume el meta de Laravel (current_page, last_page,
         total, per_page) tal cual lo entrega Resource::collection(paginator).
         No se muestra si no se pasa `paginacion` (listas sin paginar). -->
    <div v-if="paginacion && paginacion.last_page > 1" class="app-table__paginacion">
      <span class="app-table__resumen">
        Mostrando <strong>{{ inicioRango }}–{{ finRango }}</strong> de <strong>{{ paginacion.total }}</strong>
      </span>
      <div class="app-table__botones">
        <button
          type="button"
          class="app-table__pag-btn"
          :disabled="paginacion.current_page <= 1"
          @click="$emit('cambiar-pagina', paginacion.current_page - 1)"
        ><AppIcon name="chevron-izq" :size="16" /><span class="app-table__pag-texto">Anterior</span></button>
        <span class="app-table__pagina-actual">Página {{ paginacion.current_page }} de {{ paginacion.last_page }}</span>
        <button
          type="button"
          class="app-table__pag-btn"
          :disabled="paginacion.current_page >= paginacion.last_page"
          @click="$emit('cambiar-pagina', paginacion.current_page + 1)"
        ><span class="app-table__pag-texto">Siguiente</span><AppIcon name="chevron-der" :size="16" /></button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    columnas: { type: Array, required: true }, // [{ key, label, width }]
    filas: { type: Array, default: () => [] },
    cargando: { type: Boolean, default: false },
    clicable: { type: Boolean, default: false },
    // { current_page, last_page, per_page, total } — el `meta` que Laravel
    // ya incluye en toda respuesta paginada (Resource::collection($paginator)).
    paginacion: { type: Object, default: null },
});
defineEmits(['fila-click', 'cambiar-pagina']);

const inicioRango = computed(() => {
    if (!props.paginacion) return 0;
    return (props.paginacion.current_page - 1) * props.paginacion.per_page + 1;
});
const finRango = computed(() => {
    if (!props.paginacion) return 0;
    return Math.min(props.paginacion.current_page * props.paginacion.per_page, props.paginacion.total);
});
</script>

<style scoped>
.app-table-wrap {
    border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);
    box-shadow: var(--shadow-sm); overflow: hidden; min-width: 0;
}
.app-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.app-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
.app-table thead th {
    text-align: left; padding: 12px 18px; background: var(--bg-subtle); color: var(--text-muted);
    font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em;
    border-bottom: 1px solid var(--border); white-space: nowrap;
}
.app-table--clicable thead tr::after { content: ''; display: table-cell; width: 40px; background: var(--bg-subtle); border-bottom: 1px solid var(--border); }
.app-table tbody td {
    padding: 14px 18px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text);
    overflow-wrap: anywhere;
}
.app-table tbody tr:last-child td { border-bottom: none; }
.app-table tbody tr { transition: background var(--transition-fast); }
.app-table tbody tr:not(.app-table__fila-estado):hover { background: var(--surface-hover); }
.app-table__fila--clic { cursor: pointer; }
.app-table__flecha { width: 40px; padding-left: 0 !important; color: var(--text-subtle); text-align: right; }
.app-table__fila--clic:hover .app-table__flecha { color: var(--c-primary); }
.app-table__fila--clic:hover .app-table__flecha :deep(svg) { transform: translateX(2px); }
.app-table__flecha :deep(svg) { transition: transform var(--transition-fast); }
.app-table__estado { text-align: center; padding: var(--space-6); color: var(--text-muted); }
.app-table__cargando { display: inline-flex; align-items: center; gap: 10px; font-weight: 500; }
/* Esqueleto de carga (solo presentación): líneas que anticipan las filas. */
.app-table__esqueleto { display: flex; flex-direction: column; gap: 10px; margin: 0 auto var(--space-4); max-width: 560px; }
.app-table__esqueleto-linea { display: block; height: 12px; border-radius: 6px; width: calc(100% - var(--i) * 14%); margin: 0 auto; }
.app-table__fila--clic:active td { background: var(--bg-subtle); transition: background 60ms; }
.app-table__spinner {
    width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--c-primary-soft); border-top-color: var(--c-primary);
    animation: girar-tabla 0.8s linear infinite;
}
@keyframes girar-tabla { to { transform: rotate(360deg); } }

.app-table__paginacion {
    display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap;
    padding: 12px 18px; border-top: 1px solid var(--border); font-size: var(--text-xs); color: var(--text-muted);
    background: var(--surface);
}
.app-table__resumen strong { color: var(--text); font-weight: 600; }
.app-table__botones { display: flex; align-items: center; gap: var(--space-2); }
.app-table__pagina-actual { padding: 0 var(--space-2); white-space: nowrap; }
.app-table__pag-btn {
    display: inline-flex; align-items: center; gap: 4px; min-height: 36px;
    background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius-sm); padding: 6px 12px;
    color: var(--text); font-size: var(--text-xs); font-weight: 600; cursor: pointer;
    transition: background var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast);
}
.app-table__pag-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.app-table__pag-btn:not(:disabled):hover { background: var(--c-primary-soft); border-color: var(--c-primary); color: var(--c-primary); }

/* ---- Celulares: cada fila se presenta como tarjeta legible ---- */
@media (max-width: 720px) {
    .app-table-wrap { background: transparent; border: none; box-shadow: none; overflow: visible; }
    .app-table-scroll { overflow: visible; }
    .app-table, .app-table tbody { display: block; }
    .app-table thead { display: none; }
    .app-table tbody tr {
        display: block; position: relative; background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius-lg); padding: 6px 16px; margin-bottom: var(--space-3); box-shadow: var(--shadow-sm);
    }
    .app-table tbody tr:not(.app-table__fila-estado):hover { background: var(--surface); }
    .app-table__fila--clic:active { transform: scale(0.99); }
    .app-table tbody td {
        display: flex; align-items: center; justify-content: space-between; gap: var(--space-3);
        padding: 10px 0; border-bottom: 1px solid var(--border); text-align: right; min-height: 44px;
    }
    .app-table tbody td::before {
        content: attr(data-label); flex-shrink: 0; max-width: 45%; text-align: left;
        font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
    }
    .app-table tbody td:last-of-type,
    .app-table--clicable tbody td:nth-last-of-type(2) { border-bottom: none; }
    .app-table tbody td.app-table__flecha::before { display: none; }
    /* Primer campo como "título" de la tarjeta */
    .app-table tbody tr:not(.app-table__fila-estado) td:first-child {
        display: block; text-align: left; font-weight: 600; font-size: var(--text-base); padding-right: 28px;
    }
    .app-table tbody tr:not(.app-table__fila-estado) td:first-child::before { display: block; margin-bottom: 2px; max-width: none; }
    .app-table__flecha {
        position: absolute; top: 14px; right: 12px; border: none !important; padding: 0 !important; min-height: 0 !important;
        width: auto;
    }
    .app-table__flecha::before { display: none; }
    .app-table--clicable thead tr::after { display: none; }
    .app-table__fila-estado { padding: 0 !important; }
    .app-table__fila-estado td { display: block !important; text-align: center !important; border: none !important; }
    .app-table__fila-estado td::before { display: none; }
    .app-table tbody td :deep(.app-field) { flex: 1; max-width: 60%; }

    .app-table__paginacion {
        border: 1px solid var(--border); border-radius: var(--radius-lg); justify-content: center; text-align: center;
    }
    .app-table__botones { width: 100%; justify-content: space-between; }
    .app-table__pag-btn { min-height: 44px; padding: 8px 14px; }
}
@media (max-width: 380px) {
    .app-table__pag-texto { display: none; }
}
</style>
