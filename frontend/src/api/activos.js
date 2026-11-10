import http from './http';

export default {
    listar: (params) => http.get('/v1/activos', { params }),
    obtener: (uuid) => http.get(`/v1/activos/${uuid}`),
    crear: (datos) => http.post('/v1/activos', datos),
    actualizar: (uuid, datos) => http.put(`/v1/activos/${uuid}`, datos),
    eliminar: (uuid) => http.delete(`/v1/activos/${uuid}`),
    qrUrl: (uuid) => `${http.defaults.baseURL}/v1/activos/${uuid}/qr`,
};