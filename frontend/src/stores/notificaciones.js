import { defineStore } from 'pinia';
import notificacionesApi from '../api/notificaciones';

export const useNotificacionesStore = defineStore('notificaciones', {
    state: () => ({
        noLeidas: 0,
        intervalId: null,
    }),
    actions: {
        async refrescarConteo() {
            try {
                const { data } = await notificacionesApi.contarNoLeidas();
                this.noLeidas = data.data.no_leidas;
            } catch {
                // silencioso: el conteo de notificaciones no debe interrumpir la navegación
            }
        },
        iniciarSondeo() {
            this.refrescarConteo();
            if (this.intervalId) return;
            // Sondeo simple cada 30s — suficiente para una app institucional,
            // sin necesitar WebSockets/Reverb para este alcance.
            this.intervalId = setInterval(() => this.refrescarConteo(), 30000);
        },
        detenerSondeo() {
            clearInterval(this.intervalId);
            this.intervalId = null;
        },
    },
});