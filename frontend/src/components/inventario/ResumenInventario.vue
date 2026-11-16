<template>
  <div class="resumen-inv">
    <section class="resumen-inv__kpis" aria-label="Indicadores del inventario">
      <StatCard class="aparecer" label="Computadoras" :valor="resumen.total" icono="activos" />
      <StatCard class="aparecer" style="animation-delay: 40ms" label="Operativas" :valor="resumen.por_estado?.operativo ?? 0" tono="exito" icono="check" />
      <StatCard class="aparecer" style="animation-delay: 80ms" label="En mantenimiento" :valor="resumen.por_estado?.mantenimiento ?? 0" tono="advertencia" icono="configuracion" />
      <StatCard class="aparecer" style="animation-delay: 120ms" label="Fuera de servicio" :valor="resumen.por_estado?.fuera_servicio ?? 0" tono="riesgo" icono="alerta" />
      <StatCard class="aparecer" style="animation-delay: 160ms" label="Antivirus actualizado" :valor="resumen.antivirus?.actualizado ?? 0" tono="exito" icono="escudo"
        :detalle="detalleAntivirus" />
      <StatCard class="aparecer" style="animation-delay: 200ms" label="Con software por actualizar" :valor="resumen.con_software_pendiente ?? 0" tono="advertencia" icono="descargar"
        :detalle="resumen.so_sin_soporte ? `${resumen.so_sin_soporte} con sistema operativo sin soporte` : ''" />
    </section>

    <div v-if="resumen.total" class="resumen-inv__graficas">
      <AppCard titulo="Estado de los equipos" subtitulo="Distribución actual" icono="activos" class="aparecer">
        <BarChartGenerico :datos="estados" :colores-por-clave="COLORES_ESTADO" etiqueta-accesible="Computadoras por estado operativo" :altura="220" />
      </AppCard>
      <AppCard titulo="Sistemas operativos" subtitulo="Equipos por sistema y versión" icono="capas" class="aparecer">
        <BarChartGenerico :datos="resumen.sistemas_operativos || {}" etiqueta-accesible="Computadoras por sistema operativo" :altura="220" />
      </AppCard>
      <AppCard titulo="Antivirus" subtitulo="Estado de protección" icono="escudo" class="aparecer">
        <BarChartGenerico :datos="antivirus" :colores-por-clave="COLORES_ANTIVIRUS" etiqueta-accesible="Computadoras por estado del antivirus" :altura="220" />
      </AppCard>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import StatCard from '../ui/StatCard.vue';
import AppCard from '../ui/AppCard.vue';
import BarChartGenerico from '../BarChartGenerico.vue';

const props = defineProps({
    resumen: { type: Object, required: true },
    diasAntivirus: { type: Number, default: 7 },
});

// Solo presentación: etiquetas legibles; los conteos vienen tal cual del backend.
const ETIQUETAS_ESTADO = { operativo: 'Operativo', mantenimiento: 'Mantenimiento', fuera_servicio: 'Fuera de servicio', baja: 'Baja' };
const COLORES_ESTADO = { Operativo: '--c-success', Mantenimiento: '--c-amber', 'Fuera de servicio': '--c-risk', Baja: '--c-gray-500' };
const COLORES_ANTIVIRUS = { Actualizado: '--c-success', 'Requiere atención': '--c-risk', 'Sin información': '--c-gray-500' };

const estados = computed(() => Object.fromEntries(
    Object.entries(props.resumen.por_estado || {}).map(([k, v]) => [ETIQUETAS_ESTADO[k] || k, v]),
));
const antivirus = computed(() => ({
    Actualizado: props.resumen.antivirus?.actualizado ?? 0,
    'Requiere atención': props.resumen.antivirus?.desactualizado ?? 0,
    'Sin información': props.resumen.antivirus?.sin_informacion ?? 0,
}));
const detalleAntivirus = computed(() => {
    const a = props.resumen.antivirus || {};
    const partes = [];
    if (a.desactualizado) partes.push(`${a.desactualizado} ${a.desactualizado === 1 ? 'requiere' : 'requieren'} atención`);
    if (a.sin_informacion) partes.push(`${a.sin_informacion} sin información`);
    return partes.length ? partes.join(' · ') : `Actualizado en los últimos ${props.diasAntivirus} días`;
});
</script>

<style scoped>
.resumen-inv__kpis {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); margin-bottom: var(--space-5);
}
@media (min-width: 768px) { .resumen-inv__kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); } }
@media (min-width: 1440px) { .resumen-inv__kpis { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
.resumen-inv__graficas {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: var(--space-5); margin-bottom: var(--space-5);
}
</style>
