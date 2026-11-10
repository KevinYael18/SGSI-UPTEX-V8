import http from './http';

export default {
    listarControles: (params) => http.get('/v1/controles-iso', { params }),
    actualizarEstado: (id, estado_implementacion) => http.patch(`/v1/controles-iso/${id}/estado`, { estado_implementacion }),
    resumen: () => http.get('/v1/cumplimiento/resumen'),
};