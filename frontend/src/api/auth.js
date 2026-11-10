import http from './http';

export default {
    login: (email, password) => http.post('/v1/login', { email, password }),
    logout: () => http.post('/v1/logout'),
    me: () => http.get('/v1/me'),
    olvidePassword: (email) => http.post('/v1/olvide-password', { email }),
    restablecerPassword: (payload) => http.post('/v1/restablecer-password', payload),
};