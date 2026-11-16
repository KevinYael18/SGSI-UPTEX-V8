<template>
  <div class="dash">
    <header class="dash__hero">
      <div class="dash__hero-usuario">
      <RouterLink :to="{ name: 'perfil' }" class="dash__hero-avatar" title="Ir a Mi perfil" aria-label="Ir a Mi perfil">
        <UserAvatar :nombre="auth.usuario?.nombre" :foto="auth.usuario?.foto_url" :size="76" />
        <span class="dash__hero-avatar-editar" aria-hidden="true"><AppIcon name="camara" :size="13" /></span>
      </RouterLink>
      <div class="dash__hero-texto">
        <span class="dash__fecha"><AppIcon name="calendario" :size="15" />{{ fechaHoy }}</span>
        <h1>{{ saludo }}, {{ auth.usuario?.nombre?.split(' ')[0] }}</h1>
        <p class="dash__subtitulo">
          <span v-if="etiquetaVista">Vista: {{ etiquetaVista }}</span>
          <span v-else>Panel general del Sistema de Gestión de Seguridad de la Información</span>
        </p>
      </div>
      </div>
      <div class="dash__hero-sello" aria-hidden="true">
        <span class="dash__sello-icono"><AppIcon name="cumplimiento" :size="26" /></span>
        <span class="dash__sello-texto"><strong>ISO/IEC 27001</strong>2022</span>
      </div>
    </header>

    <div v-if="cargando" class="dash__cargando" aria-busy="true" aria-label="Cargando indicadores…">
      <div class="dash__kpis">
        <div v-for="n in 6" :key="n" class="skeleton dash__skeleton-kpi" />
      </div>
      <div class="dash__paneles">
        <div v-for="n in 3" :key="n" class="skeleton dash__skeleton-panel" />
      </div>
    </div>

    <template v-else>
      <section class="dash__kpis" aria-label="Indicadores clave">
        <StatCard v-if="kpis.cumplimiento_iso !== undefined" class="aparecer" style="animation-delay: 0ms"
          label="Cumplimiento ISO 27001" :valor="`${kpis.cumplimiento_iso}%`" tono="exito" icono="cumplimiento" :progreso="Number(kpis.cumplimiento_iso)" />
        <StatCard v-if="kpis.riesgos_criticos !== undefined" class="aparecer" style="animation-delay: 50ms"
          label="Riesgos críticos" :valor="kpis.riesgos_criticos" tono="riesgo" icono="riesgos" />
        <StatCard v-if="kpis.incidentes_abiertos !== undefined" class="aparecer" style="animation-delay: 100ms"
          label="Incidentes abiertos" :valor="kpis.incidentes_abiertos" tono="advertencia" icono="incidentes" />
        <StatCard v-if="kpis.auditorias_en_curso !== undefined" class="aparecer" style="animation-delay: 150ms"
          label="Auditorías en curso" :valor="kpis.auditorias_en_curso" icono="auditorias" />
        <StatCard v-if="kpis.documentos_proximos_a_vencer !== undefined" class="aparecer" style="animation-delay: 200ms"
          label="Documentos por vencer" :valor="kpis.documentos_proximos_a_vencer" tono="advertencia" icono="calendario" />
        <StatCard v-if="kpis.activos_totales !== undefined" class="aparecer" style="animation-delay: 250ms"
          label="Activos registrados" :valor="kpis.activos_totales" icono="activos" />
      </section>

      <!-- Indicadores adicionales (cada uno solo si el backend lo envía para este rol) -->
      <template v-for="grupo in gruposExtra" :key="grupo.titulo">
        <h2 v-if="grupo.items.length" class="dash__grupo">{{ grupo.titulo }}</h2>
        <section v-if="grupo.items.length" class="dash__kpis" :aria-label="grupo.titulo">
          <component :is="item.ruta ? 'RouterLink' : 'div'" v-for="(item, i) in grupo.items" :key="item.clave" :to="item.ruta" class="dash__kpi-enlace aparecer" :style="{ animationDelay: `${i * 40}ms` }">
            <StatCard :label="item.label" :valor="kpis[item.clave]" :tono="item.tono" :icono="item.icono" :detalle="item.detalle || ''" />
          </component>
        </section>
      </template>

      <!-- SGSI Intelligence Assistant: resumen ejecutivo con cifras reales (solo con dashboard.ver + ia.usar). -->
      <IaResumenDashboard v-if="puedeResumenIa" class="aparecer dash__ia" />

      <div class="dash__paneles">
        <AppCard v-if="matriz.length" titulo="Matriz de riesgo" subtitulo="Probabilidad × impacto" icono="riesgos" class="aparecer dash__panel-matriz">
          <RiskMatrix :celdas="matriz" @celda-click="irARiesgos" />
        </AppCard>

        <AppCard v-if="kpis.cumplimiento_por_categoria" titulo="Cumplimiento por categoría" subtitulo="Controles del Anexo A" icono="cumplimiento" class="aparecer dash__panel-grafica">
          <ComplianceChart :datos="kpis.cumplimiento_por_categoria" />
        </AppCard>

        <AppCard v-if="graficas.incidentes_por_estado && Object.keys(graficas.incidentes_por_estado).length" titulo="Incidentes por estado" subtitulo="Distribución actual" icono="incidentes" class="aparecer dash__panel-grafica">
          <BarChartGenerico
            :datos="graficas.incidentes_por_estado"
            :colores-por-clave="COLORES_ESTADO_INCIDENTE"
            etiqueta-accesible="Incidentes agrupados por estado"
          />
        </AppCard>
      </div>

      <div v-if="tieneInfra" class="dash__paneles">
        <AppCard v-if="tieneInfra" titulo="Computadoras por laboratorio" subtitulo="Inventario tecnológico" icono="capas" class="aparecer dash__panel-grafica">
          <BarChartGenerico :datos="graficas.computadoras_por_laboratorio" etiqueta-accesible="Computadoras registradas por laboratorio" />
        </AppCard>
      </div>

      <div class="dash__paneles dash__paneles--listas">
        <AppCard titulo="Últimos incidentes" icono="incidentes" class="aparecer">
          <ul v-if="graficas.ultimos_incidentes?.length" class="lista-reciente">
            <li v-for="inc in graficas.ultimos_incidentes" :key="inc.uuid">
              <RouterLink :to="{ name: 'incidentes.detalle', params: { uuid: inc.uuid } }">
                <span class="lista-reciente__icono lista-reciente__icono--riesgo"><AppIcon name="incidentes" :size="15" /></span>
                <span class="lista-reciente__titulo">{{ inc.titulo }}</span>
              </RouterLink>
              <AppBadge :tono="tonoEstadoIncidente(inc.estado)">{{ inc.estado }}</AppBadge>
            </li>
          </ul>
          <p v-else class="lista-reciente__vacio"><AppIcon name="bandeja" :size="20" />Sin incidentes registrados.</p>
        </AppCard>

        <AppCard titulo="Documentos recientes" icono="documentos" class="aparecer">
          <ul v-if="graficas.documentos_recientes?.length" class="lista-reciente">
            <li v-for="doc in graficas.documentos_recientes" :key="doc.uuid">
              <RouterLink :to="{ name: 'documentos.detalle', params: { uuid: doc.uuid } }">
                <span class="lista-reciente__icono"><AppIcon name="documentos" :size="15" /></span>
                <span class="lista-reciente__titulo">{{ doc.titulo }}</span>
              </RouterLink>
              <AppBadge tono="neutral">{{ doc.estado }}</AppBadge>
            </li>
          </ul>
          <p v-else class="lista-reciente__vacio"><AppIcon name="bandeja" :size="20" />Sin documentos registrados.</p>
        </AppCard>

        <AppCard titulo="Próximas auditorías" icono="auditorias" class="aparecer">
          <ul v-if="graficas.proximas_auditorias?.length" class="lista-reciente">
            <li v-for="a in graficas.proximas_auditorias" :key="a.id">
              <RouterLink :to="{ name: 'auditorias.detalle', params: { id: a.id } }">
                <span class="lista-reciente__icono lista-reciente__icono--verde"><AppIcon name="auditorias" :size="15" /></span>
                <span class="lista-reciente__titulo">{{ a.alcance }}</span>
              </RouterLink>
              <span class="lista-reciente__fecha"><AppIcon name="reloj" :size="13" />{{ a.fecha_fin }}</span>
            </li>
          </ul>
          <p v-else class="lista-reciente__vacio"><AppIcon name="bandeja" :size="20" />Sin auditorías próximas a vencer.</p>
        </AppCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import dashboardApi from '../../api/dashboard';
