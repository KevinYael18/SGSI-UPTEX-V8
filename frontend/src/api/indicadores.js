import http from './http';

export default {
    resumen: () => http.get('/v1/indicadores'),
};