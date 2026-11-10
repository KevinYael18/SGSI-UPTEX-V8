<template>
  <div class="shell" :class="{ 'shell--menu-abierto': ui.menuMovilAbierto }">
    <Sidebar />
    <Transition name="velo">
      <div v-if="ui.menuMovilAbierto" class="shell__velo" aria-hidden="true" @click="ui.cerrarMenuMovil()" />
    </Transition>
    <div class="shell__main">
      <Topbar />
      <main class="shell__contenido">
        <RouterView v-slot="{ Component, route: rutaActual }">
          <Transition name="pagina" mode="out-in">
            <div :key="rutaActual.name" class="shell__pagina">
              <component :is="Component" />
            </div>
          </Transition>
        </RouterView>
      </main>
      <footer class="shell__pie">
        <span>© Universidad Politécnica de Texcoco</span>
        <span class="shell__pie-sep" aria-hidden="true">·</span>
        <span>Sistema de Gestión de Seguridad de la Información — ISO/IEC 27001:2022</span>
      </footer>
    </div>
    <BottomNav />
  </div>
  <AppToastHost />
</template>

<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from './Sidebar.vue';
import Topbar from './Topbar.vue';
import BottomNav from './BottomNav.vue';
import AppToastHost from '../ui/AppToastHost.vue';
import { useUiStore } from '../../stores/ui';
import { useNotificacionesStore } from '../../stores/notificaciones';

const ui = useUiStore();
const notificaciones = useNotificacionesStore();
const route = useRoute();

onMounted(() => {
    ui.aplicarTema();
    notificaciones.iniciarSondeo();
});
onUnmounted(() => notificaciones.detenerSondeo());

// Solo presentación: al navegar se cierra el drawer móvil; Escape también lo cierra.
watch(() => route.fullPath, () => ui.cerrarMenuMovil());
function alPresionarTecla(e) {
    if (e.key === 'Escape' && ui.menuMovilAbierto) ui.cerrarMenuMovil();
}
onMounted(() => document.addEventListener('keydown', alPresionarTecla));
onUnmounted(() => document.removeEventListener('keydown', alPresionarTecla));
</script>

<style scoped>
.shell { display: flex; min-height: 100vh; min-height: 100dvh; }
.shell__main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.shell__contenido {
    flex: 1; width: 100%; max-width: 1600px; margin: 0 auto;
    padding: var(--space-6) var(--page-pad) var(--space-8);
}
.shell__pagina { min-width: 0; }
.shell__pie {
    display: flex; flex-wrap: wrap; justify-content: center; gap: 4px 8px; padding: var(--space-4) var(--page-pad);
    border-top: 1px solid var(--border); color: var(--text-subtle); font-size: 0.72rem; text-align: center;
}

.shell__velo {
    position: fixed; inset: 0; z-index: 80; background: var(--overlay);
    backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px);
}
.velo-enter-active, .velo-leave-active { transition: opacity 280ms var(--ease); }
.velo-enter-from, .velo-leave-to { opacity: 0; }

/* Transición entre pantallas */
.pagina-enter-active { transition: opacity 260ms var(--ease), transform 260ms var(--ease); }
.pagina-leave-active { transition: opacity 120ms ease; }
.pagina-enter-from { opacity: 0; transform: translateY(8px); }
.pagina-leave-to { opacity: 0; }

@media (min-width: 1024px) {
    .shell__velo { display: none; }
}
@media (max-width: 640px) {
    .shell__contenido {
        padding-top: var(--space-5);
        padding-bottom: calc(var(--bottomnav-height) + env(safe-area-inset-bottom, 0px) + var(--space-5));
    }
    .shell__pie { display: none; }
}
</style>
