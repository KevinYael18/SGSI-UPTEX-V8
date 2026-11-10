<template>
  <div>
    <div class="encabezado">
      <h1>Notificaciones</h1>
      <AppButton tamano="sm" variante="secundaria" icono="check" @click="marcarTodas">Marcar todas leídas</AppButton>
    </div>

    <ul class="lista">
      <li v-for="n in notificaciones" :key="n.id" class="item" :class="{ 'item--no-leida': !n.leida }" @click="marcarLeida(n)">
        <span class="icono"><AppIcon name="campana" :size="18" /></span>
        <div class="contenido">
          <p>{{ n.datos.mensaje || n.tipo }}</p>
          <time>{{ new Date(n.creada_en).toLocaleString('es-MX') }}</time>
        </div>
        <span class="punto" v-if="!n.leida" aria-label="No leída" />
      </li>
    </ul>
    <EmptyState v-if="!cargando && !notificaciones.length" texto="No tienes notificaciones." />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import notificacionesApi from '../../api/notificaciones';
import { useNotificacionesStore } from '../../stores/notificaciones';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const notificaciones = ref([]);
const cargando = ref(false);
const store = useNotificacionesStore();

async function cargar() {
    cargando.value = true;
    const { data } = await notificacionesApi.listar();
    notificaciones.value = data.data;
    cargando.value = false;
}

async function marcarLeida(n) {
    if (n.leida) return;
    await notificacionesApi.marcarLeida(n.id);
    n.leida = true;
    await store.refrescarConteo();
}

async function marcarTodas() {
    await notificacionesApi.marcarTodasLeidas();
    notificaciones.value.forEach((n) => (n.leida = true));
    await store.refrescarConteo();
}

onMounted(cargar);
</script>

<style scoped>
.lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--space-2); }
.item {
    display: flex; gap: var(--space-3); align-items: center; padding: var(--space-4); min-height: 64px;
    border-radius: var(--radius-lg); background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-sm);
    cursor: pointer; transition: border-color var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
}
.item:hover { border-color: var(--border-strong); box-shadow: var(--shadow-md); transform: translateY(-1px); }
.icono {
    display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    background: var(--bg-subtle); color: var(--text-muted);
}
.contenido { flex: 1; min-width: 0; }
.item--no-leida { background: var(--c-primary-soft); border-color: color-mix(in srgb, var(--c-primary) 25%, transparent); }
.item--no-leida .icono { background: var(--surface); color: var(--c-primary); }
.item--no-leida p { font-weight: 600; }
.item p { margin: 0 0 2px; font-size: var(--text-sm); overflow-wrap: anywhere; }
.item time { font-size: var(--text-xs); color: var(--text-muted); }
.punto { width: 10px; height: 10px; border-radius: 50%; background: var(--c-primary); flex-shrink: 0; box-shadow: 0 0 0 4px var(--c-primary-soft); }
</style>
