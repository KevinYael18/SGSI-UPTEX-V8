<template>
  <div>
    <div class="encabezado">
      <h1>Usuarios</h1>
      <AppButton icono="mas" @click="abrirModalCreacion">Nuevo usuario</AppButton>
    </div>

    <AppTable :columnas="columnas" :filas="usuarios" :cargando="cargando" :paginacion="meta" @cambiar-pagina="cambiarPagina">
      <template #col-estado="{ fila }">
        <AppBadge :tono="fila.estado === 'activo' ? 'exito' : 'riesgo'">{{ fila.estado }}</AppBadge>
      </template>
      <template #col-acciones="{ fila }">
        <div class="acciones-fila">
          <AppButton tamano="sm" variante="secundaria" icono="editar" @click="abrirModalEdicion(fila)">Editar</AppButton>
          <AppButton
            tamano="sm"
            :variante="fila.estado === 'activo' ? 'peligro' : 'secundaria'"
            :disabled="fila.uuid === auth.usuario?.uuid"
            @click="alternarEstado(fila)"
          >
            {{ fila.estado === 'activo' ? 'Suspender' : 'Reactivar' }}
          </AppButton>
        </div>
      </template>
      <template #vacio>
        <EmptyState texto="No hay usuarios registrados." />
      </template>
    </AppTable>

    <AppModal :abierto="modalAbierto" titulo="Nuevo usuario" @cerrar="modalAbierto = false">
      <form class="form" @submit.prevent="crear">
        <AppInput v-model="nuevo.nombre" label="Nombre completo" requerido />
        <AppInput v-model="nuevo.email" type="email" label="Correo institucional" requerido />
        <AppInput v-model="nuevo.password" type="password" label="Contraseña (mínimo 12 caracteres)" requerido ayuda="Solo tú la verás una vez; comunícasela al usuario por un canal seguro." />
        <AppSelect v-model="nuevo.rol_id" label="Rol" requerido placeholder="Seleccione un rol…" :opciones="opcionesRoles" />
        <AppButton type="submit" :cargando="creando" style="width:100%">Crear usuario</AppButton>
      </form>
    </AppModal>

    <AppModal :abierto="modalEdicion" titulo="Editar usuario" @cerrar="modalEdicion = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppInput v-model="edicion.nombre" label="Nombre completo" requerido />
        <AppInput v-model="edicion.email" type="email" label="Correo institucional" requerido />
        <AppInput v-model="edicion.password" type="password" label="Nueva contraseña (opcional)" ayuda="Déjalo vacío para no cambiarla." />
        <AppSelect v-model="edicion.rol_id" label="Rol" requerido :opciones="opcionesRoles" />
        <AppButton type="submit" :cargando="guardando" style="width:100%">Guardar cambios</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import usuariosApi from '../../api/usuarios';
import rolesApi from '../../api/roles';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const columnas = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'email', label: 'Correo' },
    { key: 'rol', label: 'Rol', width: '160px' },
    { key: 'estado', label: 'Estado', width: '110px' },
    { key: 'acciones', label: '', width: '220px' },
];

const auth = useAuthStore();
const toast = useToastStore();

const usuarios = ref([]);
const meta = ref(null);
const pagina = ref(1);
const cargando = ref(false);
const opcionesRoles = ref([]);

const modalAbierto = ref(false);
const creando = ref(false);
const modalEdicion = ref(false);
const guardando = ref(false);
const usuarioEditando = ref(null);

const nuevo = reactive({ nombre: '', email: '', password: '', rol_id: '' });
const edicion = reactive({ nombre: '', email: '', password: '', rol_id: '' });

async function cargarRoles() {
    try {
        const { data } = await rolesApi.listar();
        opcionesRoles.value = data.data.map((r) => ({ value: r.id, label: r.nombre }));
    } catch (e) {
        toast.error('No se pudo cargar el catálogo de roles.');
    }
}

function abrirModalCreacion() {
    Object.assign(nuevo, { nombre: '', email: '', password: '', rol_id: '' });
    modalAbierto.value = true;
}

function abrirModalEdicion(fila) {
    usuarioEditando.value = fila;
    edicion.nombre = fila.nombre;
    edicion.email = fila.email;
    edicion.password = '';
    edicion.rol_id = opcionesRoles.value.find((r) => r.label === fila.rol)?.value ?? '';
    modalEdicion.value = true;
}

function cambiarPagina(nuevaPagina) {
    pagina.value = nuevaPagina;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await usuariosApi.listar({ page: pagina.value });
        usuarios.value = data.data;
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
        await usuariosApi.crear(nuevo);
        toast.exito('Usuario creado.');
        modalAbierto.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        creando.value = false;
    }
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        const payload = { ...edicion };
        if (!payload.password) delete payload.password;
        await usuariosApi.actualizar(usuarioEditando.value.uuid, payload);
        toast.exito('Usuario actualizado.');
        modalEdicion.value = false;
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        guardando.value = false;
    }
}

async function alternarEstado(fila) {
    try {
        await usuariosApi.suspender(fila.uuid);
        toast.exito(fila.estado === 'activo' ? 'Usuario suspendido.' : 'Usuario reactivado.');
        await cargar();
    } catch (e) {
        toast.error(e.message);
    }
}

onMounted(() => {
    cargar();
    cargarRoles();
});
</script>

<style scoped>
.acciones-fila { display: flex; gap: var(--space-2); flex-wrap: wrap; justify-content: flex-end; }
@media (min-width: 721px) { .acciones-fila { justify-content: flex-start; } }
</style>
