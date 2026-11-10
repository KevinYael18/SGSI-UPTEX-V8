import http from './http';

export default {
    listar: (params) => http.get('/v1/documentos', { params }),
    obtener: (uuid) => http.get(`/v1/documentos/${uuid}`),
    crear: (formData) => http.post('/v1/documentos', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    nuevaVersion: (uuid, formData) => http.post(`/v1/documentos/${uuid}/versiones`, formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    cambiarEstado: (uuid, estado) => http.patch(`/v1/documentos/${uuid}/estado`, { estado }),
};