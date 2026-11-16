<template>
  <div v-if="datos" class="ia-uso">
    <div class="ia-uso__kpis">
      <StatCard label="Tokens consumidos hoy" :valor="datos.tokens_hoy" icono="tendencia" />
      <StatCard label="Límite diario institucional" :valor="datos.limite_global" icono="escudo" />
      <StatCard label="Límite diario por usuario" :valor="datos.limite_usuario" icono="usuario" />
      <StatCard label="Solicitudes rechazadas (7 días)" :valor="totalRechazos" icono="alerta" tono="advertencia" />
    </div>

    <div class="ia-uso__tablas">
      <AppCard titulo="Uso por día" icono="calendario" subtitulo="Últimos 7 días">
        <EmptyState v-if="!datos.por_dia.length" texto="Sin actividad del asistente en los últimos 7 días." />
        <table v-else class="ia-uso__tabla">
          <thead><tr><th>Día</th><th>Solicitudes</th><th>Tokens</th><th>Errores</th></tr></thead>
          <tbody>
            <tr v-for="d in datos.por_dia" :key="d.dia"><td>{{ d.dia }}</td><td>{{ d.solicitudes }}</td><td>{{ d.tokens.toLocaleString('es-MX') }}</td><td>{{ d.errores }}</td></tr>
          </tbody>
        </table>
      </AppCard>
      <AppCard titulo="Uso por operación" icono="capas">
        <EmptyState v-if="!datos.por_operacion.length" texto="Sin operaciones registradas." />
        <table v-else class="ia-uso__tabla">
          <thead><tr><th>Operación</th><th>Solicitudes</th><th>Tokens</th></tr></thead>
          <tbody>
            <tr v-for="o in datos.por_operacion" :key="o.operacion"><td>{{ ETIQUETAS[o.operacion] || o.operacion }}</td><td>{{ o.solicitudes }}</td><td>{{ o.tokens.toLocaleString('es-MX') }}</td></tr>
          </tbody>
        </table>
        <p v-if="totalRechazos" class="ia-uso__nota">
          Rechazos: {{ datos.rechazos.FUERA_DE_DOMINIO || 0 }} fuera de dominio,
          {{ datos.rechazos.INTENTO_MANIPULACION || 0 }} intentos de manipulación,
          {{ datos.rechazos.ACCION_NO_PERMITIDA || 0 }} acciones no permitidas.
        </p>
      </AppCard>
    </div>
    <p class="ia-uso__nota">Por privacidad, este panel no muestra el contenido de las conversaciones.</p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import iaApi from '../../api/ia';
import AppCard from '../ui/AppCard.vue';
import StatCard from '../ui/StatCard.vue';
import EmptyState from '../ui/EmptyState.vue';

const ETIQUETAS = {
    chat: 'Conversación', clasificacion: 'Clasificación de solicitudes', embedding: 'Indexación / búsqueda (RAG)', extraccion: 'Lectura de PDF',
    analisis_riesgo: 'Análisis de riesgos', analisis_activo: 'Análisis de activos', analisis_incidente: 'Análisis de incidentes',
    analisis_auditoria: 'Análisis de auditorías', analisis_documento: 'Análisis de documentos', analisis_dashboard: 'Resumen ejecutivo',
};
const datos = ref(null);
const totalRechazos = computed(() => Object.values(datos.value?.rechazos || {}).reduce((a, b) => a + Number(b), 0));

onMounted(async () => {
    const { data } = await iaApi.consumo();
    datos.value = data.data;
});
</script>

<style scoped>
.ia-uso { display: flex; flex-direction: column; gap: var(--space-5); }
.ia-uso__kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); }
.ia-uso__tablas { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: var(--space-5); }
.ia-uso__tabla { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
.ia-uso__tabla th { text-align: left; font-size: var(--text-xs); color: var(--text-muted); font-weight: 600; padding: 6px 8px; border-bottom: 1px solid var(--border); }
.ia-uso__tabla td { padding: 8px; border-bottom: 1px solid var(--border); color: var(--text); font-variant-numeric: tabular-nums; }
.ia-uso__nota { margin: var(--space-2) 0 0; font-size: var(--text-xs); color: var(--text-muted); }
</style>
