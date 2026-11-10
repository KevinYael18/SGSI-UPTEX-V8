import http from './http';

export default {
    listar: () => http.get('/v1/roles'),
    permisosDisponibles: () => http.get('/v1/permisos'),
    actualizarPermisos: (rolId, permisoIds) => http.put(`/v1/roles/${rolId}`, { permisos: permisoIds }),
};