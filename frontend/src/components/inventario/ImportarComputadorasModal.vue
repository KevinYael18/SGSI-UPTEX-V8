<template>
  <AppModal :abierto="abierto" titulo="Importar computadoras (Excel o CSV)" ancho @cerrar="cerrar">
    <div class="importar">
      <ol class="importar__pasos">
        <li :class="{ activo: !analisis }">1. Archivo</li>
        <li :class="{ activo: analisis && !resultado }">2. Revisión</li>
        <li :class="{ activo: resultado }">3. Resultado</li>
      </ol>

      <Transition name="paso" mode="out-in">
        <!-- Paso 1 -->
        <div v-if="!analisis" key="archivo" class="form">
          <p class="form__nota">
            Usa la plantilla oficial. Nada se guarda en este paso: primero verás una vista previa con los equipos nuevos,
            los duplicados y los errores. Los edificios, laboratorios y programas deben existir previamente.
          </p>
          <AppButton type="button" variante="secundaria" icono="descargar" @click="descargarPlantilla">Descargar plantilla</AppButton>
          <input type="file" accept=".xlsx,.csv" aria-label="Archivo de importación" @change="(e) => (archivo = e.target.files?.[0] || null)" />
          <AppSelect v-model="opciones.laboratorio_id" label="Laboratorio para filas sin laboratorio (opcional)"
            :opciones="[{ value: '', label: 'No asignar' }, ...opcionesLaboratorios]" />
          <Transition name="aviso"><p v-if="error" class="importar__error" role="alert">{{ error }}</p></Transition>
          <AppButton :cargando="cargando" :disabled="!archivo" @click="analizar">Analizar archivo</AppButton>
        </div>

        <!-- Paso 2 -->
        <div v-else-if="!resultado" key="revision" class="form">
          <div class="importar__resumen">
            <span class="chip chip--exito">{{ analisis.resumen.nuevas }} nuevas</span>
            <span class="chip chip--advertencia">{{ analisis.resumen.duplicadas }} duplicadas</span>
            <span class="chip chip--riesgo">{{ analisis.resumen.con_errores }} con errores (se omitirán)</span>
          </div>
          <div class="importar__tabla">
            <table>
              <thead><tr><th>Fila</th><th>Equipo</th><th>Inventario</th><th>Laboratorio</th><th>Resultado</th></tr></thead>
              <tbody>
                <tr v-for="fila in analisis.filas" :key="fila.fila" :class="`fila--${fila.estado}`">
                  <td>{{ fila.fila }}</td>
                  <td>{{ fila.nombre_equipo || '—' }}</td>
                  <td>{{ fila.numero_inventario || '—' }}</td>
                  <td>{{ fila.laboratorio || '—' }}</td>
                  <td>
                    <strong>{{ { nueva: 'Nueva', duplicado: 'Duplicada', error: 'Error' }[fila.estado] }}</strong>
                    <span v-if="fila.coincide_con"> — coincide con {{ fila.coincide_con.nombre_equipo }}</span>
                    <ul v-if="fila.errores.length || fila.avisos.length">
                      <li v-for="(m, i) in fila.errores" :key="`e${i}`" class="msg-error">{{ m }}</li>
                      <li v-for="(m, i) in fila.avisos" :key="`a${i}`" class="msg-aviso">{{ m }}</li>
                    </ul>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <fieldset class="importar__opciones">
            <legend>Valoración de los equipos nuevos (activos de hardware)</legend>
            <div class="form__cia">
              <AppInput v-model="opciones.confidencialidad" type="number" min="1" max="5" label="Confidencialidad" requerido />
              <AppInput v-model="opciones.integridad" type="number" min="1" max="5" label="Integridad" requerido />
              <AppInput v-model="opciones.disponibilidad" type="number" min="1" max="5" label="Disponibilidad" requerido />
            </div>
            <AppSelect v-if="analisis.resumen.duplicadas" v-model="opciones.duplicados" label="¿Qué hacer con los duplicados?"
              :opciones="[{ value: 'omitir', label: 'Omitirlos (no modificar los equipos existentes)' }, { value: 'actualizar', label: 'Actualizarlos con los datos del archivo' }]" />
            <p v-if="opciones.duplicados === 'actualizar'" class="form__nota importar__advertencia">
              Se sobrescribirán {{ analisis.resumen.duplicadas }} equipo(s) existente(s) con los valores del archivo. Esta acción queda registrada en la bitácora.
            </p>
          </fieldset>

          <Transition name="aviso"><p v-if="error" class="importar__error" role="alert">{{ error }}</p></Transition>
          <div class="importar__acciones">
            <AppButton variante="secundaria" @click="analisis = null">Elegir otro archivo</AppButton>
            <AppButton :cargando="cargando" :disabled="!analisis.resumen.nuevas && !(analisis.resumen.duplicadas && opciones.duplicados === 'actualizar')" @click="importar">
              Importar
            </AppButton>
          </div>
        </div>

        <!-- Paso 3 -->
        <div v-else key="resultado" class="form">
          <p class="importar__exito" role="status">
            Importación terminada: <strong>{{ resultado.creadas }}</strong> creadas, <strong>{{ resultado.actualizadas }}</strong> actualizadas,
            <strong>{{ resultado.omitidas }}</strong> omitidas.
          </p>
          <ul v-if="resultado.fallidas.length" class="importar__fallidas">
            <li v-for="f in resultado.fallidas" :key="f.fila">Fila {{ f.fila }}: {{ f.errores.join(' ') }}</li>
          </ul>
          <AppButton @click="cerrar">Cerrar</AppButton>
        </div>
      </Transition>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import inventarioApi from '../../api/inventario';
