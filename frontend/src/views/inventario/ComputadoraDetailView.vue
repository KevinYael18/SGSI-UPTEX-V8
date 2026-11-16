<template>
  <div>
    <nav class="migas" aria-label="Ruta">
      <RouterLink :to="{ name: 'inventario' }">Inventario</RouterLink>
      <AppIcon name="chevron-der" :size="14" />
      <RouterLink v-if="c?.laboratorio" :to="{ name: 'inventario.laboratorio', params: { id: c.laboratorio.id } }">{{ c.laboratorio.nombre }}</RouterLink>
      <span v-else>Sin laboratorio</span>
      <AppIcon name="chevron-der" :size="14" />
      <span aria-current="page">{{ c?.nombre_equipo || '…' }}</span>
    </nav>

    <div v-if="cargando" aria-busy="true"><div class="skeleton" style="height: 320px" /></div>

    <template v-else-if="c">
      <div class="encabezado">
        <div>
          <h1>{{ c.nombre_equipo }}</h1>
          <div class="etiquetas">
            <AppBadge :tono="TONO_ESTADO[c.estado_operativo]">{{ c.estado_operativo_label }}</AppBadge>
            <AppBadge :tono="TONO_AV[c.antivirus.estado]">Antivirus: {{ ETIQUETA_AV[c.antivirus.estado] }}</AppBadge>
            <AppBadge v-if="c.software_pendiente" tono="advertencia">{{ c.software_pendiente }} programa(s) por actualizar</AppBadge>
            <AppBadge v-if="c.sistema_operativo.soporte === 'sin_soporte'" tono="riesgo">SO sin soporte</AppBadge>
          </div>
        </div>
        <div class="encabezado__acciones">
          <RouterLink v-if="c.activo_uuid" :to="{ name: 'activos.detalle', params: { uuid: c.activo_uuid } }" class="boton-descarga"><AppIcon name="activos" :size="17" />Ver como activo</RouterLink>
          <AppButton v-if="puedeGestionar" variante="secundaria" icono="editar" @click="edicionAbierta = true">Editar ficha</AppButton>
          <AppButton v-if="puedeEliminar" variante="peligro" icono="papelera" @click="eliminar">Eliminar</AppButton>
        </div>
      </div>

      <div class="tabs" role="tablist" aria-label="Secciones de la ficha técnica">
        <button v-for="t in TABS" :key="t.id" type="button" role="tab" class="tabs__tab" :class="{ 'tabs__tab--activa': tab === t.id }"
                :aria-selected="tab === t.id" @click="tab = t.id">
          {{ t.label }}<span v-if="t.id === 'software'" class="tabs__conteo">{{ c.software?.length || 0 }}</span>
          <span v-if="t.id === 'mantenimiento'" class="tabs__conteo">{{ c.mantenimientos?.length || 0 }}</span>
        </button>
      </div>

      <Transition name="pagina-tab" mode="out-in">
        <!-- General -->
        <div v-if="tab === 'general'" key="general" class="ficha-grid">
          <AppCard titulo="Identificación" icono="activos">
            <dl class="datos">
              <div><dt>No. inventario</dt><dd class="mono">{{ c.numero_inventario || '—' }}</dd></div>
              <div><dt>No. serie</dt><dd class="mono">{{ c.numero_serie || '—' }}</dd></div>
              <div><dt>Dirección MAC</dt><dd class="mono">{{ c.direccion_mac || '—' }}</dd></div>
              <div><dt>Dirección IP</dt><dd class="mono">{{ c.direccion_ip || '—' }}</dd></div>
              <div><dt>Edificio</dt><dd>{{ c.laboratorio?.edificio || '—' }}</dd></div>
              <div><dt>Laboratorio</dt><dd>{{ c.laboratorio?.nombre || '—' }}</dd></div>
              <div><dt>Ubicación física</dt><dd>{{ c.ubicacion_fisica || '—' }}</dd></div>
              <div><dt>Responsable</dt><dd>{{ c.activo?.responsable || '—' }}</dd></div>
            </dl>
          </AppCard>
          <AppCard titulo="Hardware" icono="configuracion">
            <dl class="datos">
              <div><dt>Fabricante</dt><dd>{{ c.hardware.fabricante || '—' }}</dd></div>
              <div><dt>Modelo</dt><dd>{{ c.hardware.modelo || '—' }}</dd></div>
              <div><dt>Procesador</dt><dd>{{ c.hardware.procesador || '—' }}</dd></div>
              <div><dt>Memoria RAM</dt><dd>{{ c.hardware.ram_gb ? `${c.hardware.ram_gb} GB` : '—' }}</dd></div>
              <div><dt>Almacenamiento</dt><dd>{{ c.hardware.almacenamiento_gb ? `${c.hardware.almacenamiento_gb} GB` : '—' }} {{ etiqueta('tipos_almacenamiento', c.hardware.tipo_almacenamiento) }}</dd></div>
              <div><dt>Tarjeta gráfica</dt><dd>{{ c.hardware.tarjeta_grafica || '—' }}</dd></div>
              <div><dt>Adquisición</dt><dd>{{ fecha(c.hardware.fecha_adquisicion) }}</dd></div>
              <div><dt>Garantía</dt><dd :class="{ 'texto-riesgo': garantiaVencida }">{{ c.hardware.garantia_hasta ? `${garantiaVencida ? 'Vencida el' : 'Hasta el'} ${fecha(c.hardware.garantia_hasta)}` : 'Desconocida' }}</dd></div>
            </dl>
          </AppCard>
          <AppCard titulo="Sistema operativo" icono="capas">
            <dl class="datos">
              <div><dt>Sistema</dt><dd>{{ c.sistema_operativo.nombre || 'Sin registrar' }}</dd></div>
              <div><dt>Edición</dt><dd>{{ c.sistema_operativo.edicion || '—' }}</dd></div>
              <div><dt>Versión</dt><dd>{{ c.sistema_operativo.version || '—' }}</dd></div>
              <div><dt>Arquitectura</dt><dd>{{ etiqueta('arquitecturas', c.sistema_operativo.arquitectura) || '—' }}</dd></div>
              <div><dt>Activación</dt><dd>{{ etiqueta('activacion_so', c.sistema_operativo.activacion) }}</dd></div>
              <div><dt>Soporte</dt><dd :class="{ 'texto-riesgo': c.sistema_operativo.soporte === 'sin_soporte' }">{{ etiqueta('soporte_so', c.sistema_operativo.soporte) }}</dd></div>
              <div><dt>Última actualización</dt><dd>{{ fecha(c.sistema_operativo.ultima_actualizacion) }}</dd></div>
            </dl>
          </AppCard>
          <AppCard titulo="Antivirus" icono="escudo">
            <dl class="datos">
              <div><dt>Producto</dt><dd>{{ c.antivirus.nombre || 'Sin registrar' }}</dd></div>
              <div><dt>Fabricante</dt><dd>{{ c.antivirus.fabricante || '—' }}</dd></div>
              <div><dt>Versión</dt><dd>{{ c.antivirus.version || '—' }}</dd></div>
              <div><dt>Instalación</dt><dd>{{ etiqueta('instalacion_antivirus', c.antivirus.instalacion) }}</dd></div>
              <div><dt>Protección</dt><dd>{{ etiqueta('proteccion_antivirus', c.antivirus.proteccion) }}</dd></div>
              <div><dt>Última actualización</dt><dd>{{ fecha(c.antivirus.ultima_actualizacion) }}</dd></div>
            </dl>
          </AppCard>
          <AppCard v-if="c.observaciones" titulo="Observaciones" icono="comentario" class="ficha-grid__ancho"><p class="observaciones">{{ c.observaciones }}</p></AppCard>
        </div>

        <!-- Software -->
        <div v-else-if="tab === 'software'" key="software">
          <div v-if="puedeGestionar" class="barra-acciones">
            <AppButton icono="mas" @click="abrirInstalacion()">Registrar programa</AppButton>
          </div>
          <AppTable :columnas="columnasSoftware" :filas="c.software || []">
            <template #col-nombre="{ fila }"><strong>{{ fila.nombre }}</strong><small class="sub"> {{ fila.fabricante || '' }}</small></template>
            <template #col-version="{ fila }">
              <span class="mono">{{ fila.version || '—' }}</span>
              <AppBadge v-if="fila.desactualizado" tono="advertencia" class="badge-mini">Actualizar{{ fila.version_vigente ? ` (≥ ${fila.version_vigente})` : '' }}</AppBadge>
            </template>
            <template #col-estado_autorizacion="{ fila }">
              <AppBadge :tono="{ autorizado: 'exito', no_autorizado: 'riesgo' }[fila.estado_autorizacion] || 'neutral'">{{ etiqueta('estados_autorizacion', fila.estado_autorizacion) }}</AppBadge>
            </template>
            <template #col-estado_licencia="{ fila }">{{ etiqueta('estados_licencia', fila.estado_licencia) }}</template>
            <template #col-acciones="{ fila }">
              <div v-if="puedeGestionar" class="acciones-fila">
                <AppButton tamano="sm" variante="secundaria" icono="editar" @click.stop="abrirInstalacion(fila)">Editar</AppButton>
                <AppButton tamano="sm" variante="fantasma" icono="papelera" :aria-label="`Quitar ${fila.nombre}`" @click.stop="quitarPrograma(fila)" />
              </div>
            </template>
            <template #vacio><EmptyState texto="No hay programas registrados en este equipo." /></template>
          </AppTable>
        </div>

        <!-- Mantenimiento -->
        <div v-else-if="tab === 'mantenimiento'" key="mantenimiento">
          <div v-if="puedeGestionar" class="barra-acciones">
            <AppButton icono="mas" @click="abrirMantenimiento">Registrar mantenimiento</AppButton>
          </div>
          <ol v-if="c.mantenimientos?.length" class="historial">
            <li v-for="(m, i) in c.mantenimientos" :key="m.id" class="historial__item entrada-escalonada" :style="{ '--i': i }">
              <span class="historial__punto" aria-hidden="true" />
              <div>
                <strong>{{ etiqueta('tipos_mantenimiento', m.tipo) }}</strong> · <span class="mono">{{ fecha(m.fecha) }}</span>
                <p>{{ m.descripcion }}</p>
                <small class="sub">
                  <template v-if="m.realizado_por">Realizado por {{ m.realizado_por }} · </template>
                  Registrado por {{ m.registrado_por || '—' }}
                  <template v-if="m.estado_resultante"> · Estado resultante: {{ etiqueta('estados_operativos', m.estado_resultante) }}</template>
                </small>
              </div>
            </li>
          </ol>
          <EmptyState v-else texto="Sin mantenimientos registrados." />
        </div>

        <!-- Seguridad -->
        <div v-else-if="tab === 'seguridad'" key="seguridad" class="ficha-grid">
          <AppCard titulo="Valoración del activo" icono="riesgos">
            <dl class="datos">
              <div><dt>Confidencialidad</dt><dd>{{ c.activo?.confidencialidad }}</dd></div>
              <div><dt>Integridad</dt><dd>{{ c.activo?.integridad }}</dd></div>
              <div><dt>Disponibilidad</dt><dd>{{ c.activo?.disponibilidad }}</dd></div>
              <div><dt>Criticidad</dt><dd><AppBadge :tono="c.activo?.criticidad >= 4 ? 'riesgo' : c.activo?.criticidad === 3 ? 'advertencia' : 'exito'">{{ c.activo?.criticidad }}</AppBadge></dd></div>
              <div><dt>Departamento</dt><dd>{{ c.activo?.departamento || '—' }}</dd></div>
              <div><dt>Riesgos registrados</dt><dd>{{ c.activo?.riesgos ?? 0 }}</dd></div>
              <div><dt>Incidentes</dt><dd>{{ c.activo?.incidentes ?? 0 }}</dd></div>
            </dl>
            <p class="nota">Los riesgos e incidentes de este equipo se registran en sus módulos seleccionando el activo «{{ c.nombre_equipo }}».</p>
          </AppCard>
          <AppCard titulo="Controles ISO/IEC 27001:2022" icono="cumplimiento">
            <ul v-if="c.activo?.controles_iso?.length" class="controles">
              <li v-for="ctl in c.activo.controles_iso" :key="ctl.id">
                <span class="mono">{{ ctl.codigo }}</span> {{ ctl.nombre }}
                <AppBadge :tono="{ implementado: 'exito', en_proceso: 'advertencia', no_implementado: 'riesgo' }[ctl.estado] || 'neutral'" class="badge-mini">{{ ctl.estado?.replace('_', ' ') }}</AppBadge>
              </li>
            </ul>
            <p v-else class="sub">Sin controles vinculados. Edita la ficha (sección «Valoración e ISO») para vincularlos.</p>
          </AppCard>
        </div>

        <!-- Historial -->
        <div v-else key="historial">
          <TimelinePanel tipo="computadoras" :id="c.uuid" />
        </div>
      </Transition>
    </template>

    <ComputadoraFormModal :abierto="edicionAbierta" :computadora="c" :catalogos="catalogos" :laboratorios="laboratorios"
      @cerrar="edicionAbierta = false" @guardado="alGuardar" />

    <!-- Instalación de software -->
    <AppModal :abierto="inst.abierto" :titulo="inst.editando ? `Editar ${inst.nombre}` : 'Registrar programa'" @cerrar="inst.abierto = false">
      <form class="form" @submit.prevent="guardarInstalacion">
        <AppSelect v-if="!inst.editando" v-model="inst.software_id" label="Programa del catálogo" requerido placeholder="Seleccione…" :opciones="opcionesSoftware" />
        <p v-if="!inst.editando && !opcionesSoftware.length" class="form__nota">
          No hay programas disponibles. <RouterLink :to="{ name: 'inventario.software' }">Agrégalos al catálogo</RouterLink>.
        </p>
        <div class="form__grid">
          <AppInput v-model="inst.version" label="Versión instalada" />
          <AppInput v-model="inst.fecha_instalacion" type="date" label="Fecha de instalación" />
        </div>
        <AppSelect v-model="inst.estado_licencia" label="Estado de la licencia" :opciones="catalogos.estados_licencia || []" />
        <label class="form__checkbox"><input v-model="inst.requiere_actualizacion" type="checkbox" /> Marcar como pendiente de actualización</label>
        <AppInput v-model="inst.observaciones" label="Observaciones" />
        <AppButton type="submit" :cargando="inst.guardando">Guardar</AppButton>
      </form>
    </AppModal>

    <!-- Mantenimiento -->
    <AppModal :abierto="mant.abierto" titulo="Registrar mantenimiento" @cerrar="mant.abierto = false">
      <form class="form" @submit.prevent="guardarMantenimiento">
        <div class="form__grid">
          <AppSelect v-model="mant.tipo" label="Tipo" requerido placeholder="Seleccione…" :opciones="catalogos.tipos_mantenimiento || []" />
          <AppInput v-model="mant.fecha" type="date" label="Fecha" requerido />
        </div>
        <AppInput v-model="mant.descripcion" label="Trabajo realizado" requerido :error="mant.errores.descripcion?.[0]" />
        <AppInput v-model="mant.realizado_por" label="Realizado por (técnico o proveedor)" />
        <AppSelect v-model="mant.estado_resultante" label="Estado del equipo después" :opciones="[{ value: '', label: 'Sin cambio' }, ...(catalogos.estados_operativos || [])]" />
        <AppButton type="submit" :cargando="mant.guardando">Registrar</AppButton>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import inventarioApi from '../../api/inventario';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppButton from '../../components/ui/AppButton.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppTable from '../../components/ui/AppTable.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import TimelinePanel from '../../components/TimelinePanel.vue';