import StatCard from '../../components/ui/StatCard.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import UserAvatar from '../../components/ui/UserAvatar.vue';
import RiskMatrix from '../../components/RiskMatrix.vue';
import ComplianceChart from '../../components/ComplianceChart.vue';
import BarChartGenerico from '../../components/BarChartGenerico.vue';
import IaResumenDashboard from '../../components/ia/IaResumenDashboard.vue';

const auth = useAuthStore();
const puedeResumenIa = computed(() => {
    const permisos = auth.usuario?.permisos ?? [];
    return permisos.includes('ia.usar') && permisos.includes('dashboard.ver');
});
const toast = useToastStore();
const router = useRouter();
const kpis = ref({});
const matriz = ref([]);
const graficas = ref({});
const cargando = ref(true);

const etiquetaVista = computed(() => ({
    administrador_completa: 'Administrador — panorama completo',
    director_ejecutiva: 'Director — resumen ejecutivo',
    auditor_seguimiento: 'Auditor — seguimiento',
    sgsi_operativa: 'Responsable SGSI — operativa',
    responsable_activo: 'Responsable de Activo',
    consulta_limitada: 'Consulta — solo lectura',
}[kpis.value.vista] || ''));

function irARiesgos() { router.push({ name: 'riesgos.lista' }); }