import { useToastStore } from '../../stores/toast';
import AppModal from '../ui/AppModal.vue';
import AppButton from '../ui/AppButton.vue';
import AppInput from '../ui/AppInput.vue';
import AppSelect from '../ui/AppSelect.vue';

const props = defineProps({
    abierto: Boolean,
    laboratorios: { type: Array, default: () => [] },
    laboratorioId: { type: [Number, String], default: '' },
});
const emit = defineEmits(['cerrar', 'importado']);
const toast = useToastStore();

const archivo = ref(null);
const analisis = ref(null);
const resultado = ref(null);
const cargando = ref(false);
const error = ref('');
const opciones = reactive({ laboratorio_id: '', confidencialidad: 3, integridad: 3, disponibilidad: 3, duplicados: 'omitir' });
const opcionesLaboratorios = computed(() => props.laboratorios.map((l) => ({ value: l.id, label: `${l.nombre} · ${l.edificio}` })));

watch(() => props.abierto, (abierto) => {
    if (!abierto) return;
    Object.assign(opciones, { laboratorio_id: props.laboratorioId || '', confidencialidad: 3, integridad: 3, disponibilidad: 3, duplicados: 'omitir' });
    archivo.value = null; analisis.value = null; resultado.value = null; error.value = '';
});

function formulario(conOpciones) {
    const fd = new FormData();
    fd.append('archivo', archivo.value);
    if (opciones.laboratorio_id) fd.append('laboratorio_id', opciones.laboratorio_id);
    if (conOpciones) {
        ['confidencialidad', 'integridad', 'disponibilidad', 'duplicados'].forEach((k) => fd.append(k, opciones[k]));
    }
    return fd;
}

async function analizar() {
    error.value = '';
    cargando.value = true;
    try {
        const { data } = await inventarioApi.analizarImportacion(formulario(false));
        analisis.value = data.data;
    } catch (e) {
        error.value = e.details?.archivo?.[0] || e.message;
    } finally {
        cargando.value = false;
    }
}

async function importar() {
    error.value = '';
    cargando.value = true;
    try {
        const { data } = await inventarioApi.importar(formulario(true));
        resultado.value = data.data;
        emit('importado', data.data);
    } catch (e) {
        error.value = Object.values(e.details || {})[0]?.[0] || e.message;
    } finally {
        cargando.value = false;
    }
}

async function descargarPlantilla() {
    try { await inventarioApi.plantilla(); } catch (e) { toast.error(e.message); }
}

function cerrar() { emit('cerrar'); }
</script>

<style scoped>
.importar { display: flex; flex-direction: column; gap: var(--space-4); }
.importar__pasos { display: flex; gap: var(--space-3); list-style: none; margin: 0; padding: 0; font-size: var(--text-xs); color: var(--text-subtle); font-weight: 600; flex-wrap: wrap; }
.importar__pasos li { padding: 4px 10px; border-radius: 999px; background: var(--bg-subtle); transition: background var(--transition-base), color var(--transition-base); }
.importar__pasos li.activo { background: var(--c-primary-soft); color: var(--c-primary); }
.importar__resumen { display: flex; gap: 8px; flex-wrap: wrap; }
.chip { padding: 4px 10px; border-radius: 999px; font-size: var(--text-xs); font-weight: 600; background: var(--bg-subtle); }
.chip--exito { background: var(--c-success-soft); color: var(--c-success); }
.chip--advertencia { background: var(--c-amber-soft); color: var(--c-amber); }
.chip--riesgo { background: var(--c-risk-soft); color: var(--c-risk); }
.importar__tabla { max-height: 320px; overflow: auto; border: 1px solid var(--border); border-radius: var(--radius-md); }
.importar__tabla table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
.importar__tabla th, .importar__tabla td { padding: 8px 10px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
.importar__tabla th { position: sticky; top: 0; background: var(--bg-subtle); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); }
.importar__tabla ul { margin: 4px 0 0; padding-left: 16px; }
.fila--error td:last-child strong { color: var(--c-risk); }
.fila--duplicado td:last-child strong { color: var(--c-amber); }
.fila--nueva td:last-child strong { color: var(--c-success); }
.msg-error { color: var(--c-risk); }
.msg-aviso { color: var(--text-muted); }
.importar__opciones { border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-4); margin: 0; display: flex; flex-direction: column; gap: var(--space-3); min-width: 0; }
.importar__opciones legend { padding: 0 6px; font-weight: 600; font-size: var(--text-sm); }
.importar__advertencia { color: var(--c-amber); }
.importar__error { margin: 0; padding: 10px 14px; border-radius: var(--radius-md); background: var(--c-risk-soft); color: var(--c-risk); font-size: var(--text-sm); }
.importar__exito { margin: 0; padding: 14px; border-radius: var(--radius-md); background: var(--c-success-soft); color: var(--c-success); }
.importar__fallidas { margin: 0; padding-left: 18px; color: var(--c-risk); font-size: var(--text-sm); }
.importar__acciones { display: flex; justify-content: flex-end; gap: var(--space-3); flex-wrap: wrap; }
.paso-enter-active, .paso-leave-active { transition: opacity 180ms var(--ease), transform 180ms var(--ease); }
.paso-enter-from { opacity: 0; transform: translateY(6px); }
.paso-leave-to { opacity: 0; }
@media (max-width: 640px) { .form__cia { grid-template-columns: 1fr; } }
</style>
