<template>
  <div>
    <nav class="migas" aria-label="Ruta">
      <RouterLink :to="{ name: 'inventario' }">Inventario</RouterLink>
      <AppIcon name="chevron-der" :size="14" />
      <span aria-current="page">Catálogo de software</span>
    </nav>
    <div class="encabezado">
      <div>
        <h1>Catálogo de software</h1>
        <p class="subtitulo">Programas que pueden registrarse en los equipos · ISO/IEC 27001 A.8.19</p>
      </div>
      <div class="encabezado__acciones">
        <AppButton v-if="puedeGestionar" icono="mas" @click="abrir()">Agregar programa</AppButton>
      </div>
    </div>

    <div class="indicadores">
      <button v-for="ind in indicadores" :key="ind.clave" type="button" class="indicador" :class="[`indicador--${ind.tono}`, { 'indicador--activo': filtroRapido === ind.clave }]"
              @click="filtroRapido = filtroRapido === ind.clave ? '' : ind.clave">
        <strong>{{ ind.valor }}</strong><span>{{ ind.label }}</span>
      </button>
    </div>

    <div class="filtros">
      <input v-model="buscar" class="filtros__buscar" placeholder="Buscar por nombre o fabricante…" />
      <AppSelect v-model="categoria" placeholder="Todas las categorías" :opciones="[{ value: '', label: 'Todas las categorías' }, ...(catalogos.categorias_software || [])]" />
    </div>

    <AppTable :columnas="columnas" :filas="filtrados" :cargando="cargando">
      <template #col-nombre="{ fila }"><strong>{{ fila.nombre }}</strong><small class="sub"> {{ fila.fabricante || '' }}</small>
        <AppBadge v-if="fila.es_antivirus" tono="signal" class="badge-mini">Antivirus</AppBadge></template>
      <template #col-categoria="{ fila }">{{ etiqueta('categorias_software', fila.categoria) }}</template>
      <template #col-estado_autorizacion="{ fila }">
        <AppBadge :tono="{ autorizado: 'exito', no_autorizado: 'riesgo' }[fila.estado_autorizacion] || 'neutral'">{{ etiqueta('estados_autorizacion', fila.estado_autorizacion) }}</AppBadge>
      </template>
      <template #col-tipo_licencia="{ fila }">{{ etiqueta('tipos_licencia', fila.tipo_licencia) }}</template>
      <template #col-instalaciones="{ fila }">
        <button type="button" class="enlace-equipos" :disabled="!fila.instalaciones" @click.stop="verEquipos(fila)">{{ fila.instalaciones }} equipo{{ fila.instalaciones === 1 ? '' : 's' }}</button>
        <AppBadge v-if="fila.pendientes_actualizacion" tono="advertencia" class="badge-mini">{{ fila.pendientes_actualizacion }} por actualizar</AppBadge>
      </template>
      <template #col-acciones="{ fila }">
        <div v-if="puedeGestionar" class="acciones-fila">
          <AppButton tamano="sm" variante="secundaria" icono="editar" @click.stop="abrir(fila)">Editar</AppButton>
          <AppButton v-if="puedeEliminar && !fila.instalaciones" tamano="sm" variante="fantasma" icono="papelera" :aria-label="`Eliminar ${fila.nombre}`" @click.stop="eliminar(fila)" />
        </div>
      </template>
      <template #vacio>
        <EmptyState texto="El catálogo está vacío. Agrega los programas que usa la universidad para poder registrarlos en cada equipo.">
          <template v-if="puedeGestionar" #accion><AppButton tamano="sm" @click="abrir()">Agregar el primero</AppButton></template>
        </EmptyState>
      </template>
    </AppTable>

    <AppModal :abierto="!!equipos.programa" :titulo="`Equipos con ${equipos.programa?.nombre || ''}`" @cerrar="equipos.programa = null">
      <p v-if="equipos.cargando" class="sub">Cargando…</p>
      <ul v-else class="lista-equipos">
        <li v-for="e in equipos.lista" :key="e.uuid" :class="{ 'lista-equipos__baja': e.estado_instalacion !== 'instalado' }">
          <RouterLink :to="{ name: 'inventario.computadora', params: { uuid: e.uuid } }" @click="equipos.programa = null">{{ e.nombre_equipo }}</RouterLink>
          <small>{{ e.laboratorio || 'Sin laboratorio' }}<template v-if="e.version"> · versión {{ e.version }}</template></small>
          <AppBadge v-if="e.estado_instalacion !== 'instalado'" tono="neutral">Desinstalado</AppBadge>
          <AppBadge v-else-if="e.desactualizado" tono="advertencia">Requiere actualización</AppBadge>
          <AppBadge v-else tono="exito">Instalado</AppBadge>
        </li>
      </ul>
    </AppModal>

    <AppModal :abierto="form.abierto" :titulo="form.id ? 'Editar programa' : 'Agregar programa'" @cerrar="form.abierto = false">
      <form class="form" @submit.prevent="guardar">
        <div class="form__grid">
          <AppInput v-model="form.nombre" label="Nombre" requerido :error="form.errores.nombre?.[0]" placeholder="Ej. Visual Studio Code" />
          <AppInput v-model="form.fabricante" label="Fabricante" placeholder="Ej. Microsoft" />
        </div>
        <div class="form__grid">
          <AppSelect v-model="form.categoria" label="Categoría" requerido placeholder="Seleccione…" :opciones="catalogos.categorias_software || []" />
          <AppSelect v-model="form.tipo_licencia" label="Tipo de licencia" :opciones="catalogos.tipos_licencia || []" />
        </div>
        <div class="form__grid">
          <AppSelect v-model="form.estado_autorizacion" label="Autorización" :opciones="catalogos.estados_autorizacion || []" />
          <AppInput v-model="form.version_vigente" label="Versión mínima aprobada" ayuda="Las instalaciones con una versión menor se marcarán por actualizar." />
        </div>
        <label class="form__checkbox"><input v-model="form.es_antivirus" type="checkbox" /> Es un antivirus / software de seguridad</label>
        <AppInput v-model="form.descripcion" label="Descripción (opcional)" />
        <p class="form__nota">Si no conoces la licencia o la autorización, deja «Pendiente de verificación»: el sistema nunca presume que un programa es ilegal.</p>
        <AppButton type="submit" :cargando="form.guardando">Guardar</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import inventarioApi from '../../api/inventario';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppButton from '../../components/ui/AppButton.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppTable from '../../components/ui/AppTable.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const auth = useAuthStore();
