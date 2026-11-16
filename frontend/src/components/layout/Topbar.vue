<template>
  <header class="topbar" :class="{ 'topbar--buscando': buscadorAbierto }">
    <div class="topbar__fila">
      <button class="topbar__icono-btn topbar__hamburguesa" aria-label="Abrir menú" @click="ui.abrirMenuMovil()">
        <AppIcon name="menu" :size="22" />
      </button>

      <RouterLink :to="{ name: 'dashboard' }" class="topbar__marca" aria-label="Ir al dashboard">
        <img src="../../assets/logo-uptex-marca.png" alt="UPTex" width="480" height="223" />
        <span class="topbar__marca-texto">SGSI</span>
      </RouterLink>

      <div class="topbar__busqueda">
        <GlobalSearch />
      </div>

      <div class="topbar__acciones">
        <button
          class="topbar__icono-btn topbar__buscar-movil"
          :aria-label="buscadorAbierto ? 'Cerrar búsqueda' : 'Buscar'"
          :aria-expanded="buscadorAbierto"
          @click="buscadorAbierto = !buscadorAbierto"
        >
          <AppIcon :name="buscadorAbierto ? 'cerrar' : 'buscar'" :size="20" />
        </button>

        <RouterLink
          v-if="puedeUsarIa"
          :to="{ name: 'asistente' }"
          class="topbar__icono-btn topbar__ia"
          aria-label="Abrir SGSI Intelligence Assistant"
          title="SGSI Intelligence Assistant"
        >
          <AppIcon name="asistente" :size="20" />
        </RouterLink>

        <button
          class="topbar__icono-btn topbar__tema"
          @click="ui.alternarTema($event)"
          :aria-label="ui.tema === 'light' ? 'Modo oscuro' : 'Modo claro'"
          :title="ui.tema === 'light' ? 'Activar modo oscuro' : 'Activar modo claro'"
        >
          <Transition name="giro" mode="out-in">
            <AppIcon :key="ui.tema" :name="ui.tema === 'light' ? 'luna' : 'sol'" :size="20" />
          </Transition>
        </button>

        <NotificationBell />

        <div ref="refUsuario" class="topbar__usuario" @keydown.esc="cerrarMenu(true)">
          <button
            ref="refBotonPerfil"
            class="topbar__perfil"
            :class="{ 'topbar__perfil--abierto': menuUsuario }"
            :aria-expanded="menuUsuario"
            aria-haspopup="menu"
            aria-controls="menu-usuario"
            aria-label="Menú de usuario"
            @click="menuUsuario = !menuUsuario"
          >
            <UserAvatar class="topbar__avatar" :nombre="auth.usuario?.nombre" :foto="auth.usuario?.foto_url" :size="36" />
            <span class="topbar__datos">
              <span class="topbar__nombre">{{ auth.usuario?.nombre }}</span>
              <span class="topbar__rol">{{ auth.rol }}</span>
            </span>
            <AppIcon name="chevron-abajo" :size="16" class="topbar__chevron" />
          </button>

          <Transition name="desplegar">
            <div v-if="menuUsuario" id="menu-usuario" class="topbar__menu" role="menu" aria-label="Opciones de la cuenta">
              <div class="topbar__menu-cabecera">
                <UserAvatar class="topbar__avatar topbar__avatar--grande" :nombre="auth.usuario?.nombre" :foto="auth.usuario?.foto_url" :size="52" />
                <div class="topbar__menu-textos">
                  <strong>{{ auth.usuario?.nombre }}</strong>
                  <span v-if="auth.usuario?.email" class="topbar__menu-correo">{{ auth.usuario.email }}</span>
                  <span v-if="auth.rol" class="topbar__menu-rol"><AppIcon name="escudo" :size="12" />{{ auth.rol }}</span>
                </div>
              </div>

              <RouterLink :to="{ name: 'perfil' }" class="topbar__menu-item" role="menuitem" @click="cerrarMenu()">
                <span class="topbar__menu-icono"><AppIcon name="usuario" :size="17" /></span>
                <span class="topbar__menu-texto">Mi perfil<small>Datos personales y fotografía</small></span>
              </RouterLink>
              <RouterLink :to="{ name: 'perfil', hash: '#cambiar-password' }" class="topbar__menu-item" role="menuitem" @click="cerrarMenu()">
                <span class="topbar__menu-icono"><AppIcon name="candado" :size="17" /></span>
                <span class="topbar__menu-texto">Cambiar contraseña<small>Actualiza tus credenciales</small></span>
              </RouterLink>
              <button class="topbar__menu-item" role="menuitem" @click="ui.alternarTema($event)">
                <span class="topbar__menu-icono"><AppIcon :name="ui.tema === 'light' ? 'luna' : 'sol'" :size="17" /></span>
                <span class="topbar__menu-texto">{{ ui.tema === 'light' ? 'Modo oscuro' : 'Modo claro' }}</span>
              </button>

              <div class="topbar__menu-separador" role="separator" />
              <button class="topbar__menu-item topbar__menu-item--salir" role="menuitem" @click="salir">
                <span class="topbar__menu-icono"><AppIcon name="salir" :size="17" /></span>
                <span class="topbar__menu-texto">Cerrar sesión</span>
              </button>
            </div>
          </Transition>
        </div>

        <button class="topbar__salir" @click="salir" title="Cerrar sesión">
          <AppIcon name="salir" :size="18" />
          <span>Salir</span>
        </button>
      </div>
    </div>

    <div v-if="buscadorAbierto" class="topbar__busqueda-movil">
      <GlobalSearch />
    </div>
  </header>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import GlobalSearch from '../GlobalSearch.vue';
