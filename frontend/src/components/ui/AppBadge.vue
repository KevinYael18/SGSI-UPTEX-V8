<template>
  <span class="app-badge" :class="`app-badge--${tono}`"><span class="app-badge__punto" aria-hidden="true" /><slot /></span>
</template>

<script setup>
// tono: neutral | signal | exito | advertencia | riesgo
defineProps({ tono: { type: String, default: 'neutral' } });
</script>

<style scoped>
.app-badge {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 8px; border-radius: 999px;
    font-size: 0.7rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; line-height: 1.3;
    white-space: nowrap; max-width: 100%; border: 1px solid transparent;
}
.app-badge__punto { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.app-badge--neutral { background: var(--bg-subtle); color: var(--text-muted); border-color: var(--border); }
.app-badge--signal { background: var(--c-signal-soft); color: var(--c-signal); }
.app-badge--exito { background: var(--c-success-soft); color: var(--c-success); }
.app-badge--advertencia { background: var(--c-amber-soft); color: var(--c-amber); }
.app-badge--riesgo { background: var(--c-risk-soft); color: var(--c-risk); }
</style>
