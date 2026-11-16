<template>
  <aside
    class="sidebar"
    :class="{ 'sidebar--colapsado': compacto, 'sidebar--abierto': ui.menuMovilAbierto }"
    aria-label="Menú principal"
  >
    <div class="sidebar__marca">
      <RouterLink :to="{ name: 'dashboard' }" class="sidebar__logo-enlace" title="Ir al dashboard">
        <img
          v-if="!compacto"
          src="../../assets/logo-uptex-completo.png"
          alt="Universidad Politécnica de Texcoco"
          class="sidebar__logo"
          width="720" height="462"
        />
        <img
          v-else
          src="../../assets/logo-uptex-marca.png"
          alt="UPTex"
          class="sidebar__logo sidebar__logo--marca"
          width="480" height="223"
        />
      </RouterLink>
      <button class="sidebar__cerrar-movil" aria-label="Cerrar menú" @click="ui.cerrarMenuMovil()">
        <AppIcon name="cerrar" :size="20" />
      </button>
    </div>

    <div v-if="!compacto" class="sidebar__sistema">
      <span class="sidebar__sistema-icono"><AppIcon name="escudo" :size="14" grosor="2.2" /></span>
      <span>SGSI · Seguridad de la Información</span>
    </div>

    <nav class="sidebar__nav">
      <div v-for="grupo in grupos" :key="grupo.nombre" class="sidebar__grupo">
        <span v-if="!compacto" class="sidebar__grupo-titulo">{{ grupo.nombre }}</span>
        <span v-else class="sidebar__grupo-separador" aria-hidden="true" />
        <RouterLink
          v-for="item in grupo.items"
          :key="item.to"
          :to="{ name: item.to }"
          class="sidebar__link"
          :active-class="item.to === 'dashboard' ? '' : 'sidebar__link--activo'"
          exact-active-class="sidebar__link--activo"
          :title="compacto ? item.label : undefined"
          @click="ui.cerrarMenuMovil()"
        >
          <span class="sidebar__icono"><AppIcon :name="item.icono" :size="19" /></span>
          <span v-if="!compacto" class="sidebar__texto">{{ item.label }}</span>
        </RouterLink>
      </div>
    </nav>

    <button class="sidebar__colapsar" @click="ui.alternarSidebar()" :aria-label="compacto ? 'Expandir menú' : 'Colapsar menú'">
      <AppIcon :name="compacto ? 'chevrons-der' : 'chevrons-izq'" :size="18" />
      <span v-if="!compacto">Contraer menú</span>
    </button>
  </aside>
</template>

<script setup>
import { useUiStore } from '../../stores/ui';
import { useNavegacion } from '../../composables/useNavegacion';
import AppIcon from '../ui/AppIcon.vue';
import { computed } from 'vue';
import { useMediaQuery } from '../../composables/useMediaQuery';

const ui = useUiStore();
// La lista de módulos y sus reglas de permisos viven en useNavegacion.js
// (misma lógica que antes estaba aquí, compartida con la barra móvil).
const { grupos } = useNavegacion();

// El modo contraído solo aplica en escritorio; en el drawer móvil/tablet
// siempre se muestran los nombres completos de los módulos.
const esEscritorio = useMediaQuery('(min-width: 1024px)');
const compacto = computed(() => ui.sidebarColapsado && esEscritorio.value);
</script>

<style scoped>
.sidebar {
    width: var(--sidebar-width); flex-shrink: 0;
    background: var(--surface); border-right: 1px solid var(--border);
    display: flex; flex-direction: column; height: 100vh; height: 100dvh; position: sticky; top: 0; z-index: 40;
    transition: width var(--transition-base), transform 300ms var(--ease), background-color var(--transition-base);
}
.sidebar::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--brand-stripe);
}
.sidebar--colapsado { width: var(--sidebar-collapsed); }

/* ---- Marca ---- */
.sidebar__marca {
    display: flex; align-items: center; justify-content: center; gap: var(--space-2);
    padding: 22px var(--space-4) 10px;
}
.sidebar__logo-enlace {
    display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md);
    padding: 8px 12px; transition: background var(--transition-fast);
}
.sidebar__logo-enlace:hover { text-decoration: none; }
.sidebar__logo { display: block; width: 168px; height: auto; }
.sidebar__logo--marca { width: 50px; }
.sidebar--colapsado .sidebar__logo-enlace { padding: 6px; }
/* En modo oscuro el logotipo se presenta sobre una placa blanca para
   conservar sus colores originales y la legibilidad del texto gris. */
