<template>
  <div class="ia-marca" :class="`ia-marca--${tamano}`">
    <span class="ia-marca__icono" aria-hidden="true"><AppIcon name="asistente" :size="tamano === 'lg' ? 26 : 18" grosor="2" /></span>
    <span class="ia-marca__textos">
      <span class="ia-marca__nombre">SGSI Intelligence Assistant</span>
      <span v-if="subtitulo" class="ia-marca__subtitulo">{{ subtitulo }}</span>
    </span>
  </div>
</template>

<script setup>
import AppIcon from '../ui/AppIcon.vue';

defineProps({
    subtitulo: { type: String, default: '' },
    tamano: { type: String, default: 'md' }, // md | lg
});
</script>

<style scoped>
.ia-marca { display: flex; align-items: center; gap: 10px; min-width: 0; }
.ia-marca__icono {
    position: relative; display: grid; place-items: center; flex-shrink: 0;
    width: 34px; height: 34px; border-radius: 10px; color: #fff;
    background: var(--brand-gradient); box-shadow: var(--shadow-brand);
}
.ia-marca__icono::after {
    content: ''; position: absolute; inset: 0; border-radius: inherit;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.28), transparent 55%);
}
.ia-marca--lg .ia-marca__icono { width: 48px; height: 48px; border-radius: 14px; }
.ia-marca__textos { display: flex; flex-direction: column; min-width: 0; }
.ia-marca__nombre {
    font-family: var(--font-display); font-weight: 700; font-size: var(--text-sm); color: var(--text);
    letter-spacing: -0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ia-marca--lg .ia-marca__nombre { font-size: var(--text-lg); }
.ia-marca__subtitulo { font-size: var(--text-xs); color: var(--text-muted); }
</style>
