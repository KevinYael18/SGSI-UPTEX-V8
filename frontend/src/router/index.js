import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/LoginView.vue'),
        meta: { publico: true },
    },
    {
    path: '/olvide-password',
    name: 'olvide-password',
    component: () => import('../views/auth/OlvidePasswordView.vue'),
    meta: { publico: true },
},
{
    path: '/restablecer-password',
    name: 'restablecer-password',
    component: () => import('../views/auth/RestablecerPasswordView.vue'),
    meta: { publico: true },
},
    {
        path: '/',
        component: () => import('../components/layout/AppShell.vue'),
        meta: { requiereAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('../views/dashboard/DashboardView.vue') },

            { path: 'activos', name: 'activos.lista', component: () => import('../views/activos/ActivosListView.vue') },
            { path: 'activos/:uuid', name: 'activos.detalle', component: () => import('../views/activos/ActivoDetailView.vue'), props: true },

            { path: 'riesgos', name: 'riesgos.lista', component: () => import('../views/riesgos/RiesgosListView.vue') },

            { path: 'incidentes', name: 'incidentes.lista', component: () => import('../views/incidentes/IncidentesListView.vue') },
            { path: 'incidentes/:uuid', name: 'incidentes.detalle', component: () => import('../views/incidentes/IncidenteDetailView.vue'), props: true },

            { path: 'auditorias', name: 'auditorias.lista', component: () => import('../views/auditorias/AuditoriasListView.vue') },
            { path: 'auditorias/:id', name: 'auditorias.detalle', component: () => import('../views/auditorias/AuditoriaDetailView.vue'), props: true },
            { path: 'acciones-correctivas', name: 'acciones-correctivas.lista', component: () => import('../views/auditorias/AccionesCorrectivasListView.vue') },

            { path: 'documentos', name: 'documentos.lista', component: () => import('../views/documentos/DocumentosListView.vue') },
            { path: 'documentos/:uuid', name: 'documentos.detalle', component: () => import('../views/documentos/DocumentoDetailView.vue'), props: true },

            { path: 'cumplimiento', name: 'cumplimiento', component: () => import('../views/cumplimiento/CumplimientoView.vue') },
            { path: 'reportes', name: 'reportes', component: () => import('../views/reportes/ReportesView.vue') },

            { path: 'notificaciones', name: 'notificaciones', component: () => import('../views/notificaciones/NotificacionesView.vue') },

            { path: 'busqueda', name: 'busqueda', component: () => import('../views/busqueda/BusquedaView.vue') },

            // Módulo «Mi perfil»: disponible para cualquier usuario autenticado (solo su propio perfil).
            { path: 'perfil', name: 'perfil', component: () => import('../views/perfil/PerfilView.vue') },

            {
                path: 'configuracion',
                name: 'configuracion',
                component: () => import('../views/config/ConfiguracionView.vue'),
                meta: { soloAdmin: true },
            },
            {
              path: 'usuarios',
              name: 'usuarios.lista',
              component: () => import('../views/usuarios/UsuariosListView.vue'),
             meta: { soloAdmin: true },
            },
            {
              path: 'roles',
              name: 'roles.lista',
              component: () => import('../views/usuarios/RolesListView.vue'),
              meta: { soloAdmin: true },
            },
            {
              path: 'bitacora',
              name: 'bitacora',
              component: () => import('../views/usuarios/BitacoraView.vue'),
              meta: { soloAdmin: true },
            },
        ],
    },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue') },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

/**
 * Guard de navegación: resuelve la sesión UNA vez (cookie Sanctum ya
 * emitida por el backend) y redirige según corresponda. La autorización
 * real (qué puede hacer cada rol) siempre se revalida en el backend vía
 * Policies — este guard solo evita parpadeos de UI para rutas obviamente
 * no permitidas.
 */
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.listo) {
        await auth.resolverSesion();
    }

    if (to.meta.publico) {
        if (auth.autenticado && to.name === 'login') {
            return { name: 'dashboard' };
        }
        return true;
    }

    if (to.meta.requiereAuth && !auth.autenticado) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.soloAdmin && !auth.esAdministrador) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;