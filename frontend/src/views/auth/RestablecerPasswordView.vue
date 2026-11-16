<template>
  <AuthMarco :icono="estado === 'listo' ? 'exito' : 'candado'">
    <Transition name="paso" mode="out-in">
      <!-- 1. Verificando el enlace -->
      <div v-if="estado === 'verificando'" key="verificando" class="paso" aria-busy="true">
        <h1>Restablecer contraseña</h1>
        <p class="descripcion"><span class="mini-spinner" aria-hidden="true" />Verificando tu enlace de recuperación…</p>
        <div class="skeleton esqueleto-campo" />
        <div class="skeleton esqueleto-campo" />
      </div>

      <!-- 2. Enlace inválido, vencido o ya utilizado -->
      <div v-else-if="estado === 'invalido'" key="invalido" class="paso">
        <h1>Enlace no válido</h1>
        <p class="error" role="alert"><AppIcon name="alerta" :size="18" />{{ mensajeInvalido }}</p>
        <p class="descripcion">Por seguridad, cada enlace funciona una sola vez y durante un tiempo limitado.</p>
        <AppButton style="width:100%" @click="router.push({ name: 'olvide-password' })">Solicitar un enlace nuevo</AppButton>
      </div>

      <!-- 3. Formulario de nueva contraseña -->
      <div v-else-if="estado === 'formulario'" key="formulario" class="paso">
        <h1>Crea tu nueva contraseña</h1>
        <p class="descripcion">Cuenta: <strong class="cuenta">{{ email }}</strong></p>

        <form novalidate @submit.prevent="restablecer">
          <AppInput
            v-model="password"
            type="password"
            label="Nueva contraseña"
            icono="candado"
            autocomplete="new-password"
            requerido
            autofocus
            :error="errores.password"
          />

          <div class="requisitos" aria-live="polite">
            <div class="fuerza" :class="`fuerza--${nivel.clase}`" role="meter" aria-label="Seguridad de la contraseña"
                 :aria-valuenow="cumplidos" aria-valuemin="0" :aria-valuemax="requisitos.length" :aria-valuetext="nivel.texto">
              <span class="fuerza__barra"><span :style="{ transform: `scaleX(${cumplidos / requisitos.length})` }" /></span>
              <span class="fuerza__texto">{{ nivel.texto }}</span>
            </div>
            <ul class="requisitos__lista">
              <li v-for="r in requisitos" :key="r.clave" :class="{ 'requisitos__item--ok': r.ok }">
                <span class="requisitos__marca" aria-hidden="true"><AppIcon :name="r.ok ? 'check' : 'circulo'" :size="13" :grosor="r.ok ? 3 : 2" /></span>
                <span>{{ r.texto }}<span class="visually-hidden">{{ r.ok ? ' (cumplido)' : ' (pendiente)' }}</span></span>
              </li>
            </ul>
          </div>

          <AppInput
            v-model="passwordConfirmacion"
            type="password"
            label="Confirmar nueva contraseña"
            icono="candado"
            autocomplete="new-password"
            requerido
            :error="errorConfirmacion"
          />
          <Transition name="aviso">
            <p v-if="coinciden" class="coincide" role="status"><AppIcon name="check" :size="15" :grosor="3" />Las contraseñas coinciden.</p>
          </Transition>

          <Transition name="aviso">
            <p v-if="errorGeneral" class="error" role="alert"><AppIcon name="alerta" :size="18" />{{ errorGeneral }}</p>
          </Transition>

          <AppButton type="submit" :cargando="cargando" :disabled="!puedeEnviar" style="width:100%">
            {{ cargando ? 'Guardando…' : 'Guardar nueva contraseña' }}
          </AppButton>
        </form>
      </div>

      <!-- 4. Confirmación (solo después de la respuesta 200 del backend) -->
      <div v-else key="listo" class="paso">
        <h1>¡Contraseña actualizada!</h1>
        <p class="confirmacion" role="status">
          <span class="confirmacion__icono" aria-hidden="true"><AppIcon name="check" :size="18" :grosor="3" /></span>
          {{ mensajeExito }}
        </p>
        <p class="descripcion">El enlace que utilizaste quedó invalidado y las sesiones abiertas con la contraseña anterior se cerraron.</p>
        <AppButton style="width:100%" icono="flecha" autofocus @click="router.push({ name: 'login' })">Iniciar sesión</AppButton>
      </div>
    </Transition>

    <RouterLink v-if="estado !== 'listo'" :to="{ name: 'login' }" class="volver"><AppIcon name="flecha-izq" :size="16" />Volver a iniciar sesión</RouterLink>
  </AuthMarco>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import authApi from '../../api/auth';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AuthMarco from '../../components/layout/AuthMarco.vue';

