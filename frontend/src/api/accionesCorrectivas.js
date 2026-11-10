import http from './http';

export default {
    listar: (params) => http.get('/v1/acciones-correctivas', { params }),
    cerrar: (id) => http.patch(`/v1/acciones-correctivas/${id}/cerrar`),
};