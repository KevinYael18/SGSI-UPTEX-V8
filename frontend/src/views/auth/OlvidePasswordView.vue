<template>
  <AuthMarco icono="candado">
    <h1>Recuperar contraseña</h1>

    <Transition name="paso" mode="out-in">
      <div v-if="!enviado" key="formulario" class="paso">
        <p class="descripcion">Escribe tu correo institucional y te enviaremos un enlace para restablecer tu contraseña.</p>

        <form novalidate @submit.prevent="enviar">
          <AppInput
            v-model="email"
            type="email"
            label="Correo institucional"
            placeholder="usuario@uptex.edu.mx"
            icono="correo"
            autocomplete="email"
            requerido
            autofocus
            :error="errorCampo"
          />
          <Transition name="aviso">
            <p v-if="errorGeneral" class="error" role="alert"><AppIcon name="alerta" :size="18" />{{ errorGeneral }}</p>
          </Transition>
          <AppButton type="submit" :cargando="cargando" style="width:100%">
            {{ cargando ? 'Enviando enlace…' : 'Enviar enlace' }}
          </AppButton>
        </form>
      </div>

      <div v-else key="enviado" class="paso">
        <div class="confirmacion" role="status">
          <span class="confirmacion__icono"><AppIcon name="correo" :size="20" /></span>
          <div>
            <strong>Revisa tu bandeja de entrada</strong>
            <p>{{ mensajeServidor }}</p>
            <p class="confirmacion__nota">Correo indicado: <strong>{{ email }}</strong>. Si no lo ves en unos minutos, revisa también la carpeta de correo no deseado.</p>
          </div>
        </div>
        <AppButton variante="secundaria" :disabled="espera > 0" :cargando="cargando" style="width:100%" @click="enviar">
          {{ espera > 0 ? `Reenviar enlace en ${espera} s` : 'Reenviar enlace' }}
        </AppButton>
        <button type="button" class="cambiar" @click="cambiarCorreo">Usar otro correo</button>
      </div>
    </Transition>

    <RouterLink :to="{ name: 'login' }" class="volver"><AppIcon name="flecha-izq" :size="16" />Volver a iniciar sesión</RouterLink>
  </AuthMarco>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue';
import authApi from '../../api/auth';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AuthMarco from '../../components/layout/AuthMarco.vue';

// Debe coincidir con AUTH_PASSWORD_RESET_THROTTLE del backend (60 s por defecto).
const SEGUNDOS_ENTRE_ENVIOS = 60;

const email = ref('');
const cargando = ref(false);
const enviado = ref(false);
const mensajeServidor = ref('');
const errorCampo = ref('');
const errorGeneral = ref('');
const espera = ref(0);
let temporizador = null;

function iniciarEspera() {
    espera.value = SEGUNDOS_ENTRE_ENVIOS;
    clearInterval(temporizador);
    temporizador = setInterval(() => {
        espera.value -= 1;
        if (espera.value <= 0) clearInterval(temporizador);
    }, 1000);
}
onBeforeUnmount(() => clearInterval(temporizador));

async function enviar() {
    errorCampo.value = '';
    errorGeneral.value = '';
    const correo = email.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        errorCampo.value = 'Escribe un correo electrónico válido.';
        return;
    }
    cargando.value = true;
    try {
        // La confirmación SOLO se muestra cuando el backend respondió 200, es
        // decir, cuando el servidor SMTP ya aceptó el mensaje.
        const { data } = await authApi.olvidePassword(correo);
        mensajeServidor.value = data?.mensaje || 'Si el correo pertenece a una cuenta, recibirás un enlace en unos minutos.';
        enviado.value = true;
        iniciarEspera();
    } catch (e) {
        const detalle = e.details?.email?.[0];
        if (detalle) errorCampo.value = detalle;
        else errorGeneral.value = e.message || 'No se pudo procesar la solicitud.';
        if (enviado.value) enviado.value = false;
    } finally {
        cargando.value = false;
    }
}

function cambiarCorreo() {
    enviado.value = false;
    clearInterval(temporizador);
    espera.value = 0;
}
</script>

<style scoped>
.paso { display: flex; flex-direction: column; gap: var(--space-4); }
.descripcion { color: var(--text-muted); margin: 0; font-size: var(--text-sm); }
.error {
    display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 12px 14px; border-radius: var(--radius-md);
    background: var(--c-risk-soft); color: var(--c-risk); font-size: var(--text-sm); font-weight: 500; line-height: 1.5;
}
.confirmacion {
    display: flex; gap: 12px; align-items: flex-start; padding: 14px; border-radius: var(--radius-md);
    background: var(--c-success-soft); border: 1px solid color-mix(in srgb, var(--c-success) 25%, transparent);
    color: var(--text); font-size: var(--text-sm); line-height: 1.6;
}
.confirmacion p { margin: 4px 0 0; overflow-wrap: anywhere; }
.confirmacion__nota { color: var(--text-muted); }
.confirmacion__icono {
    display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    background: var(--surface); color: var(--c-success);
    animation: sello 520ms var(--ease) both;
}
.cambiar {
    align-self: center; background: none; border: none; padding: 4px 8px; border-radius: var(--radius-sm);
    color: var(--c-signal); font-weight: 600; font-size: var(--text-sm); cursor: pointer;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.cambiar:hover { background: var(--c-primary-soft); color: var(--c-primary-strong); }
.volver { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: var(--text-sm); font-weight: 600; text-align: center; }

.paso-enter-active, .paso-leave-active { transition: opacity 220ms var(--ease), transform 220ms var(--ease); }
.paso-enter-from { opacity: 0; transform: translateY(8px); }
.paso-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
