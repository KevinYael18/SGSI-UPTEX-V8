<template>
  <div v-if="auditoria" class="detalle">
    <div class="encabezado-principal">
      <div>
        <h1>{{ auditoria.alcance }}</h1>
        <p class="meta">
          <span class="meta__item"><AppIcon name="usuario" :size="15" />Auditor: {{ auditoria.auditor }}</span>
          <span class="meta__item"><AppIcon name="calendario" :size="15" />{{ auditoria.fecha_inicio }} → {{ auditoria.fecha_fin || 'en curso' }}</span>
        </p>
      </div>
      <div class="encabezado-principal__acciones" v-if="puedeEditar || puedeEliminar">
        <AppButton v-if="puedeEditar" tamano="sm" variante="secundaria" icono="editar" @click="abrirModalEdicion">Editar</AppButton>
        <AppButton v-if="puedeEliminar" tamano="sm" variante="peligro" icono="papelera" @click="confirmarEliminar = true">Eliminar</AppButton>
      </div>
    </div>

    <div class="encabezado">
      <h2 class="titulo-seccion"><AppIcon name="auditorias" :size="20" />Hallazgos</h2>
      <AppButton tamano="sm" icono="mas" @click="modalAbierto = true">Registrar hallazgo</AppButton>
    </div>

    <AppCard v-for="h in auditoria.hallazgos" :key="h.id" class="hallazgo" :class="`hallazgo--${h.severidad}`">
      <div class="hallazgo__header">
        <AppBadge :tono="tonoSeveridad(h.severidad)">{{ h.severidad }}</AppBadge>
        <p>{{ h.descripcion }}</p>
      </div>
      <div v-for="nc in h.no_conformidades" :key="nc.id" class="nc">
        <div class="nc__header">
          <strong>No conformidad</strong>
          <AppBadge :tono="nc.estado === 'cerrada' ? 'exito' : 'advertencia'">{{ nc.estado }}</AppBadge>
          <AppButton v-if="nc.estado !== 'cerrada'" tamano="sm" variante="secundaria" @click="cerrarNC(nc.id)">Cerrar</AppButton>
        </div>
        <ul class="acciones">
          <li v-for="ac in nc.acciones_correctivas" :key="ac.id">
            <span class="acciones__texto"><AppIcon name="usuario" :size="14" />{{ ac.responsable }} — vence {{ ac.fecha_compromiso }}</span>
            <AppBadge :tono="ac.estado === 'cerrada' ? 'exito' : ac.estado === 'vencida' ? 'riesgo' : 'advertencia'">{{ ac.estado }}</AppBadge>
          </li>
        </ul>
      </div>
    </AppCard>
    <EmptyState v-if="!auditoria.hallazgos.length" texto="Sin hallazgos registrados todavía." />

    <AppModal :abierto="modalAbierto" titulo="Registrar hallazgo" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="registrarHallazgo">
        <AppSelect v-model="nuevoHallazgo.severidad" label="Severidad" requerido :opciones="[{value:'menor',label:'Menor'},{value:'mayor',label:'Mayor'},{value:'critica',label:'Crítica'}]" />
        <label class="form__campo"><span>Descripción</span><textarea v-model="nuevoHallazgo.descripcion" rows="3" required /></label>

        <label class="form__checkbox">
          <input type="checkbox" v-model="generarNoConformidad" @change="onCambiarGenerarNC" />
          Este hallazgo requiere una no conformidad con acción correctiva
        </label>

        <template v-if="generarNoConformidad">
          <AppSelect
            v-model="nuevoHallazgo.no_conformidad.accion_correctiva.responsable_id"
            label="Responsable de la acción correctiva"
            requerido
            placeholder="Seleccione un usuario…"
            :opciones="opcionesUsuarios"
          />
          <AppInput
            v-model="nuevoHallazgo.no_conformidad.accion_correctiva.fecha_compromiso"
            type="date"
            label="Fecha compromiso"
            requerido
            ayuda="Debe ser una fecha futura."
          />
        </template>

        <AppButton type="submit" :cargando="registrando" style="width:100%">Registrar</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="modalEdicion" titulo="Editar auditoría" @cerrar="modalEdicion = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppInput v-model="edicion.alcance" label="Alcance" requerido />
        <AppInput v-model="edicion.fecha_inicio" type="date" label="Fecha de inicio" requerido />
        <AppInput v-model="edicion.fecha_fin" type="date" label="Fecha de fin (opcional)" />
        <AppSelect v-model="edicion.auditor_id" label="Auditor" requerido :opciones="opcionesUsuarios" />
        <AppButton type="submit" :cargando="guardando" style="width:100%">Guardar cambios</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="confirmarEliminar" titulo="Eliminar auditoría" @cerrar="confirmarEliminar = false">
      <p>¿Seguro que deseas eliminar esta auditoría y todos sus hallazgos asociados? Esta acción no se puede deshacer.</p>
      <template #footer>
        <AppButton variante="secundaria" @click="confirmarEliminar = false">Cancelar</AppButton>
        <AppButton variante="peligro" :cargando="eliminando" @click="eliminar">Sí, eliminar</AppButton>
      </template>
    </AppModal>

    <div class="detalle__grid">
      <AppCard><CommentsPanel tipo="auditorias" :id="id" /></AppCard>
      <AppCard><TimelinePanel tipo="auditorias" :id="id" /></AppCard>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import auditoriasApi from '../../api/auditorias';
