<template>
  <div>
    <div class="encabezado">
      <h1>Gestión Documental</h1>
      <AppButton icono="mas" @click="modalAbierto = true">Subir documento</AppButton>
    </div>

    <div class="filtros">
      <AppSelect v-model="filtroEstado" placeholder="Todos los estados" :opciones="opcionesEstado" @update:modelValue="() => { pagina = 1; cargar(); }" />
    </div>

    <AppTable :columnas="columnas" :filas="documentos" :cargando="cargando" :paginacion="meta" clicable
      @fila-click="(f) => $router.push({ name: 'documentos.detalle', params: { uuid: f.uuid } })"
      @cambiar-pagina="cambiarPagina">
      <template #col-estado="{ fila }"><AppBadge :tono="tonoEstado(fila.estado)">{{ fila.estado }}</AppBadge></template>
      <template #vacio>
        <EmptyState texto="No hay documentos con este filtro.">
          <template #accion><AppButton tamano="sm" @click="modalAbierto = true">Subir el primero</AppButton></template>
        </EmptyState>
      </template>
    </AppTable>

    <AppModal :abierto="modalAbierto" titulo="Subir documento" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="crear">
        <AppInput v-model="nuevo.titulo" label="Título" requerido />
        <label class="form__campo"><span>Archivo (PDF/Word/Excel)</span>
          <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.xlsm" required @change="(e) => nuevo.archivo = e.target.files[0]" />
        </label>
        <AppInput v-model.number="nuevo.dias_vigencia" type="number" label="Días de vigencia (opcional)" ayuda="Para alertar vencimiento" />
        <AppButton type="submit" :cargando="creando" style="width:100%">Subir</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import documentosApi from '../../api/documentos';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const columnas = [
    { key: 'titulo', label: 'Título' },
    { key: 'estado', label: 'Estado', width: '130px' },
    { key: 'version_actual', label: 'Versión', width: '90px' },
];
const opcionesEstado = ['borrador', 'en_revision', 'aprobado', 'publicado', 'obsoleto'].map((v) => ({ value: v, label: v }));

const documentos = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const modalAbierto = ref(false);
const creando = ref(false);
const filtroEstado = ref('');
const toast = useToastStore();
const nuevo = reactive({ titulo: '', archivo: null, dias_vigencia: null });

function tonoEstado(e) {
    return { borrador: 'neutral', en_revision: 'advertencia', aprobado: 'signal', publicado: 'exito', obsoleto: 'riesgo' }[e] || 'neutral';
}

function cambiarPagina(nuevaPagina) {
    pagina.value = nuevaPagina;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await documentosApi.listar({
            ...(filtroEstado.value ? { estado: filtroEstado.value } : {}),
            page: pagina.value,
        });
        documentos.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

async function crear() {
    creando.value = true;
    try {
        const fd = new FormData();
        fd.append('titulo', nuevo.titulo);
        fd.append('archivo', nuevo.archivo);
        if (nuevo.dias_vigencia) fd.append('dias_vigencia', nuevo.dias_vigencia);
        await documentosApi.crear(fd);
        toast.exito('Documento subido en estado Borrador.');
        modalAbierto.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        creando.value = false;
    }
}

onMounted(cargar);
</script>

<style scoped>
/* Estilos de encabezado, filtros y formularios: assets/styles/paginas.css */
</style>
