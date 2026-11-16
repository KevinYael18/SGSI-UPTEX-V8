<template>
  <div>
    <h1>Cumplimiento ISO/IEC 27001:2022</h1>
    <div v-if="resumen" class="resumen">
      <div class="resumen__dato">
        <span class="resumen__etiqueta">Cumplimiento general</span>
        <strong class="resumen__valor">{{ resumen.porcentaje_general }}%</strong>
      </div>
      <div class="resumen__barra" role="progressbar" :aria-valuenow="resumen.porcentaje_general" aria-valuemin="0" aria-valuemax="100">
        <span :style="{ width: `${Math.min(100, Number(resumen.porcentaje_general) || 0)}%` }" />
      </div>
    </div>

    <div class="filtros">
      <AppSelect v-model="filtroCategoria" placeholder="Todas las categorías" :opciones="opcionesCategoria" @update:modelValue="cargar" />
      <AppSelect v-model="filtroEstado" placeholder="Todos los estados" :opciones="opcionesEstado" @update:modelValue="cargar" />
    </div>

    <AppTable :columnas="columnas" :filas="controles" :cargando="cargando">
      <template #col-categoria="{ fila }"><AppBadge tono="signal">{{ etiquetaCategoria(fila.categoria) }}</AppBadge></template>
      <template #col-estado_implementacion="{ fila }">
        <AppSelect
          :model-value="fila.estado_implementacion"
          :opciones="opcionesEstado"
          @update:modelValue="(v) => actualizar(fila, v)"
        />
      </template>
    </AppTable>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import cumplimientoApi from '../../api/cumplimiento';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppSelect from '../../components/ui/AppSelect.vue';

const columnas = [
    { key: 'codigo', label: 'Código', width: '90px' },
    { key: 'categoria', label: 'Categoría', width: '140px' },
    { key: 'nombre', label: 'Control' },
    { key: 'estado_implementacion', label: 'Estado', width: '180px' },
];
const opcionesCategoria = [
    { value: 'organizacional', label: 'Organizacional' },
    { value: 'personas', label: 'Personas' },
    { value: 'fisico', label: 'Físico' },
    { value: 'tecnologico', label: 'Tecnológico' },
];
const opcionesEstado = [
    { value: 'no_implementado', label: 'No implementado' },
    { value: 'en_proceso', label: 'En proceso' },
    { value: 'implementado', label: 'Implementado' },
    { value: 'no_aplica', label: 'No aplica' },
];

const controles = ref([]);
const cargando = ref(false);
const resumen = ref(null);
const filtroCategoria = ref('');
const filtroEstado = ref('');
const toast = useToastStore();

function etiquetaCategoria(c) { return opcionesCategoria.find((o) => o.value === c)?.label || c; }

async function cargar() {
    cargando.value = true;
    try {
        const params = {};
        if (filtroCategoria.value) params.categoria = filtroCategoria.value;
        if (filtroEstado.value) params.estado_implementacion = filtroEstado.value;
        const [{ data: listaRes }, { data: resumenRes }] = await Promise.all([
            cumplimientoApi.listarControles({ ...params, por_pagina: 100 }),
            cumplimientoApi.resumen(),
        ]);
        controles.value = listaRes.data;
        resumen.value = resumenRes.data;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

async function actualizar(fila, nuevoEstado) {
    try {
        await cumplimientoApi.actualizarEstado(fila.id, nuevoEstado);
        toast.exito(`${fila.codigo} actualizado.`);
        await cargar();
    } catch (e) {
        toast.error(e.message);
    }
}

onMounted(cargar);
</script>

<style scoped>
.resumen {
    display: flex; align-items: center; gap: var(--space-5); flex-wrap: wrap;
    padding: var(--space-4) var(--space-5); margin-bottom: var(--space-4);
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
}
.resumen__dato { display: flex; flex-direction: column; }
.resumen__etiqueta { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); }
.resumen__valor { font-family: var(--font-display); font-size: 1.9rem; color: var(--c-success); line-height: 1.15; }
.resumen__barra { flex: 1; min-width: 180px; height: 10px; border-radius: 999px; background: var(--bg-subtle); overflow: hidden; }
.resumen__barra span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--c-primary), var(--c-lime)); transition: width 900ms var(--ease);
    /* Solo presentación: se llena desde 0 al aparecer (el ancho real no cambia). */
    transform-origin: left center; animation: barra-llenar 1000ms var(--ease-salida, var(--ease)) 120ms backwards; }
@keyframes barra-llenar { from { transform: scaleX(0); } }
:deep(.app-table td .app-field) { min-width: 170px; }
@media (max-width: 720px) { :deep(.app-table td .app-field) { min-width: 0; } }
</style>
