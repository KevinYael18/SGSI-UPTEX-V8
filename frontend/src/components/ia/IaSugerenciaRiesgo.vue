<template>
  <IaPanel
    titulo="Análisis del riesgo"
    aviso="Se envían al asistente el activo (nombre, tipo y valoración CID), la amenaza, la vulnerabilidad y el contexto que escribas. Nada se aplica sin tu confirmación."
    :cargando="loading"
    :error="error"
    :hay-resultado="!!resultado"
    :analisis-uuid="resultado?.analisis_uuid || ''"
    texto-aceptar="Aplicar selección al formulario"
    nota-aceptar="Solo se aplica lo que marques; podrás ajustar los valores antes de guardar."
    :al-aceptar="aplicar"
  >
    <template #entrada>
      <label class="ia-riesgo__campo">
        <span>Contexto adicional (opcional)</span>
        <textarea v-model="contexto" maxlength="500" rows="2" placeholder="Ej. el servidor está expuesto a Internet y no tiene respaldo externo" />
      </label>
      <AppButton type="button" variante="secundaria" icono="chispa" :cargando="loading" style="width:100%" @click="sugerir">
        {{ resultado ? 'Volver a analizar' : 'Sugerir con IA' }}
      </AppButton>
    </template>

    <template v-if="resultado">
      <div class="ia-riesgo__nivel">
        <div class="ia-riesgo__dato">
          <span class="ia-riesgo__etiqueta">Probabilidad</span>
          <span class="ia-riesgo__numero">{{ resultado.probabilidad_sugerida }}</span>
          <span class="ia-riesgo__escala">{{ etiquetaNivel(resultado.probabilidad_sugerida) }}</span>
        </div>
        <div class="ia-riesgo__dato">
          <span class="ia-riesgo__etiqueta">Impacto</span>
          <span class="ia-riesgo__numero">{{ resultado.impacto_sugerido }}</span>
          <span class="ia-riesgo__escala">{{ etiquetaNivel(resultado.impacto_sugerido) }}</span>
        </div>
        <div class="ia-riesgo__dato">
          <span class="ia-riesgo__etiqueta">Nivel (matriz del sistema)</span>
          <AppBadge v-if="resultado.nivel_sugerido" :tono="tonoNivel(resultado.nivel_sugerido)">{{ resultado.nivel_sugerido }}</AppBadge>
          <span v-else class="ia-riesgo__escala">Se calculará al guardar</span>
        </div>
      </div>
      <p v-if="resultado.metodologia" class="ia-riesgo__metodologia">
        Metodología del sistema: probabilidad {{ resultado.metodologia.probabilidad ?? '—' }}, impacto {{ resultado.metodologia.impacto ?? '—' }}<template v-if="resultado.metodologia.nivel"> → {{ resultado.metodologia.nivel }}</template>.
      </p>
      <IaSeccion v-if="resultado.justificacion_nivel" titulo="Justificación" icono="info"><p>{{ resultado.justificacion_nivel }}</p></IaSeccion>

      <IaSeccion titulo="Amenazas identificadas" icono="alerta" tono="riesgo" :elementos="resultado.amenazas_identificadas" />
      <IaSeccion titulo="Vulnerabilidades" icono="candado" tono="advertencia" :elementos="resultado.vulnerabilidades_identificadas" />
      <IaSeccion titulo="Posibles consecuencias" icono="incidentes" :elementos="resultado.consecuencias" />
      <IaSeccion v-if="tieneCid" titulo="Análisis de confidencialidad, integridad y disponibilidad" icono="escudo">
        <dl class="ia-riesgo__cid">
          <template v-for="(texto, clave) in resultado.analisis_cid" :key="clave">
            <div v-if="texto"><dt>{{ clave }}</dt><dd>{{ texto }}</dd></div>
          </template>
        </dl>
      </IaSeccion>
      <IaSeccion v-if="resultado.tratamiento_sugerido" :titulo="`Tratamiento sugerido: ${resultado.tratamiento_sugerido}`" icono="check" tono="exito">
        <p v-if="resultado.justificacion_tratamiento">{{ resultado.justificacion_tratamiento }}</p>
      </IaSeccion>
      <IaControles :controles="resultado.controles_sugeridos" :descartados="resultado.controles_descartados" />
      <IaSeccion titulo="Acciones preventivas" icono="escudo" :elementos="resultado.acciones_preventivas" />
      <IaSeccion v-if="resultado.riesgo_residual" titulo="Riesgo residual estimado" icono="tendencia">
        <p>
          Probabilidad {{ resultado.riesgo_residual.probabilidad }} × impacto {{ resultado.riesgo_residual.impacto }}
          <AppBadge v-if="resultado.riesgo_residual.nivel" :tono="tonoNivel(resultado.riesgo_residual.nivel)">{{ resultado.riesgo_residual.nivel }}</AppBadge>
        </p>
        <p v-if="resultado.riesgo_residual.justificacion">{{ resultado.riesgo_residual.justificacion }}</p>
      </IaSeccion>
      <IaSeccion titulo="Recomendaciones" icono="chispa" :elementos="resultado.recomendaciones" />

      <fieldset class="ia-riesgo__aplicar">
        <legend>¿Qué deseas aplicar al formulario?</legend>
        <label><input v-model="seleccion.nivel" type="checkbox" /> Probabilidad e impacto</label>
        <label v-if="resultado.tratamiento_sugerido"><input v-model="seleccion.tratamiento" type="checkbox" /> Tratamiento ({{ resultado.tratamiento_sugerido }})</label>
        <label v-if="resultado.riesgo_residual && permiteResidual"><input v-model="seleccion.residual" type="checkbox" /> Riesgo residual</label>
      </fieldset>
    </template>
  </IaPanel>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import riesgosApi from '../../api/riesgos';
