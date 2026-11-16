<template>
  <AppModal :abierto="abierto" :titulo="esEdicion ? `Editar ${computadora?.nombre_equipo || 'equipo'}` : 'Registrar computadora'" ancho @cerrar="$emit('cerrar')">
    <form class="form equipo-form" novalidate @submit.prevent="guardar">
      <nav class="equipo-form__pasos" aria-label="Secciones del formulario">
        <button v-for="s in SECCIONES" :key="s.id" type="button" class="equipo-form__paso"
                :class="{ 'equipo-form__paso--activo': seccion === s.id, 'equipo-form__paso--error': seccionConError(s.id) }"
                @click="seccion = s.id">
          {{ s.label }}
        </button>
      </nav>

      <Transition name="paso" mode="out-in">
        <!-- Identificación -->
        <fieldset v-if="seccion === 'identificacion'" key="identificacion" class="equipo-form__seccion">
          <div class="form__grid">
            <AppInput v-model="f.nombre_equipo" label="Nombre del equipo" requerido placeholder="Ej. LAB1-PC01" :error="err('nombre_equipo')" />
            <AppSelect v-model="f.laboratorio_id" label="Laboratorio" placeholder="Sin laboratorio" :opciones="[{ value: '', label: 'Sin laboratorio' }, ...opcionesLaboratorios]" />
            <AppInput v-model="f.numero_inventario" label="No. de inventario institucional" :error="err('numero_inventario')" />
            <AppInput v-model="f.numero_serie" label="Número de serie" :error="err('numero_serie')" />
            <AppInput v-model="f.direccion_mac" label="Dirección MAC" placeholder="00:1A:2B:3C:4D:5E" :error="err('direccion_mac')" />
            <AppInput v-model="f.direccion_ip" label="Dirección IP" placeholder="192.168.0.10" :error="err('direccion_ip')" />
            <AppInput v-model="f.ubicacion_fisica" label="Ubicación física" placeholder="Ej. Fila 2, lugar 5" :error="err('ubicacion_fisica')" />
            <AppSelect v-model="f.estado_operativo" label="Estado" :opciones="cat.estados_operativos || []" />
          </div>
        </fieldset>

        <!-- Hardware -->
        <fieldset v-else-if="seccion === 'hardware'" key="hardware" class="equipo-form__seccion">
          <div class="form__grid">
            <AppInput v-model="f.fabricante" label="Fabricante" placeholder="Ej. Dell, HP, Lenovo" />
            <AppInput v-model="f.modelo" label="Modelo" />
            <AppInput v-model="f.procesador" label="Procesador" placeholder="Ej. Intel Core i5-10400" />
            <AppInput v-model="f.ram_gb" type="number" min="1" label="Memoria RAM (GB)" :error="err('ram_gb')" />
            <AppInput v-model="f.almacenamiento_gb" type="number" min="1" label="Almacenamiento (GB)" :error="err('almacenamiento_gb')" />
            <AppSelect v-model="f.tipo_almacenamiento" label="Tipo de almacenamiento" placeholder="Sin especificar" :opciones="[{ value: '', label: 'Sin especificar' }, ...(cat.tipos_almacenamiento || [])]" />
            <AppInput v-model="f.tarjeta_grafica" label="Tarjeta gráfica (si aplica)" />
            <AppInput v-model="f.fecha_adquisicion" type="date" label="Fecha de adquisición" :error="err('fecha_adquisicion')" />
            <AppInput v-model="f.garantia_hasta" type="date" label="Garantía hasta (si se conoce)" :error="err('garantia_hasta')" />
          </div>
        </fieldset>

        <!-- Sistema operativo -->
        <fieldset v-else-if="seccion === 'so'" key="so" class="equipo-form__seccion">
          <div class="form__grid">
            <AppInput v-model="f.so_nombre" label="Sistema operativo" placeholder="Ej. Windows 11, Ubuntu" />
            <AppInput v-model="f.so_edicion" label="Edición" placeholder="Ej. Pro, Education" />
            <AppInput v-model="f.so_version" label="Versión" placeholder="Ej. 23H2, 22.04 LTS" />
            <AppSelect v-model="f.so_arquitectura" label="Arquitectura" placeholder="Sin especificar" :opciones="[{ value: '', label: 'Sin especificar' }, ...(cat.arquitecturas || [])]" />
            <AppSelect v-model="f.so_activacion" label="Activación" :opciones="cat.activacion_so || []" />
            <AppSelect v-model="f.so_soporte" label="Estado de soporte" :opciones="cat.soporte_so || []" />
            <AppInput v-model="f.so_ultima_actualizacion" type="date" label="Última actualización" :error="err('so_ultima_actualizacion')" />
          </div>
        </fieldset>

        <!-- Antivirus -->
        <fieldset v-else-if="seccion === 'antivirus'" key="antivirus" class="equipo-form__seccion">
          <div class="form__grid">
            <AppInput v-model="f.antivirus_nombre" label="Antivirus" />
            <AppInput v-model="f.antivirus_fabricante" label="Fabricante" />
            <AppInput v-model="f.antivirus_version" label="Versión" />
            <AppSelect v-model="f.antivirus_instalacion" label="Instalación" :opciones="cat.instalacion_antivirus || []" />
            <AppSelect v-model="f.antivirus_proteccion" label="Protección" :opciones="cat.proteccion_antivirus || []" />
            <AppInput v-model="f.antivirus_ultima_actualizacion" type="date" label="Última actualización de firmas" :error="err('antivirus_ultima_actualizacion')"
              :ayuda="`Se considera desactualizado si tiene más de ${cat.dias_antivirus_desactualizado || 7} días.`" />
          </div>
        </fieldset>

        <!-- Activo / ISO 27001 -->
        <fieldset v-else key="activo" class="equipo-form__seccion">
          <p class="form__nota">Cada computadora se registra como un <strong>activo de hardware</strong>: aparece en el módulo de Activos y puede tener riesgos, incidentes y controles asociados.</p>
          <div class="form__cia">
            <AppInput v-model="f.confidencialidad" type="number" min="1" max="5" label="Confidencialidad (1-5)" requerido :error="err('confidencialidad')" />
            <AppInput v-model="f.integridad" type="number" min="1" max="5" label="Integridad (1-5)" requerido :error="err('integridad')" />
            <AppInput v-model="f.disponibilidad" type="number" min="1" max="5" label="Disponibilidad (1-5)" requerido :error="err('disponibilidad')" />
          </div>
          <div class="form__grid">
            <AppSelect v-model="f.departamento_id" label="Departamento" :placeholder="f.laboratorio_id ? 'El del laboratorio' : 'Seleccione…'" :opciones="[{ value: '', label: f.laboratorio_id ? 'El del laboratorio' : 'Seleccione…' }, ...departamentos]" />
            <AppSelect v-model="f.responsable_id" label="Responsable" placeholder="Sin responsable" :opciones="[{ value: '', label: 'Sin responsable / el del laboratorio' }, ...usuarios]" />
          </div>
          <div class="equipo-form__controles">
            <span class="app-field__label">Controles ISO/IEC 27001:2022 aplicables</span>
            <label v-for="c in cat.controles_sugeridos || []" :key="c.id" class="form__checkbox">
              <input v-model="f.controles_iso_ids" type="checkbox" :value="c.id" />
              <span><strong>{{ c.codigo }}</strong> {{ c.nombre }}</span>
            </label>
          </div>
          <AppInput v-model="f.observaciones" label="Observaciones" />
        </fieldset>
      </Transition>

      <Transition name="aviso">
        <p v-if="errorGeneral" class="equipo-form__error" role="alert">{{ errorGeneral }}</p>
      </Transition>

      <div class="equipo-form__acciones">
        <AppButton type="button" variante="secundaria" @click="$emit('cerrar')">Cancelar</AppButton>
        <AppButton type="submit" :cargando="guardando">{{ esEdicion ? 'Guardar cambios' : 'Registrar equipo' }}</AppButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import inventarioApi from '../../api/inventario';
