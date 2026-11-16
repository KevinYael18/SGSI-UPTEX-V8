<template>
  <IaPanel
    titulo="Análisis del incidente"
    aviso="Se envían el título, la descripción, el activo afectado, el número de evidencias y las últimas notas internas (sin autores). El análisis no cambia el estado del incidente."
    :cargando="cargando"
    :error="error"
    :hay-resultado="!!r"
    :analisis-uuid="r?.analisis_uuid || ''"
    texto-aceptar="Guardar informe como nota interna"
    nota-aceptar="Al aceptar, el borrador del informe se agrega como comentario interno del incidente."
    :al-aceptar="guardarComoNota"
  >
    <template #entrada>
      <AppButton type="button" variante="secundaria" icono="chispa" :cargando="cargando" style="width:100%" @click="analizar">
        {{ r ? 'Volver a analizar' : 'Analizar incidente con IA' }}
      </AppButton>
    </template>

    <template v-if="r">
      <div class="ia-inc__cabecera">
        <AppBadge :tono="TONO_CERTEZA[r.nivel_certeza]">{{ ETIQUETA_CERTEZA[r.nivel_certeza] }}</AppBadge>
        <AppBadge tono="neutral">Categoría sugerida: {{ r.categoria_sugerida.replaceAll('_', ' ') }}</AppBadge>
        <AppBadge :tono="TONO_SEVERIDAD[r.severidad_sugerida]">Severidad {{ r.severidad_sugerida }}</AppBadge>
      </div>
      <p v-if="r.naturaleza" class="ia-inc__texto">{{ r.naturaleza }}</p>
      <p v-if="r.justificacion_certeza" class="ia-inc__nota">{{ r.justificacion_certeza }}</p>
      <p v-if="r.nota_certeza" class="ia-inc__ajuste"><AppIcon name="info" :size="14" />{{ r.nota_certeza }}</p>

      <IaSeccion titulo="Activos posiblemente afectados" icono="activos" :elementos="r.activos_afectados" />
      <IaSeccion v-if="Object.values(r.impacto || {}).some(Boolean)" titulo="Impacto en confidencialidad, integridad y disponibilidad" icono="escudo">
        <dl class="ia-inc__cid">
          <template v-for="(texto, clave) in r.impacto" :key="clave">
            <div v-if="texto"><dt>{{ clave }}</dt><dd>{{ texto }}</dd></div>
          </template>
        </dl>
      </IaSeccion>
      <IaSeccion titulo="Contención" icono="alerta" prioridad="alta" tono="riesgo" :elementos="r.acciones_contencion" />
      <IaSeccion titulo="Recuperación" icono="historial" prioridad="media" tono="advertencia" :elementos="r.acciones_recuperacion" />
      <IaSeccion titulo="Prevención" icono="escudo" prioridad="baja" tono="exito" :elementos="r.medidas_preventivas" />
      <IaControles :controles="r.controles_relacionados" :descartados="r.controles_descartados" titulo="Controles del Anexo A relacionados" />
      <IaSeccion titulo="Información pendiente para confirmar" icono="info" :elementos="r.informacion_faltante" />

      <div v-if="r.borrador_informe" class="ia-inc__informe">
        <div class="ia-inc__informe-cabecera">
          <h4>Borrador de informe técnico</h4>
          <AppButton tamano="sm" variante="fantasma" icono="copiar" @click="copiar">Copiar</AppButton>
        </div>
        <div class="ia-markdown" v-html="renderizarMarkdown(r.borrador_informe)" />
      </div>
    </template>
  </IaPanel>
</template>

<script setup>
import { ref } from 'vue';
import iaApi from '../../api/ia';
import comentariosApi from '../../api/comentarios';
import { useToastStore } from '../../stores/toast';
import { renderizarMarkdown } from '../../utils/markdownSeguro';
import AppButton from '../ui/AppButton.vue';
import AppBadge from '../ui/AppBadge.vue';
import AppIcon from '../ui/AppIcon.vue';
import IaPanel from './IaPanel.vue';
import IaSeccion from './IaSeccion.vue';
import IaControles from './IaControles.vue';

const props = defineProps({ uuid: { type: String, required: true } });
const emit = defineEmits(['nota-guardada']);
const toast = useToastStore();

const ETIQUETA_CERTEZA = { sospecha: 'Sospecha', indicio: 'Indicio', confirmado: 'Incidente confirmado' };
const TONO_CERTEZA = { sospecha: 'neutral', indicio: 'advertencia', confirmado: 'riesgo' };
const TONO_SEVERIDAD = { baja: 'exito', media: 'advertencia', alta: 'riesgo', critica: 'riesgo' };

const cargando = ref(false);
const error = ref('');
const r = ref(null);

async function analizar() {
    cargando.value = true;
    error.value = '';
    r.value = null;
    try {
        const { data } = await iaApi.analizarIncidente(props.uuid);
        r.value = data.data;
    } catch (e) {
        error.value = e.message || 'SGSI Intelligence Assistant no está disponible en este momento.';
    }
    cargando.value = false;
}

async function guardarComoNota() {
    const encabezado = '[Borrador generado por SGSI Intelligence Assistant — revisado por el usuario]\n\n';
    const cuerpo = r.value.borrador_informe || r.value.naturaleza;
    // Los comentarios admiten 2000 caracteres (StoreComentarioRequest).
    const maximo = 2000 - encabezado.length;
    const texto = encabezado + (cuerpo.length > maximo ? `${cuerpo.slice(0, maximo - 30)}\n… (informe recortado)` : cuerpo);
    // Mismo endpoint y permisos que un comentario escrito a mano.
    await comentariosApi.crear('incidentes', props.uuid, texto);
    toast.exito('Informe agregado como nota interna del incidente.');
    emit('nota-guardada');

    return { decision: 'aceptada', elementos: ['nota_interna'] };
}

async function copiar() {
    try {
        await navigator.clipboard.writeText(r.value.borrador_informe);
        toast.exito('Informe copiado al portapapeles.');
    } catch {
        toast.error('No se pudo copiar automáticamente; selecciona el texto manualmente.');
    }
}
</script>

<style scoped>
.ia-inc__cabecera { display: flex; flex-wrap: wrap; gap: 6px; text-transform: capitalize; }
.ia-inc__texto { margin: 0; font-size: var(--text-sm); color: var(--text); line-height: 1.55; }
.ia-inc__nota { margin: 0; font-size: var(--text-xs); color: var(--text-muted); line-height: 1.5; }
.ia-inc__ajuste {
    display: flex; gap: 6px; align-items: flex-start; margin: 0; padding: 8px 10px; border-radius: var(--radius-sm);
    font-size: var(--text-xs); background: var(--c-amber-soft); color: var(--c-amber);
}
.ia-inc__cid { display: flex; flex-direction: column; gap: 6px; margin: 0; }
.ia-inc__cid dt { font-size: var(--text-xs); font-weight: 700; color: var(--text); text-transform: capitalize; }
.ia-inc__cid dd { margin: 0; font-size: var(--text-sm); color: var(--text-muted); line-height: 1.5; }
.ia-inc__informe { border: 1px solid var(--border); border-radius: var(--radius-md); padding: 12px 14px; background: var(--bg-subtle); }
.ia-inc__informe-cabecera { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.ia-inc__informe-cabecera h4 { margin: 0; font-size: var(--text-sm); color: var(--text); }
</style>
