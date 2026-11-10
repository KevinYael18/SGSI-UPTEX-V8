<template>
  <div v-if="activo" class="detalle">
    <div class="encabezado">
      <div class="encabezado__titulo">
        <h1>{{ activo.nombre }}</h1>
        <AppBadge tono="signal">{{ etiquetaTipo(activo.tipo) }}</AppBadge>
      </div>
      <div class="encabezado__derecha">
        <img :src="qrUrl" alt="Código QR del activo" class="qr" />
        <div class="encabezado__acciones" v-if="puedeEditar || puedeEliminar">
          <AppButton v-if="puedeEditar" tamano="sm" variante="secundaria" icono="editar" @click="abrirModalEdicion">Editar</AppButton>
          <AppButton v-if="puedeEliminar" tamano="sm" variante="peligro" icono="papelera" @click="confirmarEliminar = true">Eliminar</AppButton>
        </div>
      </div>
    </div>

    <div class="detalle__grid">
      <AppCard titulo="Clasificación CIA" icono="escudo">
        <dl class="dl">
          <dt>Confidencialidad</dt><dd>{{ activo.cia.confidencialidad }}</dd>
          <dt>Integridad</dt><dd>{{ activo.cia.integridad }}</dd>
          <dt>Disponibilidad</dt><dd>{{ activo.cia.disponibilidad }}</dd>
          <dt>Criticidad calculada</dt><dd><AppBadge tono="riesgo">{{ activo.criticidad_calculada }}</AppBadge></dd>
        </dl>
      </AppCard>

      <AppCard titulo="Organización" icono="usuarios">
        <dl class="dl">
          <dt>Departamento</dt><dd>{{ activo.departamento }}</dd>
          <dt>Ubicación</dt><dd>{{ activo.ubicacion || '—' }}</dd>
          <dt>Responsable</dt><dd>{{ activo.responsable || '—' }}</dd>
        </dl>
      </AppCard>
    </div>

    <AppCard titulo="Historial de actividad" icono="bitacora">
      <TimelinePanel tipo="activos" :id="uuid" />
    </AppCard>

    <!-- Modal de edición: mismos selectores reales que la creación, precargados -->
    <AppModal :abierto="modalEdicion" titulo="Editar activo" @cerrar="modalEdicion = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppInput v-model="edicion.nombre" label="Nombre" requerido />
        <AppSelect v-model="edicion.tipo" label="Tipo" requerido :opciones="opcionesTipo" placeholder="Seleccione…" />

        <div class="form__cia">
          <AppInput v-model.number="edicion.confidencialidad" type="number" min="1" max="5" label="Confidencialidad (1-5)" requerido />
          <AppInput v-model.number="edicion.integridad" type="number" min="1" max="5" label="Integridad (1-5)" requerido />
          <AppInput v-model.number="edicion.disponibilidad" type="number" min="1" max="5" label="Disponibilidad (1-5)" requerido />
        </div>

        <AppSelect
          v-model="edicion.ubicacion_id"
          label="Ubicación (opcional)"
          :placeholder="'Sin ubicación específica'"
          :opciones="opcionesUbicaciones"
        />

        <AppSelect
          v-model="edicion.responsable_id"
          label="Responsable (opcional)"
          placeholder="Sin responsable asignado"
          :opciones="opcionesUsuarios"
        />

        <AppButton type="submit" :cargando="guardando" style="width:100%">Guardar cambios</AppButton>
      </form>
    </AppModal>

    <!-- Confirmación de eliminación -->
    <AppModal :abierto="confirmarEliminar" titulo="Eliminar activo" @cerrar="confirmarEliminar = false">
      <p>¿Seguro que deseas eliminar <strong>{{ activo.nombre }}</strong>? Esta acción no se puede deshacer.</p>
      <p v-if="activo.criticidad_calculada" class="detalle__nota">
        Si este activo tiene riesgos abiertos asociados, el sistema rechazará la eliminación hasta que se traten o cierren.
      </p>
      <template #footer>
        <AppButton variante="secundaria" @click="confirmarEliminar = false">Cancelar</AppButton>
        <AppButton variante="peligro" :cargando="eliminando" @click="eliminar">Sí, eliminar</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import activosApi from '../../api/activos';
