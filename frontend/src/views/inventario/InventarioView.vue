<template>
  <div>
    <div class="encabezado">
      <div>
        <h1>Inventario tecnológico</h1>
        <p class="subtitulo">Edificios, laboratorios y cada computadora como activo individual · ISO/IEC 27001 A.5.9</p>
      </div>
      <div class="encabezado__acciones">
        <RouterLink :to="{ name: 'inventario.software' }" class="boton-descarga"><AppIcon name="capas" :size="17" />Catálogo de software</RouterLink>
        <RouterLink :to="{ name: 'inventario.ubicaciones' }" class="boton-descarga"><AppIcon name="activos" :size="17" />Ubicaciones</RouterLink>
        <button type="button" class="boton-descarga" :disabled="exportando" @click="exportar()"><AppIcon name="descargar" :size="17" />{{ exportando ? 'Generando…' : 'Exportar inventario' }}</button>
        <AppButton v-if="puedeGestionar" variante="secundaria" icono="mas" @click="importarAbierto = true">Importar</AppButton>
        <AppButton v-if="puedeGestionar" icono="mas" @click="abrirAlta">Registrar computadora</AppButton>
      </div>
    </div>

    <div v-if="cargando" aria-busy="true" aria-label="Cargando inventario…">
      <div class="skeleton-kpis"><div v-for="n in 6" :key="n" class="skeleton" /></div>
      <div class="skeleton" style="height: 220px" />
    </div>

    <template v-else-if="resumen">
      <ResumenInventario :resumen="resumen.global" :dias-antivirus="catalogos.dias_antivirus_desactualizado || 7" />

      <section class="bloque">
        <div class="bloque__titulo">
          <h2>Edificios y laboratorios</h2>
          <div v-if="puedeGestionar" class="bloque__acciones">
            <AppButton tamano="sm" variante="secundaria" icono="mas" @click="abrirEdificio()">Edificio</AppButton>
            <AppButton tamano="sm" variante="secundaria" icono="mas" :disabled="!resumen.edificios.length" @click="abrirLaboratorio()">Laboratorio</AppButton>
          </div>
        </div>

        <EmptyState v-if="!resumen.edificios.length" texto="Aún no hay edificios registrados. Registra los edificios y laboratorios de la universidad para organizar los equipos.">
          <template v-if="puedeGestionar" #accion><AppButton tamano="sm" @click="abrirEdificio()">Registrar el primer edificio</AppButton></template>
        </EmptyState>

        <div v-for="(ed, i) in resumen.edificios" :key="ed.id" class="edificio entrada-escalonada" :style="{ '--i': i }">
          <header class="edificio__cabecera">
            <div>
              <h3>{{ ed.nombre }} <span v-if="ed.clave" class="edificio__clave">{{ ed.clave }}</span></h3>
              <p v-if="ed.descripcion" class="edificio__desc">{{ ed.descripcion }}</p>
            </div>
            <div v-if="puedeGestionar" class="edificio__acciones">
              <button type="button" class="icono-btn" title="Editar edificio" aria-label="Editar edificio" @click="abrirEdificio(ed)"><AppIcon name="editar" :size="16" /></button>
              <button v-if="puedeEliminar" type="button" class="icono-btn icono-btn--peligro" title="Eliminar edificio" aria-label="Eliminar edificio" @click="eliminarEdificio(ed)"><AppIcon name="papelera" :size="16" /></button>
            </div>
          </header>
          <p v-if="!ed.laboratorios.length" class="edificio__vacio">Sin laboratorios registrados.</p>
          <div class="laboratorios">
            <RouterLink v-for="lab in ed.laboratorios" :key="lab.id" :to="{ name: 'inventario.laboratorio', params: { id: lab.id } }" class="lab-card">
              <div class="lab-card__top">
                <strong>{{ lab.nombre }}</strong>
                <AppIcon name="chevron-der" :size="16" class="lab-card__flecha" />
              </div>
              <span class="lab-card__total">{{ lab.resumen.total }} <small>equipo{{ lab.resumen.total === 1 ? '' : 's' }}{{ lab.capacidad ? ` / ${lab.capacidad} lugares` : '' }}</small></span>
              <div class="lab-card__barra" role="img" :aria-label="`Operativos ${lab.resumen.por_estado.operativo}, mantenimiento ${lab.resumen.por_estado.mantenimiento}, fuera de servicio ${lab.resumen.por_estado.fuera_servicio}`">
                <span v-for="seg in segmentos(lab.resumen)" :key="seg.clave" :class="`seg seg--${seg.clave}`" :style="{ flexGrow: seg.valor }" />
              </div>
              <div class="lab-card__datos">
                <span><i class="punto punto--ok" />{{ lab.resumen.por_estado.operativo }} operativos</span>
                <span v-if="lab.resumen.por_estado.mantenimiento"><i class="punto punto--mant" />{{ lab.resumen.por_estado.mantenimiento }} mant.</span>
                <span v-if="lab.resumen.por_estado.fuera_servicio"><i class="punto punto--fuera" />{{ lab.resumen.por_estado.fuera_servicio }} fuera</span>
                <span v-if="lab.resumen.antivirus.desactualizado" class="lab-card__alerta"><AppIcon name="alerta" :size="13" />{{ lab.resumen.antivirus.desactualizado }} antivirus</span>
              </div>
            </RouterLink>
          </div>
        </div>
      </section>

      <section class="bloque">
        <div class="bloque__titulo"><h2>Todos los equipos</h2></div>
        <div class="filtros">
          <input v-model="filtros.buscar" class="filtros__buscar" placeholder="Buscar por nombre, inventario, serie, MAC o IP…" @input="buscarDebounced" />
          <AppSelect v-model="filtros.edificio_id" placeholder="Todos los edificios" :opciones="[{ value: '', label: 'Todos los edificios' }, ...opcionesEdificios]" @update:modelValue="recargarLista" />
          <AppSelect v-model="filtros.estado_operativo" placeholder="Todos los estados" :opciones="[{ value: '', label: 'Todos los estados' }, ...(catalogos.estados_operativos || [])]" @update:modelValue="recargarLista" />
        </div>
        <TablaComputadoras :equipos="equipos" :cargando="cargandoLista" :paginacion="meta" @cambiar-pagina="(p) => { pagina = p; cargarLista(); }">
          <template #vacio>
            <EmptyState texto="No hay equipos que coincidan con el filtro.">
              <template v-if="puedeGestionar && !hayFiltros" #accion><AppButton tamano="sm" @click="abrirAlta">Registrar el primero</AppButton></template>
            </EmptyState>
          </template>
        </TablaComputadoras>
      </section>
    </template>

    <!-- Edificio -->
    <AppModal :abierto="edificio.abierto" :titulo="edificio.id ? 'Editar edificio' : 'Nuevo edificio'" @cerrar="edificio.abierto = false">
      <form class="form" @submit.prevent="guardarEdificio">
        <AppInput v-model="edificio.nombre" label="Nombre del edificio" requerido placeholder="Ej. Edificio B" :error="edificio.errores.nombre?.[0]" />
        <AppInput v-model="edificio.clave" label="Clave (opcional)" :error="edificio.errores.clave?.[0]" />
        <AppInput v-model="edificio.descripcion" label="Descripción (opcional)" />
        <AppButton type="submit" :cargando="edificio.guardando">Guardar</AppButton>
      </form>
    </AppModal>

    <!-- Laboratorio -->
    <AppModal :abierto="lab.abierto" :titulo="lab.id ? 'Editar laboratorio' : 'Nuevo laboratorio'" @cerrar="lab.abierto = false">
      <form class="form" @submit.prevent="guardarLaboratorio">
        <AppSelect v-model="lab.edificio_id" label="Edificio" requerido placeholder="Seleccione…" :opciones="opcionesEdificios" />
        <AppInput v-model="lab.nombre" label="Nombre del laboratorio" requerido placeholder="Ej. Laboratorio 1" :error="lab.errores.nombre?.[0]" />
        <div class="form__grid">
          <AppInput v-model="lab.clave" label="Clave (opcional)" />
          <AppInput v-model="lab.capacidad" type="number" min="1" label="Capacidad (equipos)" />
        </div>
        <AppSelect v-model="lab.departamento_id" label="Departamento responsable" requerido placeholder="Seleccione…" :opciones="departamentos" />
        <AppSelect v-model="lab.responsable_id" label="Responsable (opcional)" placeholder="Sin responsable" :opciones="[{ value: '', label: 'Sin responsable' }, ...usuarios]" />
        <AppInput v-model="lab.descripcion" label="Descripción (opcional)" />
        <AppButton type="submit" :cargando="lab.guardando">Guardar</AppButton>
      </form>
    </AppModal>

    <ComputadoraFormModal :abierto="altaAbierta" :catalogos="catalogos" :laboratorios="listaLaboratorios"
      @cerrar="altaAbierta = false" @guardado="alGuardarEquipo" />
    <ImportarComputadorasModal :abierto="importarAbierto" :laboratorios="listaLaboratorios"
      @cerrar="importarAbierto = false" @importado="cargarTodo" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import inventarioApi from '../../api/inventario';
