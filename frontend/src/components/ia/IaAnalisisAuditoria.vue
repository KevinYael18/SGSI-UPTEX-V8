<template>
  <IaPanel
    titulo="Apoyo a la auditoría"
    aviso="Se envían el alcance, las fechas, los hallazgos registrados y las notas que escribas. Ningún hallazgo propuesto es oficial hasta que el auditor lo registre."
    :cargando="cargando"
    :error="error"
    :hay-resultado="!!r"
    :analisis-uuid="r?.analisis_uuid || ''"
    texto-aceptar="Aceptar como referencia"
    nota-aceptar="Para registrar un hallazgo usa «Usar como hallazgo»: se abrirá el formulario oficial para que lo valides."
  >
    <template #entrada>
      <label class="ia-aud__campo">
        <span>Notas o resultados del auditor (opcional)</span>
        <textarea v-model="notas" maxlength="4000" rows="3" placeholder="Ej. Se revisaron 12 expedientes; 3 sin evidencia de revisión de accesos…" />
      </label>
      <AppButton type="button" variante="secundaria" icono="chispa" :cargando="cargando" style="width:100%" @click="analizar">
        {{ r ? 'Volver a analizar' : 'Analizar auditoría con IA' }}
      </AppButton>
    </template>

    <template v-if="r">
      <p v-if="r.resumen" class="ia-aud__resumen">{{ r.resumen }}</p>

      <div v-if="r.hallazgos_identificados?.length" class="ia-aud__hallazgos">
        <h4><AppIcon name="auditorias" :size="15" />Hallazgos propuestos (requieren validación del auditor)</h4>
        <article v-for="(h, i) in r.hallazgos_identificados" :key="i" class="ia-aud__hallazgo" :class="`ia-aud__hallazgo--${h.severidad_sugerida}`">
          <div class="ia-aud__chips">
            <AppBadge :tono="TONO_SEVERIDAD[h.severidad_sugerida]">{{ h.severidad_sugerida }}</AppBadge>
            <AppBadge tono="neutral">{{ ETIQUETA_TIPO[h.tipo] }}</AppBadge>
            <AppBadge v-if="h.requisito_relacionado" tono="signal">{{ h.requisito_relacionado }}</AppBadge>
          </div>
          <p>{{ h.descripcion }}</p>
          <p v-if="h.justificacion" class="ia-aud__just">{{ h.justificacion }}</p>
          <AppButton v-if="puedeRegistrar" tamano="sm" variante="fantasma" icono="mas" @click="usar(h)">Usar como hallazgo</AppButton>
        </article>
        <p v-if="r.referencias_descartadas > 0" class="ia-aud__just">
          Se descartaron {{ r.referencias_descartadas }} referencia(s) normativa(s) que no pudieron verificarse en el catálogo del sistema.
        </p>
      </div>

      <IaSeccion titulo="Oportunidades de mejora" icono="tendencia" :elementos="r.oportunidades_mejora" />
      <IaSeccion titulo="Acciones correctivas sugeridas" icono="acciones" prioridad="alta" :elementos="r.acciones_correctivas_sugeridas" />
      <IaSeccion titulo="Hallazgos recurrentes" icono="historial" tono="advertencia" :elementos="r.hallazgos_recurrentes" />
      <IaSeccion v-if="r.analisis_evidencias" titulo="Suficiencia de evidencias" icono="documentos"><p>{{ r.analisis_evidencias }}</p></IaSeccion>
      <IaControles :controles="r.controles_relacionados" titulo="Controles del Anexo A relacionados" />
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

const props = defineProps({
    id: { type: [String, Number], required: true },
    puedeRegistrar: { type: Boolean, default: false },
});
// Abre el formulario oficial de hallazgo precargado: { severidad, descripcion }
const emit = defineEmits(['usar-hallazgo']);

const TONO_SEVERIDAD = { menor: 'advertencia', mayor: 'riesgo', critica: 'riesgo' };
const ETIQUETA_TIPO = { no_conformidad_potencial: 'Posible no conformidad', observacion: 'Observación', oportunidad_mejora: 'Oportunidad de mejora' };

const cargando = ref(false);
const error = ref('');
const notas = ref('');
const r = ref(null);

async function analizar() {
    cargando.value = true;
    error.value = '';
    r.value = null;
    try {
        const { data } = await iaApi.analizarAuditoria(props.id, notas.value.trim());
        r.value = data.data;
    } catch (e) {
        error.value = e.message || 'SGSI Intelligence Assistant no está disponible en este momento.';
    }
    cargando.value = false;
}

function usar(h) {
    const referencia = h.requisito_relacionado ? ` (Referencia: ${h.requisito_relacionado})` : '';
    emit('usar-hallazgo', { severidad: h.severidad_sugerida, descripcion: `${h.descripcion}${referencia}` });
}
</script>

<style scoped>
.ia-aud__campo { display: flex; flex-direction: column; gap: var(--space-1); font-size: var(--text-sm); color: var(--text); }
.ia-aud__campo textarea {
    font: inherit; padding: 8px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--text); resize: vertical;
}
.ia-aud__campo textarea:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.ia-aud__resumen { margin: 0; font-size: var(--text-sm); color: var(--text); line-height: 1.55; }
.ia-aud__hallazgos { display: flex; flex-direction: column; gap: 8px; }
.ia-aud__hallazgos h4 { display: flex; align-items: center; gap: 6px; margin: 0; font-size: var(--text-sm); color: var(--text); }
.ia-aud__hallazgos h4 :deep(svg) { color: var(--c-primary); }
.ia-aud__hallazgo {
    display: flex; flex-direction: column; align-items: flex-start; gap: 6px; padding: 10px 12px;
    border-radius: var(--radius-md); background: var(--bg-subtle); border-left: 3px solid var(--c-amber);
}
.ia-aud__hallazgo--mayor, .ia-aud__hallazgo--critica { border-left-color: var(--c-risk); }
.ia-aud__hallazgo p { margin: 0; font-size: var(--text-sm); color: var(--text); line-height: 1.5; }
.ia-aud__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ia-aud__just { margin: 0; font-size: var(--text-xs) !important; color: var(--text-muted) !important; }
</style>
