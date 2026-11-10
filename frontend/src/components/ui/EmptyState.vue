<template>
  <div class="empty-state">
    <div class="empty-state__ilustracion" aria-hidden="true">
      <svg class="empty-state__grid" viewBox="0 0 100 100">
        <rect v-for="n in 25" :key="n" :x="((n-1)%5)*20+2" :y="Math.floor((n-1)/5)*20+2" width="16" height="16" rx="4"
              :fill="colorCelda(n)" />
      </svg>
    </div>
    <p class="empty-state__texto">{{ texto }}</p>
    <slot name="accion" />
  </div>
</template>

<script setup>
defineProps({ texto: { type: String, default: 'No hay datos para mostrar todavía.' } });

// Motivo de la matriz de riesgo 5×5 con los tonos institucionales.
function colorCelda(n) {
    if (n === 5) return 'var(--c-risk-soft)';
    if (n === 7 || n === 13) return 'var(--c-primary-soft)';
    if (n === 19) return 'var(--c-lime-soft)';
    return 'var(--bg-subtle)';
}
</script>

<style scoped>
.empty-state {
    display: flex; flex-direction: column; align-items: center; gap: var(--space-3);
    padding: var(--space-8) var(--space-4); text-align: center;
}
.empty-state__ilustracion {
    display: grid; place-items: center; width: 112px; height: 112px; border-radius: 28px;
    background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-sm);
}
.empty-state__grid { width: 76px; height: 76px; }
.empty-state__texto { color: var(--text-muted); margin: var(--space-2) 0 0; max-width: 36ch; font-size: var(--text-sm); }
</style>