import AppButton from '../ui/AppButton.vue';
import AppBadge from '../ui/AppBadge.vue';
import IaPanel from './IaPanel.vue';
import IaSeccion from './IaSeccion.vue';
import IaControles from './IaControles.vue';

const props = defineProps({
    activoId: { type: [Number, String], required: true },
    amenazaId: { type: [Number, String], required: true },
    vulnerabilidadId: { type: [Number, String], default: null },
    riesgoId: { type: [Number, String], default: null },
    descripcion: { type: String, default: '' },
    // El formulario de edición tiene campos de riesgo residual; el de alta no.
    permiteResidual: { type: Boolean, default: false },
});

// Emite { probabilidad, impacto, tipo_tratamiento?, probabilidad_residual?, impacto_residual?, modelo }
const emit = defineEmits(['aplicar']);

const ETIQUETAS_NIVEL = { 1: 'Muy baja', 2: 'Baja', 3: 'Media', 4: 'Alta', 5: 'Muy alta' };

const loading = ref(false);
const resultado = ref(null);
const error = ref('');
const contexto = ref(props.descripcion);
const seleccion = reactive({ nivel: true, tratamiento: true, residual: false });

const tieneCid = computed(() => Object.values(resultado.value?.analisis_cid || {}).some(Boolean));

// Una sugerencia vieja no debe aplicarse a otro activo/amenaza.
watch(() => [props.activoId, props.amenazaId, props.vulnerabilidadId], () => {
    resultado.value = null;
    error.value = '';
});

function etiquetaNivel(valor) {
    return ETIQUETAS_NIVEL[valor] ?? '';
}
function tonoNivel(n) {
    return { bajo: 'exito', medio: 'advertencia', alto: 'advertencia', critico: 'riesgo' }[n] || 'neutral';
}

