<template>
  <div>
    <div class="encabezado">
      <h1>Riesgos</h1>
      <div class="encabezado__acciones">
        <a :href="reportesApi.urlRiesgosPdf()" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar PDF</a>
        <a :href="reportesApi.urlRiesgosExcel()" target="_blank" class="boton-descarga"><AppIcon name="descargar" :size="17" />Descargar Excel</a>
        <AppButton icono="mas" @click="abrirModalCreacion">Registrar riesgo</AppButton>
      </div>
    </div>

    <div class="paneles" v-if="matriz.length || Object.keys(riesgosPorNivel).length">
      <AppCard v-if="matriz.length" titulo="Matriz de riesgo (probabilidad × impacto)" icono="riesgos">
        <RiskMatrix :celdas="matriz" />
      </AppCard>
      <AppCard v-if="Object.keys(riesgosPorNivel).length" titulo="Riesgos por nivel" icono="reportes">
        <BarChartGenerico :datos="riesgosPorNivel" :colores="['--c-success', '--c-amber', '--c-orange', '--c-risk']" :colores-por-clave="COLORES_NIVEL" :altura="280" etiqueta-accesible="Riesgos agrupados por nivel" />
      </AppCard>
    </div>

    <div class="filtros">
      <AppSelect
        v-model="filtroNivel"
        placeholder="Todos los niveles"
        :opciones="[{value:'bajo',label:'Bajo'},{value:'medio',label:'Medio'},{value:'alto',label:'Alto'},{value:'critico',label:'Crítico'}]"
        @update:modelValue="() => { pagina = 1; cargar(); }"
      />
    </div>

    <AppTable
      :columnas="columnas"
      :filas="riesgos"
      :cargando="cargando"
      :paginacion="meta"
      clicable
      @fila-click="verDetalle"
      @cambiar-pagina="cambiarPagina"
    >
      <template #col-nivel_calculado="{ fila }">
        <AppBadge :tono="tonoNivel(fila.nivel_calculado)">{{ fila.nivel_calculado || 'sin calcular' }}</AppBadge>
      </template>
      <template #col-tipo_tratamiento="{ fila }">
        <span v-if="fila.tipo_tratamiento">{{ fila.tipo_tratamiento }}</span>
        <AppBadge v-else tono="advertencia">Sin definir</AppBadge>
      </template>
      <template #vacio>
        <EmptyState texto="No hay riesgos registrados con este filtro.">
          <template #accion><AppButton tamano="sm" @click="abrirModalCreacion">Registrar el primero</AppButton></template>
        </EmptyState>
      </template>
    </AppTable>

    <AppModal :abierto="detalleAbierto" titulo="Detalle del riesgo" @cerrar="detalleAbierto = false">
      <div v-if="riesgoSeleccionado" class="detalle">
        <dl class="detalle__ficha">
          <div><dt>Activo</dt><dd>{{ riesgoSeleccionado.activo }}</dd></div>
          <div><dt>Amenaza</dt><dd>{{ riesgoSeleccionado.amenaza }}</dd></div>
          <div><dt>Vulnerabilidad</dt><dd>{{ riesgoSeleccionado.vulnerabilidad }}</dd></div>
          <div><dt>Nivel</dt><dd><AppBadge :tono="tonoNivel(riesgoSeleccionado.nivel_calculado)">{{ riesgoSeleccionado.nivel_calculado ?? '—' }}</AppBadge></dd></div>
          <div><dt>Tratamiento</dt><dd>{{ riesgoSeleccionado.tipo_tratamiento ?? 'Sin definir' }}</dd></div>
        </dl>

        <div class="detalle__acciones" v-if="puedeEditar || puedeEliminar">
          <AppButton v-if="puedeEditar" tamano="sm" variante="secundaria" icono="editar" @click="abrirModalEdicion">Editar</AppButton>
          <AppButton v-if="puedeEliminar" tamano="sm" variante="peligro" icono="papelera" @click="confirmarEliminar = true">Eliminar</AppButton>
        </div>

        <CommentsPanel tipo="riesgos" :id="riesgoSeleccionado.id" />
        <TimelinePanel tipo="riesgos" :id="riesgoSeleccionado.id" />
      </div>
    </AppModal>

    <AppModal :abierto="modalAbierto" titulo="Registrar riesgo" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="crear">
        <AppSelect v-model="nuevo.activo_id" label="Activo" requerido placeholder="Seleccione un activo…" :opciones="opcionesActivos" />
        <AppSelect v-model="nuevo.amenaza_id" label="Amenaza" requerido placeholder="Seleccione una amenaza…" :opciones="opcionesAmenazas" />
        <AppSelect v-model="nuevo.vulnerabilidad_id" label="Vulnerabilidad" requerido placeholder="Seleccione una vulnerabilidad…" :opciones="opcionesVulnerabilidades" />

        <div class="form__grid">
          <AppInput v-model.number="nuevo.probabilidad" type="number" min="1" max="5" label="Probabilidad (1-5)" />
          <AppInput v-model.number="nuevo.impacto" type="number" min="1" max="5" label="Impacto (1-5)" />
        </div>
        <p v-if="impactoSugeridoNota" class="form__nota">{{ impactoSugeridoNota }}</p>
        <AppSelect
          v-model="nuevo.tipo_tratamiento"
          label="Tratamiento (opcional)"
          placeholder="Sin definir todavía"
          :opciones="opcionesTratamiento"
        />
        <p class="form__nota">El nivel (bajo/medio/alto/crítico) se calcula automáticamente contra la matriz de riesgo configurada.</p>
        <AppButton type="submit" :cargando="creando" style="width:100%">Registrar</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="modalEdicion" titulo="Editar riesgo" @cerrar="modalEdicion = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppSelect v-model="edicion.activo_id" label="Activo" requerido :opciones="opcionesActivos" />
        <AppSelect v-model="edicion.amenaza_id" label="Amenaza" requerido :opciones="opcionesAmenazas" />
        <AppSelect v-model="edicion.vulnerabilidad_id" label="Vulnerabilidad" requerido :opciones="opcionesVulnerabilidades" />

        <div class="form__grid">
          <AppInput v-model.number="edicion.probabilidad" type="number" min="1" max="5" label="Probabilidad (1-5)" requerido />
          <AppInput v-model.number="edicion.impacto" type="number" min="1" max="5" label="Impacto (1-5)" requerido />
        </div>
        <AppSelect
          v-model="edicion.tipo_tratamiento"
          label="Tratamiento (opcional)"
          placeholder="Sin definir todavía"
          :opciones="opcionesTratamiento"
        />
        <AppButton type="submit" :cargando="guardando" style="width:100%">Guardar cambios</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="confirmarEliminar" titulo="Eliminar riesgo" @cerrar="confirmarEliminar = false">
      <p>¿Seguro que deseas eliminar este riesgo? Esta acción no se puede deshacer.</p>
      <template #footer>
        <AppButton variante="secundaria" @click="confirmarEliminar = false">Cancelar</AppButton>
        <AppButton variante="peligro" :cargando="eliminando" @click="eliminar">Sí, eliminar</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import riesgosApi from '../../api/riesgos';
