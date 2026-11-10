import http from './http';

export default {
    listar: (params) => http.get('/v1/incidentes', { params }),
    obtener: (uuid) => http.get(`/v1/incidentes/${uuid}`),
    crear: (formData) => http.post('/v1/incidentes', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    actualizar: (uuid, datos) => http.put(`/v1/incidentes/${uuid}`, datos),
    eliminar: (uuid) => http.delete(`/v1/incidentes/${uuid}`),
    cambiarEstado: (uuid, estado) => http.patch(`/v1/incidentes/${uuid}/estado`, { estado }),
};