async function sugerir() {
    loading.value = true;
    error.value = '';
    resultado.value = null;
    try {
        const { data } = await riesgosApi.sugerirConIa({
            activo_id: props.activoId,
            amenaza_id: props.amenazaId,
            vulnerabilidad_id: props.vulnerabilidadId || undefined,
            riesgo_id: props.riesgoId || undefined,
            descripcion: contexto.value.trim() || undefined,
        });
        resultado.value = data.data;
        Object.assign(seleccion, { nivel: true, tratamiento: !!data.data.tratamiento_sugerido, residual: false });
    } catch (e) {
        // http.js ya normaliza el error: { status, code, message, details }.
        error.value = e.message || 'SGSI Intelligence Assistant no está disponible en este momento.';
    }
    loading.value = false;
}

/** Se ejecuta al confirmar: aplica SOLO lo marcado y registra si fue total o parcial. */
function aplicar() {
    const r = resultado.value;
    const cambios = { modelo: r.modelo };
    const elementos = [];
    if (seleccion.nivel) {
        cambios.probabilidad = r.probabilidad_sugerida;
        cambios.impacto = r.impacto_sugerido;
        elementos.push('probabilidad', 'impacto');
    }
    if (seleccion.tratamiento && r.tratamiento_sugerido) {
        cambios.tipo_tratamiento = r.tratamiento_sugerido;
        elementos.push('tipo_tratamiento');
    }
    if (seleccion.residual && r.riesgo_residual && props.permiteResidual) {
        cambios.probabilidad_residual = r.riesgo_residual.probabilidad;
        cambios.impacto_residual = r.riesgo_residual.impacto;
        elementos.push('riesgo_residual');
    }
    if (!elementos.length) {
        throw new Error('Marca al menos un elemento para aplicar, o rechaza la propuesta.');
    }
    emit('aplicar', cambios);
    const disponibles = 1 + (r.tratamiento_sugerido ? 1 : 0) + (r.riesgo_residual && props.permiteResidual ? 1 : 0);
    const aplicados = (seleccion.nivel ? 1 : 0) + (elementos.includes('tipo_tratamiento') ? 1 : 0) + (elementos.includes('riesgo_residual') ? 1 : 0);

    return { decision: aplicados < disponibles ? 'parcial' : 'aceptada', elementos };
}
</script>

<style scoped>
.ia-riesgo__campo { display: flex; flex-direction: column; gap: var(--space-1); font-size: var(--text-sm); color: var(--text); }
.ia-riesgo__campo textarea {
    font: inherit; padding: 8px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--text); resize: vertical;
}
.ia-riesgo__campo textarea:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.ia-riesgo__nivel { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-2); }
.ia-riesgo__dato {
    display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 10px 12px;
    border-radius: var(--radius-md); background: var(--bg-subtle);
}
.ia-riesgo__etiqueta { font-size: 0.7rem; color: var(--text-muted); }
.ia-riesgo__numero { font-family: var(--font-display); font-size: var(--text-xl); font-weight: 700; color: var(--c-primary); line-height: 1.1; }
.ia-riesgo__escala { font-size: var(--text-xs); color: var(--text-muted); }
.ia-riesgo__metodologia { margin: 0; font-size: var(--text-xs); color: var(--text-muted); }
.ia-riesgo__cid { display: flex; flex-direction: column; gap: 6px; margin: 0; }
.ia-riesgo__cid dt { font-size: var(--text-xs); font-weight: 700; color: var(--text); text-transform: capitalize; }
.ia-riesgo__cid dd { margin: 0; font-size: var(--text-sm); color: var(--text-muted); line-height: 1.5; }
.ia-riesgo__aplicar {
    display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 0; padding: 10px 12px;
    border: 1px solid var(--border); border-radius: var(--radius-md); font-size: var(--text-sm); color: var(--text);
}
.ia-riesgo__aplicar legend { padding: 0 4px; font-size: var(--text-xs); font-weight: 700; color: var(--text-muted); }
.ia-riesgo__aplicar label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
@media (max-width: 480px) { .ia-riesgo__nivel { grid-template-columns: 1fr 1fr; } }
</style>