import catalogosApi from '../../api/catalogos';
import dashboardApi from '../../api/dashboard';
import reportesApi from '../../api/reportes';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppCard from '../../components/ui/AppCard.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import CommentsPanel from '../../components/CommentsPanel.vue';
import TimelinePanel from '../../components/TimelinePanel.vue';
import RiskMatrix from '../../components/RiskMatrix.vue';
import BarChartGenerico from '../../components/BarChartGenerico.vue';

const columnas = [
    { key: 'activo', label: 'Activo' },
    { key: 'amenaza', label: 'Amenaza' },
    { key: 'vulnerabilidad', label: 'Vulnerabilidad' },
    { key: 'nivel_calculado', label: 'Nivel', width: '120px' },
    { key: 'tipo_tratamiento', label: 'Tratamiento', width: '140px' },
];

const opcionesTratamiento = [
    { value: 'mitigar', label: 'Mitigar' },
    { value: 'transferir', label: 'Transferir' },
    { value: 'evitar', label: 'Evitar' },
    { value: 'aceptar', label: 'Aceptar' },
];

const auth = useAuthStore();
const toast = useToastStore();

const riesgos = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const filtroNivel = ref('');
const matriz = ref([]);
const riesgosPorNivel = ref({});

const modalAbierto = ref(false);
const creando = ref(false);
const detalleAbierto = ref(false);
const riesgoSeleccionado = ref(null);
const modalEdicion = ref(false);
const guardando = ref(false);
const confirmarEliminar = ref(false);
const eliminando = ref(false);

const opcionesActivos = ref([]);
const opcionesAmenazas = ref([]);
const opcionesVulnerabilidades = ref([]);

async function cargarCatalogos() {
    try {
        const [{ data: actRes }, { data: amRes }, { data: vulRes }] = await Promise.all([
            catalogosApi.activos(),
            catalogosApi.amenazas(),
            catalogosApi.vulnerabilidades(),
        ]);
        opcionesActivos.value = actRes.data.map((a) => ({ value: a.id, label: a.nombre }));
        opcionesAmenazas.value = amRes.data.map((a) => ({ value: a.id, label: a.nombre }));
        opcionesVulnerabilidades.value = vulRes.data.map((v) => ({ value: v.id, label: v.nombre }));
    } catch (e) {
        toast.error('No se pudieron cargar los catálogos de activos/amenazas/vulnerabilidades.');
    }
}

const valoresIniciales = () => ({
    activo_id: '', amenaza_id: '', vulnerabilidad_id: '',
    probabilidad: '', impacto: '', tipo_tratamiento: '',
});
const nuevo = reactive(valoresIniciales());
const impactoSugeridoNota = ref('');

