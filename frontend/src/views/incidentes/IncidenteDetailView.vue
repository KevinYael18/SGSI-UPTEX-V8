<template>
  <div v-if="incidente" class="detalle">
    <div class="encabezado">
      <div class="encabezado__titulo">
        <h1>{{ incidente.titulo }}</h1>
        <AppBadge :tono="tonoEstado(incidente.estado)">{{ incidente.estado }}</AppBadge>
      </div>
      <div class="encabezado__derecha">
        <AppSelect
          v-if="siguientesEstados.length"
          :model-value="''"
          placeholder="Cambiar estado…"
          :opciones="siguientesEstados.map((e) => ({ value: e, label: e }))"
          @update:modelValue="cambiarEstado"
        />
        <div class="encabezado__acciones" v-if="puedeEditar || puedeEliminar">
          <AppButton v-if="puedeEditar" tamano="sm" variante="secundaria" icono="editar" @click="abrirModalEdicion">Editar</AppButton>
          <AppButton v-if="puedeEliminar" tamano="sm" variante="peligro" icono="papelera" @click="confirmarEliminar = true">Eliminar</AppButton>
        </div>
      </div>
    </div>

    <p class="descripcion">{{ incidente.descripcion || 'Sin descripción adicional.' }}</p>

    <div class="detalle__grid">
      <AppCard titulo="Evidencias" icono="documentos">
        <EvidenciasGaleria :evidencias="incidente.evidencias" />
      </AppCard>

      <AppCard titulo="Detalle" icono="info">
        <dl class="dl">
          <dt>Activo</dt><dd>{{ incidente.activo || '—' }}</dd>
          <dt>Reportado por</dt><dd>{{ incidente.reportado_por }}</dd>
          <dt>Detectado</dt><dd>{{ new Date(incidente.fecha_deteccion).toLocaleString('es-MX') }}</dd>
        </dl>
      </AppCard>
    </div>

    <div class="detalle__grid">
      <AppCard><CommentsPanel tipo="incidentes" :id="uuid" /></AppCard>
      <AppCard><TimelinePanel tipo="incidentes" :id="uuid" /></AppCard>
    </div>

    <AppModal :abierto="modalEdicion" titulo="Editar incidente" @cerrar="modalEdicion = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppInput v-model="edicion.titulo" label="Título" requerido />
        <label class="form__campo"><span>Descripción</span><textarea v-model="edicion.descripcion" rows="3" /></label>
        <AppSelect v-model="edicion.activo_id" label="Activo afectado (opcional)" placeholder="Sin activo asociado" :opciones="opcionesActivos" />
        <AppButton type="submit" :cargando="guardando" style="width:100%">Guardar cambios</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="confirmarEliminar" titulo="Eliminar incidente" @cerrar="confirmarEliminar = false">
      <p>¿Seguro que deseas eliminar este incidente? Esta acción no se puede deshacer.</p>
      <template #footer>
        <AppButton variante="secundaria" @click="confirmarEliminar = false">Cancelar</AppButton>
        <AppButton variante="peligro" :cargando="eliminando" @click="eliminar">Sí, eliminar</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import incidentesApi from '../../api/incidentes';
import catalogosApi from '../../api/catalogos';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import CommentsPanel from '../../components/CommentsPanel.vue';
import TimelinePanel from '../../components/TimelinePanel.vue';
import EvidenciasGaleria from '../../components/EvidenciasGaleria.vue';

const props = defineProps({ uuid: { type: String, required: true } });
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const incidente = ref(null);
const opcionesActivos = ref([]);

const transiciones = {
    abierto: ['en_analisis', 'cerrado'],
    en_analisis: ['contenido', 'cerrado'],
    contenido: ['resuelto'],
    resuelto: ['cerrado'],
    cerrado: [],
};
const siguientesEstados = computed(() => transiciones[incidente.value?.estado] || []);

const puedeEditar = computed(() => {
    const permisos = auth.usuario?.permisos ?? [];
    if (permisos.includes('incidentes.gestionar')) return true;
    return incidente.value?.activo_responsable_id === auth.usuario?.id;
});
const puedeEliminar = computed(() => (auth.usuario?.permisos ?? []).includes('incidentes.eliminar'));

const modalEdicion = ref(false);
const guardando = ref(false);
const confirmarEliminar = ref(false);
const eliminando = ref(false);
const edicion = reactive({ titulo: '', descripcion: '', activo_id: '' });

function tonoEstado(e) {
    return { abierto: 'riesgo', en_analisis: 'advertencia', contenido: 'advertencia', resuelto: 'signal', cerrado: 'exito' }[e] || 'neutral';
}

async function cargar() {
    const { data } = await incidentesApi.obtener(props.uuid);
    incidente.value = data.data;
}

async function abrirModalEdicion() {
    edicion.titulo = incidente.value.titulo;
    edicion.descripcion = incidente.value.descripcion ?? '';
    edicion.activo_id = incidente.value.activo_id ?? '';

    try {
        const { data } = await catalogosApi.activos();
        opcionesActivos.value = data.data.map((a) => ({ value: a.id, label: a.nombre }));
    } catch (e) {
        toast.error('No se pudo cargar el catálogo de activos.');
    }

    modalEdicion.value = true;
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        const payload = { ...edicion };
        if (!payload.activo_id) payload.activo_id = null;
        await incidentesApi.actualizar(props.uuid, payload);
        toast.exito('Incidente actualizado.');
        modalEdicion.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        guardando.value = false;
    }
}

async function cambiarEstado(nuevoEstado) {
    try {
        await incidentesApi.cambiarEstado(props.uuid, nuevoEstado);
        toast.exito(`Estado actualizado a "${nuevoEstado}".`);
        await cargar();
    } catch (e) {
        toast.error(e.message);
    }
}

async function eliminar() {
    eliminando.value = true;
    try {
        await incidentesApi.eliminar(props.uuid);
        toast.exito('Incidente eliminado.');
        router.push({ name: 'incidentes.lista' });
    } catch (e) {
        toast.error(e.message);
        confirmarEliminar.value = false;
    } finally {
        eliminando.value = false;
    }
}

onMounted(cargar);
</script>

<style scoped>
.encabezado { align-items: flex-start; }
.encabezado__titulo h1 { margin-bottom: var(--space-2); }
.encabezado__derecha { min-width: 220px; }
.encabezado__derecha :deep(.app-field) { width: 100%; }
@media (max-width: 640px) {
    .encabezado__derecha { min-width: 0; }
}
</style>
