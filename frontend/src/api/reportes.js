import http from './http';

export default {
    urlActivosPdf: () => `${http.defaults.baseURL}/v1/reportes/activos.pdf`,
    urlActivosExcel: () => `${http.defaults.baseURL}/v1/reportes/activos.xlsx`,
    urlRiesgosPdf: () => `${http.defaults.baseURL}/v1/reportes/riesgos.pdf`,
    urlRiesgosExcel: () => `${http.defaults.baseURL}/v1/reportes/riesgos.xlsx`,
    urlIndicadoresPdf: () => `${http.defaults.baseURL}/v1/reportes/indicadores.pdf`,
    urlIndicadoresExcel: () => `${http.defaults.baseURL}/v1/reportes/indicadores.xlsx`,
};