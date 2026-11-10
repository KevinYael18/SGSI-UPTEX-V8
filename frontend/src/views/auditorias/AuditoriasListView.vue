<template>
  <div>
    <div class="encabezado">
      <h1>Auditorías</h1>
      <AppButton icono="mas" @click="abrirModalCreacion">Planear auditoría</AppButton>
    </div>

    <AppCard v-if="Object.keys(auditoriasPorEstado).length" titulo="Auditorías por estado" icono="reportes" class="panel-grafica">
      <BarChartGenerico :datos="auditoriasPorEstado" :colores="['--c-primary', '--c-gray-400']" :altura="200" etiqueta-accesible="Auditorías agrupadas por estado" />
    </AppCard>

    <AppTable :columnas="columnas" :filas="auditorias" :cargando="cargando" :paginacion="meta" clicable
      @fila-click="(f) => $router.push({ name: 'auditorias.detalle', params: { id: f.id } })"
      @cambiar-pagina="cambiarPagina">
      <template #vacio>
        <EmptyState texto="No hay auditorías planeadas todavía.">
          <template #accion><AppButton tamano="sm" @click="abrirModalCreacion">Planear la primera</AppButton></template>
        </EmptyState>
      </template>
    </AppTable>

    <AppModal :abierto="modalAbierto" titulo="Planear auditoría" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="crear">
        <AppInput v-model="nuevo.alcance" label="Alcance" requerido placeholder="Ej. Controles de acceso — TI" />
        <AppInput v-model="nuevo.fecha_inicio" type="date" label="Fecha de inicio" requerido />
        <AppInput v-model="nuevo.fecha_fin" type="date" label="Fecha de fin (opcional)" />
        <AppSelect v-model="nuevo.auditor_id" label="Auditor" requerido placeholder="Seleccione un auditor…" :opciones="opcionesUsuarios" />
        <AppButton type="submit" :cargando="creando" style="width:100%">Planear</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import auditoriasApi from '../../api/auditorias';
import catalogosApi from '../../api/catalogos';
import dashboardApi from '../../api/dashboard';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppCard from '../../components/ui/AppCard.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import BarChartGenerico from '../../components/BarChartGenerico.vue';

const columnas = [
    { key: 'alcance', label: 'Alcance' },
    { key: 'fecha_inicio', label: 'Inicio', width: '120px' },
    { key: 'fecha_fin', label: 'Fin', width: '120px' },
    { key: 'auditor', label: 'Auditor' },
];
const auditorias = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const modalAbierto = ref(false);
const creando = ref(false);
const toast = useToastStore();
const opcionesUsuarios = ref([]);
const auditoriasPorEstado = ref({});

const valoresIniciales = () => ({ alcance: '', fecha_inicio: '', fecha_fin: '', auditor_id: '' });
const nuevo = reactive(valoresIniciales());

async function abrirModalCreacion() {
    Object.assign(nuevo, valoresIniciales());
    try {
        const { data } = await catalogosApi.usuarios();
        opcionesUsuarios.value = data.data.map((u) => ({ value: u.id, label: `${u.nombre} — ${u.email}` }));
    } catch (e) {
        toast.error('No se pudo cargar el catálogo de usuarios.');
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
        const { data } = await auditoriasApi.listar({ page: pagina.value });
        auditorias.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

async function cargarGrafica() {
    try {
        const { data } = await dashboardApi.graficas();
        auditoriasPorEstado.value = data.data.auditorias_por_estado ?? {};
    } catch (e) {
        // Panel secundario: si falla, no bloquea el listado principal.
    }
}

async function crear() {
    creando.value = true;
    try {
        await auditoriasApi.crear(nuevo);
        toast.exito('Auditoría planeada.');
        modalAbierto.value = false;
        await cargar();
        await cargarGrafica();
    } catch (e) {
        toast.error(e.message);
    } finally {
        creando.value = false;
    }
}

onMounted(() => {
    cargar();
    cargarGrafica();
});
</script>

<style scoped>
.panel-grafica { margin-bottom: var(--space-5); max-width: 520px; }
@media (max-width: 640px) { .panel-grafica { max-width: none; margin-bottom: var(--space-4); } }
</style>
