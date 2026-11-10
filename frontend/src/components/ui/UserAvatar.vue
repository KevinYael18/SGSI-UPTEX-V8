<template>
  <span
    class="user-avatar"
    :class="[`user-avatar--${variante}`, { 'user-avatar--foto': mostrarFoto }]"
    :style="{ '--avatar-size': `${size}px` }"
    :title="titulo ? nombre : undefined"
  >
    <img
      v-if="mostrarFoto"
      :key="foto"
      :src="foto"
      :alt="alt || `Fotografía de ${nombre}`"
      class="user-avatar__img"
      loading="lazy"
      decoding="async"
      @error="fallo = true"
    />
    <span v-else class="user-avatar__iniciales" aria-hidden="true">{{ iniciales }}</span>
  </span>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

/**
 * Avatar de usuario reutilizable: muestra la fotografía de perfil si existe
 * y carga bien; si no, las iniciales sobre el degradado institucional.
 */
const props = defineProps({
    nombre: { type: String, default: '' },
    foto: { type: String, default: null },
    size: { type: Number, default: 36 },
    alt: { type: String, default: '' },
    titulo: { type: Boolean, default: false },
    variante: { type: String, default: 'marca' }, // marca | suave
});

const fallo = ref(false);
watch(() => props.foto, () => { fallo.value = false; });

const mostrarFoto = computed(() => !!props.foto && !fallo.value);

const iniciales = computed(() => {
    const partes = (props.nombre || '').trim().split(/\s+/).filter(Boolean);
    return partes.slice(0, 2).map((p) => p[0]).join('').toUpperCase() || '?';
});
</script>

<style scoped>
.user-avatar {
    --avatar-size: 36px;
    position: relative; display: inline-grid; place-items: center; flex-shrink: 0; overflow: hidden;
    width: var(--avatar-size); height: var(--avatar-size); border-radius: 50%;
    font-weight: 700; font-size: calc(var(--avatar-size) * 0.36); letter-spacing: 0.02em; line-height: 1;
    user-select: none;
}
.user-avatar--marca { background: var(--brand-gradient); color: #fff; }
.user-avatar--suave { background: var(--c-primary-soft); color: var(--c-primary); }
.user-avatar--foto { background: var(--bg-subtle); }
.user-avatar__img { width: 100%; height: 100%; object-fit: cover; display: block; }
.user-avatar__iniciales { font-family: var(--font-display); }
</style>