import catalogosApi from '../../api/catalogos';
import AppModal from '../ui/AppModal.vue';
import AppInput from '../ui/AppInput.vue';
import AppSelect from '../ui/AppSelect.vue';
import AppButton from '../ui/AppButton.vue';

const props = defineProps({
    abierto: Boolean,
    computadora: { type: Object, default: null }, // ficha completa para editar
    laboratorioId: { type: [Number, String], default: '' },
    catalogos: { type: Object, default: () => ({}) },
    laboratorios: { type: Array, default: () => [] }, // [{ id, nombre, edificio }]
});
const emit = defineEmits(['cerrar', 'guardado']);

const SECCIONES = [
    { id: 'identificacion', label: 'Identificación', campos: ['nombre_equipo', 'numero_inventario', 'numero_serie', 'direccion_mac', 'direccion_ip', 'ubicacion_fisica', 'laboratorio_id'] },
    { id: 'hardware', label: 'Hardware', campos: ['ram_gb', 'almacenamiento_gb', 'fecha_adquisicion', 'garantia_hasta'] },
    { id: 'so', label: 'Sistema operativo', campos: ['so_ultima_actualizacion'] },
    { id: 'antivirus', label: 'Antivirus', campos: ['antivirus_ultima_actualizacion'] },
    { id: 'activo', label: 'Valoración e ISO', campos: ['confidencialidad', 'integridad', 'disponibilidad', 'departamento_id'] },
];

