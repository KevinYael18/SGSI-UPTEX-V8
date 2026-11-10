import axios from 'axios';

/**
 * Cliente Axios único para toda la SPA. Sanctum modo SPA (cookie + CSRF,
 * decisión Fase 1.5 §1) — NUNCA se guarda un token en localStorage.
 * `withCredentials` + `withXSRFToken` hacen que Axios adjunte
 * automáticamente la cookie XSRF-TOKEN como header en cada mutación.
 */
const http = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: { Accept: 'application/json' },
});

/**
 * Laravel Sanctum requiere pedir la cookie CSRF antes del primer POST/PUT/
 * PATCH/DELETE de una sesión. Se cachea la promesa para no repetir la
 * llamada si varias peticiones mutantes salen casi al mismo tiempo.
 */
let csrfPromise = null;
export function asegurarCookieCsrf() {
    if (!csrfPromise) {
        csrfPromise = http.get('/sanctum/csrf-cookie', { baseURL: import.meta.env.VITE_APP_URL || '' });
    }
    return csrfPromise;
}

http.interceptors.request.use(async (config) => {
    const metodosMutantes = ['post', 'put', 'patch', 'delete'];
    if (metodosMutantes.includes((config.method || '').toLowerCase())) {
        await asegurarCookieCsrf();
    }
    return config;
});

/**
 * Traduce el formato uniforme de error del backend
 * ({ error: { code, message, details } }, ver Handler.php) a un objeto
 * consistente que toda la UI puede leer sin conocer la forma cruda de Axios.
 * Si por algún motivo la respuesta no trae ese formato (o no llegó
 * respuesta del todo), se usa un mensaje específico según el código HTTP
 * en vez de asumir siempre "problema de conexión" — un 403/401 NO es lo
 * mismo que no poder llegar al servidor.
 */
const MENSAJES_POR_ESTADO = {
    401: 'Debe iniciar sesión para continuar.',
    403: 'No tiene permisos para realizar esta acción.',
    404: 'El recurso solicitado no existe.',
    419: 'Su sesión expiró. Vuelva a iniciar sesión.',
    429: 'Demasiadas solicitudes. Intente de nuevo en unos minutos.',
    500: 'Ocurrió un error inesperado en el servidor.',
};

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const backend = error.response?.data?.error;
        const status = error.response?.status ?? 0;
        const normalizado = {
            status,
            code: backend?.code ?? 'ERROR_RED',
            message: backend?.message
                ?? MENSAJES_POR_ESTADO[status]
                ?? 'No se pudo conectar con el servidor. Verifique su conexión.',
            details: backend?.details ?? {},
        };
        return Promise.reject(normalizado);
    }
);

export default http;