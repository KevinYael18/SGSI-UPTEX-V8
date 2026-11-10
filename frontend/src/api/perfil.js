import http from './http';

// Módulo «Mi perfil»: todas las rutas operan sobre el usuario autenticado;
// no se envía ningún identificador de usuario.
export default {
    obtener: () => http.get('/v1/perfil'),
    actualizar: (datos) => http.put('/v1/perfil', { nombre: datos.nombre }),
    subirFoto: (archivo, onProgreso) => {
        const formulario = new FormData();
        formulario.append('foto', archivo);
        return http.post('/v1/perfil/foto', formulario, {
            onUploadProgress: (e) => onProgreso?.(e.total ? Math.round((e.loaded / e.total) * 100) : 0),
        });
    },
    eliminarFoto: () => http.delete('/v1/perfil/foto'),
    cambiarPassword: (datos) => http.put('/v1/perfil/password', {
        password_actual: datos.password_actual,
        password: datos.password,
        password_confirmation: datos.password_confirmation,
    }),
};
