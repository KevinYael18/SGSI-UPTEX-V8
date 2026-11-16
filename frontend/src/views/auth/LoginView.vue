<template>
  <div class="login">
    <section class="login__panel">
      <div class="login__patron" aria-hidden="true">
        <span v-for="n in 25" :key="n" :class="`login__patron-celda--${celdaPatron(n)}`" />
      </div>

      <div class="login__marca">
        <div class="login__logo-placa">
          <img src="../../assets/logo-uptex-completo.png" alt="Universidad Politécnica de Texcoco" class="login__logo" width="720" height="462" />
        </div>
        <span class="login__franja" aria-hidden="true" />
        <h1>SGSI UPTex</h1>
        <p class="login__lema">Sistema de Gestión de Seguridad de la Información<br />Universidad Politécnica de Texcoco</p>

        <ul class="login__puntos">
          <li><span><AppIcon name="cumplimiento" :size="18" /></span>Alineado a ISO/IEC 27001:2022</li>
          <li><span><AppIcon name="riesgos" :size="18" /></span>Gestión de riesgos e incidentes</li>
          <li><span><AppIcon name="bitacora" :size="18" /></span>Trazabilidad y auditoría continua</li>
        </ul>
      </div>
    </section>

    <section class="login__lado-form">
      <button
        type="button"
        class="login__tema"
        @click="ui.alternarTema()"
        :aria-label="ui.tema === 'light' ? 'Modo oscuro' : 'Modo claro'"
        :title="ui.tema === 'light' ? 'Activar modo oscuro' : 'Activar modo claro'"
      >
        <AppIcon :name="ui.tema === 'light' ? 'luna' : 'sol'" :size="20" />
      </button>

      <form class="login__form" @submit.prevent="entrar">
        <div class="login__encabezado">
          <span class="login__insignia"><AppIcon name="candado" :size="14" grosor="2.2" />Acceso seguro</span>
          <h2>Iniciar sesión</h2>
          <p>Ingresa con tu cuenta institucional para continuar.</p>
        </div>

        <AppInput v-model="email" type="email" label="Correo institucional" placeholder="usuario@uptex.edu.mx" icono="correo" autocomplete="username" requerido autofocus />
        <AppInput v-model="password" type="password" label="Contraseña" placeholder="••••••••••••" icono="candado" autocomplete="current-password" requerido />

        <Transition name="alerta">
          <div v-if="error" class="login__error" role="alert">
            <AppIcon name="alerta" :size="18" />
            <span>{{ error }}</span>
          </div>
        </Transition>

        <AppButton type="submit" :cargando="auth.cargando" class="login__boton">
          Entrar
          <AppIcon v-if="!auth.cargando" name="flecha" :size="18" class="login__boton-flecha" />
        </AppButton>
        <RouterLink :to="{ name: 'olvide-password' }" class="login__olvide">¿Olvidaste tu contraseña?</RouterLink>

        <p class="login__pie">
          <AppIcon name="escudo" :size="14" />
          Conexión protegida. El acceso y la actividad quedan registrados en la bitácora del sistema.
        </p>
      </form>

      <p class="login__copy">© Universidad Politécnica de Texcoco</p>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';

const email = ref('');
const password = ref('');
const error = ref(sessionStorage.getItem('sgsi_aviso_login') || '');
sessionStorage.removeItem('sgsi_aviso_login');
const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const route = useRoute();

async function entrar() {
    error.value = '';
    try {
        await auth.iniciarSesion(email.value, password.value);
        // Solo rutas internas de la SPA ("/algo"); se descarta "//dominio" o URLs absolutas.
        const destino = typeof route.query.redirect === 'string' && /^\/(?!\/)/.test(route.query.redirect) ? route.query.redirect : null;
        router.push(destino || { name: 'dashboard' });
    } catch (e) {
        error.value = e.message || 'No se pudo iniciar sesión.';
    }
}

// Motivo decorativo: matriz de riesgo 5×5 con los colores institucionales.
function celdaPatron(n) {
    if (n === 5 || n === 9) return 'rojo';
    if (n === 7 || n === 13 || n === 17) return 'lima';
    if (n === 21 || n === 22 || n === 16) return 'verde';
    return 'base';
}
</script>

<style scoped>
.login {
    min-height: 100vh; min-height: 100dvh; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
    background: var(--bg);
}

