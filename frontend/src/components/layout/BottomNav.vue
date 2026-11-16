<template>
  <nav class="bottom-nav" aria-label="Navegación rápida">
    <RouterLink
      v-for="item in principales"
      :key="item.to"
      :to="{ name: item.to }"
      class="bottom-nav__item"
      :active-class="item.to === 'dashboard' ? '' : 'bottom-nav__item--activo'"
          exact-active-class="bottom-nav__item--activo"
    >
      <span class="bottom-nav__icono"><AppIcon :name="item.icono" :size="21" /></span>
      <span class="bottom-nav__texto">{{ item.etiquetaCorta || item.label }}</span>
    </RouterLink>
    <button class="bottom-nav__item" :class="{ 'bottom-nav__item--activo': ui.menuMovilAbierto }" @click="ui.abrirMenuMovil()">
      <span class="bottom-nav__icono"><AppIcon name="menu" :size="21" /></span>
      <span class="bottom-nav__texto">Más</span>
    </button>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useUiStore } from '../../stores/ui';
import { useNavegacion } from '../../composables/useNavegacion';
import AppIcon from '../ui/AppIcon.vue';

// Barra inferior tipo app móvil: los primeros 4 módulos permitidos para el
// usuario (mismas reglas de permisos que el menú lateral) + acceso al menú completo.
const ui = useUiStore();
const { items } = useNavegacion();
const principales = computed(() => items.value.slice(0, 4));
</script>

<style scoped>
.bottom-nav { display: none; }

@media (max-width: 640px) {
    .bottom-nav {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 35;
        display: grid; grid-auto-flow: column; grid-auto-columns: 1fr;
        height: calc(var(--bottomnav-height) + env(safe-area-inset-bottom, 0px));
        padding: 6px 6px env(safe-area-inset-bottom, 0px);
        background: color-mix(in srgb, var(--surface) 92%, transparent);
        backdrop-filter: saturate(160%) blur(14px); -webkit-backdrop-filter: saturate(160%) blur(14px);
        border-top: 1px solid var(--border);
        box-shadow: 0 -8px 24px -16px rgba(0, 0, 0, 0.25);
    }
    .bottom-nav__item {
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-width: 0;
        background: none; border: none; color: var(--text-subtle); cursor: pointer; font-size: 0.66rem; font-weight: 600;
        border-radius: var(--radius-md); padding: 4px 2px;
    }
    .bottom-nav__item:hover { text-decoration: none; color: var(--text-muted); }
    .bottom-nav__icono {
        display: grid; place-items: center; width: 48px; height: 28px; border-radius: 999px;
        transition: background var(--transition-base), color var(--transition-base), transform var(--transition-base);
    }
    .bottom-nav__texto { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bottom-nav__item--activo { color: var(--c-primary); }
    .bottom-nav__item--activo .bottom-nav__icono { background: var(--c-primary-soft); transform: translateY(-1px); }
    .bottom-nav__item:active .bottom-nav__icono { transform: scale(0.92); }
}
</style>
