<template>
  <div v-if="documento" class="detalle">
    <div class="encabezado">
      <div>
        <h1>{{ documento.titulo }}</h1>
        <AppBadge :tono="tonoEstado(documento.estado)">{{ documento.estado }}</AppBadge>
        <span class="version">v{{ documento.version_actual }}</span>
      </div>
      <div class="acciones">
        <AppSelect
          v-if="siguientes.length"
          :model-value="''"
          placeholder="Cambiar estado…"
          :opciones="siguientes.map((e) => ({ value: e, label: e }))"
          @update:modelValue="cambiarEstado"
        />
        <AppButton tamano="sm" variante="secundaria" icono="capas" @click="modalVersion = true">Subir nueva versión</AppButton>
        <AppButton v-if="puedeEditar" tamano="sm" variante="secundaria" icono="editar" @click="abrirModalEdicion">Editar título</AppButton>
        <AppButton v-if="puedeEliminar" tamano="sm" variante="peligro" icono="papelera" @click="confirmarEliminar = true">Eliminar</AppButton>
      </div>
    </div>

    <AppCard titulo="Historial de versiones" icono="capas">
      <div class="tabla-versiones__scroll">
      <table class="tabla-versiones">
        <thead><tr><th>Versión</th><th>Subida por</th><th>Aprobada por</th><th>Vigencia</th></tr></thead>
        <tbody>
          <tr v-for="v in documento.versiones" :key="v.numero_version">
            <td data-label="Versión"><span class="version-chip">v{{ v.numero_version }}</span></td>
            <td data-label="Subida por">{{ v.creado_por || '—' }}</td>
            <td data-label="Aprobada por">{{ v.aprobado_por || '—' }}</td>
            <td data-label="Vigencia">{{ v.fecha_expiracion || 'sin vencimiento' }}</td>
          </tr>
        </tbody>
      </table>
      </div>
    </AppCard>

    <!-- SGSI Intelligence Assistant: análisis de la versión vigente (no modifica el documento). -->
    <IaAnalisisDocumento v-if="puedeUsarIa && documento.versiones?.length" :uuid="uuid" class="ia-en-detalle" />

    <AppCard style="margin-top: var(--space-5)"><TimelinePanel tipo="documentos" :id="uuid" /></AppCard>

    <AppModal :abierto="modalVersion" titulo="Nueva versión" @cerrar="modalVersion = false">
      <form class="form" @submit.prevent="subirVersion">
        <label class="form__campo"><span>Archivo</span>
          <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.xlsm" required @change="(e) => archivoVersion = e.target.files[0]" />
        </label>
        <AppButton type="submit" :cargando="subiendo" style="width:100%">Subir</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="modalEdicion" titulo="Editar título del documento" @cerrar="modalEdicion = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppInput v-model="tituloEditado" label="Título" requerido />
        <p class="form__nota">Solo el título es editable aquí — para cambiar el contenido, sube una nueva versión.</p>
        <AppButton type="submit" :cargando="guardando" style="width:100%">Guardar cambios</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="confirmarEliminar" titulo="Eliminar documento" @cerrar="confirmarEliminar = false">
      <p>¿Seguro que deseas eliminar <strong>{{ documento.titulo }}</strong> y todo su historial de versiones? Esta acción no se puede deshacer.</p>
      <template #footer>
        <AppButton variante="secundaria" @click="confirmarEliminar = false">Cancelar</AppButton>
        <AppButton variante="peligro" :cargando="eliminando" @click="eliminar">Sí, eliminar</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import documentosApi from '../../api/documentos';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import TimelinePanel from '../../components/TimelinePanel.vue';
import IaAnalisisDocumento from '../../components/ia/IaAnalisisDocumento.vue';

const props = defineProps({ uuid: { type: String, required: true } });
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();
const puedeUsarIa = computed(() => (auth.usuario?.permisos ?? []).includes('ia.usar'));

const documento = ref(null);
const modalVersion = ref(false);
const archivoVersion = ref(null);
const subiendo = ref(false);