import catalogosApi from '../../api/catalogos';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppModal from '../../components/ui/AppModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import ResumenInventario from '../../components/inventario/ResumenInventario.vue';
import TablaComputadoras from '../../components/inventario/TablaComputadoras.vue';
import ComputadoraFormModal from '../../components/inventario/ComputadoraFormModal.vue';
import ImportarComputadorasModal from '../../components/inventario/ImportarComputadorasModal.vue';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const permisos = computed(() => auth.usuario?.permisos || []);
const puedeGestionar = computed(() => auth.esAdministrador || permisos.value.includes('activos.crear') || permisos.value.includes('activos.editar'));
const puedeEliminar = computed(() => auth.esAdministrador || permisos.value.includes('activos.eliminar'));

const cargando = ref(true);
const resumen = ref(null);
const catalogos = ref({});
const departamentos = ref([]);
const usuarios = ref([]);
const exportando = ref(false);
const altaAbierta = ref(false);
const importarAbierto = ref(false);

const equipos = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargandoLista = ref(false);
const filtros = reactive({ buscar: '', edificio_id: '', estado_operativo: '' });
const hayFiltros = computed(() => !!(filtros.buscar || filtros.edificio_id || filtros.estado_operativo));

const opcionesEdificios = computed(() => (resumen.value?.edificios || []).map((e) => ({ value: e.id, label: e.nombre })));
const listaLaboratorios = computed(() => (resumen.value?.edificios || []).flatMap((e) => e.laboratorios.map((l) => ({ id: l.id, nombre: l.nombre, edificio: e.nombre }))));