import NotificationBell from '../NotificationBell.vue';
import AppIcon from '../ui/AppIcon.vue';
import UserAvatar from '../ui/UserAvatar.vue';

const auth = useAuthStore();
const puedeUsarIa = computed(() => (auth.usuario?.permisos ?? []).includes('ia.usar'));
const ui = useUiStore();
const router = useRouter();
const route = useRoute();

async function salir() {
    try {
        await auth.cerrarSesion();
    } finally {
        router.push({ name: 'login' });
    }
}

// ---- Solo presentación ----
const buscadorAbierto = ref(false);
const menuUsuario = ref(false);
const refUsuario = ref(null);
const refBotonPerfil = ref(null);

function cerrarMenu(devolverFoco = false) {
    if (!menuUsuario.value) return;
    menuUsuario.value = false;
    if (devolverFoco) refBotonPerfil.value?.focus();
}

function alClicFuera(e) {
    if (menuUsuario.value && refUsuario.value && !refUsuario.value.contains(e.target)) menuUsuario.value = false;
}
onMounted(() => document.addEventListener('click', alClicFuera));
onBeforeUnmount(() => document.removeEventListener('click', alClicFuera));

watch(() => route.fullPath, () => {
    buscadorAbierto.value = false;
    menuUsuario.value = false;
});
</script>

<style scoped>
.topbar {
    position: sticky; top: 0; z-index: 30;
    background: color-mix(in srgb, var(--surface) 88%, transparent);
    backdrop-filter: saturate(160%) blur(12px); -webkit-backdrop-filter: saturate(160%) blur(12px);
    border-bottom: 1px solid var(--border);
    padding-top: env(safe-area-inset-top, 0px);
}
.topbar__fila {
    height: var(--topbar-height); display: flex; align-items: center; gap: var(--space-3);
    padding: 0 var(--page-pad);
}
.topbar__busqueda { flex: 1; min-width: 0; max-width: 520px; }
.topbar__acciones { display: flex; align-items: center; gap: 6px; margin-left: auto; }

