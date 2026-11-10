import http from './http';

export default {
    obtener: (tipo, id) => http.get(`/v1/${tipo}/${id}/timeline`),
};