function tonoEstadoIncidente(e) {
    return { abierto: 'riesgo', en_analisis: 'advertencia', contenido: 'advertencia', resuelto: 'signal', cerrado: 'exito' }[e] || 'neutral';
}

// ---- Solo presentación ----
// Color semántico por estado de incidente (mismo criterio que los badges).
const COLORES_ESTADO_INCIDENTE = {
    abierto: '--c-risk',
    en_analisis: '--c-amber',
    contenido: '--c-gray-500',
    resuelto: '--c-lime',
    cerrado: '--c-success',
};

// ---- Indicadores adicionales: SOLO se muestran los que el backend envía
// (calculados con datos reales y filtrados por rol en DashboardService). ----
const DEF_EXTRA = [
    { titulo: 'Infraestructura', items: [
        { clave: 'computadoras_total', label: 'Computadoras', icono: 'activos', ruta: { name: 'inventario' } },
        { clave: 'computadoras_operativas', label: 'Equipos operativos', tono: 'exito', icono: 'check', ruta: { name: 'inventario' } },
        { clave: 'computadoras_mantenimiento', label: 'En mantenimiento', tono: 'advertencia', icono: 'configuracion', ruta: { name: 'inventario' } },
        { clave: 'computadoras_fuera_servicio', label: 'Fuera de servicio', tono: 'riesgo', icono: 'alerta', ruta: { name: 'inventario' } },
        { clave: 'computadoras_antivirus_desactualizado', label: 'Antivirus por atender', tono: 'riesgo', icono: 'escudo', ruta: { name: 'inventario' } },
        { clave: 'computadoras_software_pendiente', label: 'Con software por actualizar', tono: 'advertencia', icono: 'descargar', ruta: { name: 'inventario.software' } },
    ] },
];
const gruposExtra = computed(() => DEF_EXTRA.map((g) => ({ ...g, items: g.items.filter((it) => kpis.value[it.clave] !== undefined) })));
const tieneInfra = computed(() => Object.keys(graficas.value.computadoras_por_laboratorio || {}).length > 0);