const modalEdicion = ref(false);
const tituloEditado = ref('');
const guardando = ref(false);
const confirmarEliminar = ref(false);
const eliminando = ref(false);

const puedeEditar = computed(() => (auth.usuario?.permisos ?? []).includes('documentos.editar'));
const puedeEliminar = computed(() => (auth.usuario?.permisos ?? []).includes('documentos.eliminar'));

const transiciones = {
    borrador: ['en_revision'],
    en_revision: ['aprobado', 'borrador'],
    aprobado: ['publicado', 'obsoleto'],
    publicado: ['obsoleto'],
    obsoleto: [],
};
const siguientes = computed(() => transiciones[documento.value?.estado] || []);

function tonoEstado(e) {
    return { borrador: 'neutral', en_revision: 'advertencia', aprobado: 'signal', publicado: 'exito', obsoleto: 'riesgo' }[e] || 'neutral';
}

async function cargar() {
    const { data } = await documentosApi.obtener(props.uuid);
    documento.value = data.data;
}

async function cambiarEstado(nuevoEstado) {
    try {
        await documentosApi.cambiarEstado(props.uuid, nuevoEstado);
        toast.exito(`Estado actualizado a "${nuevoEstado}".`);
        await cargar();
    } catch (e) {
        toast.error(e.message);
    }
}

async function subirVersion() {
    subiendo.value = true;
    try {
        const fd = new FormData();
        fd.append('archivo', archivoVersion.value);
        await documentosApi.nuevaVersion(props.uuid, fd);
        toast.exito('Nueva versión subida. El documento vuelve a "En revisión".');
        modalVersion.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        subiendo.value = false;
    }
}

function abrirModalEdicion() {
    tituloEditado.value = documento.value.titulo;
    modalEdicion.value = true;
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        await documentosApi.actualizar(props.uuid, tituloEditado.value);
        toast.exito('Título actualizado.');
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
        await documentosApi.eliminar(props.uuid);
        toast.exito('Documento eliminado.');
        router.push({ name: 'documentos.lista' });
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
.encabezado > div:first-child { display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-2); }
.encabezado > div:first-child h1 { flex-basis: 100%; margin-bottom: var(--space-1); }
.version {
    color: var(--text-muted); font-family: var(--font-mono); font-size: var(--text-xs); font-weight: 500;
    padding: 3px 8px; border-radius: 999px; background: var(--bg-subtle); border: 1px solid var(--border);
}
.acciones :deep(.app-field) { min-width: 190px; }
.tabla-versiones__scroll { overflow-x: auto; margin: calc(var(--space-5) * -1); }
.tabla-versiones { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
.tabla-versiones th {
    text-align: left; color: var(--text-muted); font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
    padding: 12px 20px; background: var(--bg-subtle); border-bottom: 1px solid var(--border); white-space: nowrap;
}
.tabla-versiones td { padding: 12px 20px; border-bottom: 1px solid var(--border); }
.tabla-versiones tbody tr:last-child td { border-bottom: none; }
.tabla-versiones tbody tr:hover { background: var(--surface-hover); }
.version-chip {
    display: inline-block; font-family: var(--font-mono); font-size: var(--text-xs); font-weight: 600;
    padding: 2px 8px; border-radius: 6px; background: var(--c-primary-soft); color: var(--c-primary);
}
@media (max-width: 640px) {
    .acciones :deep(.app-field) { min-width: 0; flex: 1 1 100%; }
    .tabla-versiones__scroll { margin: calc(var(--space-4) * -1); }
    .tabla-versiones thead { display: none; }
    .tabla-versiones, .tabla-versiones tbody, .tabla-versiones tr, .tabla-versiones td { display: block; }
    .tabla-versiones tr { padding: 6px 16px; border-bottom: 1px solid var(--border); }
    .tabla-versiones tbody tr:last-child { border-bottom: none; }
    .tabla-versiones td { display: flex; justify-content: space-between; gap: var(--space-3); padding: 6px 0; border: none; text-align: right; }
    .tabla-versiones td::before {
        content: attr(data-label); font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); text-align: left;
    }
}
</style>
