<template>
  <RouterView />
</template>

<script setup>
import { onMounted } from 'vue';
import { useUiStore } from './stores/ui';

// El tema (claro/oscuro) se aplica aquí, ANTES de cualquier vista —
// incluida LoginView, que no pasa por AppShell.vue. Antes solo se
// aplicaba dentro de AppShell (después de iniciar sesión), así que el
// login siempre se veía claro sin importar la preferencia guardada.
const ui = useUiStore();
onMounted(() => ui.aplicarTema());

// El layout autenticado vive en components/layout/AppShell.vue (registrado
// como componente padre de las rutas protegidas en router/index.js).
// LoginView se renderiza sola, sin sidebar/topbar.
</script>