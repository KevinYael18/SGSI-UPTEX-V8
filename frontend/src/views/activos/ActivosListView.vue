<template>
  <div>
    <div class="encabezado">
      <h1>Activos</h1>
      <div class="encabezado__acciones">
        <a :href="reportesApi.urlActivosPdf()" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar PDF</a>
        <a :href="reportesApi.urlActivosExcel()" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar Excel</a>
        <AppButton icono="mas" @click="abrirModalCreacion">Nuevo activo</AppButton>
      </div>
    </div>

    <div class="filtros">
      <AppSelect v-model="filtros.tipo" placeholder="Todos los tipos" :opciones="opcionesTipo" @update:modelValue="() => { pagina = 1; cargar(); }" />
      <input v-model="filtros.busqueda" class="filtros__buscar" placeholder="Buscar por nombre…" @input="buscarDebounced" />
    </div>

    <AppTable
      :columnas="columnas"
      :filas="activos"
      :cargando="cargando"
      :paginacion="meta"
      clicable
      @fila-click="(f) => $router.push({ name: 'activos.detalle', params: { uuid: f.uuid } })"
      @cambiar-pagina="cambiarPagina"
    >
      <template #col-tipo="{ fila }"><AppBadge tono="signal">{{ etiquetaTipo(fila.tipo) }}</AppBadge></template>
      <template #col-criticidad_calculada="{ fila }">
        <AppBadge :tono="fila.criticidad_calculada >= 4 ? 'riesgo' : fila.criticidad_calculada === 3 ? 'advertencia' : 'exito'">
          {{ fila.criticidad_calculada }}
        </AppBadge>
      </template>
      <template #vacio>
        <EmptyState texto="Todavía no hay activos registrados.">
          <template #accion><AppButton tamano="sm" @click="abrirModalCreacion">Registrar el primero</AppButton></template>
        </EmptyState>
      </template>
    </AppTable>

    <AppModal :abierto="modalAbierto" titulo="Nuevo activo" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="crear">
        <AppInput v-model="nuevo.nombre" label="Nombre" requerido placeholder="Ej. Servidor de Base de Datos Académica" />
        <AppSelect v-model="nuevo.tipo" label="Tipo" requerido :opciones="opcionesTipo" placeholder="Seleccione…" />

        <div class="form__cia">
          <AppInput v-model.number="nuevo.confidencialidad" type="number" min="1" max="5" label="Confidencialidad (1-5)" requerido />
          <AppInput v-model.number="nuevo.integridad" type="number" min="1" max="5" label="Integridad (1-5)" requerido />
          <AppInput v-model.number="nuevo.disponibilidad" type="number" min="1" max="5" label="Disponibilidad (1-5)" requerido />
        </div>

        <AppSelect
          v-model="nuevo.departamento_id"
          label="Departamento"
          requerido
          placeholder="Seleccione un departamento…"
          :opciones="opcionesDepartamentos"
          @update:modelValue="onCambiarDepartamento"
        />

        <AppSelect
          v-model="nuevo.ubicacion_id"
          label="Ubicación (opcional)"
          :placeholder="nuevo.departamento_id ? 'Sin ubicación específica' : 'Seleccione primero un departamento'"
          :opciones="opcionesUbicaciones"
          :disabled="!nuevo.departamento_id"
        />

        <AppSelect
          v-model="nuevo.responsable_id"
          label="Responsable (opcional)"
          placeholder="Sin responsable asignado"
          :opciones="opcionesUsuarios"
        />

        <AppButton type="submit" :cargando="creando" style="width:100%">Crear activo</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import activosApi from '../../api/activos';
import catalogosApi from '../../api/catalogos';
import reportesApi from '../../api/reportes';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const columnas = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'tipo', label: 'Tipo', width: '140px' },
    { key: 'departamento', label: 'Departamento' },
    { key: 'responsable', label: 'Responsable' },
    { key: 'criticidad_calculada', label: 'Criticidad', width: '100px' },
];

const opcionesTipo = [
    { value: 'hardware', label: 'Hardware' },
    { value: 'software', label: 'Software' },
    { value: 'informacion', label: 'Información' },
    { value: 'servicio', label: 'Servicio' },
    { value: 'infraestructura', label: 'Infraestructura' },
];

function etiquetaTipo(valor) {
    return opcionesTipo.find((o) => o.value === valor)?.label || valor;
}

const activos = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const modalAbierto = ref(false);
const creando = ref(false);
const toast = useToastStore();
const filtros = reactive({ tipo: '', busqueda: '' });

const valoresIniciales = () => ({
    nombre: '',
    tipo: '',
    confidencialidad: 3,
    integridad: 3,
    disponibilidad: 3,
    departamento_id: '',
    ubicacion_id: '',
    responsable_id: '',
});
const nuevo = reactive(valoresIniciales());

const opcionesDepartamentos = ref([]);
const opcionesUsuarios = ref([]);
const opcionesUbicaciones = ref([]);

async function cargarCatalogos() {
    try {
        const [{ data: depRes }, { data: usrRes }] = await Promise.all([
            catalogosApi.departamentos(),
            catalogosApi.usuarios(),
        ]);
        opcionesDepartamentos.value = depRes.data.map((d) => ({ value: d.id, label: `${d.nombre} (${d.clave})` }));
        opcionesUsuarios.value = usrRes.data.map((u) => ({ value: u.id, label: `${u.nombre} — ${u.email}` }));
    } catch (e) {
        toast.error('No se pudieron cargar los catálogos de departamentos/usuarios.');
    }
}

async function onCambiarDepartamento(departamentoId) {
    nuevo.ubicacion_id = '';
    opcionesUbicaciones.value = [];
    if (!departamentoId) return;

    try {
        const { data } = await catalogosApi.ubicaciones(departamentoId);
        opcionesUbicaciones.value = data.data.map((u) => ({ value: u.id, label: u.nombre }));
    } catch (e) {
        toast.error('No se pudieron cargar las ubicaciones de ese departamento.');
    }
}

function abrirModalCreacion() {
    Object.assign(nuevo, valoresIniciales());
    opcionesUbicaciones.value = [];
    modalAbierto.value = true;
}

let timeoutId = null;
function buscarDebounced() {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => { pagina.value = 1; cargar(); }, 350);
}

function cambiarPagina(nuevaPagina) {
    pagina.value = nuevaPagina;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await activosApi.listar({ ...filtros, page: pagina.value });
        activos.value = data.data;
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
        const payload = { ...nuevo };
        if (!payload.ubicacion_id) delete payload.ubicacion_id;
        if (!payload.responsable_id) delete payload.responsable_id;

        await activosApi.crear(payload);
        toast.exito('Activo creado.');
        modalAbierto.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        creando.value = false;
    }
}

onMounted(() => {
    cargar();
    cargarCatalogos();
});
</script>

<style scoped>
/* Estilos de encabezado, filtros, descargas y formularios: assets/styles/paginas.css */
</style>