const cat = computed(() => props.catalogos || {});
const esEdicion = computed(() => !!props.computadora);
const seccion = ref('identificacion');
const guardando = ref(false);
const errores = ref({});
const errorGeneral = ref('');
const departamentos = ref([]);
const usuarios = ref([]);
const f = reactive({});

const opcionesLaboratorios = computed(() => props.laboratorios.map((l) => ({ value: l.id, label: `${l.nombre} · ${l.edificio}` })));

function vacio() {
    return {
        nombre_equipo: '', laboratorio_id: props.laboratorioId || '', numero_inventario: '', numero_serie: '', direccion_mac: '', direccion_ip: '',
        ubicacion_fisica: '', estado_operativo: 'operativo', fabricante: '', modelo: '', procesador: '', ram_gb: '', almacenamiento_gb: '',
        tipo_almacenamiento: '', tarjeta_grafica: '', fecha_adquisicion: '', garantia_hasta: '', so_nombre: '', so_edicion: '', so_version: '',
        so_arquitectura: '', so_activacion: 'desconocido', so_ultima_actualizacion: '', so_soporte: 'desconocido', antivirus_nombre: '',
        antivirus_fabricante: '', antivirus_version: '', antivirus_instalacion: 'desconocido', antivirus_proteccion: 'desconocido',
        antivirus_ultima_actualizacion: '', observaciones: '', confidencialidad: 3, integridad: 3, disponibilidad: 3,
        departamento_id: '', responsable_id: '',
        controles_iso_ids: (cat.value.controles_sugeridos || []).map((c) => c.id),
    };
}

function desdeFicha(c) {
    const h = c.hardware || {}; const so = c.sistema_operativo || {}; const av = c.antivirus || {}; const a = c.activo || {};
    return {
        nombre_equipo: c.nombre_equipo || '', laboratorio_id: c.laboratorio?.id || '', numero_inventario: c.numero_inventario || '',
        numero_serie: c.numero_serie || '', direccion_mac: c.direccion_mac || '', direccion_ip: c.direccion_ip || '',
        ubicacion_fisica: c.ubicacion_fisica || '', estado_operativo: c.estado_operativo, fabricante: h.fabricante || '', modelo: h.modelo || '',
        procesador: h.procesador || '', ram_gb: h.ram_gb ?? '', almacenamiento_gb: h.almacenamiento_gb ?? '', tipo_almacenamiento: h.tipo_almacenamiento || '',
        tarjeta_grafica: h.tarjeta_grafica || '', fecha_adquisicion: h.fecha_adquisicion || '', garantia_hasta: h.garantia_hasta || '',
        so_nombre: so.nombre || '', so_edicion: so.edicion || '', so_version: so.version || '', so_arquitectura: so.arquitectura || '',
        so_activacion: so.activacion || 'desconocido', so_ultima_actualizacion: so.ultima_actualizacion || '', so_soporte: so.soporte || 'desconocido',
        antivirus_nombre: av.nombre || '', antivirus_fabricante: av.fabricante || '', antivirus_version: av.version || '',
        antivirus_instalacion: av.instalacion || 'desconocido', antivirus_proteccion: av.proteccion || 'desconocido',
        antivirus_ultima_actualizacion: av.ultima_actualizacion || '', observaciones: c.observaciones || '',
        confidencialidad: a.confidencialidad ?? 3, integridad: a.integridad ?? 3, disponibilidad: a.disponibilidad ?? 3,
        departamento_id: a.departamento_id || '', responsable_id: a.responsable_id || '',
        controles_iso_ids: (a.controles_iso || []).map((x) => x.id),
    };
}

