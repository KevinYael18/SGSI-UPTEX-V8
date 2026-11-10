import http from './http';

export default {
    departamentos: () => http.get('/v1/catalogos/departamentos'),
    ubicaciones: (departamentoId) => http.get('/v1/catalogos/ubicaciones', {
        params: departamentoId ? { departamento_id: departamentoId } : {},
    }),
    usuarios: () => http.get('/v1/catalogos/usuarios'),
    activos: () => http.get('/v1/catalogos/activos'),
    amenazas: () => http.get('/v1/catalogos/amenazas'),
    vulnerabilidades: () => http.get('/v1/catalogos/vulnerabilidades'),
};