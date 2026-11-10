import http from './http';

export default {
    listar: () => http.get('/v1/configuracion'),
    actualizar: (valores) => http.put('/v1/configuracion', { valores }),
};