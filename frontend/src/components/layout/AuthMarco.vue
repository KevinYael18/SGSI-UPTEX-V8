<template>
  <div class="auth-marco">
    <div class="auth-marco__fondo" aria-hidden="true" />
    <div class="auth-marco__tarjeta">
      <span class="auth-marco__franja" aria-hidden="true" />
      <div class="auth-marco__logo-placa">
        <img src="../../assets/logo-uptex-completo.png" alt="Universidad Politécnica de Texcoco" width="720" height="462" />
      </div>
      <span v-if="icono" class="auth-marco__icono"><AppIcon :name="icono" :size="22" /></span>
      <slot />
    </div>
    <p class="auth-marco__copy">SGSI UPTex · Universidad Politécnica de Texcoco</p>
  </div>
</template>

<script setup>
import AppIcon from '../ui/AppIcon.vue';

// Marco visual compartido por las pantallas públicas de recuperación de contraseña.
defineProps({ icono: { type: String, default: '' } });
</script>

<style scoped>
.auth-marco {
    position: relative; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column;
    align-items: center; justify-content: center; padding: var(--space-6) var(--space-4); isolation: isolate;
}
.auth-marco__fondo {
    position: absolute; inset: 0 0 auto 0; height: 46vh; z-index: -1; background: var(--brand-gradient);
}
.auth-marco__fondo::after {
    content: ''; position: absolute; inset: 0;
    background:
        linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px) 0 0 / 32px 32px,
        linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px) 0 0 / 32px 32px;
}
.auth-marco__tarjeta {
    position: relative; overflow: hidden; width: 100%; max-width: 440px;
    display: flex; flex-direction: column; gap: var(--space-4);
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
    padding: var(--space-6); box-shadow: var(--shadow-lg);
    animation: aparecer 500ms var(--ease) both;
}
.auth-marco__franja { position: absolute; inset: 0 0 auto 0; height: 4px; background: var(--brand-stripe); }
.auth-marco__logo-placa { align-self: center; background: #fff; border-radius: var(--radius-md); padding: 8px 12px; }
.auth-marco__logo-placa img { display: block; width: 130px; height: auto; }
.auth-marco__icono {
    display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px;
    background: var(--c-primary-soft); color: var(--c-primary);
}
.auth-marco__tarjeta :deep(h1) { font-size: clamp(1.35rem, 1.1rem + 1vw, 1.65rem); margin: 0; }
.auth-marco__tarjeta :deep(form) { display: flex; flex-direction: column; gap: var(--space-4); }
.auth-marco__copy { margin: var(--space-5) 0 0; font-size: var(--text-xs); color: var(--text-subtle); text-align: center; }

@media (max-width: 640px) {
    .auth-marco { justify-content: flex-start; padding-top: calc(var(--space-8) + env(safe-area-inset-top, 0px)); }
    .auth-marco__tarjeta { padding: var(--space-5); }
}
</style>