// Al elegir activo, amenaza o vulnerabilidad, el sistema sugiere impacto y probabilidad.
// Ambos valores pueden cambiarse antes de registrar el riesgo.
watch(() => [nuevo.activo_id, nuevo.amenaza_id, nuevo.vulnerabilidad_id], async ([activoId, amenazaId, vulnerabilidadId]) => {
    impactoSugeridoNota.value = '';
    if (!activoId) return;
    try {
        const { data } = await riesgosApi.sugerencia({
            activo_id: activoId,
            amenaza_id: amenazaId || undefined,
            vulnerabilidad_id: vulnerabilidadId || undefined,
        });
        const s = data.data;
        nuevo.impacto = s.impacto_sugerido;
        if (s.probabilidad_sugerida !== null) nuevo.probabilidad = s.probabilidad_sugerida;
        impactoSugeridoNota.value = s.probabilidad_sugerida !== null
            ? `Sugerido por el sistema: impacto ${s.impacto_sugerido}/5 (criticidad del activo) y probabilidad ${s.probabilidad_sugerida}/5 (amenaza, vulnerabilidad y controles del activo).`
            : `Sugerido por el sistema: impacto ${s.impacto_sugerido}/5. Seleccione amenaza y vulnerabilidad para sugerir la probabilidad.`;
    } catch (e) {
        impactoSugeridoNota.value = 'No se pudo sugerir valores; captúrelos manualmente.';
    }
});
const edicion = reactive(valoresIniciales());

const puedeEditar = computed(() => (auth.usuario?.permisos ?? []).includes('riesgos.editar'));
const puedeEliminar = computed(() => (auth.usuario?.permisos ?? []).includes('riesgos.eliminar'));

// Solo presentación: color semántico fijo por nivel en la gráfica.
const COLORES_NIVEL = { bajo: '--c-success', medio: '--c-amber', alto: '--c-orange', critico: '--c-risk' };

function tonoNivel(n) {
    return { bajo: 'exito', medio: 'advertencia', alto: 'advertencia', critico: 'riesgo' }[n] || 'neutral';
}

function abrirModalCreacion() {
    Object.assign(nuevo, valoresIniciales());
    impactoSugeridoNota.value = '';
    modalAbierto.value = true;
}

function verDetalle(fila) {
    riesgoSeleccionado.value = fila;
    detalleAbierto.value = true;
}

function abrirModalEdicion() {
    edicion.activo_id = riesgoSeleccionado.value.activo_id;
    edicion.amenaza_id = riesgoSeleccionado.value.amenaza_id;
    edicion.vulnerabilidad_id = riesgoSeleccionado.value.vulnerabilidad_id;
    edicion.probabilidad = riesgoSeleccionado.value.probabilidad;
    edicion.impacto = riesgoSeleccionado.value.impacto;
    edicion.tipo_tratamiento = riesgoSeleccionado.value.tipo_tratamiento ?? '';
    modalEdicion.value = true;
}

function cambiarPagina(nuevaPagina) {
    pagina.value = nuevaPagina;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await riesgosApi.listar({
            ...(filtroNivel.value ? { nivel_calculado: filtroNivel.value } : {}),
            page: pagina.value,
        });
        riesgos.value = data.data;
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
        if (!payload.tipo_tratamiento) delete payload.tipo_tratamiento;
        if (payload.impacto === '' || payload.impacto === null) delete payload.impacto;
        if (payload.probabilidad === '' || payload.probabilidad === null) delete payload.probabilidad;
        await riesgosApi.crear(payload);
        toast.exito('Riesgo registrado.');
        modalAbierto.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        creando.value = false;
    }
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        const payload = { ...edicion };
        if (!payload.tipo_tratamiento) payload.tipo_tratamiento = null;
        const { data } = await riesgosApi.actualizar(riesgoSeleccionado.value.id, payload);
        toast.exito('Riesgo actualizado.');
        modalEdicion.value = false;
        riesgoSeleccionado.value = data.data;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        guardando.value = false;
    }
}

async function eliminar() {
    eliminando.value = true;
    try {
        await riesgosApi.eliminar(riesgoSeleccionado.value.id);
        toast.exito('Riesgo eliminado.');
        confirmarEliminar.value = false;
        detalleAbierto.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        eliminando.value = false;
    }
}

async function cargarPaneles() {
    try {
        const [{ data: matrizRes }, { data: graficasRes }] = await Promise.all([
            dashboardApi.matrizRiesgo(),
            dashboardApi.graficas(),
        ]);
        matriz.value = matrizRes.data;
        riesgosPorNivel.value = graficasRes.data.riesgos_por_nivel ?? {};
    } catch (e) {
        // Paneles secundarios: si fallan, no bloquean el listado principal.
    }
}

onMounted(() => {
    cargar();
    cargarCatalogos();
    cargarPaneles();
});
</script>

<style scoped>
.detalle { display: flex; flex-direction: column; gap: var(--space-5); }
.detalle__ficha {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); margin: 0;
}
.detalle__ficha > div {
    padding: var(--space-3); border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--bg-subtle); min-width: 0;
}
.detalle__ficha dt { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 4px; }
.detalle__ficha dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
.detalle__acciones { display: flex; gap: var(--space-2); flex-wrap: wrap; }
@media (max-width: 480px) {
    .detalle__ficha { grid-template-columns: 1fr; }
    .detalle__acciones > * { flex: 1; }
}
</style>