/* ---- Panel institucional ---- */
.login__panel {
    position: relative; overflow: hidden; isolation: isolate;
    background: var(--brand-gradient); color: #fff;
    display: flex; align-items: center; justify-content: center; padding: var(--space-8) var(--space-6);
}
.login__panel::before {
    content: ''; position: absolute; inset: 0; z-index: -1;
    background:
        radial-gradient(circle at 15% 20%, rgba(122, 181, 29, 0.35), transparent 45%),
        radial-gradient(circle at 90% 90%, rgba(200, 16, 46, 0.25), transparent 40%),
        linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px) 0 0 / 32px 32px,
        linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px) 0 0 / 32px 32px;
}
.login__patron {
    position: absolute; right: -40px; bottom: -40px; z-index: -1; width: 260px; height: 260px; opacity: 0.22;
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; transform: rotate(-12deg);
}
.login__patron span { border-radius: 10px; background: rgba(255, 255, 255, 0.35); }
.login__patron-celda--rojo { background: #C8102E !important; }
.login__patron-celda--lima { background: #9CCB4C !important; }
.login__patron-celda--verde { background: #34B36A !important; }

.login__marca {
    display: flex; flex-direction: column; align-items: center; text-align: center; max-width: 440px;
    animation: aparecer 600ms var(--ease) both;
}
.login__logo-placa {
    background: #fff; border-radius: 28px; padding: 26px 30px;
    box-shadow: 0 24px 48px -16px rgba(0, 0, 0, 0.45), 0 0 0 6px rgba(255, 255, 255, 0.12);
}
.login__logo { display: block; width: clamp(180px, 22vw, 250px); height: auto; }
.login__franja { display: block; width: 96px; height: 5px; border-radius: 4px; background: var(--brand-stripe); margin: 30px 0 18px; }
.login__marca h1 { font-size: clamp(1.8rem, 1.2rem + 2vw, 2.6rem); font-weight: 700; color: #fff; margin: 0 0 10px; letter-spacing: -0.02em; }
.login__lema { margin: 0; color: rgba(255, 255, 255, 0.85); line-height: 1.6; font-size: var(--text-base); }
.login__puntos {
    list-style: none; padding: 0; margin: 32px 0 0; display: flex; flex-direction: column; gap: 12px; text-align: left;
    font-size: var(--text-sm); color: rgba(255, 255, 255, 0.92);
}
.login__puntos li { display: flex; align-items: center; gap: 12px; }
.login__puntos span {
    display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
    background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2);
}

/* ---- Formulario ---- */
.login__lado-form {
    position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: var(--space-8) var(--space-6) var(--space-6);
}
.login__tema {
    position: absolute; top: max(20px, env(safe-area-inset-top, 0px)); right: 20px;
    display: grid; place-items: center; width: 44px; height: 44px;
    background: var(--surface); border: 1px solid var(--border); border-radius: 50%; color: var(--text-muted); cursor: pointer;
    box-shadow: var(--shadow-sm); transition: color var(--transition-fast), border-color var(--transition-fast), transform var(--transition-base);
}
.login__tema:hover { color: var(--c-primary); border-color: var(--c-primary); transform: rotate(-15deg); }

.login__form {
    width: 100%; max-width: 420px; display: flex; flex-direction: column; gap: var(--space-4);
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
    padding: var(--space-6); box-shadow: var(--shadow-md);
    animation: aparecer 600ms 120ms var(--ease) both;
}
.login__encabezado { margin-bottom: var(--space-2); }
.login__insignia {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px;
    background: var(--c-primary-soft); color: var(--c-primary); font-size: var(--text-xs); font-weight: 600;
}
.login__encabezado h2 { margin: 14px 0 6px; font-size: clamp(1.5rem, 1.2rem + 1vw, 1.85rem); font-weight: 700; }
.login__encabezado p { margin: 0; color: var(--text-muted); font-size: var(--text-sm); }

.login__error {
    display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: var(--radius-md);
    background: var(--c-risk-soft); color: var(--c-risk); border: 1px solid color-mix(in srgb, var(--c-risk) 30%, transparent);
    font-size: var(--text-sm); font-weight: 500;
}
.login__error :deep(svg) { margin-top: 1px; }
.alerta-enter-active, .alerta-leave-active { transition: opacity 220ms var(--ease), transform 220ms var(--ease); }
.alerta-enter-from, .alerta-leave-to { opacity: 0; transform: translateY(-6px); }
.login__error { animation: sacudir 380ms var(--ease); }
@keyframes sacudir { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }

.login__boton { width: 100%; min-height: 48px; font-size: var(--text-base); margin-top: var(--space-1); }
.login__boton-flecha { transition: transform var(--transition-fast); }
.login__boton:hover .login__boton-flecha { transform: translateX(3px); }
.login__olvide { text-align: center; font-size: var(--text-sm); font-weight: 600; }
.login__pie {
    display: flex; align-items: flex-start; gap: 8px; margin: var(--space-2) 0 0; padding-top: var(--space-4);
    border-top: 1px solid var(--border); color: var(--text-subtle); font-size: var(--text-xs); line-height: 1.5;
}
.login__pie :deep(svg) { flex-shrink: 0; margin-top: 2px; color: var(--c-primary); }
.login__copy { margin: var(--space-5) 0 0; color: var(--text-subtle); font-size: var(--text-xs); }

/* ---- Tablets ---- */
@media (max-width: 1023px) {
    .login { grid-template-columns: 1fr; }
    .login__panel { padding: var(--space-8) var(--space-5) 88px; }
    .login__puntos { display: none; }
    .login__lado-form { justify-content: flex-start; padding-top: 0; margin-top: -64px; }
    .login__tema { position: fixed; z-index: 5; background: rgba(255, 255, 255, 0.16); border-color: rgba(255, 255, 255, 0.3); color: #fff; }
    .login__tema:hover { color: #fff; border-color: #fff; }
}

/* ---- Celulares: experiencia tipo app ---- */
@media (max-width: 640px) {
    .login__panel { padding: calc(var(--space-6) + env(safe-area-inset-top, 0px)) var(--space-4) 76px; }
    .login__logo-placa { border-radius: 22px; padding: 16px 20px; }
    .login__logo { width: 150px; }
    .login__franja { margin: 18px 0 12px; }
    .login__lema { font-size: var(--text-sm); }
    .login__lado-form { padding: 0 var(--space-4) var(--space-5); margin-top: -52px; }
    .login__form { padding: var(--space-5); border-radius: var(--radius-xl); }
    .login__patron { width: 180px; height: 180px; }
}

/* Teléfonos en horizontal: panel compacto */
@media (max-height: 520px) and (orientation: landscape) and (max-width: 1023px) {
    .login__panel { padding: var(--space-4) var(--space-4) 64px; }
    .login__logo-placa { padding: 10px 14px; }
    .login__logo { width: 110px; }
    .login__lema { display: none; }
    .login__franja { margin: 12px 0 8px; }
}
</style>
