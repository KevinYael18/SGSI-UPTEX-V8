import http from './http';

export default {
    listar: (params) => http.get('/v1/notificaciones', { params }),
    contarNoLeidas: () => http.get('/v1/notificaciones/no-leidas/conteo'),
    marcarLeida: (id) => http.patch(`/v1/notificaciones/${id}/leida`),
    marcarTodasLeidas: () => http.post('/v1/notificaciones/marcar-todas-leidas'),
};