const saludo = computed(() => {
    const hora = new Date().getHours();
    if (hora < 12) return 'Buenos días';
    if (hora < 19) return 'Buenas tardes';
    return 'Buenas noches';
});

const fechaHoy = computed(() => {
    const texto = new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return texto.charAt(0).toUpperCase() + texto.slice(1);
});

onMounted(async () => {
    try {
        const [{ data: kpiRes }, { data: matrizRes }, { data: graficasRes }] = await Promise.all([
            dashboardApi.kpis(),
            dashboardApi.matrizRiesgo().catch(() => ({ data: { data: [] } })),
            dashboardApi.graficas().catch(() => ({ data: { data: {} } })),
        ]);
        kpis.value = kpiRes.data;
        matriz.value = matrizRes.data;
        graficas.value = graficasRes.data;
    } catch (e) {
        toast.error(e.message || 'No se pudo cargar el dashboard.');
    } finally {
        cargando.value = false;
    }
});
</script>

<style scoped>
/* ---- Encabezado de bienvenida ---- */
.dash__hero {
    position: relative; overflow: hidden; isolation: isolate;
    display: flex; align-items: center; justify-content: space-between; gap: var(--space-5);
    padding: var(--space-6); margin-bottom: var(--space-5);
    border-radius: var(--radius-xl); background: var(--brand-gradient); color: #fff;
    box-shadow: var(--shadow-md);
}
.dash__hero::before {
    content: ''; position: absolute; inset: 0; z-index: -1;
    background:
        radial-gradient(circle at 100% 0%, rgba(122, 181, 29, 0.38), transparent 42%),
        radial-gradient(circle at 85% 120%, rgba(200, 16, 46, 0.22), transparent 38%),
        linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px) 0 0 / 28px 28px,
        linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px) 0 0 / 28px 28px;
}
.dash__hero::after {
    content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 4px; background: var(--brand-stripe);
}
.dash__hero-usuario { display: flex; align-items: center; gap: var(--space-5); min-width: 0; }
.dash__hero-avatar {
    position: relative; flex-shrink: 0; border-radius: 50%; padding: 3px;
    background: rgba(255, 255, 255, 0.9); box-shadow: 0 8px 24px -10px rgba(0, 0, 0, 0.45);
    transition: transform var(--transition-base);
}
.dash__hero-avatar:hover { transform: scale(1.04); text-decoration: none; }
.dash__hero-avatar:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
.dash__hero-avatar-editar {
    position: absolute; right: -2px; bottom: 2px; width: 26px; height: 26px; border-radius: 50%;
    display: grid; place-items: center; background: #fff; color: #0A7A3D; box-shadow: var(--shadow-sm);
    opacity: 0; transform: scale(0.8); transition: opacity var(--transition-fast), transform var(--transition-fast);
}
.dash__hero-avatar:hover .dash__hero-avatar-editar, .dash__hero-avatar:focus-visible .dash__hero-avatar-editar { opacity: 1; transform: scale(1); }
.dash__hero-texto { min-width: 0; }
.dash__fecha {
    display: inline-flex; align-items: center; gap: 6px; font-size: var(--text-xs); font-weight: 500;
    padding: 4px 10px; border-radius: 999px; background: rgba(255, 255, 255, 0.14); color: rgba(255, 255, 255, 0.92);
}
.dash__hero h1 { color: #fff; margin: 12px 0 6px; font-weight: 700; }
.dash__subtitulo { margin: 0; color: rgba(255, 255, 255, 0.85); font-size: var(--text-sm); }
.dash__hero-sello {
    display: flex; align-items: center; gap: 12px; flex-shrink: 0; padding: 12px 16px 12px 12px;
    border-radius: var(--radius-lg); background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
}
.dash__sello-icono {
    display: grid; place-items: center; width: 46px; height: 46px; border-radius: 12px; background: #fff; color: #0A7A3D;
}
.dash__sello-texto { display: flex; flex-direction: column; font-size: var(--text-xs); color: rgba(255, 255, 255, 0.85); line-height: 1.3; }
.dash__sello-texto strong { font-family: var(--font-display); font-size: var(--text-base); color: #fff; }

/* ---- Indicadores ---- */
.dash__kpis {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); margin-bottom: var(--space-5);
}
@media (min-width: 768px) { .dash__kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); } }
@media (min-width: 1440px) { .dash__kpis { grid-template-columns: repeat(6, minmax(0, 1fr)); } }