const toast = useToastStore();
const permisos = computed(() => auth.usuario?.permisos || []);
const puedeGestionar = computed(() => auth.esAdministrador || permisos.value.includes('activos.crear') || permisos.value.includes('activos.editar'));
const puedeEliminar = computed(() => auth.esAdministrador || permisos.value.includes('activos.eliminar'));

const columnas = [
    { key: 'nombre', label: 'Programa' }, { key: 'categoria', label: 'Categoría', width: '170px' },
    { key: 'estado_autorizacion', label: 'Autorización', width: '190px' }, { key: 'tipo_licencia', label: 'Licencia', width: '150px' },
    { key: 'version_vigente', label: 'Versión vigente', width: '130px' }, { key: 'instalaciones', label: 'Equipos', width: '170px' },
    { key: 'acciones', label: '', width: '150px' },
];

const programas = ref([]);
const catalogos = ref({});
const cargando = ref(true);
const buscar = ref('');
const categoria = ref('');
const filtroRapido = ref('');

const indicadores = computed(() => {
    const p = programas.value;
    return [
        { clave: 'autorizado', label: 'Autorizados', tono: 'exito', valor: p.filter((x) => x.estado_autorizacion === 'autorizado').length },
        { clave: 'no_autorizado', label: 'No autorizados', tono: 'riesgo', valor: p.filter((x) => x.estado_autorizacion === 'no_autorizado').length },
        { clave: 'pendiente_verificacion', label: 'Pendientes de verificación', tono: 'neutral', valor: p.filter((x) => x.estado_autorizacion === 'pendiente_verificacion').length },
        { clave: 'desactualizado', label: 'Con instalaciones por actualizar', tono: 'advertencia', valor: p.filter((x) => x.pendientes_actualizacion > 0).length },
        { clave: 'sin_licencia', label: 'Con licencias sin identificar', tono: 'advertencia', valor: p.filter((x) => x.sin_licencia_identificada > 0).length },
    ];
});

const filtrados = computed(() => {
    const t = buscar.value.trim().toLowerCase();
    return programas.value.filter((p) => {
        if (categoria.value && p.categoria !== categoria.value) return false;
        if (t && !`${p.nombre} ${p.fabricante || ''}`.toLowerCase().includes(t)) return false;
        if (filtroRapido.value === 'desactualizado') return p.pendientes_actualizacion > 0;
        if (filtroRapido.value === 'sin_licencia') return p.sin_licencia_identificada > 0;
        if (filtroRapido.value) return p.estado_autorizacion === filtroRapido.value;
        return true;
    });
});

