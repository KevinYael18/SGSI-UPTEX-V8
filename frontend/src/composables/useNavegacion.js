import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';

/**
 * Cada módulo requiere al menos uno de estos permisos para mostrarse.
 * Administrador ve todo porque su rol tiene TODOS los permisos asignados
 * en el seeder (RolesPermisosSeeder), no por un caso especial aquí.
 *
 * Lógica trasladada sin cambios desde Sidebar.vue para que el menú lateral
 * y la barra de navegación móvil compartan exactamente las mismas reglas.
 * Solo se añadieron `icono` (nombre de AppIcon) y `grupo` (encabezado
 * visual del menú), ambos de presentación.
 */
const TODOS_LOS_ITEMS = [
    { to: 'dashboard', label: 'Dashboard', icono: 'dashboard', grupo: 'General', permiso: 'dashboard.ver' },
    // SGSI Intelligence Assistant: acceso directo desde la navegación principal.
    { to: 'asistente', label: 'SGSI Intelligence Assistant', etiquetaCorta: 'Asistente', icono: 'asistente', grupo: 'General', permiso: 'ia.usar' },
    { to: 'activos.lista', label: 'Activos', icono: 'activos', grupo: 'Gestión de seguridad', permiso: 'activos.ver' },
    { to: 'inventario', label: 'Inventario TI', icono: 'capas', grupo: 'Gestión de seguridad', permiso: 'activos.ver' },
    { to: 'riesgos.lista', label: 'Riesgos', icono: 'riesgos', grupo: 'Gestión de seguridad', permiso: 'riesgos.ver' },
    { to: 'incidentes.lista', label: 'Incidentes', icono: 'incidentes', grupo: 'Gestión de seguridad', permiso: 'incidentes.ver' },
    { to: 'auditorias.lista', label: 'Auditorías', icono: 'auditorias', grupo: 'Gestión de seguridad', permiso: 'auditorias.ver' },
    { to: 'documentos.lista', label: 'Documentos', icono: 'documentos', grupo: 'Gestión de seguridad', permiso: 'documentos.ver' },
    { to: 'cumplimiento', label: 'Cumplimiento ISO', icono: 'cumplimiento', grupo: 'Cumplimiento', permiso: 'cumplimiento.ver' },
    { to: 'reportes', label: 'Reportes', icono: 'reportes', grupo: 'Cumplimiento', permiso: 'dashboard.ver' },
];

export function useNavegacion() {
    const auth = useAuthStore();

    const items = computed(() => {
        const permisos = auth.usuario?.permisos ?? [];
        const visibles = TODOS_LOS_ITEMS.filter((item) => permisos.includes(item.permiso));
        if (permisos.includes('auditorias.ver')) {
            visibles.push({ to: 'acciones-correctivas.lista', label: 'Acciones correctivas', icono: 'acciones', grupo: 'Cumplimiento' });
        }

        // Configuración no usa un permiso de RolesPermisosSeeder — es exclusiva
        // de Administrador vía Gate ('gestionar-configuracion'), igual que en
        // el backend (ConfiguracionPolicy::gestionar() siempre false salvo
        // Gate::before).
        if (auth.esAdministrador) {
            visibles.push({ to: 'configuracion', label: 'Configuración', icono: 'configuracion', grupo: 'Administración' });
            visibles.push({ to: 'usuarios.lista', label: 'Usuarios', icono: 'usuarios', grupo: 'Administración' });
            visibles.push({ to: 'roles.lista', label: 'Roles y permisos', icono: 'roles', grupo: 'Administración' });
            visibles.push({ to: 'bitacora', label: 'Bitácora', icono: 'bitacora', grupo: 'Administración' });
        }

        return visibles;
    });

    // Agrupa conservando el orden original de los módulos.
    const grupos = computed(() => {
        const resultado = [];
        for (const item of items.value) {
            let grupo = resultado.find((g) => g.nombre === item.grupo);
            if (!grupo) {
                grupo = { nombre: item.grupo, items: [] };
                resultado.push(grupo);
            }
            grupo.items.push(item);
        }
        return resultado;
    });

    return { items, grupos };
}
