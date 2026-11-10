<template>
  <div>
    <div class="encabezado">
      <h1>Incidentes</h1>
      <AppButton icono="mas" @click="abrirModalCreacion">Reportar incidente</AppButton>
    </div>

    <div class="filtros">
      <AppSelect
        v-model="filtroEstado"
        placeholder="Todos los estados"
        :opciones="opcionesEstado"
        @update:modelValue="() => { pagina = 1; cargar(); }"
      />
    </div>

    <AppTable :columnas="columnas" :filas="incidentes" :cargando="cargando" :paginacion="meta" clicable
      @fila-click="(f) => $router.push({ name: 'incidentes.detalle', params: { uuid: f.uuid } })"
      @cambiar-pagina="cambiarPagina">
      <template #col-estado="{ fila }"><AppBadge :tono="tonoEstado(fila.estado)">{{ fila.estado }}</AppBadge></template>
      <template #vacio>
        <EmptyState texto="No hay incidentes reportados con este filtro.">
          <template #accion><AppButton tamano="sm" @click="abrirModalCreacion">Reportar el primero</AppButton></template>
        </EmptyState>
      </template>
    </AppTable>

    <AppModal :abierto="modalAbierto" titulo="Reportar incidente" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="crear">
        <AppInput v-model="nuevo.titulo" label="Título" requerido />
        <AppSelect
          v-model="nuevo.activo_id"
          label="Activo afectado (opcional)"
          placeholder="Sin activo asociado"
          :opciones="opcionesActivos"
        />
        <label class="form__campo">
          <span>Descripción</span>
          <textarea v-model="nuevo.descripcion" rows="3" />
        </label>
        <AppInput v-model="nuevo.fecha_deteccion" type="datetime-local" label="Fecha de detección" requerido />
        <label class="form__campo">
          <span>Evidencia (opcional — imagen, PDF o video)</span>
          <input type="file" accept=".jpg,.jpeg,.png,.pdf,.mp4" @change="onArchivo" />
        </label>
        <AppButton type="submit" :cargando="creando" style="width:100%">Reportar</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import incidentesApi from '../../api/incidentes';
import catalogosApi from '../../api/catalogos';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const columnas = [
    { key: 'titulo', label: 'Título' },
    { key: 'activo', label: 'Activo' },
    { key: 'reportado_por', label: 'Reportado por' },
    { key: 'estado', label: 'Estado', width: '130px' },
];
const opcionesEstado = ['abierto', 'en_analisis', 'contenido', 'resuelto', 'cerrado'].map((v) => ({ value: v, label: v }));

const incidentes = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const modalAbierto = ref(false);
const creando = ref(false);
const filtroEstado = ref('');
const toast = useToastStore();
const opcionesActivos = ref([]);

const valoresIniciales = () => ({ titulo: '', activo_id: '', descripcion: '', fecha_deteccion: '', evidencia: null });
const nuevo = reactive(valoresIniciales());

function tonoEstado(e) {
    return { abierto: 'riesgo', en_analisis: 'advertencia', contenido: 'advertencia', resuelto: 'signal', cerrado: 'exito' }[e] || 'neutral';
}
function onArchivo(e) { nuevo.evidencia = e.target.files[0] || null; }

async function abrirModalCreacion() {
    Object.assign(nuevo, valoresIniciales());

    try {
        const { data } = await catalogosApi.activos();
        opcionesActivos.value = data.data.map((a) => ({ value: a.id, label: a.nombre }));
    } catch (e) {
        toast.error('No se pudo cargar el catálogo de activos.');
    }

    modalAbierto.value = true;
}

function cambiarPagina(nuevaPagina) {
    pagina.value = nuevaPagina;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await incidentesApi.listar({
            ...(filtroEstado.value ? { estado: filtroEstado.value } : {}),
            page: pagina.value,
        });
        incidentes.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

async function crear() {
    creando.value = true;
    try {
        const fd = new FormData();
        Object.entries(nuevo).forEach(([k, v]) => { if (v !== null && v !== '') fd.append(k, v); });
        await incidentesApi.crear(fd);
        toast.exito('Incidente reportado.');
        modalAbierto.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        creando.value = false;
    }
}

onMounted(cargar);
</script>

<style scoped>
/* Estilos de encabezado, filtros y formularios: assets/styles/paginas.css */
</style>
