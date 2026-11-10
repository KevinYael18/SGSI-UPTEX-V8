<template>
  <button
    class="app-btn"
    :class="[`app-btn--${variante}`, { 'app-btn--sm': tamano === 'sm', 'app-btn--cargando': cargando }]"
    :disabled="cargando || disabled"
    v-bind="$attrs"
  >
    <span v-if="cargando" class="app-btn__spinner" aria-hidden="true" />
    <AppIcon v-else-if="icono" :name="icono" :size="tamano === 'sm' ? 15 : 17" />
    <slot />
  </button>
</template>

<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    variante: { type: String, default: 'primaria' }, // primaria | secundaria | peligro | fantasma
    tamano: { type: String, default: 'md' },
    cargando: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    icono: { type: String, default: '' }, // opcional: nombre de AppIcon
});
</script>

<style scoped>
.app-btn {
    position: relative;
    display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2);
    min-height: 42px;
    font-family: var(--font-body); font-weight: 600; font-size: var(--text-sm); letter-spacing: 0.005em;
    padding: 10px 18px; border-radius: var(--radius-md); border: 1px solid transparent;
    cursor: pointer; white-space: nowrap; line-height: 1.1; text-decoration: none;
    transition: background var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast),
                box-shadow var(--transition-fast), transform var(--transition-fast), opacity var(--transition-fast);
    user-select: none;
}
.app-btn:active:not(:disabled) { transform: translateY(1px) scale(0.985); }
.app-btn:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.app-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.app-btn--cargando:disabled { opacity: 0.85; cursor: progress; }
.app-btn--sm { min-height: 34px; padding: 6px 12px; font-size: var(--text-xs); border-radius: var(--radius-sm); gap: 6px; }

.app-btn--primaria {
    background: var(--c-primary); color: var(--on-primary);
    box-shadow: var(--shadow-brand), inset 0 1px 0 rgba(255, 255, 255, 0.14);
}
.app-btn--primaria:hover:not(:disabled) { background: var(--c-primary-strong); }

.app-btn--secundaria {
    background: var(--surface); color: var(--text); border-color: var(--border-strong);
    box-shadow: var(--shadow-sm);
}
.app-btn--secundaria:hover:not(:disabled) { border-color: var(--c-primary); color: var(--c-primary); background: var(--c-primary-soft); }

.app-btn--peligro { background: var(--c-risk-solid); color: #fff; box-shadow: 0 6px 16px -8px rgba(200, 16, 46, 0.6); }
.app-btn--peligro:hover:not(:disabled) { background: var(--c-risk-strong); }
[data-theme='dark'] .app-btn--peligro:hover:not(:disabled) { background: #B02C3B; }

.app-btn--fantasma { background: transparent; color: var(--text-muted); }
.app-btn--fantasma:hover:not(:disabled) { background: var(--bg-subtle); color: var(--text); }

.app-btn__spinner {
    width: 15px; height: 15px; border-radius: 50%;
    border: 2px solid currentColor; border-top-color: transparent;
    animation: girar 0.7s linear infinite;
}
@keyframes girar { to { transform: rotate(360deg); } }

/* Pantallas táctiles: área mínima de 44px */
@media (pointer: coarse) {
    .app-btn { min-height: 44px; }
    .app-btn--sm { min-height: 38px; padding: 8px 14px; }
}
</style>
