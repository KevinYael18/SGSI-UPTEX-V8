<template>
  <section class="app-card">
    <header v-if="titulo || $slots.acciones" class="app-card__header">
      <div class="app-card__titulo">
        <span v-if="icono" class="app-card__icono"><AppIcon :name="icono" :size="18" /></span>
        <div class="app-card__textos">
          <h3 v-if="titulo">{{ titulo }}</h3>
          <span v-if="subtitulo" class="app-card__subtitulo">{{ subtitulo }}</span>
        </div>
      </div>
      <div v-if="$slots.acciones" class="app-card__acciones"><slot name="acciones" /></div>
    </header>
    <div class="app-card__body"><slot /></div>
  </section>
</template>

<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    titulo: { type: String, default: '' },
    icono: { type: String, default: '' }, // opcional
    subtitulo: { type: String, default: '' }, // opcional
});
</script>

<style scoped>
.app-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm); min-width: 0;
    transition: box-shadow var(--transition-base), border-color var(--transition-base), background-color var(--transition-base);
}
.app-card:hover { box-shadow: var(--shadow-md); }
.app-card__header {
    display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap;
    padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--border);
}
.app-card__titulo { display: flex; align-items: center; gap: var(--space-3); min-width: 0; }
.app-card__icono {
    display: inline-grid; place-items: center; width: 32px; height: 32px; border-radius: var(--radius-sm);
    background: var(--c-primary-soft); color: var(--c-primary); flex-shrink: 0;
}
.app-card__textos { display: flex; flex-direction: column; min-width: 0; }
.app-card__header h3 { margin: 0; font-size: 1rem; line-height: 1.35; }
.app-card__subtitulo { font-size: var(--text-xs); color: var(--text-muted); margin-top: 1px; }
.app-card__acciones { display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap; }
.app-card__body { padding: var(--space-5); min-width: 0; }

@media (max-width: 640px) {
    .app-card { border-radius: var(--radius-md); }
    .app-card__header { padding: var(--space-3) var(--space-4); }
    .app-card__body { padding: var(--space-4); }
}
</style>