import ComputadoraFormModal from '../../components/inventario/ComputadoraFormModal.vue';

const props = defineProps({ uuid: { type: String, required: true } });
const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const permisos = computed(() => auth.usuario?.permisos || []);
const puedeGestionar = computed(() => auth.esAdministrador || permisos.value.includes('activos.editar') || permisos.value.includes('activos.crear'));
const puedeEliminar = computed(() => auth.esAdministrador || permisos.value.includes('activos.eliminar'));

const TABS = [
    { id: 'general', label: 'Ficha técnica' }, { id: 'software', label: 'Software' },
    { id: 'mantenimiento', label: 'Mantenimiento' }, { id: 'seguridad', label: 'Seguridad e ISO' }, { id: 'historial', label: 'Historial' },
];
const TONO_ESTADO = { operativo: 'exito', mantenimiento: 'advertencia', fuera_servicio: 'riesgo', baja: 'neutral' };
const TONO_AV = { actualizado: 'exito', desactualizado: 'riesgo', sin_informacion: 'neutral' };
const ETIQUETA_AV = { actualizado: 'actualizado', desactualizado: 'requiere atención', sin_informacion: 'sin información' };
const columnasSoftware = [
    { key: 'nombre', label: 'Programa' }, { key: 'version', label: 'Versión' }, { key: 'estado_autorizacion', label: 'Autorización', width: '170px' },
    { key: 'estado_licencia', label: 'Licencia', width: '180px' }, { key: 'acciones', label: '', width: '170px' },
];

