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

    <AppModal :abierto="modalAbierto" titulo="Nuevo activo" :ancho="!!ficha.subtipo" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="crear">
        <AppSelect v-model="nuevo.tipo" label="Clasificación" requerido :opciones="opcionesTipo" placeholder="Seleccione…" />
        <AppInput v-model="nuevo.nombre" label="Nombre" requerido placeholder="Ej. Computadora Dell OptiPlex, Cámara Hikvision, Windows 11…"
          :ayuda="nuevo.tipo ? 'El sistema reconoce el tipo de activo mientras escribes.' : ''" />

        <!-- Ficha técnica dinámica: campos según el tipo reconocido en el nombre -->
        <FichaTecnicaActivo v-if="nuevo.tipo" :tipo="nuevo.tipo" :nombre="nuevo.nombre" :catalogo="catalogoTipos" :modelo="ficha" :errores="erroresFicha" />

        <div class="form__cia">
          <AppInput v-model.number="nuevo.confidencialidad" type="number" min="1" max="5" label="Confidencialidad (1-5)" requerido />
          <AppInput v-model.number="nuevo.integridad" type="number" min="1" max="5" label="Integridad (1-5)" requerido />
          <AppInput v-model.number="nuevo.disponibilidad" type="number" min="1" max="5" label="Disponibilidad (1-5)" requerido />
        </div>

        <!-- SGSI Intelligence Assistant: revisa el borrador antes de guardar (no crea nada). -->
        <IaAnalisisActivo v-if="puedeUsarIa && (nuevo.nombre || nuevo.tipo)" :borrador="borradorIa" @aplicar="aplicarClasificacionIa" />

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

        <AppInput v-if="nuevo.ubicacion_id" v-model="ficha.ubicacion_detalle" label="Lugar específico (opcional)" maxlength="150" placeholder="Ej. Fila 2, lugar 5 · Rack A · Techo" />

        <AppSelect
          v-model="nuevo.responsable_id"
          label="Responsable (opcional)"
          placeholder="Sin responsable asignado"
          :opciones="opcionesUsuarios"
        />

        <fieldset v-if="controlesSugeridos.length" class="controles-sugeridos">
          <legend>Controles ISO/IEC 27001:2022 relacionados (opcional)</legend>
          <label v-for="c in controlesSugeridos" :key="c.id" class="form__checkbox">
            <input v-model="nuevo.controles_iso_ids" type="checkbox" :value="c.id" />
            <span><strong>{{ c.codigo }}</strong> {{ c.nombre }}</span>
          </label>
          <p class="form__nota">Vincular un control documenta su relación con el activo; no significa que el control se cumpla.</p>
        </fieldset>

        <AppButton type="submit" :cargando="creando" style="width:100%">Crear activo</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
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
import FichaTecnicaActivo, { tecnicoParaEnviar, personalizadosParaEnviar, modeloVacio } from '../../components/activos/FichaTecnicaActivo.vue';
import IaAnalisisActivo from '../../components/ia/IaAnalisisActivo.vue';
import { useAuthStore } from '../../stores/auth';

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
    controles_iso_ids: [],
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

// ---- Ficha técnica dinámica ----
const catalogoTipos = ref(null);
const ficha = reactive({ ...modeloVacio(), ubicacion_detalle: '' });
const erroresFicha = ref({});
const controlesSugeridos = computed(() => {
    const tipo = (catalogoTipos.value?.tipos || []).find((t) => t.clave === ficha.subtipo);
    if (!tipo) return [];
    return (catalogoTipos.value.controles || []).filter((c) => tipo.controles_sugeridos.includes(c.codigo));
});

async function cargarTiposTecnicos() {
    if (catalogoTipos.value) return;
    try {
        const { data } = await activosApi.tiposTecnicos();
        catalogoTipos.value = data.data;
    } catch { /* sin catálogo el formulario funciona con los campos generales */ }
}

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
        opcionesUbicaciones.value = data.data.map((u) => ({ value: u.id, label: u.descripcion || u.nombre }));
    } catch (e) {
        toast.error('No se pudieron cargar las ubicaciones de ese departamento.');
    }
}

function abrirModalCreacion() {
    Object.assign(nuevo, valoresIniciales());
    Object.assign(ficha, { ...modeloVacio(), ubicacion_detalle: '' });
    erroresFicha.value = {};
    cargarTiposTecnicos();
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

// SGSI Intelligence Assistant: solo se envían campos del formulario (sin texto libre de contacto).
const auth = useAuthStore();
const puedeUsarIa = computed(() => (auth.usuario?.permisos ?? []).includes('ia.usar'));
const borradorIa = computed(() => ({
    nombre: nuevo.nombre,
    tipo: nuevo.tipo,
    subtipo: ficha.subtipo || undefined,
    caracteristicas: { campos: tecnicoParaEnviar(ficha, catalogoTipos.value) || {} },
    confidencialidad: nuevo.confidencialidad || undefined,
    integridad: nuevo.integridad || undefined,
    disponibilidad: nuevo.disponibilidad || undefined,
    departamento_id: nuevo.departamento_id || undefined,
    ubicacion_id: nuevo.ubicacion_id || undefined,
    responsable_id: nuevo.responsable_id || undefined,
}));
function aplicarClasificacionIa({ confidencialidad, integridad, disponibilidad }) {
    Object.assign(nuevo, { confidencialidad, integridad, disponibilidad });
    toast.exito('Clasificación propuesta aplicada. Revísela antes de crear el activo.');
}

async function crear() {
    creando.value = true;
    try {
        const payload = { ...nuevo };
        if (!payload.ubicacion_id) delete payload.ubicacion_id;
        if (!payload.responsable_id) delete payload.responsable_id;
        if (!payload.controles_iso_ids.length) delete payload.controles_iso_ids;
        // Ficha técnica: tipo reconocido/confirmado + solo los campos de ese tipo.
        payload.subtipo = ficha.subtipo;
        payload.tecnico = tecnicoParaEnviar(ficha, catalogoTipos.value);
        payload.personalizados = personalizadosParaEnviar(ficha);
        if (payload.ubicacion_id && ficha.ubicacion_detalle) payload.ubicacion_detalle = ficha.ubicacion_detalle;
        erroresFicha.value = {};

        await activosApi.crear(payload);
        toast.exito('Activo creado.');
        modalAbierto.value = false;
        await cargar();
    } catch (e) {
        erroresFicha.value = e.details || {};
        const primero = Object.values(erroresFicha.value)[0]?.[0];
        toast.error(primero || e.message);
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
.controles-sugeridos { border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin: 0; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.controles-sugeridos legend { padding: 0 6px; font-weight: 600; font-size: var(--text-sm); }
</style>