const equipos = reactive({ programa: null, lista: [], cargando: false });
async function verEquipos(p) {
    Object.assign(equipos, { programa: p, lista: [], cargando: true });
    try {
        const { data } = await inventarioApi.equiposDeSoftware(p.id);
        equipos.lista = data.data;
    } catch (e) {
        toast.error(e.message);
    } finally {
        equipos.cargando = false;
    }
}

function etiqueta(cat, valor) {
    return (catalogos.value[cat] || []).find((o) => o.value === valor)?.label || valor || '—';
}

async function cargar() {
    try {
        const [{ data: s }, { data: c }] = await Promise.all([inventarioApi.software(), inventarioApi.catalogos()]);
        programas.value = s.data;
        catalogos.value = c.data;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

const form = reactive({ abierto: false, id: null, nombre: '', fabricante: '', categoria: '', tipo_licencia: 'desconocida', estado_autorizacion: 'pendiente_verificacion', version_vigente: '', es_antivirus: false, descripcion: '', guardando: false, errores: {} });
function abrir(p = null) {
    Object.assign(form, {
        abierto: true, id: p?.id || null, nombre: p?.nombre || '', fabricante: p?.fabricante || '', categoria: p?.categoria || '',
        tipo_licencia: p?.tipo_licencia || 'desconocida', estado_autorizacion: p?.estado_autorizacion || 'pendiente_verificacion',
        version_vigente: p?.version_vigente || '', es_antivirus: !!p?.es_antivirus, descripcion: p?.descripcion || '', errores: {},
    });
}
async function guardar() {
    form.guardando = true;
    form.errores = {};
    try {
        const datos = {
            nombre: form.nombre, fabricante: form.fabricante || null, categoria: form.categoria, tipo_licencia: form.tipo_licencia,
            estado_autorizacion: form.estado_autorizacion, version_vigente: form.version_vigente || null, es_antivirus: form.es_antivirus,
            descripcion: form.descripcion || null,
        };
        if (form.id) await inventarioApi.actualizarProgramaCatalogo(form.id, datos); else await inventarioApi.crearSoftware(datos);
        form.abierto = false;
        toast.exito('Programa guardado en el catálogo.');
        await cargar();
    } catch (e) {
        form.errores = e.details || {};
        toast.error(Object.values(form.errores)[0]?.[0] || e.message);
    } finally {
        form.guardando = false;
    }
}
async function eliminar(p) {
    if (!window.confirm(`¿Eliminar «${p.nombre}» del catálogo?`)) return;
    try {
        await inventarioApi.eliminarSoftware(p.id);
        toast.exito('Programa eliminado del catálogo.');
        await cargar();
    } catch (e) {
        toast.error(Object.values(e.details || {})[0]?.[0] || e.message);
    }
}

onMounted(cargar);
</script>

<style scoped>
.enlace-equipos { border: none; background: none; padding: 0; color: var(--c-signal); font-weight: 600; cursor: pointer; font: inherit; }
.enlace-equipos:disabled { color: var(--text-subtle); cursor: default; }
.enlace-equipos:not(:disabled):hover { text-decoration: underline; }
.lista-equipos { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.lista-equipos li { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 0; border-bottom: 1px solid var(--border); }
.lista-equipos li:last-child { border-bottom: none; }
.lista-equipos small { color: var(--text-muted); flex: 1; }
.lista-equipos__baja a { color: var(--text-muted); text-decoration: line-through; }

.migas { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: var(--text-sm); color: var(--text-muted); margin-bottom: var(--space-3); }
.subtitulo { margin: 4px 0 0; color: var(--text-muted); font-size: var(--text-sm); }
.sub { color: var(--text-subtle); font-size: var(--text-xs); }
.badge-mini { margin-left: 6px; }
.acciones-fila { display: flex; gap: 6px; justify-content: flex-end; }
.indicadores { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 170px), 1fr)); gap: var(--space-3); margin-bottom: var(--space-5); }
.indicador {
    --tono: var(--text-muted);
    display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: var(--space-4); text-align: left;
    border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface); cursor: pointer; color: var(--text);
    border-bottom: 3px solid var(--tono);
    transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
}
.indicador strong { font-family: var(--font-display); font-size: 1.5rem; color: var(--tono); }
.indicador span { font-size: var(--text-xs); color: var(--text-muted); font-weight: 600; }
.indicador:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.indicador:active { transform: scale(0.98); }
.indicador--activo { border-color: var(--tono); box-shadow: 0 0 0 2px color-mix(in srgb, var(--tono) 30%, transparent); }
.indicador--exito { --tono: var(--c-success); }
.indicador--riesgo { --tono: var(--c-risk); }
.indicador--advertencia { --tono: var(--c-amber); }
.indicador--neutral { --tono: var(--c-gray-500); }
</style>