.topbar__icono-btn {
    position: relative; display: grid; place-items: center; width: 42px; height: 42px; flex-shrink: 0;
    background: none; border: 1px solid transparent; border-radius: var(--radius-md); cursor: pointer; color: var(--text-muted);
    transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast);
}
.topbar__icono-btn:hover { background: var(--bg-subtle); color: var(--c-primary); border-color: var(--border); }
.topbar__ia { color: var(--c-primary); }
.topbar__ia:hover { text-decoration: none; }
.topbar__ia.router-link-active { background: var(--c-primary-soft); }
.topbar__hamburguesa, .topbar__marca, .topbar__buscar-movil { display: none; }

.topbar__marca { align-items: center; gap: 8px; flex-shrink: 0; }
.topbar__marca:hover { text-decoration: none; }
.topbar__marca img { width: 64px; height: auto; display: block; }
[data-theme='dark'] .topbar__marca img { background: #fff; border-radius: 8px; padding: 4px 6px; width: 72px; }
.topbar__marca-texto {
    font-family: var(--font-display); font-weight: 700; font-size: var(--text-sm); color: var(--text);
    padding-left: 8px; border-left: 1px solid var(--border-strong); letter-spacing: 0.02em;
}

/* ---- Usuario ---- */
.topbar__usuario { position: relative; margin-left: 4px; }
.topbar__perfil {
    display: flex; align-items: center; gap: 10px; padding: 4px 8px 4px 4px; min-height: 44px;
    background: none; border: 1px solid transparent; border-radius: 999px; cursor: pointer; color: var(--text); text-align: left;
    transition: background var(--transition-fast), border-color var(--transition-fast);
}
.topbar__perfil:hover, .topbar__perfil--abierto { background: var(--bg-subtle); border-color: var(--border); }
.topbar__perfil:focus-visible { outline: 2px solid var(--c-primary); outline-offset: 2px; }
.topbar__avatar { box-shadow: 0 0 0 2px var(--surface), 0 0 0 3px var(--c-primary-soft); }
.topbar__datos { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.topbar__nombre { font-size: var(--text-sm); font-weight: 600; white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.topbar__rol { font-size: var(--text-xs); color: var(--c-primary); font-weight: 500; white-space: nowrap; }
.topbar__chevron { color: var(--text-subtle); transition: transform var(--transition-base); }
.topbar__perfil--abierto .topbar__chevron { transform: rotate(180deg); color: var(--c-primary); }

.topbar__menu {
    position: absolute; right: 0; top: calc(100% + 10px); width: 300px; z-index: 60; overflow: hidden;
    background: var(--surface-raised); border: 1px solid var(--border); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); padding: 0 8px 8px; transform-origin: top right;
}
.topbar__menu::before { content: ''; display: block; height: 4px; margin: 0 -8px; background: var(--brand-stripe); }
.topbar__menu-cabecera {
    display: flex; align-items: center; gap: 12px; padding: 16px 10px 14px; margin: 0 -8px 6px; padding-inline: 18px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg, var(--c-primary-soft), transparent);
}
.topbar__menu-textos { display: flex; flex-direction: column; gap: 2px; min-width: 0; font-size: var(--text-xs); color: var(--text-muted); }
.topbar__menu-textos strong { font-size: var(--text-sm); color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.topbar__menu-correo { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.topbar__menu-rol {
    align-self: flex-start; display: inline-flex; align-items: center; gap: 4px; margin-top: 3px; padding: 2px 8px;
    border-radius: 999px; background: var(--c-primary-soft); color: var(--c-primary); font-weight: 600; white-space: nowrap;
}
.topbar__menu-item {
    display: flex; align-items: center; gap: 12px; width: 100%; min-height: 46px; padding: 8px 10px; text-align: left;
    background: none; border: none; border-radius: var(--radius-sm); cursor: pointer; color: var(--text); font-size: var(--text-sm); font-weight: 500;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.topbar__menu-item:hover, .topbar__menu-item:focus-visible { background: var(--bg-subtle); text-decoration: none; outline: none; }
.topbar__menu-item.router-link-exact-active:not([href*='#']) { color: var(--c-primary); }
.topbar__menu-icono {
    display: grid; place-items: center; width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
    background: var(--bg-subtle); color: var(--text-muted); transition: background var(--transition-fast), color var(--transition-fast);
}
.topbar__menu-item:hover .topbar__menu-icono { background: var(--c-primary-soft); color: var(--c-primary); }
.topbar__menu-texto { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.topbar__menu-texto small { font-size: var(--text-xs); color: var(--text-muted); font-weight: 400; }
.topbar__menu-separador { height: 1px; background: var(--border); margin: 6px 4px; }
.topbar__menu-item--salir { color: var(--c-risk); }
.topbar__menu-item--salir .topbar__menu-icono { background: var(--c-risk-soft); color: var(--c-risk); }
.topbar__menu-item--salir:hover { background: var(--c-risk-soft); }
.topbar__menu-item--salir:hover .topbar__menu-icono { background: var(--c-risk); color: #fff; }

.topbar__salir {
    display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 8px 12px; margin-left: 2px;
    background: none; border: 1px solid var(--border); border-radius: var(--radius-md); cursor: pointer;
    color: var(--text-muted); font-size: var(--text-xs); font-weight: 600;
    transition: color var(--transition-fast), border-color var(--transition-fast), background var(--transition-fast);
}
.topbar__salir:hover { color: var(--c-risk); border-color: var(--c-risk); background: var(--c-risk-soft); }

.topbar__busqueda-movil { padding: 0 var(--page-pad) 12px; animation: aparecer 220ms var(--ease) both; }

/* Transiciones */
.giro-enter-active, .giro-leave-active { transition: transform 220ms var(--ease), opacity 220ms var(--ease); }
.giro-enter-from { transform: rotate(-90deg) scale(0.6); opacity: 0; }
.giro-leave-to { transform: rotate(90deg) scale(0.6); opacity: 0; }
.desplegar-enter-active, .desplegar-leave-active { transition: opacity 180ms var(--ease), transform 200ms var(--ease); }
.desplegar-enter-from, .desplegar-leave-to { opacity: 0; transform: translateY(-6px) scale(0.98); }
@media (prefers-reduced-motion: reduce) {
    .desplegar-enter-active, .desplegar-leave-active { transition: opacity 120ms linear; }
    .desplegar-enter-from, .desplegar-leave-to { transform: none; }
}

/* ---- Laptops pequeñas / tablets horizontales ---- */
@media (max-width: 1279px) {
    .topbar__nombre { max-width: 120px; }
}

/* ---- Tablets: aparece el botón hamburguesa y la marca ---- */
@media (max-width: 1023px) {
    .topbar__hamburguesa { display: grid; }
    .topbar__marca { display: flex; }
    .topbar__salir { display: none; }
    .topbar__datos { display: none; }
    .topbar__perfil { padding: 3px; }
}

/* ---- Celulares ---- */
@media (max-width: 640px) {
    .topbar__fila { gap: 4px; padding: 0 10px 0 6px; }
    .topbar__busqueda { display: none; }
    .topbar__buscar-movil { display: grid; }
    .topbar__icono-btn { width: 40px; height: 40px; }
    .topbar__chevron { display: none; }
    .topbar__menu {
        position: fixed; left: 12px; right: 12px; top: calc(var(--topbar-height) + env(safe-area-inset-top, 0px) + 6px); width: auto;
        max-height: calc(100dvh - var(--topbar-height) - var(--bottomnav-height, 64px) - 24px); overflow-y: auto;
    }
    .topbar__menu-item { min-height: 50px; }
    .topbar__marca-texto { display: none; }
}
@media (max-width: 360px) {
    .topbar__marca img { width: 54px; }
}
</style>
