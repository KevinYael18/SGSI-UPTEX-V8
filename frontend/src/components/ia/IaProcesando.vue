<template>
  <div class="ia-procesando" role="status" aria-live="polite">
    <span class="ia-procesando__puntos" aria-hidden="true"><i /><i /><i /></span>
    <span>{{ texto }}</span>
    <span class="ia-procesando__barra" aria-hidden="true" />
  </div>
</template>

<script setup>
defineProps({ texto: { type: String, default: 'SGSI Intelligence Assistant está analizando…' } });
</script>

<style scoped>
.ia-procesando {
    position: relative; overflow: hidden; display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; border-radius: var(--radius-md); background: var(--c-primary-soft);
    color: var(--c-primary); font-size: var(--text-sm); font-weight: 600;
}
.ia-procesando__puntos { display: inline-flex; gap: 4px; }
.ia-procesando__puntos i {
    width: 7px; height: 7px; border-radius: 50%; background: currentColor;
    animation: ia-punto 1.1s var(--ease) infinite;
}
.ia-procesando__puntos i:nth-child(2) { animation-delay: 0.15s; }
.ia-procesando__puntos i:nth-child(3) { animation-delay: 0.3s; }
.ia-procesando__barra {
    position: absolute; left: 0; bottom: 0; height: 2px; width: 40%;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    animation: ia-barrido 1.6s var(--ease) infinite;
}
@keyframes ia-punto { 0%, 80%, 100% { opacity: 0.25; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }
@keyframes ia-barrido { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
@media (prefers-reduced-motion: reduce) {
    .ia-procesando__puntos i, .ia-procesando__barra { animation: none; }
}
</style>
