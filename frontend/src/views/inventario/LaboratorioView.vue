<template>
  <div>
    <nav class="migas" aria-label="Ruta">
      <RouterLink :to="{ name: 'inventario' }">Inventario</RouterLink>
      <AppIcon name="chevron-der" :size="14" />
      <span>{{ datos?.edificio?.nombre || '…' }}</span>
      <AppIcon name="chevron-der" :size="14" />
      <span aria-current="page">{{ datos?.nombre || '…' }}</span>
    </nav>

    <div v-if="cargando" aria-busy="true">
      <div class="skeleton" style="height: 48px; width: 40%; margin-bottom: 16px" />
      <div class="skeleton" style="height: 260px" />
    </div>

    <template v-else-if="datos">
      <div class="encabezado">
        <div>
          <h1>{{ datos.nombre }}</h1>
          <p class="subtitulo">
            {{ datos.edificio.nombre }} · {{ datos.departamento.nombre }}
            <template v-if="datos.responsable"> · Responsable: {{ datos.responsable.nombre }}</template>
            <template v-if="datos.capacidad"> · Capacidad: {{ datos.capacidad }} equipos</template>
          </p>
        </div>
        <div class="encabezado__acciones">
          <button type="button" class="boton-descarga" :disabled="exportando" @click="exportar"><AppIcon name="descargar" :size="17" />{{ exportando ? 'Generando…' : 'Exportar laboratorio' }}</button>
          <AppButton v-if="puedeGestionar" variante="secundaria" icono="editar" @click="abrirEdicion">Editar</AppButton>
          <AppButton v-if="puedeGestionar" variante="secundaria" icono="mas" @click="importarAbierto = true">Importar</AppButton>
          <AppButton v-if="puedeGestionar" icono="mas" @click="altaAbierta = true">Agregar computadora</AppButton>
        </div>
      </div>

      <ResumenInventario :resumen="datos.resumen" :dias-antivirus="catalogos.dias_antivirus_desactualizado || 7" />

      <div class="filtros">
        <input v-model="buscar" class="filtros__buscar" placeholder="Filtrar equipos de este laboratorio…" />
        <AppSelect v-model="estado" placeholder="Todos los estados" :opciones="[{ value: '', label: 'Todos los estados' }, ...(catalogos.estados_operativos || [])]" />
      </div>
      <TablaComputadoras :equipos="filtrados">
        <template #vacio>
          <EmptyState :texto="datos.computadoras.length ? 'Ningún equipo coincide con el filtro.' : 'Este laboratorio aún no tiene computadoras registradas.'">
            <template v-if="puedeGestionar && !datos.computadoras.length" #accion>
              <AppButton tamano="sm" @click="altaAbierta = true">Registrar la primera</AppButton>
            </template>
          </EmptyState>
        </template>
      </TablaComputadoras>
    </template>

    <AppModal :abierto="edicion.abierto" titulo="Editar laboratorio" @cerrar="edicion.abierto = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppInput v-model="edicion.nombre" label="Nombre" requerido :error="edicion.errores.nombre?.[0]" />
        <div class="form__grid">
          <AppInput v-model="edicion.clave" label="Clave" />
          <AppInput v-model="edicion.capacidad" type="number" min="1" label="Capacidad" />
        </div>
        <AppSelect v-model="edicion.responsable_id" label="Responsable" :opciones="[{ value: '', label: 'Sin responsable' }, ...usuarios]" />
        <AppInput v-model="edicion.descripcion" label="Descripción" />
        <div class="acciones-modal">
          <AppButton v-if="puedeEliminar" type="button" variante="peligro" :disabled="datos?.computadoras.length > 0"
            :title="datos?.computadoras.length ? 'Reubica primero sus computadoras' : ''" @click="eliminar">Eliminar laboratorio</AppButton>
          <AppButton type="submit" :cargando="edicion.guardando">Guardar</AppButton>
        </div>
      </form>
    </AppModal>

    <ComputadoraFormModal :abierto="altaAbierta" :catalogos="catalogos" :laboratorios="laboratoriosDisponibles" :laboratorio-id="Number(id)"
      @cerrar="altaAbierta = false" @guardado="alGuardar" />
    <ImportarComputadorasModal :abierto="importarAbierto" :laboratorios="laboratoriosDisponibles" :laboratorio-id="Number(id)"
      @cerrar="importarAbierto = false" @importado="cargar" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
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