[data-theme='dark'] .sidebar__logo-enlace { background: #FFFFFF; box-shadow: var(--shadow-sm); }

.sidebar__cerrar-movil {
    display: none; position: absolute; top: 14px; right: 12px; width: 40px; height: 40px; place-items: center;
    background: var(--bg-subtle); border: none; border-radius: 50%; color: var(--text-muted); cursor: pointer;
}

.sidebar__sistema {
    display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0 var(--space-4) var(--space-2);
    padding: 6px 10px; border-radius: 999px; background: var(--c-primary-soft); color: var(--c-primary);
    font-size: 0.68rem; font-weight: 600; letter-spacing: 0.02em; white-space: nowrap;
}
.sidebar__sistema-icono { display: inline-flex; }

/* ---- Navegación ---- */
.sidebar__nav {
    display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-2) var(--space-3) var(--space-4);
    flex: 1; overflow-y: auto; overflow-x: hidden;
}
.sidebar__grupo { display: flex; flex-direction: column; gap: 2px; }
.sidebar__grupo-titulo {
    padding: 12px 12px 6px; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase;
    color: var(--text-subtle); white-space: nowrap;
}
.sidebar__grupo-separador { display: block; height: 1px; margin: 8px 14px; background: var(--border); }
.sidebar__grupo:first-child .sidebar__grupo-separador { display: none; }

.sidebar__link {
    position: relative; display: flex; align-items: center; gap: 12px; min-height: 42px; padding: 9px 12px;
    border-radius: var(--radius-md); color: var(--text-muted); font-size: var(--text-sm); font-weight: 500;
    transition: background var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
}
.sidebar__link:hover { background: var(--bg-subtle); color: var(--text); text-decoration: none; }
.sidebar__link:hover .sidebar__icono { transform: translateX(2px); color: var(--c-primary); }
.sidebar__icono { display: inline-flex; width: 20px; justify-content: center; transition: transform var(--transition-fast), color var(--transition-fast); }
.sidebar__texto { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.sidebar__link--activo,
.sidebar__link--activo:hover { background: var(--c-primary-soft); color: var(--c-primary); font-weight: 600; }
.sidebar__link--activo .sidebar__icono { color: var(--c-primary); }
.sidebar__link--activo::before {
    content: ''; position: absolute; left: -12px; top: 8px; bottom: 8px; width: 4px; border-radius: 0 4px 4px 0;
    background: linear-gradient(180deg, var(--c-primary), var(--c-lime));
    /* Solo presentación: el indicador "crece" al activar la sección. */
    transform-origin: center; animation: indicador-activo 300ms var(--ease-salida, var(--ease)) backwards;
}
@keyframes indicador-activo { from { transform: scaleY(0.2); opacity: 0; } }
.sidebar__link:active { transform: scale(0.985); }

.sidebar--colapsado .sidebar__nav { padding-inline: var(--space-3); }
.sidebar--colapsado .sidebar__link { justify-content: center; padding: 10px; }
.sidebar--colapsado .sidebar__link:hover .sidebar__icono { transform: none; }

/* ---- Pie ---- */
.sidebar__colapsar {
    display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px;
    border: none; border-top: 1px solid var(--border); background: none; color: var(--text-muted);
    font-size: var(--text-xs); font-weight: 600; padding: var(--space-3); cursor: pointer;
    transition: color var(--transition-fast), background var(--transition-fast);
}
.sidebar__colapsar:hover { color: var(--c-primary); background: var(--bg-subtle); }

/* ---- Tablets y celulares: drawer deslizable ---- */
@media (max-width: 1023px) {
    .sidebar,
    .sidebar--colapsado {
        position: fixed; left: 0; top: 0; bottom: 0; width: min(300px, 86vw); z-index: 90;
        transform: translateX(-104%); box-shadow: var(--shadow-lg); border-right: none;
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    .sidebar--abierto { transform: none; }
    .sidebar__cerrar-movil { display: grid; }
    .sidebar__marca { justify-content: flex-start; padding-right: 60px; }
    .sidebar__colapsar { display: none; }
    /* En el drawer siempre se muestra el menú completo, aunque en escritorio esté contraído */
    .sidebar--colapsado .sidebar__link { justify-content: flex-start; padding: 9px 12px; }
    .sidebar__link { min-height: 46px; }
}
</style>