import catalogosApi from '../../api/catalogos';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import TimelinePanel from '../../components/TimelinePanel.vue';

const props = defineProps({ uuid: { type: String, required: true } });
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const activo = ref(null);
const qrUrl = computed(() => activosApi.qrUrl(props.uuid));

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

// Espeja las reglas reales de ActivoPolicy del backend — el backend sigue
// siendo quien realmente autoriza; esto solo evita mostrar botones que de
// todas formas serían rechazados con 403.
const puedeEditar = computed(() => {
    const permisos = auth.usuario?.permisos ?? [];
    if (permisos.includes('activos.editar')) return true;
    return permisos.includes('activos.editar_propio') && activo.value?.responsable_id === auth.usuario?.id;
});
const puedeEliminar = computed(() => (auth.usuario?.permisos ?? []).includes('activos.eliminar'));

const opcionesUsuarios = ref([]);
const opcionesUbicaciones = ref([]);

const modalEdicion = ref(false);
const guardando = ref(false);
const confirmarEliminar = ref(false);
const eliminando = ref(false);

const edicion = reactive({
    nombre: '', tipo: '', confidencialidad: 3, integridad: 3, disponibilidad: 3,
    ubicacion_id: '', responsable_id: '',
});

async function cargar() {
    const { data } = await activosApi.obtener(props.uuid);
    activo.value = data.data;
}

async function abrirModalEdicion() {
    edicion.nombre = activo.value.nombre;
    edicion.tipo = activo.value.tipo;
    edicion.confidencialidad = activo.value.cia.confidencialidad;
    edicion.integridad = activo.value.cia.integridad;
    edicion.disponibilidad = activo.value.cia.disponibilidad;
    edicion.ubicacion_id = activo.value.ubicacion_id ?? '';
    edicion.responsable_id = activo.value.responsable_id ?? '';

    try {
        const [{ data: ubiRes }, { data: usrRes }] = await Promise.all([
            catalogosApi.ubicaciones(activo.value.departamento_id),
            catalogosApi.usuarios(),
        ]);
        opcionesUbicaciones.value = ubiRes.data.map((u) => ({ value: u.id, label: u.nombre }));
        opcionesUsuarios.value = usrRes.data.map((u) => ({ value: u.id, label: `${u.nombre} — ${u.email}` }));
    } catch (e) {
        toast.error('No se pudieron cargar los catálogos.');
    }

    modalEdicion.value = true;
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        const payload = { ...edicion };
        if (!payload.ubicacion_id) delete payload.ubicacion_id;
        if (!payload.responsable_id) delete payload.responsable_id;

        await activosApi.actualizar(props.uuid, payload);
        toast.exito('Activo actualizado.');
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
        await activosApi.eliminar(props.uuid);
        toast.exito('Activo eliminado.');
        router.push({ name: 'activos.lista' });
    } catch (e) {
        // Mensaje específico de ActivoConRiesgosAsociadosException si aplica
        // (código ACTIVO_CON_RIESGOS_ABIERTOS, HTTP 409) o el genérico de permisos.
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
.encabezado__derecha { flex-direction: row; align-items: center; }
.qr {
    width: 104px; height: 104px; border: 1px solid var(--border); border-radius: var(--radius-md);
    background: #fff; padding: 6px; box-shadow: var(--shadow-sm);
}
.encabezado__acciones { flex-direction: column; align-items: stretch; }
@media (max-width: 640px) {
    .encabezado__derecha { flex-direction: row; justify-content: space-between; }
    .encabezado__acciones { flex: 1; }
}
</style>
