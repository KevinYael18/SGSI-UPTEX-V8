<template>
  <div>
    <div class="encabezado">
      <div>
        <h1>Bitácora del sistema</h1>
        <p class="subtitulo">Historial completo de creación/edición/eliminación en todos los módulos.</p>
      </div>
      <div class="encabezado__acciones">
        <a :href="urlPdf" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar PDF</a>
        <a :href="urlExcel" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar Excel</a>
      </div>
    </div>

    <div class="filtros">
      <AppSelect v-model="filtros.entidad" placeholder="Todas las entidades" :opciones="opcionesEntidad" @update:modelValue="() => { pagina = 1; cargar(); }" />
      <AppSelect v-model="filtros.accion" placeholder="Todas las acciones" :opciones="opcionesAccion" @update:modelValue="() => { pagina = 1; cargar(); }" />
      <AppInput v-model="filtros.desde" type="date" label="" placeholder="Desde" @update:modelValue="() => { pagina = 1; cargar(); }" />
      <AppInput v-model="filtros.hasta" type="date" label="" placeholder="Hasta" @update:modelValue="() => { pagina = 1; cargar(); }" />
    </div>

    <AppTable :columnas="columnas" :filas="registros" :cargando="cargando" :paginacion="meta" @cambiar-pagina="cambiarPagina">
      <template #col-accion="{ fila }">
        <AppBadge :tono="tonoAccion(fila.accion)">{{ etiquetaAccion(fila.accion) }}</AppBadge>
      </template>
      <template #col-entidad="{ fila }">
        <span class="entidad">{{ fila.entidad }} #{{ fila.entidad_id }}</span>
      </template>
      <template #col-creado_en="{ fila }">
        {{ new Date(fila.creado_en).toLocaleString('es-MX') }}
      </template>
      <template #vacio>
        <EmptyState texto="Sin actividad registrada con estos filtros." />
      </template>
    </AppTable>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import bitacoraApi from '../../api/bitacora';
import http from '../../api/http';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const columnas = [
    { key: 'entidad', label: 'Entidad' },
    { key: 'accion', label: 'Acción', width: '120px' },
    { key: 'usuario', label: 'Usuario' },
    { key: 'creado_en', label: 'Fecha', width: '180px' },
];

const opcionesAccion = [
    { value: 'create', label: 'Creación' },
    { value: 'update', label: 'Actualización' },
    { value: 'delete', label: 'Eliminación' },
    { value: 'restore', label: 'Restauración' },
];

const toast = useToastStore();
const registros = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const opcionesEntidad = ref([]);

const filtros = reactive({ entidad: '', accion: '', desde: '', hasta: '' });

function queryActual() {
    const params = new URLSearchParams();
    if (filtros.entidad) params.set('entidad', filtros.entidad);
    if (filtros.accion) params.set('accion', filtros.accion);
    if (filtros.desde) params.set('desde', filtros.desde);
    if (filtros.hasta) params.set('hasta', filtros.hasta);
    return params.toString();
}
const urlPdf = computed(() => `${http.defaults.baseURL}/v1/bitacora.pdf?${queryActual()}`);
const urlExcel = computed(() => `${http.defaults.baseURL}/v1/bitacora.xlsx?${queryActual()}`);

function tonoAccion(a) {
    return { create: 'exito', update: 'advertencia', delete: 'riesgo', restore: 'signal' }[a] || 'neutral';
}
function etiquetaAccion(a) {
    return opcionesAccion.find((o) => o.value === a)?.label || a;
}

async function cargarEntidades() {
    try {
        const { data } = await bitacoraApi.entidadesDisponibles();
        opcionesEntidad.value = data.data.map((e) => ({ value: e, label: e }));
    } catch (e) {
        // Filtro secundario: si falla, el listado principal sigue funcionando.
    }
}

function cambiarPagina(nuevaPagina) {
    pagina.value = nuevaPagina;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const params = { page: pagina.value };
        if (filtros.entidad) params.entidad = filtros.entidad;
        if (filtros.accion) params.accion = filtros.accion;
        if (filtros.desde) params.desde = filtros.desde;
        if (filtros.hasta) params.hasta = filtros.hasta;

        const { data } = await bitacoraApi.listar(params);
        registros.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

onMounted(() => {
    cargar();
    cargarEntidades();
});
</script>

<style scoped>
.encabezado { align-items: flex-start; }
.subtitulo { margin: var(--space-2) 0 0; }
.entidad { text-transform: capitalize; font-weight: 500; }
</style>
