import http from './http';

export default {
    listar: (tipo, id) => http.get(`/v1/${tipo}/${id}/comentarios`),
    crear: (tipo, id, contenido) => http.post(`/v1/${tipo}/${id}/comentarios`, { contenido }),
    eliminar: (comentarioId) => http.delete(`/v1/comentarios/${comentarioId}`),
};