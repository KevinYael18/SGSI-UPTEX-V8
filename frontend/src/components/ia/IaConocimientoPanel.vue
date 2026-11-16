<template>
  <div class="ia-kb">
    <AppCard titulo="Agregar documento autorizado" icono="libro" subtitulo="Políticas, procedimientos y documentación técnica que el asistente puede consultar y citar">
      <form class="ia-kb__form" @submit.prevent="cargar">
        <AppInput v-model="nuevo.titulo" label="Título" requerido maxlength="200" placeholder="Ej. Política de control de acceso UPTex" />
        <AppSelect v-model="nuevo.categoria" label="Categoría" requerido :opciones="CATEGORIAS" placeholder="Seleccione…" />
        <AppInput v-model="nuevo.descripcion" label="Descripción (opcional)" maxlength="500" />
        <label class="ia-kb__archivo">
          <span>Archivo (PDF, DOCX, XLSX, TXT, MD o CSV)</span>
          <input ref="refArchivo" type="file" accept=".pdf,.docx,.xlsx,.xlsm,.xls,.txt,.md,.csv" required @change="(e) => (archivo = e.target.files[0])" />
        </label>
        <p class="ia-kb__nota">
          <AppIcon name="info" :size="14" />
          Solo cargue material que la institución esté autorizada a usar. Las normas ISO tienen derechos de autor: no cargue su texto completo sin licencia.
          El texto se divide en fragmentos; los que contengan instrucciones dirigidas a una IA se excluyen automáticamente.
        </p>
        <AppButton type="submit" icono="mas" :cargando="cargando">Agregar a la base de conocimiento</AppButton>
      </form>
    </AppCard>

    <AppCard titulo="Documentos indexados" icono="documentos">
      <EmptyState v-if="!documentos.length" texto="Todavía no hay documentos. El asistente responde con su conocimiento general y los datos del sistema." />
      <ul v-else class="ia-kb__lista">
        <li v-for="d in documentos" :key="d.uuid" class="ia-kb__item" :class="{ 'ia-kb__item--inactivo': !d.activo }">
          <div class="ia-kb__info">
            <strong>{{ d.titulo }}</strong>
            <span class="ia-kb__meta">
              <AppBadge tono="neutral">{{ d.categoria }}</AppBadge>
              <AppBadge :tono="d.estado === 'listo' ? 'exito' : d.estado === 'error' ? 'riesgo' : 'advertencia'">{{ d.estado }}</AppBadge>
              {{ d.total_fragmentos }} fragmentos
              <template v-if="d.fragmentos_sospechosos"> · <span class="ia-kb__sospechoso">{{ d.fragmentos_sospechosos }} excluido(s) por seguridad</span></template>
            </span>
            <small v-if="d.aviso">{{ d.aviso }}</small>
          </div>
          <div class="ia-kb__acciones">
            <label class="ia-kb__switch" :title="d.activo ? 'Desactivar como fuente' : 'Activar como fuente'">
              <input type="checkbox" :checked="d.activo" :disabled="d.estado !== 'listo'" @change="alternar(d)" /> Fuente activa
            </label>
            <AppButton tamano="sm" variante="fantasma" icono="papelera" @click="porEliminar = d">Retirar</AppButton>
          </div>
        </li>
      </ul>
    </AppCard>

    <AppModal :abierto="!!porEliminar" titulo="Retirar documento" @cerrar="porEliminar = null">
      <p>¿Retirar <strong>{{ porEliminar?.titulo }}</strong> de la base de conocimiento? El asistente dejará de consultarlo.</p>
      <template #footer>
        <AppButton variante="secundaria" @click="porEliminar = null">Cancelar</AppButton>
        <AppButton variante="peligro" :cargando="eliminando" @click="eliminar">Sí, retirar</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import iaApi from '../../api/ia';
import { useToastStore } from '../../stores/toast';
import AppCard from '../ui/AppCard.vue';
import AppInput from '../ui/AppInput.vue';
import AppSelect from '../ui/AppSelect.vue';
import AppButton from '../ui/AppButton.vue';
import AppBadge from '../ui/AppBadge.vue';
import AppIcon from '../ui/AppIcon.vue';
import AppModal from '../ui/AppModal.vue';
import EmptyState from '../ui/EmptyState.vue';

