<template>
  <div v-if="activo" class="detalle">
    <div class="encabezado">
      <div class="encabezado__titulo">
        <h1>{{ activo.nombre }}</h1>
        <div class="etiquetas">
          <AppBadge tono="signal">{{ etiquetaTipo(activo.tipo) }}</AppBadge>
          <AppBadge v-if="activo.subtipo_etiqueta" tono="neutral">{{ activo.subtipo_etiqueta }}</AppBadge>
        </div>
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
          <dt>Ubicación</dt><dd>{{ activo.ubicacion_completa || activo.ubicacion || '—' }}<template v-if="activo.ubicacion_detalle"> · {{ activo.ubicacion_detalle }}</template></dd>
          <dt>Responsable</dt><dd>{{ activo.responsable || '—' }}</dd>
        </dl>
      </AppCard>
    </div>

    <!-- Ficha técnica (solo con los datos registrados; nunca valores supuestos) -->
    <AppCard titulo="Ficha técnica" :subtitulo="activo.subtipo_etiqueta || 'Sin tipo específico'" icono="capas" class="ficha-card">
      <template v-if="seccionesConDatos.length || activo.personalizados?.length">
        <div v-for="sec in seccionesConDatos" :key="sec.titulo" class="ficha-card__seccion">
          <h4>{{ sec.titulo }}</h4>
          <dl class="ficha-card__datos">
            <div v-for="c in sec.campos" :key="c.clave"><dt>{{ c.etiqueta }}</dt><dd>{{ valorLegible(c) }}</dd></div>
          </dl>
        </div>
        <div v-if="activo.personalizados?.length" class="ficha-card__seccion">
          <h4>Características adicionales</h4>
          <dl class="ficha-card__datos">
            <div v-for="(p, i) in activo.personalizados" :key="i"><dt>{{ p.nombre }}</dt><dd>{{ p.valor || '—' }}</dd></div>
          </dl>
        </div>
      </template>
      <p v-else class="ficha-card__vacia">
        Aún no hay información técnica registrada.<template v-if="puedeEditar"> Usa «Editar» para completarla.</template>
      </p>
      <RouterLink v-if="activo.computadora_uuid" :to="{ name: 'inventario.computadora', params: { uuid: activo.computadora_uuid } }" class="ficha-card__enlace">
        Ver software instalado, antivirus y mantenimiento en Inventario TI →
      </RouterLink>
    </AppCard>

    <AppCard v-if="activo.programa" titulo="Equipos donde está instalado" :subtitulo="`${activo.programa.equipos.length} equipo(s)`" icono="activos">
      <ul v-if="activo.programa.equipos.length" class="equipos">
        <li v-for="e in activo.programa.equipos" :key="e.uuid">
          <RouterLink :to="{ name: 'inventario.computadora', params: { uuid: e.uuid } }">{{ e.nombre_equipo }}</RouterLink>
          <small>{{ e.laboratorio || 'Sin laboratorio' }}<template v-if="e.version"> · versión {{ e.version }}</template></small>
          <AppBadge v-if="e.requiere_actualizacion" tono="advertencia">Requiere actualización</AppBadge>
        </li>
      </ul>
      <p v-else class="ficha-card__vacia">Todavía no está registrado en ningún equipo. Se asocia desde la ficha de cada computadora (Inventario TI → Software).</p>
    </AppCard>

    <!-- SGSI Intelligence Assistant: análisis contextual del activo (no lo modifica). -->
    <IaAnalisisActivo v-if="puedeUsarIa" :activo-uuid="uuid" class="ia-en-detalle" />

    <AppCard titulo="Historial de actividad" icono="bitacora">
      <TimelinePanel tipo="activos" :id="uuid" />
    </AppCard>

    <!-- Modal de edición: mismos selectores reales que la creación, precargados -->
    <AppModal :abierto="modalEdicion" titulo="Editar activo" :ancho="!!ficha.subtipo" @cerrar="modalEdicion = false">
      <form class="form" @submit.prevent="guardarEdicion">
        <AppInput v-model="edicion.nombre" label="Nombre" requerido />
        <AppSelect v-model="edicion.tipo" label="Tipo" requerido :opciones="opcionesTipo" placeholder="Seleccione…" />
        <FichaTecnicaActivo :tipo="edicion.tipo" :nombre="edicion.nombre" :catalogo="catalogoTipos" :modelo="ficha" :errores="erroresFicha" />

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

        <AppInput v-if="edicion.ubicacion_id" v-model="ficha.ubicacion_detalle" label="Lugar específico (opcional)" maxlength="150" />

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
import FichaTecnicaActivo, { tecnicoParaEnviar, personalizadosParaEnviar, modeloVacio } from '../../components/activos/FichaTecnicaActivo.vue';
import { seccionesDe } from '../../composables/useReconocimientoActivo';
import IaAnalisisActivo from '../../components/ia/IaAnalisisActivo.vue';