import catalogosApi from '../../api/catalogos';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import CommentsPanel from '../../components/CommentsPanel.vue';
import TimelinePanel from '../../components/TimelinePanel.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const auditoria = ref(null);
const modalAbierto = ref(false);
const registrando = ref(false);
function valoresIniciales() {
    return {
        severidad: '',
        descripcion: '',
        no_conformidad: { accion_correctiva: { responsable_id: '', fecha_compromiso: '' } },
    };
}
const nuevoHallazgo = reactive(valoresIniciales());
const generarNoConformidad = ref(false);

async function onCambiarGenerarNC() {
    if (!generarNoConformidad.value) return;
    if (opcionesUsuarios.value.length) return;

    try {
        const { data } = await catalogosApi.usuarios();
        opcionesUsuarios.value = data.data.map((u) => ({ value: u.id, label: `${u.nombre} — ${u.email}` }));
    } catch (e) {
        toast.error('No se pudo cargar el catálogo de usuarios.');
    }
}

const opcionesUsuarios = ref([]);
const modalEdicion = ref(false);
const guardando = ref(false);
const confirmarEliminar = ref(false);
const eliminando = ref(false);
const edicion = reactive({ alcance: '', fecha_inicio: '', fecha_fin: '', auditor_id: '' });

const puedeEditar = computed(() => {
    const permisos = auth.usuario?.permisos ?? [];
    if (permisos.includes('auditorias.editar')) return true;
    return auditoria.value?.auditor_id === auth.usuario?.id;
});
const puedeEliminar = computed(() => (auth.usuario?.permisos ?? []).includes('auditorias.eliminar'));

function tonoSeveridad(s) { return { menor: 'neutral', mayor: 'advertencia', critica: 'riesgo' }[s]; }

async function cargar() {
    const { data } = await auditoriasApi.obtener(props.id);
    auditoria.value = data.data;
}

async function abrirModalEdicion() {
    edicion.alcance = auditoria.value.alcance;
    edicion.fecha_inicio = auditoria.value.fecha_inicio;
    edicion.fecha_fin = auditoria.value.fecha_fin ?? '';
    edicion.auditor_id = auditoria.value.auditor_id;

    try {
        const { data } = await catalogosApi.usuarios();
        opcionesUsuarios.value = data.data.map((u) => ({ value: u.id, label: `${u.nombre} — ${u.email}` }));
    } catch (e) {
        toast.error('No se pudo cargar el catálogo de usuarios.');
    }

    modalEdicion.value = true;
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        const payload = { ...edicion };
        if (!payload.fecha_fin) delete payload.fecha_fin;
        await auditoriasApi.actualizar(props.id, payload);
        toast.exito('Auditoría actualizada.');
        modalEdicion.value = false;
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
        await auditoriasApi.eliminar(props.id);
        toast.exito('Auditoría eliminada.');
        router.push({ name: 'auditorias.lista' });
    } catch (e) {
        toast.error(e.message);
        confirmarEliminar.value = false;
    } finally {
        eliminando.value = false;
    }
}

async function registrarHallazgo() {
    registrando.value = true;
    try {
        const payload = { severidad: nuevoHallazgo.severidad, descripcion: nuevoHallazgo.descripcion };
        if (generarNoConformidad.value) {
            payload.no_conformidad = { accion_correctiva: { ...nuevoHallazgo.no_conformidad.accion_correctiva } };
        }

        await auditoriasApi.registrarHallazgo(props.id, payload);
        toast.exito('Hallazgo registrado.');
        modalAbierto.value = false;
        Object.assign(nuevoHallazgo, valoresIniciales());
        generarNoConformidad.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        registrando.value = false;
    }
}

async function cerrarNC(id) {
    try {
        await auditoriasApi.cerrarNoConformidad(id);
        toast.exito('No conformidad cerrada.');
        await cargar();
    } catch (e) {
        toast.error(e.message);
    }
}

onMounted(cargar);
</script>

<style scoped>
.encabezado-principal { align-items: flex-start; }
.meta { display: flex; flex-wrap: wrap; gap: 6px 18px; }
.meta__item { display: inline-flex; align-items: center; gap: 6px; }
.meta__item :deep(svg) { color: var(--c-primary); }
.encabezado { margin: var(--space-6) 0 var(--space-4); }
.titulo-seccion { display: flex; align-items: center; gap: 10px; }
.titulo-seccion :deep(svg) { color: var(--c-primary); }
.hallazgo { margin-bottom: var(--space-3); border-left: 4px solid var(--c-gray-400); }
.hallazgo--menor { border-left-color: var(--c-success); }
.hallazgo--mayor { border-left-color: var(--c-amber); }
.hallazgo--critica { border-left-color: var(--c-risk); }
.hallazgo__header { display: flex; gap: var(--space-3); align-items: flex-start; flex-wrap: wrap; }
.hallazgo__header p { margin: 0; flex: 1; min-width: 200px; overflow-wrap: anywhere; }
.nc {
    background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-md);
    padding: var(--space-3) var(--space-4); margin-top: var(--space-3);
}
.nc__header { display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-2); margin-bottom: var(--space-2); font-size: var(--text-sm); }
.nc__header :deep(.app-btn) { margin-left: auto; }
.acciones { list-style: none; margin: 0; padding: 0; font-size: var(--text-sm); display: flex; flex-direction: column; gap: 6px; }
.acciones li {
    display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); flex-wrap: wrap;
    padding: 8px 10px; background: var(--surface); border-radius: var(--radius-sm); border: 1px solid var(--border);
}
.acciones__texto { display: inline-flex; align-items: center; gap: 6px; min-width: 0; color: var(--text-muted); }
.detalle__grid { margin-top: var(--space-5); }
</style>