function segmentos(r) {
    return ['operativo', 'mantenimiento', 'fuera_servicio', 'baja']
        .map((clave) => ({ clave, valor: r.por_estado[clave] || 0 }))
        .filter((s) => s.valor > 0);
}

async function cargarTodo() {
    try {
        const [{ data: r }, { data: c }] = await Promise.all([inventarioApi.resumen(), inventarioApi.catalogos()]);
        resumen.value = r.data;
        catalogos.value = c.data;
    } catch (e) {
        toast.error(e.message || 'No se pudo cargar el inventario.');
    } finally {
        cargando.value = false;
    }
    cargarLista();
}

async function cargarLista() {
    cargandoLista.value = true;
    try {
        const params = { page: pagina.value };
        Object.entries(filtros).forEach(([k, v]) => { if (v) params[k] = v; });
        const { data } = await inventarioApi.computadoras(params);
        equipos.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargandoLista.value = false;
    }
}
function recargarLista() { pagina.value = 1; cargarLista(); }
let temporizador = null;
function buscarDebounced() { clearTimeout(temporizador); temporizador = setTimeout(recargarLista, 350); }

async function cargarSelectores() {
    if (departamentos.value.length) return;
    try {
        const [{ data: d }, { data: u }] = await Promise.all([catalogosApi.departamentos(), catalogosApi.usuarios()]);
        departamentos.value = d.data.map((x) => ({ value: x.id, label: `${x.nombre} (${x.clave})` }));
        usuarios.value = u.data.map((x) => ({ value: x.id, label: x.nombre }));
    } catch { /* el backend valida igualmente */ }
}