const route = useRoute();
const router = useRouter();
const token = typeof route.query.token === 'string' ? route.query.token : '';
const email = typeof route.query.email === 'string' ? route.query.email : '';

const estado = ref('verificando'); // verificando | invalido | formulario | listo
const mensajeInvalido = ref('');
const password = ref('');
const passwordConfirmacion = ref('');
const cargando = ref(false);
const errorGeneral = ref('');
const errores = reactive({ password: '' });
const mensajeExito = ref('');

// Mismos requisitos que valida el backend (RestablecerPasswordRequest).
const requisitos = computed(() => [
    { clave: 'longitud', texto: 'Al menos 12 caracteres', ok: password.value.length >= 12 },
    { clave: 'mayuscula', texto: 'Una letra mayúscula', ok: /\p{Lu}/u.test(password.value) },
    { clave: 'minuscula', texto: 'Una letra minúscula', ok: /\p{Ll}/u.test(password.value) },
    { clave: 'numero', texto: 'Al menos un número', ok: /\d/.test(password.value) },
]);
const cumplidos = computed(() => requisitos.value.filter((r) => r.ok).length);
const nivel = computed(() => {
    if (!password.value) return { clase: 'vacia', texto: 'Escribe una contraseña' };
    if (cumplidos.value <= 2) return { clase: 'debil', texto: 'Débil' };
    if (cumplidos.value === 3) return { clase: 'media', texto: 'Casi lista' };
    return { clase: 'fuerte', texto: password.value.length >= 16 ? 'Muy segura' : 'Segura' };
});
const coinciden = computed(() => passwordConfirmacion.value.length > 0 && passwordConfirmacion.value === password.value);
// Mientras la confirmación sea el inicio de la contraseña no se marca error
// (el usuario sigue escribiendo); en cuanto diverge, se avisa de inmediato.
const errorConfirmacion = computed(() => (
    passwordConfirmacion.value.length > 0 && !password.value.startsWith(passwordConfirmacion.value)
        ? 'La confirmación no coincide con la nueva contraseña.'
        : ''
));
const puedeEnviar = computed(() => cumplidos.value === requisitos.value.length && coinciden.value);

function marcarInvalido(mensaje) {
    mensajeInvalido.value = mensaje || 'El enlace de recuperación no es válido, ya se utilizó o expiró. Solicita uno nuevo.';
    estado.value = 'invalido';
}

onMounted(async () => {
    if (!token || !email) {
        marcarInvalido('El enlace está incompleto. Ábrelo directamente desde el botón del correo que recibiste.');
        return;
    }
    try {
        await authApi.validarTokenRecuperacion({ token, email });
        estado.value = 'formulario';
    } catch (e) {
        if (e.status === 422) marcarInvalido(e.message);
        else {
            // Error de red o del servidor: se permite intentar de todos modos;
            // el backend vuelve a validar el token al guardar.
            estado.value = 'formulario';
            errorGeneral.value = e.message;
        }
    }
});