const c = ref(null);
const catalogos = ref({});
const laboratorios = ref([]);
const catalogoSoftware = ref([]);
const cargando = ref(true);
const tab = ref('general');
const edicionAbierta = ref(false);

const garantiaVencida = computed(() => c.value?.hardware.garantia_hasta && new Date(c.value.hardware.garantia_hasta) < new Date());
const opcionesSoftware = computed(() => {
    const instalados = new Set((c.value?.software || []).map((s) => s.software_id));
    return catalogoSoftware.value.filter((s) => !instalados.has(s.id)).map((s) => ({ value: s.id, label: `${s.nombre}${s.fabricante ? ` — ${s.fabricante}` : ''}` }));
});

function etiqueta(catalogo, valor) {
    if (!valor) return '';
    return (catalogos.value[catalogo] || []).find((o) => o.value === valor)?.label || valor;
}
function fecha(v) {
    return v ? new Date(`${v}T00:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

async function cargar() {
    try {
        const [{ data: f }, { data: cat }] = await Promise.all([inventarioApi.computadora(props.uuid), inventarioApi.catalogos()]);
        c.value = f.data;
        catalogos.value = cat.data;
    } catch (e) {
        toast.error(e.message || 'No se pudo cargar la ficha del equipo.');
    } finally {
        cargando.value = false;
    }
    try {
        const { data } = await inventarioApi.resumen();
        laboratorios.value = data.data.edificios.flatMap((e) => e.laboratorios.map((l) => ({ id: l.id, nombre: l.nombre, edificio: e.nombre })));
    } catch { /* solo para el selector de laboratorio */ }
}

function alGuardar(actualizada) {
    edicionAbierta.value = false;
    toast.exito('Ficha técnica actualizada.');
    cargar();
    return actualizada;
}

async function eliminar() {
    if (!window.confirm(`¿Eliminar «${c.value.nombre_equipo}» del inventario? También se dará de baja su activo. No es posible si tiene riesgos abiertos.`)) return;
    try {
        await inventarioApi.eliminarComputadora(props.uuid);
        toast.exito('Equipo eliminado del inventario.');
        router.push(c.value.laboratorio ? { name: 'inventario.laboratorio', params: { id: c.value.laboratorio.id } } : { name: 'inventario' });
    } catch (e) {
        toast.error(e.message);
    }
}

// ---- Software ----
const inst = reactive({ abierto: false, editando: false, software_id: '', nombre: '', version: '', fecha_instalacion: '', estado_licencia: 'pendiente_verificacion', requiere_actualizacion: false, observaciones: '', guardando: false });
async function abrirInstalacion(fila = null) {
    Object.assign(inst, {
        abierto: true, editando: !!fila, software_id: fila?.software_id || '', nombre: fila?.nombre || '', version: fila?.version || '',
        fecha_instalacion: fila?.fecha_instalacion || '', estado_licencia: fila?.estado_licencia || 'pendiente_verificacion',
        requiere_actualizacion: !!fila?.requiere_actualizacion, observaciones: fila?.observaciones || '',
    });
    if (!fila && !catalogoSoftware.value.length) {
        try { const { data } = await inventarioApi.software(); catalogoSoftware.value = data.data; } catch (e) { toast.error(e.message); }
    }
}
async function guardarInstalacion() {
    inst.guardando = true;
    try {
        const datos = {
            version: inst.version || null, fecha_instalacion: inst.fecha_instalacion || null, estado_licencia: inst.estado_licencia,
            requiere_actualizacion: inst.requiere_actualizacion, observaciones: inst.observaciones || null,
        };
        const { data } = inst.editando
            ? await inventarioApi.actualizarSoftware(props.uuid, inst.software_id, datos)
            : await inventarioApi.instalarSoftware(props.uuid, { ...datos, software_id: Number(inst.software_id) });
        c.value = data.data;
        inst.abierto = false;
        toast.exito('Software guardado.');
    } catch (e) {
        toast.error(Object.values(e.details || {})[0]?.[0] || e.message);
    } finally {
        inst.guardando = false;
    }
}
async function quitarPrograma(fila) {
    if (!window.confirm(`¿Quitar «${fila.nombre}» de este equipo? El programa seguirá en el catálogo.`)) return;
    try {
        const { data } = await inventarioApi.quitarSoftware(props.uuid, fila.software_id);
        c.value = data.data;
        toast.exito('Programa retirado del equipo.');
    } catch (e) { toast.error(e.message); }
}

// ---- Mantenimiento ----
const hoy = () => new Date().toISOString().slice(0, 10);
const mant = reactive({ abierto: false, tipo: '', fecha: hoy(), descripcion: '', realizado_por: '', estado_resultante: '', guardando: false, errores: {} });
function abrirMantenimiento() {
    Object.assign(mant, { abierto: true, tipo: '', fecha: hoy(), descripcion: '', realizado_por: '', estado_resultante: '', errores: {} });
}
async function guardarMantenimiento() {
    mant.guardando = true;
    mant.errores = {};
    try {
        const { data } = await inventarioApi.registrarMantenimiento(props.uuid, {
            tipo: mant.tipo, fecha: mant.fecha, descripcion: mant.descripcion,
            realizado_por: mant.realizado_por || null, estado_resultante: mant.estado_resultante || null,
        });
        c.value = data.data;
        mant.abierto = false;
        toast.exito('Mantenimiento registrado.');
    } catch (e) {
        mant.errores = e.details || {};
        toast.error(Object.values(mant.errores)[0]?.[0] || e.message);
    } finally {
        mant.guardando = false;
    }
}

onMounted(cargar);
watch(() => props.uuid, () => { cargando.value = true; cargar(); });
</script>

<style scoped>
.migas { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: var(--text-sm); color: var(--text-muted); margin-bottom: var(--space-3); }
.etiquetas { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: var(--space-5); overflow-x: auto; scrollbar-width: none; }
.tabs__tab {
    position: relative; display: inline-flex; align-items: center; gap: 6px; padding: 10px 14px; border: none; background: none; cursor: pointer;
    color: var(--text-muted); font-weight: 600; font-size: var(--text-sm); white-space: nowrap; border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    transition: color var(--transition-fast), background var(--transition-fast);
}
.tabs__tab::after {
    content: ''; position: absolute; left: 10px; right: 10px; bottom: -1px; height: 3px; border-radius: 3px 3px 0 0;
    background: var(--c-primary); transform: scaleX(0); transition: transform 240ms var(--ease);
}
.tabs__tab:hover { color: var(--text); background: var(--bg-subtle); }
.tabs__tab--activa { color: var(--c-primary); }
.tabs__tab--activa::after { transform: scaleX(1); }
.tabs__conteo { font-size: 0.7rem; padding: 1px 7px; border-radius: 999px; background: var(--bg-subtle); color: var(--text-muted); }
.ficha-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: var(--space-5); }
.ficha-grid__ancho { grid-column: 1 / -1; }
.datos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; margin: 0; }
.datos dt { font-size: var(--text-xs); color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
.datos dd { margin: 2px 0 0; font-weight: 500; overflow-wrap: anywhere; }
.mono { font-family: var(--font-mono); font-size: 0.88em; }
.sub { color: var(--text-subtle); font-size: var(--text-xs); }
.texto-riesgo { color: var(--c-risk); }
.observaciones { margin: 0; white-space: pre-line; }
.nota { margin: var(--space-4) 0 0; font-size: var(--text-xs); color: var(--text-muted); }
.controles { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; font-size: var(--text-sm); }
.badge-mini { margin-left: 6px; }
.barra-acciones { display: flex; justify-content: flex-end; margin-bottom: var(--space-4); }
.acciones-fila { display: flex; gap: 6px; justify-content: flex-end; }
.historial { list-style: none; margin: 0; padding: 0 0 0 8px; border-left: 2px solid var(--border); display: flex; flex-direction: column; gap: var(--space-4); }
.historial__item { position: relative; padding-left: var(--space-4); }
.historial__item p { margin: 4px 0; }
.historial__punto { position: absolute; left: -15px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: var(--c-primary); box-shadow: 0 0 0 3px var(--surface); }
.pagina-tab-enter-active, .pagina-tab-leave-active { transition: opacity 180ms var(--ease), transform 180ms var(--ease); }
.pagina-tab-enter-from { opacity: 0; transform: translateY(6px); }
.pagina-tab-leave-to { opacity: 0; }
@media (max-width: 640px) { .datos { grid-template-columns: 1fr; } }
</style>
