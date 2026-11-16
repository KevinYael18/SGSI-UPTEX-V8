<template>
  <IaPanel
    :titulo="activoUuid ? 'Análisis del activo' : 'Revisión del registro'"
    aviso="Se envían el nombre, tipo, valoración CID y características técnicas del activo (sin números de serie, IP ni datos de contacto)."
    :cargando="cargando"
    :error="error"
    :hay-resultado="!!r"
    :analisis-uuid="r?.analisis_uuid || ''"
    :texto-aceptar="activoUuid ? 'Aceptar recomendaciones' : 'Aplicar clasificación al formulario'"
    :nota-aceptar="activoUuid ? 'Aceptar no modifica el activo: aplica los cambios con «Editar».' : 'Solo se aplican confidencialidad, integridad y disponibilidad; puedes ajustarlas antes de guardar.'"
    :al-aceptar="aceptar"
  >
    <template #entrada>
      <AppButton type="button" variante="secundaria" icono="chispa" :cargando="cargando" style="width:100%" @click="analizar">
        {{ r ? 'Volver a analizar' : activoUuid ? 'Analizar activo con IA' : 'Revisar registro con IA' }}
      </AppButton>
    </template>

    <template v-if="r">
      <p v-if="r.resumen" class="ia-activo__resumen">{{ r.resumen }}</p>

      <IaSeccion v-if="r.campos_faltantes?.length" titulo="Campos faltantes en el registro" icono="alerta" tono="advertencia" :elementos="r.campos_faltantes" />

      <IaSeccion v-if="r.clasificacion_sugerida" titulo="Clasificación sugerida" icono="escudo">
        <div class="ia-activo__cid">
          <div v-for="c in ['confidencialidad', 'integridad', 'disponibilidad']" :key="c">
            <span>{{ c }}</span><strong>{{ r.clasificacion_sugerida[c] }}</strong>
          </div>
          <div><span>criticidad</span><strong>{{ r.clasificacion_sugerida.criticidad_resultante }}</strong></div>
        </div>
        <p v-if="r.nivel_informacion">Nivel de clasificación de la información: <AppBadge tono="signal">{{ r.nivel_informacion }}</AppBadge></p>
        <p v-if="r.clasificacion_sugerida.justificacion">{{ r.clasificacion_sugerida.justificacion }}</p>
      </IaSeccion>

      <IaSeccion v-if="r.riesgos_asociados?.length" titulo="Riesgos asociados" icono="riesgos" tono="riesgo">
        <ul class="ia-activo__riesgos">
          <li v-for="(x, i) in r.riesgos_asociados" :key="i">
            <strong>{{ x.amenaza }}</strong><template v-if="x.vulnerabilidad"> · {{ x.vulnerabilidad }}</template>
            <AppBadge v-if="x.amenaza_id" tono="neutral">en catálogo</AppBadge>
            <p v-if="x.descripcion">{{ x.descripcion }}</p>
          </li>
        </ul>
      </IaSeccion>
      <IaSeccion titulo="Posibles vulnerabilidades" icono="candado" tono="advertencia" :elementos="r.vulnerabilidades_posibles" />
      <IaSeccion titulo="Medidas de protección" icono="escudo" tono="exito" :elementos="r.medidas_proteccion" />
      <IaControles :controles="r.controles_sugeridos" :descartados="r.controles_descartados" />
      <IaSeccion titulo="Mejoras para el inventario" icono="capas" :elementos="r.mejoras_inventario" />
    </template>
  </IaPanel>
</template>

<script setup>
import { ref, watch } from 'vue';
import iaApi from '../../api/ia';
import AppButton from '../ui/AppButton.vue';
import AppBadge from '../ui/AppBadge.vue';
import IaPanel from './IaPanel.vue';
import IaSeccion from './IaSeccion.vue';
import IaControles from './IaControles.vue';

const props = defineProps({
    // Activo registrado (detalle) o borrador del formulario de alta.
    activoUuid: { type: String, default: '' },
    borrador: { type: Object, default: null },
});
// Solo en modo borrador: { confidencialidad, integridad, disponibilidad }
const emit = defineEmits(['aplicar']);

const cargando = ref(false);
const error = ref('');
const r = ref(null);

watch(() => props.activoUuid, () => { r.value = null; });

function limpiarBorrador(b) {
    const datos = {};
    for (const [k, v] of Object.entries(b || {})) {
        if (v !== '' && v !== null && v !== undefined) datos[k] = v;
    }
    return datos;
}

async function analizar() {
    cargando.value = true;
    error.value = '';
    r.value = null;
    try {
        const cuerpo = props.activoUuid ? { activo_uuid: props.activoUuid } : { borrador: limpiarBorrador(props.borrador) };
        const { data } = await iaApi.analizarActivo(cuerpo);
        r.value = data.data;
    } catch (e) {
        error.value = e.message || 'SGSI Intelligence Assistant no está disponible en este momento.';
    }
    cargando.value = false;
}

function aceptar() {
    if (props.activoUuid || !r.value?.clasificacion_sugerida) {
        return { decision: 'aceptada', elementos: [] };
    }
    const { confidencialidad, integridad, disponibilidad } = r.value.clasificacion_sugerida;
    emit('aplicar', { confidencialidad, integridad, disponibilidad });

    return { decision: 'aceptada', elementos: ['confidencialidad', 'integridad', 'disponibilidad'] };
}
</script>

<style scoped>
.ia-activo__resumen { margin: 0; font-size: var(--text-sm); color: var(--text); line-height: 1.55; }
.ia-activo__cid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
.ia-activo__cid div { display: flex; flex-direction: column; padding: 6px 8px; border-radius: var(--radius-sm); background: var(--surface); }
.ia-activo__cid span { font-size: 0.68rem; color: var(--text-muted); text-transform: capitalize; }
.ia-activo__cid strong { font-family: var(--font-display); font-size: var(--text-lg); color: var(--c-primary); }
.ia-activo__riesgos { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; font-size: var(--text-sm); color: var(--text); }
.ia-activo__riesgos p { margin: 2px 0 0; font-size: var(--text-xs); color: var(--text-muted); }
@media (max-width: 480px) { .ia-activo__cid { grid-template-columns: 1fr 1fr; } }
</style>
