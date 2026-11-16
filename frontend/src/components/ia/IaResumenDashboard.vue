<template>
  <IaPanel
    titulo="Resumen ejecutivo del SGSI"
    aviso="Las cifras provienen directamente de GSI UPTex; la IA redacta el resumen y las prioridades con los módulos que tu rol puede ver."
    :cargando="cargando"
    texto-cargando="Analizando el estado del SGSI…"
    :error="error"
    :hay-resultado="!!r"
    :analisis-uuid="r?.analisis_uuid || ''"
    texto-aceptar="Marcar como revisado"
  >
    <template #entrada>
      <div class="ia-dash__accion">
        <p v-if="!r" class="ia-dash__invitacion">Obtén un panorama del estado del SGSI, los principales riesgos, incidentes pendientes y las acciones prioritarias.</p>
        <AppButton type="button" variante="secundaria" icono="chispa" :cargando="cargando" @click="generar">
          {{ r ? 'Actualizar resumen' : 'Generar resumen ejecutivo' }}
        </AppButton>
      </div>
    </template>

    <template v-if="r">
      <div class="ia-dash__estado">
        <AppBadge :tono="TONO_ESTADO[r.estado_general]">Estado general: {{ ETIQUETA_ESTADO[r.estado_general] }}</AppBadge>
        <span class="ia-dash__fecha">Generado {{ new Date(r.generado_en).toLocaleString('es-MX') }}</span>
      </div>
      <p v-if="r.resumen_ejecutivo" class="ia-dash__resumen">{{ r.resumen_ejecutivo }}</p>

      <dl v-if="indicadores.length" class="ia-dash__indicadores" aria-label="Indicadores reales del sistema">
        <div v-for="i in indicadores" :key="i.clave">
          <dt>{{ i.etiqueta }}</dt><dd>{{ i.valor }}</dd>
        </div>
      </dl>

      <div v-if="r.prioridades?.length" class="ia-dash__prioridades">
        <h4><AppIcon name="tendencia" :size="15" />Prioridades recomendadas</h4>
        <component
          :is="p.modulo && RUTAS[p.modulo] ? 'RouterLink' : 'div'"
          v-for="(p, i) in r.prioridades"
          :key="i"
          :to="RUTAS[p.modulo]"
          class="ia-dash__prioridad"
          :class="`ia-dash__prioridad--${p.prioridad}`"
        >
          <AppBadge :tono="TONO_PRIORIDAD[p.prioridad]">{{ p.prioridad }}</AppBadge>
          <div>
            <strong>{{ p.titulo }}</strong>
            <p v-if="p.descripcion">{{ p.descripcion }}</p>
          </div>
        </component>
      </div>
      <IaSeccion titulo="Áreas de mejora" icono="tendencia" :elementos="r.areas_mejora" />
      <IaSeccion titulo="Datos insuficientes" icono="info" tono="advertencia" :elementos="r.datos_insuficientes" />
    </template>
  </IaPanel>
</template>

<script setup>
import { computed, ref } from 'vue';
import iaApi from '../../api/ia';
import AppButton from '../ui/AppButton.vue';
import AppBadge from '../ui/AppBadge.vue';
import AppIcon from '../ui/AppIcon.vue';
import IaPanel from './IaPanel.vue';
import IaSeccion from './IaSeccion.vue';

const ETIQUETA_ESTADO = { adecuado: 'adecuado', requiere_atencion: 'requiere atención', critico: 'crítico', sin_datos_suficientes: 'sin datos suficientes' };
const TONO_ESTADO = { adecuado: 'exito', requiere_atencion: 'advertencia', critico: 'riesgo', sin_datos_suficientes: 'neutral' };
const TONO_PRIORIDAD = { alta: 'riesgo', media: 'advertencia', baja: 'signal' };
const RUTAS = {
    activos: { name: 'activos.lista' }, riesgos: { name: 'riesgos.lista' }, incidentes: { name: 'incidentes.lista' },
    auditorias: { name: 'auditorias.lista' }, documentos: { name: 'documentos.lista' }, cumplimiento: { name: 'cumplimiento' },
};
const ETIQUETAS = {
    activos_totales: 'Activos', activos_criticidad_alta: 'Activos de criticidad alta', activos_sin_responsable: 'Activos sin responsable',
    riesgos_totales: 'Riesgos', riesgos_criticos: 'Riesgos críticos', riesgos_altos: 'Riesgos altos', riesgos_sin_tratamiento: 'Riesgos sin tratamiento',
    incidentes_sin_resolver: 'Incidentes sin resolver', no_conformidades_abiertas: 'No conformidades abiertas',
    acciones_correctivas_vencidas: 'Acciones correctivas vencidas', controles_implementados: 'Controles implementados',
    controles_aplicables: 'Controles aplicables', cumplimiento_anexo_a_pct: 'Cumplimiento Anexo A (%)',
};

const cargando = ref(false);
const error = ref('');
const r = ref(null);

const indicadores = computed(() => Object.entries(r.value?.indicadores || {})
    .map(([clave, valor]) => ({ clave, valor, etiqueta: ETIQUETAS[clave] || clave })));

async function generar() {
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await iaApi.analizarDashboard();
        r.value = data.data;
    } catch (e) {
        error.value = e.message || 'SGSI Intelligence Assistant no está disponible en este momento.';
    }
    cargando.value = false;
}
</script>

<style scoped>
.ia-dash__accion { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; }
.ia-dash__invitacion { margin: 0; flex: 1 1 260px; font-size: var(--text-sm); color: var(--text-muted); }
.ia-dash__estado { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.ia-dash__fecha { font-size: var(--text-xs); color: var(--text-subtle); }
.ia-dash__resumen { margin: 0; font-size: var(--text-base); color: var(--text); line-height: 1.6; }
.ia-dash__indicadores { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin: 0; }
.ia-dash__indicadores div { padding: 10px 12px; border-radius: var(--radius-md); background: var(--bg-subtle); }
.ia-dash__indicadores dt { font-size: var(--text-xs); color: var(--text-muted); }
.ia-dash__indicadores dd { margin: 2px 0 0; font-family: var(--font-display); font-size: var(--text-xl); font-weight: 700; color: var(--text); }
.ia-dash__prioridades { display: flex; flex-direction: column; gap: 8px; }
.ia-dash__prioridades h4 { display: flex; align-items: center; gap: 6px; margin: 0; font-size: var(--text-sm); color: var(--text); }
.ia-dash__prioridades h4 :deep(svg) { color: var(--c-primary); }
.ia-dash__prioridad {
    display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: var(--radius-md);
    background: var(--bg-subtle); border-left: 3px solid var(--c-signal); color: inherit;
    transition: background var(--transition-fast), transform var(--transition-fast);
}
a.ia-dash__prioridad:hover { text-decoration: none; background: var(--surface-hover); transform: translateX(2px); }
.ia-dash__prioridad--alta { border-left-color: var(--c-risk); }
.ia-dash__prioridad--media { border-left-color: var(--c-amber); }
.ia-dash__prioridad strong { font-size: var(--text-sm); color: var(--text); }
.ia-dash__prioridad p { margin: 2px 0 0; font-size: var(--text-xs); color: var(--text-muted); line-height: 1.5; }
</style>
