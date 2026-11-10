<template>
  <div>
    <div class="encabezado">
      <h1>Reportes e indicadores</h1>
      <div class="encabezado__acciones">
        <a :href="reportesApi.urlIndicadoresPdf()" target="_blank" class="boton-descarga">Descargar PDF</a>
        <a :href="reportesApi.urlIndicadoresExcel()" target="_blank" class="boton-descarga">Descargar Excel</a>
      </div>
    </div>

    <div v-if="cargando" class="cargando">Cargando indicadores…</div>

    <template v-else>
      <div class="kpis">
        <StatCard label="Índice de cumplimiento ISO 27001" :valor="`${datos.indice_cumplimiento}%`" />
        <StatCard label="Riesgos críticos" :valor="datos.riesgos_criticos" tono="riesgo" />
        <StatCard label="Incidentes abiertos" :valor="datos.incidentes_abiertos" tono="advertencia" />
        <StatCard label="Controles implementados" :valor="`${datos.controles_implementados} / ${datos.controles_totales}`" />
      </div>

      <div class="paneles">
        <AppCard titulo="Incidentes por mes (últimos 6 meses)">
          <BarChartGenerico :datos="datos.incidentes_por_mes" :colores="['#14508C']" etiqueta-accesible="Incidentes reportados por mes" />
        </AppCard>
        <AppCard titulo="Riesgos por nivel">
          <BarChartGenerico :datos="datos.riesgos_por_nivel" :colores="['#16A34A', '#D97706', '#EA580C', '#DC2626']" etiqueta-accesible="Riesgos agrupados por nivel" />
        </AppCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import indicadoresApi from '../../api/indicadores';
import reportesApi from '../../api/reportes';
import { useToastStore } from '../../stores/toast';
import StatCard from '../../components/ui/StatCard.vue';
import AppCard from '../../components/ui/AppCard.vue';
import BarChartGenerico from '../../components/BarChartGenerico.vue';

const toast = useToastStore();
const cargando = ref(true);
const datos = ref({});

onMounted(async () => {
    try {
        const { data } = await indicadoresApi.resumen();
        datos.value = data.data;
    } catch (e) {
        toast.error(e.message || 'No se pudieron cargar los indicadores.');
    } finally {
        cargando.value = false;
    }
});
</script>

<style scoped>
.encabezado { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); }
.encabezado__acciones { display: flex; gap: var(--space-3); }
.boton-descarga {
    font-size: var(--text-sm); color: var(--c-signal); font-weight: 600; padding: 8px 12px;
    border: 1px solid var(--border); border-radius: var(--radius-sm);
}
.boton-descarga:hover { background: var(--bg); text-decoration: none; }
.cargando { color: var(--text-muted); padding: var(--space-6) 0; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-5); }
.paneles { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--space-5); align-items: start; }
</style>