async function exportar() {
    exportando.value = true;
    try { await inventarioApi.exportar({}, 'inventario-completo.xlsx'); } catch (e) { toast.error(e.message); } finally { exportando.value = false; }
}

function abrirAlta() { altaAbierta.value = true; }
function alGuardarEquipo(equipo) {
    altaAbierta.value = false;
    toast.exito('Computadora registrada.');
    router.push({ name: 'inventario.computadora', params: { uuid: equipo.uuid } });
}

// ---- Edificios ----
const edificio = reactive({ abierto: false, id: null, nombre: '', clave: '', descripcion: '', guardando: false, errores: {} });
function abrirEdificio(ed = null) {
    Object.assign(edificio, { abierto: true, id: ed?.id || null, nombre: ed?.nombre || '', clave: ed?.clave || '', descripcion: ed?.descripcion || '', errores: {} });
}
async function guardarEdificio() {
    edificio.guardando = true;
    edificio.errores = {};
    try {
        const datos = { nombre: edificio.nombre, clave: edificio.clave || null, descripcion: edificio.descripcion || null };
        if (edificio.id) await inventarioApi.actualizarEdificio(edificio.id, datos); else await inventarioApi.crearEdificio(datos);
        toast.exito('Edificio guardado.');
        edificio.abierto = false;
        await cargarTodo();
    } catch (e) {
        edificio.errores = e.details || {};
        if (!Object.keys(edificio.errores).length) toast.error(e.message);
    } finally {
        edificio.guardando = false;
    }
}
async function eliminarEdificio(ed) {
    if (!window.confirm(`¿Eliminar el edificio «${ed.nombre}»? Solo es posible si no tiene laboratorios.`)) return;
    try {
        await inventarioApi.eliminarEdificio(ed.id);
        toast.exito('Edificio eliminado.');
        await cargarTodo();
    } catch (e) {
        toast.error(Object.values(e.details || {})[0]?.[0] || e.message);
    }
}

// ---- Laboratorios ----
const lab = reactive({ abierto: false, id: null, edificio_id: '', nombre: '', clave: '', capacidad: '', departamento_id: '', responsable_id: '', descripcion: '', guardando: false, errores: {} });
function abrirLaboratorio() {
    Object.assign(lab, { abierto: true, id: null, edificio_id: '', nombre: '', clave: '', capacidad: '', departamento_id: '', responsable_id: '', descripcion: '', errores: {} });
    cargarSelectores();
}
async function guardarLaboratorio() {
    lab.guardando = true;
    lab.errores = {};
    try {
        const datos = {
            edificio_id: Number(lab.edificio_id) || null, nombre: lab.nombre, clave: lab.clave || null,
            capacidad: lab.capacidad ? Number(lab.capacidad) : null, departamento_id: Number(lab.departamento_id) || null,
            responsable_id: lab.responsable_id ? Number(lab.responsable_id) : null, descripcion: lab.descripcion || null,
        };
        await inventarioApi.crearLaboratorio(datos);
        toast.exito('Laboratorio registrado.');
        lab.abierto = false;
        await cargarTodo();
    } catch (e) {
        lab.errores = e.details || {};
        toast.error(Object.values(lab.errores)[0]?.[0] || e.message);
    } finally {
        lab.guardando = false;
    }
}