const props = defineProps({ id: { type: [String, Number], required: true } });
const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const permisos = computed(() => auth.usuario?.permisos || []);
const puedeGestionar = computed(() => auth.esAdministrador || permisos.value.includes('activos.crear') || permisos.value.includes('activos.editar'));
const puedeEliminar = computed(() => auth.esAdministrador || permisos.value.includes('activos.eliminar'));

const datos = ref(null);
const catalogos = ref({});
const laboratoriosDisponibles = ref([]);
const usuarios = ref([]);
const cargando = ref(true);
const exportando = ref(false);
const altaAbierta = ref(false);
const importarAbierto = ref(false);
const buscar = ref('');
const estado = ref('');

const filtrados = computed(() => {
    const t = buscar.value.trim().toLowerCase();
    return (datos.value?.computadoras || []).filter((c) => (!estado.value || c.estado_operativo === estado.value)
        && (!t || [c.nombre_equipo, c.numero_inventario, c.numero_serie, c.direccion_mac, c.direccion_ip].some((v) => v && v.toLowerCase().includes(t))));
});

async function cargar() {
    try {
        const [{ data: l }, { data: c }, { data: r }] = await Promise.all([
            inventarioApi.laboratorio(props.id), inventarioApi.catalogos(), inventarioApi.resumen(),
        ]);
        datos.value = l.data;
        catalogos.value = c.data;
        laboratoriosDisponibles.value = r.data.edificios.flatMap((e) => e.laboratorios.map((x) => ({ id: x.id, nombre: x.nombre, edificio: e.nombre })));
    } catch (e) {
        toast.error(e.message || 'No se pudo cargar el laboratorio.');
    } finally {
        cargando.value = false;
    }
}

async function exportar() {
    exportando.value = true;
    try {
        await inventarioApi.exportar({ laboratorio_id: props.id }, `inventario-${(datos.value?.nombre || 'laboratorio').toLowerCase().replace(/\s+/g, '-')}.xlsx`);
    } catch (e) { toast.error(e.message); } finally { exportando.value = false; }
}

function alGuardar(equipo) {
    altaAbierta.value = false;
    toast.exito('Computadora registrada.');
    router.push({ name: 'inventario.computadora', params: { uuid: equipo.uuid } });
}

const edicion = reactive({ abierto: false, nombre: '', clave: '', capacidad: '', responsable_id: '', descripcion: '', guardando: false, errores: {} });
async function abrirEdicion() {
    const d = datos.value;
    Object.assign(edicion, { abierto: true, nombre: d.nombre, clave: d.clave || '', capacidad: d.capacidad || '', responsable_id: d.responsable?.id || '', descripcion: d.descripcion || '', errores: {} });
    if (!usuarios.value.length) {
        try { const { data } = await catalogosApi.usuarios(); usuarios.value = data.data.map((u) => ({ value: u.id, label: u.nombre })); } catch { /* opcional */ }
    }
}
async function guardarEdicion() {
    edicion.guardando = true;
    edicion.errores = {};
    try {
        await inventarioApi.actualizarLaboratorio(props.id, {
            nombre: edicion.nombre, clave: edicion.clave || null, capacidad: edicion.capacidad ? Number(edicion.capacidad) : null,
            responsable_id: edicion.responsable_id ? Number(edicion.responsable_id) : null, descripcion: edicion.descripcion || null,
        });
        toast.exito('Laboratorio actualizado.');
        edicion.abierto = false;
        await cargar();
    } catch (e) {
        edicion.errores = e.details || {};
        toast.error(Object.values(edicion.errores)[0]?.[0] || e.message);
    } finally {
        edicion.guardando = false;
    }
}
async function eliminar() {
    if (!window.confirm(`¿Eliminar el laboratorio «${datos.value.nombre}»?`)) return;
    try {
        await inventarioApi.eliminarLaboratorio(props.id);
        toast.exito('Laboratorio eliminado.');
        router.push({ name: 'inventario' });
    } catch (e) {
        toast.error(Object.values(e.details || {})[0]?.[0] || e.message);
    }
}

onMounted(cargar);
watch(() => props.id, () => { cargando.value = true; cargar(); });
</script>

<style scoped>
.migas { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: var(--text-sm); color: var(--text-muted); margin-bottom: var(--space-3); }
.subtitulo { margin: 4px 0 0; color: var(--text-muted); font-size: var(--text-sm); }
button.boton-descarga { cursor: pointer; font-family: inherit; }
button.boton-descarga:disabled { opacity: 0.6; cursor: progress; }
.acciones-modal { display: flex; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; }
</style>
