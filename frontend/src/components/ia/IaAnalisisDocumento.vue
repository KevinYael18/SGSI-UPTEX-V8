<template>
  <IaPanel
    titulo="Análisis del documento"
    aviso="Se analiza el texto de la versión vigente. El documento original nunca se modifica: las mejoras son propuestas para una nueva versión."
    :cargando="cargando"
    texto-cargando="Leyendo y analizando el documento…"
    :error="error"
    :hay-resultado="!!r"
    :analisis-uuid="r?.analisis_uuid || ''"
    texto-aceptar="Aceptar recomendaciones"
    nota-aceptar="Aceptar no cambia el documento: incorpora las mejoras al preparar una nueva versión."
  >
    <template #entrada>
      <label class="ia-doc__campo">
        <span>Pregunta sobre el documento (opcional)</span>
        <input v-model="pregunta" maxlength="500" placeholder="Ej. ¿Quién es responsable de aprobar los respaldos?" @keydown.enter.prevent="analizar" />
      </label>
      <AppButton type="button" variante="secundaria" icono="chispa" :cargando="cargando" style="width:100%" @click="analizar">
        {{ r ? 'Volver a analizar' : 'Analizar documento con IA' }}
      </AppButton>
    </template>

    <template v-if="r">
      <p class="ia-doc__advertencia"><AppIcon name="info" :size="14" />{{ r.advertencia }}</p>
      <p v-if="r.contiene_instrucciones_sospechosas" class="ia-doc__alerta">
        <AppIcon name="alerta" :size="14" />El documento contiene texto que parece una instrucción dirigida a una IA. Se trató solo como contenido; revíselo.
      </p>

      <div class="ia-doc__meta">
        <AppBadge tono="signal">{{ r.tipo_documento }}</AppBadge>
        <AppBadge tono="neutral">Versión analizada: v{{ r.version_analizada }}</AppBadge>
        <AppBadge v-if="r.necesita_actualizacion" tono="advertencia">Requiere actualización</AppBadge>
        <AppBadge v-if="r.documento_recortado" tono="neutral">Análisis parcial (documento extenso)</AppBadge>
      </div>

      <IaSeccion v-if="r.respuesta_pregunta" titulo="Respuesta a tu pregunta" icono="comentario" tono="exito"><p>{{ r.respuesta_pregunta }}</p></IaSeccion>
      <IaSeccion v-if="r.resumen" titulo="Resumen" icono="documentos"><p>{{ r.resumen }}</p></IaSeccion>
      <IaSeccion titulo="Posibles inconsistencias" icono="alerta" tono="riesgo" :elementos="r.inconsistencias" />
      <IaSeccion titulo="Información aparentemente faltante" icono="info" tono="advertencia" :elementos="r.informacion_faltante" />
      <IaSeccion titulo="Mejoras recomendadas" icono="tendencia" tono="exito" :elementos="r.mejoras_recomendadas" />
      <IaSeccion v-if="r.necesita_actualizacion && r.motivo_actualizacion" titulo="Motivo de actualización" icono="calendario" tono="advertencia"><p>{{ r.motivo_actualizacion }}</p></IaSeccion>
      <IaControles :controles="r.controles_relacionados" :descartados="r.controles_descartados" titulo="Controles del Anexo A relacionados" />
    </template>
  </IaPanel>
</template>

<script setup>
import { ref } from 'vue';
import iaApi from '../../api/ia';
import AppButton from '../ui/AppButton.vue';
import AppBadge from '../ui/AppBadge.vue';
import AppIcon from '../ui/AppIcon.vue';
import IaPanel from './IaPanel.vue';
import IaSeccion from './IaSeccion.vue';
import IaControles from './IaControles.vue';

const props = defineProps({ uuid: { type: String, required: true } });

const cargando = ref(false);
const error = ref('');
const pregunta = ref('');
const r = ref(null);

async function analizar() {
    if (cargando.value) return;
    cargando.value = true;
    error.value = '';
    r.value = null;
    try {
        const { data } = await iaApi.analizarDocumento(props.uuid, pregunta.value.trim());
        r.value = data.data;
    } catch (e) {
        error.value = e.message || 'SGSI Intelligence Assistant no está disponible en este momento.';
    }
    cargando.value = false;
}
</script>

<style scoped>
.ia-doc__campo { display: flex; flex-direction: column; gap: var(--space-1); font-size: var(--text-sm); color: var(--text); }
.ia-doc__campo input {
    font: inherit; padding: 9px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--text);
}
.ia-doc__campo input:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.ia-doc__advertencia, .ia-doc__alerta {
    display: flex; gap: 6px; align-items: flex-start; margin: 0; padding: 8px 10px; border-radius: var(--radius-sm);
    font-size: var(--text-xs); line-height: 1.5; background: var(--bg-subtle); color: var(--text-muted);
}
.ia-doc__alerta { background: var(--c-amber-soft); color: var(--c-amber); }
.ia-doc__meta { display: flex; flex-wrap: wrap; gap: 6px; text-transform: capitalize; }
</style>
