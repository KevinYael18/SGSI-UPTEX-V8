import http from './http';

export default {
    buscar: (q) => http.get('/v1/busqueda', { params: { q } }),
};