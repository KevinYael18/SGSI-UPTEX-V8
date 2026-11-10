<template>
  <div>
    <div class="encabezado">
      <h1>Reportes e indicadores</h1>
      <div class="encabezado__acciones">
        <a :href="reportesApi.urlIndicadoresPdf()" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar PDF</a>
        <a :href="reportesApi.urlIndicadoresExcel()" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar Excel</a>
      </div>
    </div>

    <div v-if="cargando" class="cargando">Cargando indicadores…</div>

    <template v-else>
      <div class="kpis">
        <StatCard label="Índice de cumplimiento ISO 27001" :valor="`${datos.indice_cumplimiento}%`" tono="exito" icono="cumplimiento" :progreso="Number(datos.indice_cumplimiento)" />
        <StatCard label="Riesgos críticos" :valor="datos.riesgos_criticos" tono="riesgo" icono="riesgos" />
        <StatCard label="Incidentes abiertos" :valor="datos.incidentes_abiertos" tono="advertencia" icono="incidentes" />
        <StatCard label="Controles implementados" :valor="`${datos.controles_implementados} / ${datos.controles_totales}`" icono="auditorias" />
      </div>

      <div class="paneles">
        <AppCard titulo="Incidentes por mes (últimos 6 meses)" icono="tendencia">
          <BarChartGenerico :datos="datos.incidentes_por_mes" :colores="['--c-primary']" :altura="260" etiqueta-accesible="Incidentes reportados por mes" />
        </AppCard>
        <AppCard titulo="Riesgos por nivel" icono="riesgos">
          <BarChartGenerico :datos="datos.riesgos_por_nivel" :colores="['--c-success', '--c-amber', '--c-orange', '--c-risk']" :colores-por-clave="{ bajo: '--c-success', medio: '--c-amber', alto: '--c-orange', critico: '--c-risk' }" :altura="260" etiqueta-accesible="Riesgos agrupados por nivel" />
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
import AppIcon from '../../components/ui/AppIcon.vue';
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
/* Estilos de encabezado, KPIs y paneles: assets/styles/paginas.css */
</style>
