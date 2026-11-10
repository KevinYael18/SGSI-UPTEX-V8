import http from './http';

export default {
    listar: (params) => http.get('/v1/riesgos', { params }),
    crear: (datos) => http.post('/v1/riesgos', datos),
    actualizar: (id, datos) => http.put(`/v1/riesgos/${id}`, datos),
    eliminar: (id) => http.delete(`/v1/riesgos/${id}`),
    impactoSugerido: (activoId) => http.get('/v1/riesgos/impacto-sugerido', { params: { activo_id: activoId } }),
    sugerencia: (params) => http.get('/v1/riesgos/sugerencia', { params }),
};
