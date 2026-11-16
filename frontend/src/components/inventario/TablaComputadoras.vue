<template>
  <AppTable :columnas="columnas" :filas="equipos" :cargando="cargando" :paginacion="paginacion" clicable
    @fila-click="(f) => $router.push({ name: 'inventario.computadora', params: { uuid: f.uuid } })"
    @cambiar-pagina="(p) => $emit('cambiar-pagina', p)">
    <template #col-nombre_equipo="{ fila }">
      <div class="equipo-celda">
        <strong>{{ fila.nombre_equipo }}</strong>
        <span v-if="fila.numero_inventario" class="mono">{{ fila.numero_inventario }}</span>
      </div>
    </template>
    <template #col-laboratorio="{ fila }">
      <span v-if="fila.laboratorio">{{ fila.laboratorio.nombre }}<small class="sub"> · {{ fila.laboratorio.edificio }}</small></span>
      <span v-else class="sub">Sin laboratorio</span>
    </template>
    <template #col-estado_operativo="{ fila }">
      <AppBadge :tono="TONO_ESTADO[fila.estado_operativo] || 'neutral'">{{ fila.estado_operativo_label }}</AppBadge>
    </template>
    <template #col-so="{ fila }">
      <span v-if="fila.sistema_operativo.nombre">{{ fila.sistema_operativo.nombre }} {{ fila.sistema_operativo.version || '' }}</span>
      <span v-else class="sub">Sin registrar</span>
      <AppBadge v-if="fila.sistema_operativo.soporte === 'sin_soporte'" tono="riesgo" class="badge-mini">Sin soporte</AppBadge>
    </template>
    <template #col-antivirus="{ fila }">
      <AppBadge :tono="TONO_AV[fila.antivirus.estado]">{{ ETIQUETA_AV[fila.antivirus.estado] }}</AppBadge>
    </template>
    <template #col-software="{ fila }">
      <span>{{ fila.software_total ?? 0 }}</span>
      <AppBadge v-if="fila.software_pendiente" tono="advertencia" class="badge-mini">{{ fila.software_pendiente }} por actualizar</AppBadge>
    </template>
    <template #vacio><slot name="vacio">Sin equipos.</slot></template>
  </AppTable>
</template>

<script setup>
import AppTable from '../ui/AppTable.vue';
import AppBadge from '../ui/AppBadge.vue';

defineProps({
    equipos: { type: Array, default: () => [] },
    cargando: Boolean,
    paginacion: { type: Object, default: null },
    mostrarLaboratorio: { type: Boolean, default: true },
});
defineEmits(['cambiar-pagina']);

const columnas = [
    { key: 'nombre_equipo', label: 'Equipo' },
    { key: 'laboratorio', label: 'Laboratorio' },
    { key: 'estado_operativo', label: 'Estado', width: '150px' },
    { key: 'so', label: 'Sistema operativo' },
    { key: 'antivirus', label: 'Antivirus', width: '150px' },
    { key: 'software', label: 'Programas', width: '150px' },
];
const TONO_ESTADO = { operativo: 'exito', mantenimiento: 'advertencia', fuera_servicio: 'riesgo', baja: 'neutral' };
const TONO_AV = { actualizado: 'exito', desactualizado: 'riesgo', sin_informacion: 'neutral' };
const ETIQUETA_AV = { actualizado: 'Actualizado', desactualizado: 'Requiere atención', sin_informacion: 'Sin información' };
</script>

<style scoped>
.equipo-celda { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mono { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--text-muted); }
.sub { color: var(--text-subtle); font-size: var(--text-xs); }
.badge-mini { margin-left: 6px; }
</style>
