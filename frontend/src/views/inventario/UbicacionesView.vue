<template>
  <div>
    <nav class="migas" aria-label="Ruta">
      <RouterLink :to="{ name: 'inventario' }">Inventario</RouterLink>
      <AppIcon name="chevron-der" :size="14" />
      <span aria-current="page">Ubicaciones</span>
    </nav>
    <div class="encabezado">
      <div>
        <h1>Ubicaciones</h1>
        <p class="subtitulo">Edificio → piso → espacio. Los activos y laboratorios usan estas ubicaciones · ISO/IEC 27001 A.7.8</p>
      </div>
      <div class="encabezado__acciones">
        <AppButton v-if="puedeGestionar" icono="mas" @click="abrir()">Nueva ubicación</AppButton>
      </div>
    </div>

    <div class="filtros">
      <input v-model="buscar" class="filtros__buscar" placeholder="Buscar por nombre, edificio o piso…" />
      <AppSelect v-model="filtroTipo" placeholder="Todos los espacios" :opciones="[{ value: '', label: 'Todos los espacios' }, ...tiposEspacio]" />
    </div>

    <AppTable :columnas="columnas" :filas="filtradas" :cargando="cargando">
      <template #col-descripcion="{ fila }"><strong>{{ fila.nombre }}</strong><small class="sub"> {{ [fila.edificio, fila.piso].filter(Boolean).join(' · ') }}</small></template>
      <template #col-tipo_espacio="{ fila }">{{ fila.tipo_espacio_label || '—' }}</template>
      <template #col-acciones="{ fila }">
        <div v-if="puedeGestionar" class="acciones-fila">
          <AppButton tamano="sm" variante="secundaria" icono="editar" @click.stop="abrir(fila)">Editar</AppButton>
          <AppButton v-if="puedeEliminar && !fila.activos" tamano="sm" variante="fantasma" icono="papelera" :aria-label="`Eliminar ${fila.nombre}`" @click.stop="eliminar(fila)" />
        </div>
      </template>
      <template #vacio><EmptyState texto="No hay ubicaciones registradas." /></template>
    </AppTable>

    <AppModal :abierto="form.abierto" :titulo="form.id ? 'Editar ubicación' : 'Nueva ubicación'" @cerrar="form.abierto = false">
      <form class="form" @submit.prevent="guardar">
        <AppInput v-model="form.nombre" label="Nombre del espacio" requerido placeholder="Ej. Laboratorio 1, Aula 204, Oficina de TI" :error="form.errores.nombre?.[0]" />
        <div class="form__grid">
          <AppSelect v-model="form.tipo_espacio" label="Tipo de espacio" :opciones="[{ value: '', label: 'Sin especificar' }, ...tiposEspacio]" />
          <AppInput v-model="form.piso" label="Piso / planta" maxlength="30" placeholder="Ej. Planta baja" />
        </div>
        <AppSelect v-model="form.edificio_id" label="Edificio" :opciones="[{ value: '', label: 'Sin edificio' }, ...edificios]" />
        <AppSelect v-model="form.departamento_id" label="Departamento" requerido placeholder="Seleccione…" :opciones="departamentos" />
        <p class="form__nota">Si cambias la ubicación de un activo, el cambio queda en su historial (bitácora).</p>
        <AppButton type="submit" :cargando="form.guardando">Guardar</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import inventarioApi from '../../api/inventario';
import catalogosApi from '../../api/catalogos';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppButton from '../../components/ui/AppButton.vue';
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
    { key: 'descripcion', label: 'Ubicación' }, { key: 'tipo_espacio', label: 'Tipo', width: '170px' },
    { key: 'departamento', label: 'Departamento' }, { key: 'activos', label: 'Activos', width: '90px' }, { key: 'acciones', label: '', width: '150px' },
];
const ubicaciones = ref([]);
const tiposEspacio = ref([]);
const edificios = ref([]);
const departamentos = ref([]);
const cargando = ref(true);
const buscar = ref('');
const filtroTipo = ref('');

const filtradas = computed(() => {
    const t = buscar.value.trim().toLowerCase();
    return ubicaciones.value.filter((u) => (!filtroTipo.value || u.tipo_espacio === filtroTipo.value)
        && (!t || (u.descripcion || '').toLowerCase().includes(t)));
});

async function cargar() {
    try {
        const [{ data: u }, { data: r }] = await Promise.all([inventarioApi.ubicaciones(), inventarioApi.resumen()]);
        ubicaciones.value = u.data;
        tiposEspacio.value = u.tipos_espacio;
        edificios.value = r.data.edificios.map((e) => ({ value: e.id, label: e.nombre }));
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

const form = reactive({ abierto: false, id: null, nombre: '', tipo_espacio: '', piso: '', edificio_id: '', departamento_id: '', guardando: false, errores: {} });
async function abrir(u = null) {
    Object.assign(form, {
        abierto: true, id: u?.id || null, nombre: u?.nombre || '', tipo_espacio: u?.tipo_espacio || '', piso: u?.piso || '',
        edificio_id: u?.edificio_id || '', departamento_id: u?.departamento_id || '', errores: {},
    });
    if (!departamentos.value.length) {
        try {
            const { data } = await catalogosApi.departamentos();
            departamentos.value = data.data.map((d) => ({ value: d.id, label: `${d.nombre} (${d.clave})` }));
        } catch { /* el backend valida igualmente */ }
    }
}
async function guardar() {
    form.guardando = true;
    form.errores = {};
    try {
        const datos = {
            nombre: form.nombre, tipo_espacio: form.tipo_espacio || null, piso: form.piso || null,
            edificio_id: form.edificio_id ? Number(form.edificio_id) : null, departamento_id: Number(form.departamento_id) || null,
        };
        if (form.id) await inventarioApi.actualizarUbicacion(form.id, datos); else await inventarioApi.crearUbicacion(datos);
        form.abierto = false;
        toast.exito('Ubicación guardada.');
        await cargar();
    } catch (e) {
        form.errores = e.details || {};
        toast.error(Object.values(form.errores)[0]?.[0] || e.message);
    } finally {
        form.guardando = false;
    }
}
async function eliminar(u) {
    if (!window.confirm(`¿Eliminar la ubicación «${u.nombre}»?`)) return;
    try {
        await inventarioApi.eliminarUbicacion(u.id);
        toast.exito('Ubicación eliminada.');
        await cargar();
    } catch (e) {
        toast.error(Object.values(e.details || {})[0]?.[0] || e.message);
    }
}

onMounted(cargar);
</script>

<style scoped>
.migas { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: var(--text-sm); color: var(--text-muted); margin-bottom: var(--space-3); }
.subtitulo { margin: 4px 0 0; color: var(--text-muted); font-size: var(--text-sm); }
.sub { color: var(--text-subtle); font-size: var(--text-xs); }
.acciones-fila { display: flex; gap: 6px; justify-content: flex-end; }
</style>
