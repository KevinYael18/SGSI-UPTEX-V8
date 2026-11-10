import { defineStore } from 'pinia';
import authApi from '../api/auth';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        usuario: null,
        cargando: false,
        listo: false, // ya se intentó resolver la sesión al menos una vez
    }),
    getters: {
        autenticado: (state) => state.usuario !== null,
        rol: (state) => state.usuario?.rol ?? null,
        // Permisos finos viven en el backend (Policies); en el frontend solo
        // se usa el rol para decidir qué MOSTRAR, nunca para autorizar de
        // verdad — cada acción vuelve a validarse en el servidor.
        esAdministrador: (state) => state.usuario?.rol === 'Administrador',
    },
    actions: {
        async iniciarSesion(email, password) {
            this.cargando = true;
            try {
                const { data } = await authApi.login(email, password);
                this.usuario = data.data;
                return this.usuario;
            } finally {
                this.cargando = false;
            }
        },
        async cerrarSesion() {
            try {
                await authApi.logout();
            } finally {
                this.usuario = null;
            }
        },
        /**
         * Sincroniza los datos visibles del usuario (nombre, foto…) tras
         * editarlos en «Mi perfil». Al ser estado reactivo de Pinia, el menú
         * superior, el dashboard y demás componentes se actualizan al instante,
         * sin cerrar sesión. Solo se copian campos de presentación conocidos.
         */
        sincronizarPerfil(perfil) {
            if (!this.usuario || !perfil) return;
            const campos = ['nombre', 'email', 'foto_url', 'ultimo_acceso', 'estado'];
            for (const campo of campos) {
                if (campo in perfil) this.usuario[campo] = perfil[campo];
            }
        },
        async resolverSesion() {
            try {
                const { data } = await authApi.me();
                this.usuario = data.data;
            } catch {
                this.usuario = null;
            } finally {
                this.listo = true;
            }
        },
    },
});