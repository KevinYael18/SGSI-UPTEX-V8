<template>
  <section class="ia-panel" :aria-label="`SGSI Intelligence Assistant — ${titulo}`">
    <header class="ia-panel__cabecera">
      <IaMarca :subtitulo="titulo" />
      <span class="ia-panel__etiqueta">IA</span>
    </header>
    <p v-if="aviso" class="ia-panel__aviso"><AppIcon name="candado" :size="13" />{{ aviso }}</p>

    <slot name="entrada" />

    <IaProcesando v-if="cargando" :texto="textoCargando" />
    <div v-else-if="error" class="ia-panel__error" role="alert">
      <AppIcon name="alerta" :size="16" />
      <span>{{ error }}</span>
    </div>

    <Transition name="ia-aparecer">
      <div v-if="!cargando && hayResultado" class="ia-panel__resultado">
        <slot />
        <IaDecision
          v-if="analisisUuid"
          :analisis-uuid="analisisUuid"
          :texto-aceptar="textoAceptar"
          :nota-aceptar="notaAceptar"
          :al-aceptar="alAceptar"
          @decidido="(d) => emit('decidido', d)"
        />
      </div>
    </Transition>
  </section>
</template>

<script setup>
import AppIcon from '../ui/AppIcon.vue';
import IaMarca from './IaMarca.vue';
import IaProcesando from './IaProcesando.vue';
import IaDecision from './IaDecision.vue';

defineProps({
    titulo: { type: String, required: true },
    aviso: { type: String, default: '' },
    cargando: { type: Boolean, default: false },
    textoCargando: { type: String, default: 'SGSI Intelligence Assistant está analizando…' },
    error: { type: String, default: '' },
    hayResultado: { type: Boolean, default: false },
    analisisUuid: { type: String, default: '' },
    textoAceptar: { type: String, default: 'Aceptar propuesta' },
    notaAceptar: { type: String, default: '' },
    alAceptar: { type: Function, default: null },
});
const emit = defineEmits(['decidido']);
</script>

<style scoped>
.ia-panel {
    position: relative; display: flex; flex-direction: column; gap: var(--space-3);
    padding: var(--space-4); border: 1px solid var(--border); border-radius: var(--radius-lg);
    background: var(--surface); box-shadow: var(--shadow-sm); overflow: hidden;
}
.ia-panel::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--c-primary), var(--c-lime));
}
.ia-panel__cabecera { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); }
.ia-panel__etiqueta {
    flex-shrink: 0; padding: 2px 8px; border-radius: 999px; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.08em;
    color: var(--c-primary); background: var(--c-primary-soft);
}
.ia-panel__aviso { display: flex; gap: 6px; align-items: flex-start; margin: 0; font-size: var(--text-xs); color: var(--text-muted); line-height: 1.5; }
.ia-panel__aviso :deep(svg) { flex-shrink: 0; margin-top: 2px; }
.ia-panel__error {
    display: flex; gap: 8px; align-items: flex-start; padding: 10px 12px; border-radius: var(--radius-sm); font-size: var(--text-sm);
    background: var(--c-risk-soft); color: var(--c-risk-strong); border: 1px solid var(--c-risk);
}
.ia-panel__resultado { display: flex; flex-direction: column; gap: var(--space-3); }
.ia-aparecer-enter-active { transition: opacity 260ms var(--ease), transform 260ms var(--ease); }
.ia-aparecer-enter-from { opacity: 0; transform: translateY(6px); }
@media (prefers-reduced-motion: reduce) { .ia-aparecer-enter-active { transition: none; } }
</style>
