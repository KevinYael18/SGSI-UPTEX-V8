<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="abierto" class="app-modal-overlay" @mousedown.self="$emit('cerrar')">
        <div class="app-modal" :class="{ 'app-modal--ancho': ancho }" role="dialog" aria-modal="true" :aria-label="titulo || undefined">
          <span class="app-modal__asa" aria-hidden="true" />
          <header class="app-modal__header">
            <h3>{{ titulo }}</h3>
            <button class="app-modal__cerrar" aria-label="Cerrar" @click="$emit('cerrar')">
              <AppIcon name="cerrar" :size="18" />
            </button>
          </header>
          <div class="app-modal__body"><slot /></div>
          <footer v-if="$slots.footer" class="app-modal__footer"><slot name="footer" /></footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch, onBeforeUnmount } from 'vue';
import AppIcon from './AppIcon.vue';

// `ancho`: variante grande para visores (imágenes, PDF, video).
const props = defineProps({ abierto: Boolean, titulo: { type: String, default: '' }, ancho: Boolean });
const emit = defineEmits(['cerrar']);

// Accesibilidad: Escape cierra el modal (mismo efecto que el botón ✕).
function alPresionarTecla(e) {
    if (e.key === 'Escape') emit('cerrar');
}
watch(() => props.abierto, (abierto) => {
    if (abierto) document.addEventListener('keydown', alPresionarTecla);
    else document.removeEventListener('keydown', alPresionarTecla);
}, { immediate: true });
onBeforeUnmount(() => document.removeEventListener('keydown', alPresionarTecla));
</script>

<style scoped>
.app-modal-overlay {
    position: fixed; inset: 0; background: var(--overlay);
    backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
    display: flex; align-items: center; justify-content: center; z-index: 100; padding: var(--space-4);
}
.app-modal {
    position: relative;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl); box-shadow: var(--shadow-lg);
    width: 100%; max-width: 580px; max-height: min(90vh, 900px); display: flex; flex-direction: column; overflow: hidden;
}
.app-modal--ancho { max-width: min(1100px, 100%); }
.app-modal::before {
    content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: var(--brand-stripe);
}
.app-modal__asa { display: none; }
.app-modal__header {
    display: flex; align-items: center; justify-content: space-between; gap: var(--space-3);
    padding: var(--space-5) var(--space-5) var(--space-4); border-bottom: 1px solid var(--border);
}
.app-modal__header h3 { margin: 0; font-size: 1.1rem; }
.app-modal__cerrar {
    display: grid; place-items: center; width: 38px; height: 38px; flex-shrink: 0;
    background: var(--bg-subtle); border: none; border-radius: 50%; cursor: pointer; color: var(--text-muted);
    transition: background var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
}
.app-modal__cerrar:hover { background: var(--c-risk-soft); color: var(--c-risk); transform: rotate(90deg); }
.app-modal__body { padding: var(--space-5); overflow-y: auto; overscroll-behavior: contain; }
.app-modal__footer {
    padding: var(--space-4) var(--space-5); border-top: 1px solid var(--border); background: var(--bg-subtle);
    display: flex; justify-content: flex-end; gap: var(--space-3); flex-wrap: wrap;
}

.modal-enter-active, .modal-leave-active { transition: opacity var(--transition-base); }
.modal-enter-active .app-modal, .modal-leave-active .app-modal { transition: transform 260ms var(--ease), opacity 260ms var(--ease); }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .app-modal, .modal-leave-to .app-modal { transform: translateY(12px) scale(0.98); opacity: 0; }

/* Celulares: el modal se convierte en "hoja inferior" estilo app nativa */
@media (max-width: 640px) {
    .app-modal-overlay { align-items: flex-end; padding: 0; }
    .app-modal {
        max-width: none; max-height: 92dvh; border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        border-bottom: none; padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    .app-modal::before { display: none; }
    .app-modal__asa {
        display: block; width: 40px; height: 4px; border-radius: 4px; background: var(--border-strong);
        margin: 10px auto 0;
    }
    .app-modal__header { padding: var(--space-3) var(--space-4) var(--space-3); }
    .app-modal__body { padding: var(--space-4); }
    .app-modal__footer { padding: var(--space-3) var(--space-4); flex-direction: column-reverse; }
    .app-modal__footer :deep(.app-btn) { width: 100%; }
    .modal-enter-from .app-modal, .modal-leave-to .app-modal { transform: translateY(100%); opacity: 1; }
}
</style>
