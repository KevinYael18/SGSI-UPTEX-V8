import http from './http';

export default {
    listar: (params) => http.get('/v1/bitacora', { params }),
    entidadesDisponibles: () => http.get('/v1/bitacora/entidades'),
};