async function cargarCatalogos() {
    if (departamentos.value.length) return;
    try {
        const [{ data: d }, { data: u }] = await Promise.all([catalogosApi.departamentos(), catalogosApi.usuarios()]);
        departamentos.value = d.data.map((x) => ({ value: x.id, label: `${x.nombre} (${x.clave})` }));
        usuarios.value = u.data.map((x) => ({ value: x.id, label: x.nombre }));
    } catch { /* los selectores quedan vacíos; el backend valida igualmente */ }
}

watch(() => props.abierto, (abierto) => {
    if (!abierto) return;
    Object.keys(f).forEach((k) => delete f[k]);
    Object.assign(f, props.computadora ? desdeFicha(props.computadora) : vacio());
    errores.value = {};
    errorGeneral.value = '';
    seccion.value = 'identificacion';
    cargarCatalogos();
});

function err(campo) {
    return errores.value[campo]?.[0] || '';
}
function seccionConError(id) {
    return SECCIONES.find((s) => s.id === id).campos.some((c) => errores.value[c]);
}

function payload() {
    const datos = {};
    for (const [k, v] of Object.entries(f)) {
        if (k === 'controles_iso_ids') { datos[k] = v; continue; }
        if (['ram_gb', 'almacenamiento_gb', 'confidencialidad', 'integridad', 'disponibilidad', 'laboratorio_id', 'departamento_id', 'responsable_id'].includes(k)) {
            datos[k] = v === '' || v === null ? null : Number(v);
            continue;
        }
        datos[k] = typeof v === 'string' && v.trim() === '' ? null : v;
    }
    if (!datos.departamento_id) delete datos.departamento_id;
    return datos;
}

async function guardar() {
    errores.value = {};
    errorGeneral.value = '';
    if (!String(f.nombre_equipo || '').trim()) {
        errores.value = { nombre_equipo: ['Escribe el nombre del equipo.'] };
        seccion.value = 'identificacion';
        return;
    }
    guardando.value = true;
    try {
        const { data } = esEdicion.value
            ? await inventarioApi.actualizarComputadora(props.computadora.uuid, payload())
            : await inventarioApi.crearComputadora(payload());
        emit('guardado', data.data);
    } catch (e) {
        errores.value = e.details || {};
        const conError = SECCIONES.find((s) => s.campos.some((c) => errores.value[c]));
        if (conError) seccion.value = conError.id;
        errorGeneral.value = Object.keys(errores.value).length ? 'Revisa los campos marcados.' : (e.message || 'No se pudo guardar el equipo.');
    } finally {
        guardando.value = false;
    }
}
</script>

<style scoped>
.equipo-form__pasos { display: flex; gap: 6px; flex-wrap: wrap; padding-bottom: var(--space-2); border-bottom: 1px solid var(--border); }
.equipo-form__paso {
    padding: 7px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--text-muted);
    font-size: var(--text-xs); font-weight: 600; cursor: pointer;
    transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast), transform var(--transition-fast);
}
.equipo-form__paso:hover { border-color: var(--c-primary); color: var(--c-primary); }
.equipo-form__paso:active { transform: scale(0.97); }
.equipo-form__paso--activo { background: var(--c-primary); border-color: var(--c-primary); color: var(--on-primary); }
.equipo-form__paso--error:not(.equipo-form__paso--activo) { border-color: var(--c-risk); color: var(--c-risk); }
.equipo-form__seccion { border: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--space-4); min-width: 0; }
.equipo-form__controles { display: flex; flex-direction: column; gap: 8px; }
.equipo-form__error { margin: 0; padding: 10px 14px; border-radius: var(--radius-md); background: var(--c-risk-soft); color: var(--c-risk); font-size: var(--text-sm); font-weight: 500; }
.equipo-form__acciones { display: flex; justify-content: flex-end; gap: var(--space-3); flex-wrap: wrap; }
.paso-enter-active, .paso-leave-active { transition: opacity 180ms var(--ease), transform 180ms var(--ease); }
.paso-enter-from { opacity: 0; transform: translateX(8px); }
.paso-leave-to { opacity: 0; transform: translateX(-6px); }
@media (max-width: 640px) {
    .form__grid, .form__cia { grid-template-columns: 1fr; }
    .equipo-form__acciones :deep(.app-btn) { flex: 1; }
}
</style>
