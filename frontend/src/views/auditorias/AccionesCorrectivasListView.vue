<template>
  <div>
    <h1>Acciones correctivas</h1>
    <p class="subtitulo">Vista transversal de todas las acciones correctivas, sin importar a qué auditoría pertenecen.</p>

    <div class="filtros">
      <AppSelect v-model="filtros.estado" placeholder="Todos los estados" :opciones="opcionesEstado" @update:modelValue="() => { pagina = 1; cargar(); }" />
      <label class="filtro-vencidas">
        <input type="checkbox" v-model="filtros.vencidas" @change="() => { pagina = 1; cargar(); }" />
        Solo vencidas
      </label>
    </div>

    <AppTable :columnas="columnas" :filas="acciones" :cargando="cargando" :paginacion="meta" @cambiar-pagina="cambiarPagina">
      <template #col-auditoria_alcance="{ fila }">
        <RouterLink v-if="fila.auditoria_id" :to="{ name: 'auditorias.detalle', params: { id: fila.auditoria_id } }">{{ fila.auditoria_alcance }}</RouterLink>
        <span v-else>—</span>
      </template>
      <template #col-estado="{ fila }">
        <AppBadge :tono="fila.vencida ? 'riesgo' : tonoEstado(fila.estado)">{{ fila.vencida ? 'vencida' : fila.estado }}</AppBadge>
      </template>
      <template #col-acciones="{ fila }">
        <AppButton v-if="fila.estado !== 'cerrada' && puedeGestionar" tamano="sm" variante="secundaria" icono="check" @click="cerrar(fila)">Marcar cerrada</AppButton>
      </template>
      <template #vacio>
        <EmptyState texto="No hay acciones correctivas con este filtro." />
      </template>
    </AppTable>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import accionesCorrectivasApi from '../../api/accionesCorrectivas';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const columnas = [
    { key: 'auditoria_alcance', label: 'Auditoría' },
    { key: 'hallazgo_descripcion', label: 'Hallazgo' },
    { key: 'responsable', label: 'Responsable' },
    { key: 'fecha_compromiso', label: 'Vence', width: '120px' },
    { key: 'estado', label: 'Estado', width: '110px' },
    { key: 'acciones', label: '', width: '140px' },
];

const opcionesEstado = [
    { value: 'abierta', label: 'Abierta' },
    { value: 'en_proceso', label: 'En proceso' },
    { value: 'cerrada', label: 'Cerrada' },
];

const auth = useAuthStore();
const toast = useToastStore();

const acciones = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const filtros = reactive({ estado: '', vencidas: false });

const puedeGestionar = computed(() => {
    const permisos = auth.usuario?.permisos ?? [];
    return permisos.includes('auditorias.editar');
});

function tonoEstado(e) {
    return { abierta: 'riesgo', en_proceso: 'advertencia', cerrada: 'exito' }[e] || 'neutral';
}

function cambiarPagina(nuevaPagina) {
    pagina.value = nuevaPagina;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const params = { page: pagina.value };
        if (filtros.estado) params.estado = filtros.estado;
        if (filtros.vencidas) params.vencidas = 1;

        const { data } = await accionesCorrectivasApi.listar(params);
        acciones.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

async function cerrar(fila) {
    try {
        await accionesCorrectivasApi.cerrar(fila.id);
        toast.exito('Acción correctiva cerrada.');
        await cargar();
    } catch (e) {
        toast.error(e.message);
    }
}

onMounted(cargar);
</script>

<style scoped>
/* Estilos de subtítulo y filtros: assets/styles/paginas.css */
</style>
