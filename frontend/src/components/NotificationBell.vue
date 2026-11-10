<template>
  <div ref="raiz" class="bell">
    <button class="bell__btn" :class="{ 'bell__btn--activo': abierto }" @click="alAbrir" aria-label="Notificaciones" :aria-expanded="abierto">
      <AppIcon name="campana" :size="20" />
      <span v-if="store.noLeidas > 0" class="bell__contador">{{ store.noLeidas > 99 ? '99+' : store.noLeidas }}</span>
    </button>
    <Transition name="bell-panel">
      <div v-if="abierto" class="bell__panel">
        <header>
          <strong>Notificaciones</strong>
          <button class="bell__marcar-todas" @click="marcarTodas"><AppIcon name="check" :size="14" />Marcar todas leídas</button>
        </header>

        <div class="bell__lista">
          <p v-if="cargando" class="bell__estado"><span class="bell__spinner" aria-hidden="true" />Cargando…</p>
          <p v-else-if="!recientes.length" class="bell__estado bell__estado--vacio">
            <AppIcon name="bandeja" :size="26" />
            Sin notificaciones.
          </p>
          <button
            v-for="n in recientes"
            :key="n.id"
            class="bell__item"
            :class="{ 'bell__item--no-leida': !n.leida }"
            @click="alClicItem(n)"
          >
            <span class="bell__item-icono"><AppIcon name="campana" :size="15" /></span>
            <span class="bell__texto">{{ textoNotificacion(n) }}</span>
            <span class="bell__punto" v-if="!n.leida"></span>
          </button>
        </div>

        <RouterLink :to="{ name: 'notificaciones' }" class="bell__ver-todas" @click="abierto = false">Ver todas <AppIcon name="flecha" :size="15" /></RouterLink>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificacionesStore } from '../stores/notificaciones';
import notificacionesApi from '../api/notificaciones';
import AppIcon from './ui/AppIcon.vue';

const store = useNotificacionesStore();
const router = useRouter();
const abierto = ref(false);
const cargando = ref(false);
const recientes = ref([]);

function textoNotificacion(n) {
    return n.mensaje ?? n.datos?.mensaje ?? n.tipo ?? 'Notificación';
}

async function alAbrir() {
    abierto.value = !abierto.value;
    if (!abierto.value) return;

    cargando.value = true;
    try {
        const { data } = await notificacionesApi.listar({ per_page: 5 });
        recientes.value = data.data ?? [];
    } catch (e) {
        recientes.value = [];
    } finally {
        cargando.value = false;
    }
}

async function alClicItem(n) {
    if (!n.leida) {
        try {
            await notificacionesApi.marcarLeida(n.id);
            n.leida = true;
            await store.refrescarConteo();
        } catch (e) {
            // Si falla marcarla, igual dejamos navegar — no es bloqueante.
        }
    }
    abierto.value = false;
    router.push({ name: 'notificaciones' });
}

async function marcarTodas() {
    await notificacionesApi.marcarTodasLeidas();
    recientes.value = recientes.value.map((n) => ({ ...n, leida: true }));
    await store.refrescarConteo();
}

// Solo presentación: cerrar el panel al hacer clic fuera de él.
const raiz = ref(null);
function alClicFuera(e) {
    if (abierto.value && raiz.value && !raiz.value.contains(e.target)) abierto.value = false;
}
onMounted(() => document.addEventListener('click', alClicFuera));
onBeforeUnmount(() => document.removeEventListener('click', alClicFuera));
</script>

<style scoped>
.bell { position: relative; }
.bell__btn {
    position: relative; display: grid; place-items: center; width: 42px; height: 42px;
    background: none; border: 1px solid transparent; border-radius: var(--radius-md); cursor: pointer; color: var(--text-muted);
    transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast);
}
.bell__btn:hover, .bell__btn--activo { background: var(--bg-subtle); color: var(--c-primary); border-color: var(--border); }
.bell__btn:hover :deep(svg) { animation: campaneo 600ms var(--ease); }
@keyframes campaneo { 0%, 100% { transform: rotate(0); } 25% { transform: rotate(12deg); } 50% { transform: rotate(-10deg); } 75% { transform: rotate(5deg); } }
.bell__contador {
    position: absolute; top: 4px; right: 3px; min-width: 18px; height: 18px; display: grid; place-items: center;
    background: var(--c-risk-solid); color: #fff; border-radius: 999px; border: 2px solid var(--surface);
    font-size: 0.62rem; padding: 0 4px; font-weight: 700; line-height: 1;
}
.bell__panel {
    position: absolute; right: 0; top: calc(100% + 10px); width: 360px; background: var(--surface-raised);
    border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 50; overflow: hidden;
}
.bell__panel header {
    display: flex; justify-content: space-between; align-items: center; gap: var(--space-2);
    padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: var(--text-sm);
}
.bell__marcar-todas {
    display: inline-flex; align-items: center; gap: 4px; background: none; border: none; color: var(--c-signal);
    font-size: var(--text-xs); font-weight: 600; cursor: pointer; padding: 6px 8px; border-radius: var(--radius-sm);
}
.bell__marcar-todas:hover { background: var(--c-primary-soft); }
.bell__lista { display: flex; flex-direction: column; max-height: 320px; overflow-y: auto; padding: 6px; }
.bell__estado {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    font-size: var(--text-sm); color: var(--text-muted); text-align: center; padding: var(--space-5) 0; margin: 0;
}
.bell__estado--vacio { flex-direction: column; color: var(--text-subtle); }
.bell__spinner {
    width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--c-primary-soft); border-top-color: var(--c-primary);
    animation: girar-bell 0.8s linear infinite;
}
@keyframes girar-bell { to { transform: rotate(360deg); } }
.bell__item {
    display: flex; align-items: flex-start; gap: 10px; width: 100%; text-align: left; background: none; border: none;
    padding: 10px; border-radius: var(--radius-md); cursor: pointer; font-size: var(--text-sm); color: var(--text);
    transition: background var(--transition-fast);
}
.bell__item:hover { background: var(--bg-subtle); }
.bell__item-icono {
    display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    background: var(--bg-subtle); color: var(--text-muted);
}
.bell__item--no-leida { font-weight: 600; }
.bell__item--no-leida .bell__item-icono { background: var(--c-primary-soft); color: var(--c-primary); }
.bell__punto { width: 8px; height: 8px; border-radius: 50%; background: var(--c-primary); margin-top: 11px; flex-shrink: 0; margin-left: auto; }
.bell__texto { line-height: 1.4; align-self: center; flex: 1; min-width: 0; overflow-wrap: anywhere; }
.bell__ver-todas {
    display: flex; align-items: center; justify-content: center; gap: 6px; font-size: var(--text-sm); font-weight: 600;
    padding: 12px; border-top: 1px solid var(--border); background: var(--bg-subtle);
}
.bell__ver-todas:hover { text-decoration: none; background: var(--c-primary-soft); }

.bell-panel-enter-active, .bell-panel-leave-active { transition: opacity 180ms var(--ease), transform 180ms var(--ease); }
.bell-panel-enter-from, .bell-panel-leave-to { opacity: 0; transform: translateY(-6px); }

@media (max-width: 640px) {
    .bell__panel {
        position: fixed; left: 12px; right: 12px; width: auto;
        top: calc(var(--topbar-height) + env(safe-area-inset-top, 0px) + 6px);
    }
    .bell__btn { width: 40px; height: 40px; }
}
</style>