/* ---- Grupos adicionales de indicadores ---- */
.dash__grupo { font-size: var(--text-sm); text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin: 0 0 var(--space-3); }
.dash__kpi-enlace { display: block; color: inherit; min-width: 0; border-radius: var(--radius-lg); }
.dash__kpi-enlace:hover { text-decoration: none; }
.dash__kpi-enlace:focus-visible { outline: 2px solid var(--c-primary); outline-offset: 3px; }
.dash__kpi-enlace :deep(.stat-card) { height: 100%; }
.dash__kpi-enlace:active :deep(.stat-card) { transform: scale(0.985); }

/* ---- Paneles ---- */
.dash__paneles {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr));
    gap: var(--space-5); margin-bottom: var(--space-5); align-items: stretch;
}
/* Las gráficas llenan el alto de su tarjeta (que iguala al de la matriz de
   riesgo en la misma fila) en lugar de quedarse a 220 px con hueco debajo. */
.dash__panel-grafica { display: flex; flex-direction: column; }
.dash__panel-grafica :deep(.app-card__body) { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.dash__panel-grafica :deep(.compliance-chart),
.dash__panel-grafica :deep(.bar-chart-contenedor) { flex: 1; min-height: 240px; height: auto !important; }
.dash__panel-matriz :deep(.app-card__body) { display: grid; place-items: center; }

/* ---- Listas recientes ---- */
.lista-reciente { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.lista-reciente li {
    display: flex; justify-content: space-between; align-items: center; gap: var(--space-3);
    padding: 10px 0; border-bottom: 1px solid var(--border); font-size: var(--text-sm);
}
.lista-reciente li:first-child { padding-top: 0; }
.lista-reciente li:last-child { border-bottom: none; padding-bottom: 0; }
.lista-reciente a { display: flex; align-items: center; gap: 10px; min-width: 0; color: var(--text); font-weight: 500; }
.lista-reciente a:hover { color: var(--c-primary); text-decoration: none; }
.lista-reciente__titulo {
    overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.35; overflow-wrap: anywhere;
}
.lista-reciente__icono {
    display: grid; place-items: center; width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
    background: var(--bg-subtle); color: var(--text-muted);
}
.lista-reciente__icono--riesgo { background: var(--c-risk-soft); color: var(--c-risk); }
.lista-reciente__icono--verde { background: var(--c-primary-soft); color: var(--c-primary); }
.lista-reciente__fecha { display: inline-flex; align-items: center; gap: 4px; color: var(--text-muted); font-size: var(--text-xs); white-space: nowrap; font-family: var(--font-mono); }
.lista-reciente__vacio {
    display: flex; flex-direction: column; align-items: center; gap: 8px; margin: 0; padding: var(--space-4) 0;
    color: var(--text-subtle); font-size: var(--text-sm); text-align: center;
}

/* ---- Esqueletos de carga ---- */
.dash__skeleton-kpi { height: 132px; }
.dash__skeleton-panel { height: 320px; }

/* ---- Tablets ---- */
@media (max-width: 1023px) {
    .dash__hero { padding: var(--space-5); }
}

/* ---- Celulares ---- */
@media (max-width: 640px) {
    .dash__hero { flex-direction: column; align-items: flex-start; padding: var(--space-5) var(--space-4); border-radius: var(--radius-lg); gap: var(--space-4); }
    .dash__hero-sello { display: none; }
    .dash__hero-usuario { gap: var(--space-3); }
    .dash__hero-avatar :deep(.user-avatar) { --avatar-size: 56px !important; }
    .dash__paneles { gap: var(--space-4); margin-bottom: var(--space-4); }
    .dash__skeleton-kpi { height: 112px; }
}
</style>
