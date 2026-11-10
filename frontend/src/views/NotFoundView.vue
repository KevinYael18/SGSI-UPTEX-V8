<template>
  <div class="nf">
    <div class="nf__ilustracion" aria-hidden="true">
      <span v-for="n in 9" :key="n" :class="{ 'nf__celda--rojo': n === 5, 'nf__celda--verde': n === 1 || n === 9, 'nf__celda--lima': n === 3 }" />
    </div>
    <span class="nf__codigo">404</span>
    <h1>Página no encontrada</h1>
    <p>Esta página no existe o no tienes acceso a ella.</p>
    <RouterLink :to="{ name: 'dashboard' }" class="nf__boton"><AppIcon name="flecha-izq" :size="18" />Volver al dashboard</RouterLink>
  </div>
</template>

<script setup>
import AppIcon from '../components/ui/AppIcon.vue';
</script>

<style scoped>
.nf {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-3);
    min-height: 100vh; min-height: 100dvh; padding: var(--space-6) var(--space-4); text-align: center;
}
.nf__ilustracion { display: grid; grid-template-columns: repeat(3, 28px); gap: 6px; margin-bottom: var(--space-3); }
.nf__ilustracion span { width: 28px; height: 28px; border-radius: 8px; background: var(--bg-subtle); border: 1px solid var(--border); }
.nf__ilustracion .nf__celda--rojo { background: var(--c-risk); border-color: var(--c-risk); }
.nf__ilustracion .nf__celda--verde { background: var(--c-primary); border-color: var(--c-primary); }
.nf__ilustracion .nf__celda--lima { background: var(--c-lime); border-color: var(--c-lime); }
.nf__codigo { font-family: var(--font-display); font-weight: 700; font-size: 0.85rem; letter-spacing: 0.2em; color: var(--c-primary); }
.nf h1 { margin: 0; }
.nf p { margin: 0; color: var(--text-muted); max-width: 40ch; }
.nf__boton {
    display: inline-flex; align-items: center; gap: 8px; margin-top: var(--space-3); min-height: 46px; padding: 10px 20px;
    border-radius: var(--radius-md); background: var(--c-primary); color: var(--on-primary); font-weight: 600; box-shadow: var(--shadow-brand);
}
.nf__boton:hover { background: var(--c-primary-strong); color: var(--on-primary); text-decoration: none; }
</style>
