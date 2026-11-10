<template>
  <AuthMarco icono="candado">
    <h1>Restablecer contraseña</h1>

    <form v-if="!listo" @submit.prevent="restablecer">
      <AppInput v-model="password" type="password" label="Contraseña nueva (mínimo 12 caracteres)" icono="candado" requerido autofocus />
      <AppInput v-model="passwordConfirmacion" type="password" label="Confirmar contraseña" icono="candado" requerido />
      <p v-if="error" class="error" role="alert"><AppIcon name="alerta" :size="18" />{{ error }}</p>
      <AppButton type="submit" :cargando="cargando" style="width:100%">Restablecer contraseña</AppButton>
    </form>

    <template v-else>
      <p class="confirmacion" role="status"><AppIcon name="exito" :size="20" />Tu contraseña se actualizó correctamente.</p>
      <RouterLink :to="{ name: 'login' }" class="volver">Iniciar sesión <AppIcon name="flecha" :size="16" /></RouterLink>
    </template>
  </AuthMarco>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import authApi from '../../api/auth';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AuthMarco from '../../components/layout/AuthMarco.vue';

const route = useRoute();
const password = ref('');
const passwordConfirmacion = ref('');
const cargando = ref(false);
const listo = ref(false);
const error = ref('');

async function restablecer() {
    error.value = '';
    cargando.value = true;
    try {
        await authApi.restablecerPassword({
            token: route.query.token,
            email: route.query.email,
            password: password.value,
            password_confirmation: passwordConfirmacion.value,
        });
        listo.value = true;
    } catch (e) {
        error.value = e.message || 'No se pudo restablecer la contraseña.';
    } finally {
        cargando.value = false;
    }
}
</script>

<style scoped>
.error {
    display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 12px 14px; border-radius: var(--radius-md);
    background: var(--c-risk-soft); color: var(--c-risk); font-size: var(--text-sm); font-weight: 500;
}
.confirmacion {
    display: flex; align-items: center; gap: 10px; margin: 0; padding: 14px; border-radius: var(--radius-md);
    background: var(--c-success-soft); color: var(--c-success); font-weight: 600; font-size: var(--text-sm);
}
.volver { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: var(--text-sm); font-weight: 600; }
</style>