const CATEGORIAS = [
    { value: 'politica', label: 'Política de seguridad' },
    { value: 'procedimiento', label: 'Procedimiento del SGSI' },
    { value: 'institucional', label: 'Documentación institucional' },
    { value: 'tecnica', label: 'Documentación técnica de GSI UPTex' },
    { value: 'normativa', label: 'Material normativo autorizado' },
];

const toast = useToastStore();
const documentos = ref([]);
const nuevo = reactive({ titulo: '', categoria: '', descripcion: '' });
const archivo = ref(null);
const refArchivo = ref(null);
const cargando = ref(false);
const porEliminar = ref(null);
const eliminando = ref(false);

async function listar() {
    const { data } = await iaApi.conocimiento();
    documentos.value = data.data;
}

async function cargar() {
    if (!archivo.value) return;
    cargando.value = true;
    try {
        const fd = new FormData();
        fd.append('archivo', archivo.value);
        fd.append('titulo', nuevo.titulo);
        fd.append('categoria', nuevo.categoria);
        if (nuevo.descripcion) fd.append('descripcion', nuevo.descripcion);
        const { data } = await iaApi.cargarConocimiento(fd);
        if (data.data.estado === 'error') {
            toast.error(`No se pudo indexar: ${data.data.aviso}`);
        } else {
            toast.exito('Documento indexado en la base de conocimiento.');
        }
        Object.assign(nuevo, { titulo: '', categoria: '', descripcion: '' });
        archivo.value = null;
        if (refArchivo.value) refArchivo.value.value = '';
        await listar();
    } catch (e) {
        toast.error(Object.values(e.details || {})[0]?.[0] || e.message);
    } finally {
        cargando.value = false;
    }
}

async function alternar(d) {
    try {
        await iaApi.activarConocimiento(d.uuid, !d.activo);
        d.activo = !d.activo;
    } catch (e) {
        toast.error(e.message);
    }
}

async function eliminar() {
    eliminando.value = true;
    try {
        await iaApi.eliminarConocimiento(porEliminar.value.uuid);
        toast.exito('Documento retirado de la base de conocimiento.');
        porEliminar.value = null;
        await listar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        eliminando.value = false;
    }
}

onMounted(listar);
</script>

<style scoped>
.ia-kb { display: grid; grid-template-columns: minmax(0, 380px) minmax(0, 1fr); gap: var(--space-5); align-items: start; }
.ia-kb__form { display: flex; flex-direction: column; gap: var(--space-3); }
.ia-kb__archivo { display: flex; flex-direction: column; gap: 6px; font-size: var(--text-sm); color: var(--text); }
.ia-kb__archivo input { font-size: var(--text-sm); color: var(--text-muted); }
.ia-kb__nota { display: flex; gap: 6px; align-items: flex-start; margin: 0; font-size: var(--text-xs); color: var(--text-muted); line-height: 1.5; }
.ia-kb__nota :deep(svg) { flex-shrink: 0; margin-top: 2px; }
.ia-kb__lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.ia-kb__item { display: flex; justify-content: space-between; gap: var(--space-3); padding: 12px 0; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.ia-kb__item:last-child { border-bottom: none; }
.ia-kb__item--inactivo { opacity: 0.6; }
.ia-kb__info { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.ia-kb__info strong { font-size: var(--text-sm); color: var(--text); }
.ia-kb__info small { font-size: var(--text-xs); color: var(--text-muted); }
.ia-kb__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: var(--text-xs); color: var(--text-muted); }
.ia-kb__sospechoso { color: var(--c-amber); font-weight: 600; }
.ia-kb__acciones { display: flex; align-items: center; gap: var(--space-2); }
.ia-kb__switch { display: inline-flex; align-items: center; gap: 6px; font-size: var(--text-xs); color: var(--text-muted); cursor: pointer; }
@media (max-width: 900px) { .ia-kb { grid-template-columns: 1fr; } }
</style>
