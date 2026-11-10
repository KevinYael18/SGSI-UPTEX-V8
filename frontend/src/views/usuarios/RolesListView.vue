<template>
  <div>
    <h1>Roles y permisos</h1>
    <p class="subtitulo">El rol Administrador siempre tiene acceso total y no aparece aquí — no se gestiona por permisos individuales.</p>

    <div v-if="cargando" class="cargando">Cargando roles…</div>

    <div v-else class="roles">
      <AppCard v-for="rol in rolesGestionables" :key="rol.id" :titulo="rol.nombre" icono="roles" class="rol-tarjeta">
        <p v-if="rol.descripcion" class="rol-tarjeta__descripcion">{{ rol.descripcion }}</p>

        <div v-for="(permisosDelModulo, modulo) in permisosPorModulo" :key="modulo" class="modulo">
          <h3>{{ modulo }}</h3>
          <div class="modulo__opciones">
            <label v-for="permiso in permisosDelModulo" :key="permiso.id" class="opcion">
              <input
                type="checkbox"
                :checked="seleccion[rol.id]?.includes(permiso.id)"
                @change="alternarPermiso(rol.id, permiso.id)"
              />
              {{ permiso.clave.split('.')[1] }}
            </label>
          </div>
        </div>

        <AppButton tamano="sm" icono="check" :cargando="guardando === rol.id" @click="guardar(rol)" class="rol-tarjeta__guardar">
          Guardar cambios de {{ rol.nombre }}
        </AppButton>
      </AppCard>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import rolesApi from '../../api/roles';
import { useToastStore } from '../../stores/toast';
import AppCard from '../../components/ui/AppCard.vue';
import AppButton from '../../components/ui/AppButton.vue';

const toast = useToastStore();
const cargando = ref(true);
const guardando = ref(null);

const roles = ref([]);
const permisosDisponibles = ref([]);
const seleccion = reactive({});

const rolesGestionables = computed(() => roles.value.filter((r) => r.nombre !== 'Administrador'));

const permisosPorModulo = computed(() => {
    const agrupado = {};
    for (const permiso of permisosDisponibles.value) {
        if (!agrupado[permiso.modulo]) agrupado[permiso.modulo] = [];
        agrupado[permiso.modulo].push(permiso);
    }
    return agrupado;
});

function alternarPermiso(rolId, permisoId) {
    const actuales = seleccion[rolId] ?? [];
    seleccion[rolId] = actuales.includes(permisoId)
        ? actuales.filter((id) => id !== permisoId)
        : [...actuales, permisoId];
}

async function cargarTodo() {
    cargando.value = true;
    try {
        const [{ data: rolesRes }, { data: permisosRes }] = await Promise.all([
            rolesApi.listar(),
            rolesApi.permisosDisponibles(),
        ]);
        roles.value = rolesRes.data;
        permisosDisponibles.value = permisosRes.data;

        const claveAId = new Map(permisosRes.data.map((p) => [p.clave, p.id]));
        for (const rol of rolesRes.data) {
            seleccion[rol.id] = rol.permisos.map((clave) => claveAId.get(clave)).filter(Boolean);
        }
    } catch (e) {
        toast.error('No se pudieron cargar los roles y permisos.');
    } finally {
        cargando.value = false;
    }
}

async function guardar(rol) {
    guardando.value = rol.id;
    try {
        await rolesApi.actualizarPermisos(rol.id, seleccion[rol.id] ?? []);
        toast.exito(`Permisos de "${rol.nombre}" actualizados.`);
    } catch (e) {
        toast.error(e.message);
    } finally {
        guardando.value = null;
    }
}

onMounted(cargarTodo);
</script>

<style scoped>
.roles { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 440px), 1fr)); gap: var(--space-5); align-items: start; }
.rol-tarjeta__descripcion { color: var(--text-muted); font-size: var(--text-sm); margin: 0 0 var(--space-4); }
.modulo { padding: var(--space-3) 0; border-top: 1px solid var(--border); }
.modulo:first-of-type { border-top: none; padding-top: 0; }
.modulo h3 {
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: var(--space-2); font-family: var(--font-body);
}
.modulo__opciones { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.opcion {
    display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 6px 12px;
    font-size: var(--text-sm); text-transform: capitalize; cursor: pointer;
    border: 1px solid var(--border-strong); border-radius: 999px; background: var(--surface);
    transition: border-color var(--transition-fast), background var(--transition-fast), color var(--transition-fast);
}
.opcion:hover { border-color: var(--c-primary); }
.opcion:has(input:checked) { background: var(--c-primary-soft); border-color: var(--c-primary); color: var(--c-primary); font-weight: 600; }
.opcion input { width: 15px; height: 15px; }
.rol-tarjeta__guardar { margin-top: var(--space-4); }
@media (max-width: 640px) {
    .roles { gap: var(--space-4); }
    .rol-tarjeta__guardar { width: 100%; }
}
</style>