onMounted(cargarTodo);
</script>

<style scoped>
.subtitulo { margin: 4px 0 0; color: var(--text-muted); font-size: var(--text-sm); }
.skeleton-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-5); }
.skeleton-kpis .skeleton { height: 120px; }
.bloque { margin-bottom: var(--space-6); }
.bloque__titulo { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; margin-bottom: var(--space-4); }
.bloque__titulo h2 { margin: 0; }
.bloque__acciones { display: flex; gap: var(--space-2); }
button.boton-descarga { cursor: pointer; font-family: inherit; }
button.boton-descarga:disabled { opacity: 0.6; cursor: progress; }

.edificio { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4); box-shadow: var(--shadow-sm); }
.edificio__cabecera { display: flex; justify-content: space-between; gap: var(--space-3); align-items: flex-start; margin-bottom: var(--space-4); }
.edificio__cabecera h3 { margin: 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.edificio__clave { font-family: var(--font-mono); font-size: var(--text-xs); padding: 2px 8px; border-radius: 999px; background: var(--bg-subtle); color: var(--text-muted); font-weight: 500; }
.edificio__desc { margin: 4px 0 0; color: var(--text-muted); font-size: var(--text-sm); }
.edificio__vacio { margin: 0; color: var(--text-subtle); font-size: var(--text-sm); }
.edificio__acciones { display: flex; gap: 6px; }
.icono-btn { display: grid; place-items: center; width: 34px; height: 34px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text-muted); cursor: pointer; transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast), transform var(--transition-fast); }
.icono-btn:hover { color: var(--c-primary); border-color: var(--c-primary); background: var(--c-primary-soft); }
.icono-btn--peligro:hover { color: var(--c-risk); border-color: var(--c-risk); background: var(--c-risk-soft); }
.icono-btn:active { transform: scale(0.94); }

.laboratorios { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 1fr)); gap: var(--space-3); }
.lab-card {
    display: flex; flex-direction: column; gap: 8px; padding: var(--space-4); border-radius: var(--radius-md);
    border: 1px solid var(--border); background: var(--bg-subtle); color: var(--text);
    transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base), background-color var(--transition-base);
}
.lab-card:hover { text-decoration: none; transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--c-primary); background: var(--surface); }
.lab-card:active { transform: translateY(0) scale(0.99); }
.lab-card__top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.lab-card__flecha { color: var(--text-subtle); transition: transform var(--transition-fast), color var(--transition-fast); }
.lab-card:hover .lab-card__flecha { transform: translateX(3px); color: var(--c-primary); }
.lab-card__total { font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
.lab-card__total small { font-family: var(--font-body); font-size: var(--text-xs); font-weight: 500; color: var(--text-muted); }
.lab-card__barra { display: flex; height: 6px; border-radius: 999px; overflow: hidden; background: var(--border); gap: 2px; }
.seg { display: block; min-width: 4px; transform-origin: left; animation: barra-llenar 700ms var(--ease-salida, var(--ease)) backwards; }
.seg--operativo { background: var(--c-success); }
.seg--mantenimiento { background: var(--c-amber); }
.seg--fuera_servicio { background: var(--c-risk); }
.seg--baja { background: var(--c-gray-500); }
@keyframes barra-llenar { from { transform: scaleX(0); } }
.lab-card__datos { display: flex; flex-wrap: wrap; gap: 4px 10px; font-size: var(--text-xs); color: var(--text-muted); }
.lab-card__datos span { display: inline-flex; align-items: center; gap: 5px; }
.lab-card__alerta { color: var(--c-risk); font-weight: 600; }
.punto { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.punto--ok { background: var(--c-success); }
.punto--mant { background: var(--c-amber); }
.punto--fuera { background: var(--c-risk); }
@media (max-width: 640px) {
    .edificio { padding: var(--space-4); }
    .encabezado__acciones { width: 100%; }
}
</style>
