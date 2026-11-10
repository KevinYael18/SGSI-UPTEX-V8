import http from './http';

export default {
    kpis: () => http.get('/v1/dashboard'),
    matrizRiesgo: () => http.get('/v1/dashboard/matriz-riesgo'),
    graficas: () => http.get('/v1/dashboard/graficas'),
};