const props = defineProps({ uuid: { type: String, required: true } });
const router = useRouter();
const auth = useAuthStore();
const puedeUsarIa = computed(() => (auth.usuario?.permisos ?? []).includes('ia.usar'));
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
    if (activo.value.subtipo) cargarTiposTecnicos();
}

// ---- Ficha técnica dinámica ----
const catalogoTipos = ref(null);
const ficha = reactive({ ...modeloVacio(), ubicacion_detalle: '' });
const erroresFicha = ref({});
const seccionesConDatos = computed(() => {
    if (!activo.value?.subtipo || !catalogoTipos.value) return [];
    const valores = activo.value.tecnico || {};
    return seccionesDe(activo.value.subtipo, catalogoTipos.value)
        .map((s) => ({ ...s, campos: s.campos.filter((c) => valores[c.clave] !== undefined && valores[c.clave] !== null && valores[c.clave] !== '') }))
        .filter((s) => s.campos.length);
});
function valorLegible(campo) {
    const v = activo.value.tecnico?.[campo.clave];
    if (campo.tipo === 'select') return campo.opciones.find((o) => o.value === v)?.label || v;
    if (campo.tipo === 'fecha') return new Date(`${v}T00:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
    return v;
}
async function cargarTiposTecnicos() {
    if (catalogoTipos.value) return;
    try {
        const { data } = await activosApi.tiposTecnicos();
        catalogoTipos.value = data.data;
    } catch { /* el detalle general se muestra igual */ }
}

async function abrirModalEdicion() {
    edicion.nombre = activo.value.nombre;
    edicion.tipo = activo.value.tipo;
    edicion.confidencialidad = activo.value.cia.confidencialidad;
    edicion.integridad = activo.value.cia.integridad;
    edicion.disponibilidad = activo.value.cia.disponibilidad;
    edicion.ubicacion_id = activo.value.ubicacion_id ?? '';
    edicion.responsable_id = activo.value.responsable_id ?? '';
    // Precarga de la ficha: el tipo guardado se respeta (no se re-reconoce solo).
    Object.assign(ficha, {
        subtipo: activo.value.subtipo, manual: !!activo.value.subtipo,
        tecnico: { ...(activo.value.tecnico || {}) },
        personalizados: (activo.value.personalizados || []).map((p) => ({ ...p })),
        ubicacion_detalle: activo.value.ubicacion_detalle || '',
    });
    erroresFicha.value = {};
    await cargarTiposTecnicos();

    try {
        const [{ data: ubiRes }, { data: usrRes }] = await Promise.all([
            catalogosApi.ubicaciones(activo.value.departamento_id),
            catalogosApi.usuarios(),
        ]);
        opcionesUbicaciones.value = ubiRes.data.map((u) => ({ value: u.id, label: u.descripcion || u.nombre }));
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
        payload.subtipo = ficha.subtipo;
        payload.tecnico = tecnicoParaEnviar(ficha, catalogoTipos.value);
        payload.personalizados = personalizadosParaEnviar(ficha);
        payload.ubicacion_detalle = edicion.ubicacion_id ? (ficha.ubicacion_detalle || null) : null;
        erroresFicha.value = {};

        await activosApi.actualizar(props.uuid, payload);
        toast.exito('Activo actualizado.');
        modalEdicion.value = false;
        await cargar();
    } catch (e) {
        erroresFicha.value = e.details || {};
        toast.error(Object.values(erroresFicha.value)[0]?.[0] || e.message);
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
.etiquetas { display: flex; gap: 6px; flex-wrap: wrap; }
.ficha-card { margin-bottom: var(--space-5); }
.ficha-card__seccion + .ficha-card__seccion { margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border); }
.ficha-card__seccion h4 { margin: 0 0 var(--space-3); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); }
.ficha-card__datos { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 200px), 1fr)); gap: 10px 16px; margin: 0; }
.ficha-card__datos dt { font-size: var(--text-xs); color: var(--text-muted); font-weight: 600; }
.ficha-card__datos dd { margin: 2px 0 0; font-weight: 500; overflow-wrap: anywhere; white-space: pre-line; }
.ficha-card__vacia { margin: 0; color: var(--text-muted); font-size: var(--text-sm); }
.ficha-card__enlace { display: inline-block; margin-top: var(--space-4); font-weight: 600; font-size: var(--text-sm); }
.equipos { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.equipos li { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 8px 0; border-bottom: 1px solid var(--border); }
.equipos li:last-child { border-bottom: none; }
.equipos small { color: var(--text-muted); }

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
