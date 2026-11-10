<template>
  <Teleport to="body">
    <div class="toast-host" aria-live="polite">
      <TransitionGroup name="toast">
        <div v-for="t in store.items" :key="t.id" class="toast" :class="`toast--${t.tipo}`" role="status">
          <span class="toast__icono"><AppIcon :name="icono(t.tipo)" :size="18" /></span>
          <span class="toast__mensaje">{{ t.mensaje }}</span>
          <button class="toast__cerrar" aria-label="Cerrar notificación" @click="store.cerrar(t.id)">
            <AppIcon name="cerrar" :size="15" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToastStore } from '../../stores/toast';
import AppIcon from './AppIcon.vue';

const store = useToastStore();

function icono(tipo) {
    return { exito: 'exito', error: 'alerta' }[tipo] || 'info';
}
</script>

<style scoped>
.toast-host {
    position: fixed; bottom: var(--space-5); right: var(--space-5); z-index: 200;
    display: flex; flex-direction: column; gap: var(--space-2); align-items: flex-end;
    width: min(400px, calc(100vw - 32px)); pointer-events: none;
}
.toast {
    pointer-events: auto; width: 100%;
    display: flex; align-items: flex-start; gap: var(--space-3);
    padding: 12px 12px 12px 14px; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); font-size: var(--text-sm);
    background: var(--surface-raised); color: var(--text); border: 1px solid var(--border);
    border-left: 4px solid var(--c-signal);
}
.toast__icono {
    display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    background: var(--c-signal-soft); color: var(--c-signal);
}
.toast__mensaje { flex: 1; align-self: center; line-height: 1.4; overflow-wrap: anywhere; }
.toast__cerrar {
    display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; border: none; background: none;
    color: var(--text-subtle); border-radius: var(--radius-sm); cursor: pointer;
}
.toast__cerrar:hover { background: var(--bg-subtle); color: var(--text); }
.toast--exito { border-left-color: var(--c-success); }
.toast--exito .toast__icono { background: var(--c-success-soft); color: var(--c-success); }
.toast--error { border-left-color: var(--c-risk); }
.toast--error .toast__icono { background: var(--c-risk-soft); color: var(--c-risk); }

.toast-enter-active, .toast-leave-active { transition: all 280ms var(--ease); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(24px); }

/* Celulares: arriba de la barra de navegación inferior */
@media (max-width: 640px) {
    .toast-host {
        left: 16px; right: 16px; width: auto; align-items: stretch;
        bottom: calc(var(--bottomnav-height) + 12px + env(safe-area-inset-bottom, 0px));
    }
    .toast-enter-from, .toast-leave-to { transform: translateY(16px); }
}
</style>
