<template>
  <AuthMarco icono="candado">
    <h1>Recuperar contraseña</h1>
    <p class="descripcion">Escribe tu correo institucional y te enviaremos un enlace para restablecer tu contraseña.</p>

    <form @submit.prevent="enviar" v-if="!enviado">
      <AppInput v-model="email" type="email" label="Correo institucional" placeholder="usuario@uptex.edu.mx" icono="correo" requerido autofocus />
      <AppButton type="submit" :cargando="cargando" style="width:100%">Enviar enlace</AppButton>
    </form>

    <div v-else class="confirmacion" role="status">
      <AppIcon name="exito" :size="20" class="confirmacion__icono" />
      <p>
        Si <strong>{{ email }}</strong> está registrado, se envió un enlace de recuperación.
        Revisa tu correo (o, en este entorno de pruebas, pídele al Administrador que revise
        <code>storage/logs/laravel.log</code> del servidor, ya que el correo se registra ahí en vez de enviarse de verdad).
      </p>
    </div>

    <RouterLink :to="{ name: 'login' }" class="volver"><AppIcon name="flecha-izq" :size="16" />Volver a iniciar sesión</RouterLink>
  </AuthMarco>
</template>

<script setup>
import { ref } from 'vue';
import authApi from '../../api/auth';
import { useToastStore } from '../../stores/toast';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AuthMarco from '../../components/layout/AuthMarco.vue';

const email = ref('');
const cargando = ref(false);
const enviado = ref(false);
const toast = useToastStore();

async function enviar() {
    cargando.value = true;
    try {
        await authApi.olvidePassword(email.value);
        enviado.value = true;
    } catch (e) {
        toast.error(e.message || 'No se pudo procesar la solicitud.');
    } finally {
        cargando.value = false;
    }
}
</script>

<style scoped>
.descripcion { color: var(--text-muted); margin: 0; font-size: var(--text-sm); }
.confirmacion {
    display: flex; gap: 12px; align-items: flex-start; padding: 14px; border-radius: var(--radius-md);
    background: var(--c-success-soft); border: 1px solid color-mix(in srgb, var(--c-success) 25%, transparent);
    color: var(--text); font-size: var(--text-sm); line-height: 1.6;
}
.confirmacion p { margin: 0; overflow-wrap: anywhere; }
.confirmacion__icono { color: var(--c-success); margin-top: 2px; }
.confirmacion code { background: var(--surface); padding: 2px 6px; border-radius: 4px; font-family: var(--font-mono); }
.volver { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: var(--text-sm); font-weight: 600; text-align: center; }
</style>