async function restablecer() {
    errorGeneral.value = '';
    errores.password = '';
    if (!puedeEnviar.value) return;
    cargando.value = true;
    try {
        const { data } = await authApi.restablecerPassword({
            token,
            email,
            password: password.value,
            password_confirmation: passwordConfirmacion.value,
        });
        mensajeExito.value = data?.mensaje || 'Tu contraseña se actualizó correctamente.';
        password.value = '';
        passwordConfirmacion.value = '';
        estado.value = 'listo';
    } catch (e) {
        if (e.code === 'TOKEN_INVALIDO') marcarInvalido(e.message);
        else if (e.details?.password?.length) errores.password = e.details.password[0];
        else errorGeneral.value = e.message || 'No se pudo restablecer la contraseña.';
    } finally {
        cargando.value = false;
    }
}
</script>

<style scoped>
.paso { display: flex; flex-direction: column; gap: var(--space-4); }
.paso :deep(h1) { margin: 0; }
.descripcion { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; color: var(--text-muted); margin: 0; font-size: var(--text-sm); }
.cuenta { color: var(--text); overflow-wrap: anywhere; }
.esqueleto-campo { height: 64px; }
.mini-spinner {
    width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--c-primary); border-top-color: transparent;
    animation: girar-mini 0.7s linear infinite;
}
@keyframes girar-mini { to { transform: rotate(360deg); } }

.error {
    display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 12px 14px; border-radius: var(--radius-md);
    background: var(--c-risk-soft); color: var(--c-risk); font-size: var(--text-sm); font-weight: 500; line-height: 1.5;
}
.coincide { display: inline-flex; align-items: center; gap: 6px; margin: -8px 0 0; color: var(--c-success); font-size: var(--text-xs); font-weight: 600; }
.confirmacion {
    display: flex; align-items: center; gap: 12px; margin: 0; padding: 14px; border-radius: var(--radius-md);
    background: var(--c-success-soft); color: var(--c-success); font-weight: 600; font-size: var(--text-sm); line-height: 1.5;
}
.confirmacion__icono {
    display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    background: var(--c-success); color: var(--on-primary); animation: sello 560ms var(--ease) both;
}
.volver { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: var(--text-sm); font-weight: 600; }

/* ---- Indicador de requisitos ---- */
.requisitos { display: flex; flex-direction: column; gap: 10px; margin-top: -6px; }
.fuerza { display: flex; align-items: center; gap: 10px; --tono: var(--border-strong); }
.fuerza__barra { flex: 1; height: 6px; border-radius: 999px; background: var(--bg-subtle); overflow: hidden; }
.fuerza__barra span {
    display: block; height: 100%; border-radius: inherit; background: var(--tono);
    transform-origin: left center; transition: transform 320ms var(--ease), background-color 220ms var(--ease);
}
.fuerza__texto { min-width: 92px; text-align: right; font-size: var(--text-xs); font-weight: 600; color: var(--tono); transition: color 220ms var(--ease); }
.fuerza--vacia .fuerza__texto { color: var(--text-subtle); }
.fuerza--debil { --tono: var(--c-risk); }
.fuerza--media { --tono: var(--c-amber); }
.fuerza--fuerte { --tono: var(--c-success); }

.requisitos__lista { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 12px; }
.requisitos__lista li {
    display: flex; align-items: center; gap: 7px; font-size: var(--text-xs); color: var(--text-muted);
    transition: color 200ms var(--ease);
}
.requisitos__marca {
    display: grid; place-items: center; width: 18px; height: 18px; border-radius: 50%; flex-shrink: 0;
    background: var(--bg-subtle); color: var(--text-subtle);
    transition: background-color 200ms var(--ease), color 200ms var(--ease), transform 260ms var(--ease);
}
.requisitos__item--ok { color: var(--text) !important; }
.requisitos__item--ok .requisitos__marca { background: var(--c-success); color: var(--on-primary); transform: scale(1.08); }
@media (max-width: 380px) { .requisitos__lista { grid-template-columns: 1fr; } }

.paso-enter-active, .paso-leave-active { transition: opacity 220ms var(--ease), transform 220ms var(--ease); }
.paso-enter-from { opacity: 0; transform: translateY(8px); }
.paso-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
