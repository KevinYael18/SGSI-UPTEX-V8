import http from './http';

export default {
    listar: (params) => http.get('/v1/auditorias', { params }),
    obtener: (id) => http.get(`/v1/auditorias/${id}`),
    crear: (datos) => http.post('/v1/auditorias', datos),
    actualizar: (id, datos) => http.put(`/v1/auditorias/${id}`, datos),
    eliminar: (id) => http.delete(`/v1/auditorias/${id}`),
    registrarHallazgo: (auditoriaId, datos) => http.post(`/v1/auditorias/${auditoriaId}/hallazgos`, datos),
    cerrarNoConformidad: (noConformidadId) => http.patch(`/v1/no-conformidades/${noConformidadId}/cerrar`),
};