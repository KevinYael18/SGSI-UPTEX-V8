import http from './http';

export default {
    listar: (params) => http.get('/v1/usuarios', { params }),
    crear: (datos) => http.post('/v1/usuarios', datos),
    actualizar: (uuid, datos) => http.put(`/v1/usuarios/${uuid}`, datos),
    suspender: (uuid, motivo = null) => http.patch(`/v1/usuarios/${uuid}/suspender`, motivo ? { motivo } : {}),
};