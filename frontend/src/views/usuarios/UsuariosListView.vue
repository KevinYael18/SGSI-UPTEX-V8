<template>
  <div>
    <div class="encabezado">
      <h1>Usuarios</h1>
      <AppButton icono="mas" @click="abrirModalCreacion">Nuevo usuario</AppButton>
    </div>

    <AppTable :columnas="columnas" :filas="usuarios" :cargando="cargando" :paginacion="meta" @cambiar-pagina="cambiarPagina">
      <template #col-estado="{ fila }">
        <div class="estado-cuenta">
          <AppBadge :tono="fila.estado === 'activo' ? 'exito' : 'riesgo'">{{ fila.estado === 'activo' ? 'Activo' : 'Suspendido' }}</AppBadge>
          <span v-if="fila.suspension" class="estado-cuenta__detalle" :title="fila.suspension.motivo || ''">
            <template v-if="fila.suspension.desde">Desde {{ formatearFecha(fila.suspension.desde) }}</template>
            <template v-else>Suspendida antes del registro de fechas</template>
            <template v-if="fila.suspension.por"> · por {{ fila.suspension.por }}</template>
            <template v-if="fila.suspension.motivo"><br>Motivo: {{ fila.suspension.motivo }}</template>
          </span>
        </div>
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

    <AppModal :abierto="!!suspension.fila" titulo="Suspender cuenta" @cerrar="suspension.fila = null">
      <form class="form" @submit.prevent="confirmarSuspension">
        <p class="form__nota">
          <strong>{{ suspension.fila?.nombre }}</strong> perderá el acceso de inmediato: se cerrarán sus sesiones abiertas
          y no podrá iniciar sesión hasta que reactives la cuenta. Sus datos se conservan.
        </p>
        <AppInput v-model="suspension.motivo" label="Motivo (opcional, queda en la bitácora)" maxlength="255" placeholder="Ej. Baja temporal, equipo extraviado…" />
        <div class="acciones-modal">
          <AppButton type="button" variante="secundaria" @click="suspension.fila = null">Cancelar</AppButton>
          <AppButton type="submit" variante="peligro" :cargando="suspension.enviando">Suspender cuenta</AppButton>
        </div>
      </form>
    </AppModal>

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
    { key: 'estado', label: 'Estado', width: '220px' },
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

const suspension = reactive({ fila: null, motivo: '', enviando: false });

function formatearFecha(iso) {
    return iso ? new Date(iso).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }) : '';
}

async function confirmarSuspension() {
    suspension.enviando = true;
    try {
        await usuariosApi.suspender(suspension.fila.uuid, suspension.motivo.trim() || null);
        toast.exito('Cuenta suspendida. El usuario perdió el acceso de inmediato.');
        suspension.fila = null;
        await cargar();
    } catch (e) {
        toast.error(e.details?.usuario?.[0] || e.message);
    } finally {
        suspension.enviando = false;
    }
}

async function alternarEstado(fila) {
    // Suspender pide confirmación y motivo; reactivar es directo.
    if (fila.estado === 'activo') {
        Object.assign(suspension, { fila, motivo: '', enviando: false });
        return;
    }
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
.estado-cuenta { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.estado-cuenta__detalle { font-size: var(--text-xs); color: var(--text-muted); line-height: 1.4; overflow-wrap: anywhere; }
.acciones-modal { display: flex; justify-content: flex-end; gap: var(--space-3); flex-wrap: wrap; }

.acciones-fila { display: flex; gap: var(--space-2); flex-wrap: wrap; justify-content: flex-end; }
@media (min-width: 721px) { .acciones-fila { justify-content: flex-start; } }
</style>
