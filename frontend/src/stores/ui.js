import { defineStore } from 'pinia';

const CLAVE_TEMA = 'sgsi_tema';

export const useUiStore = defineStore('ui', {
    state: () => ({
        tema: localStorage.getItem(CLAVE_TEMA) || 'light',
        sidebarColapsado: false,
        // Solo presentación: menú lateral tipo "drawer" en tablets y celulares.
        menuMovilAbierto: false,
    }),
    actions: {
        aplicarTema() {
            document.documentElement.setAttribute('data-theme', this.tema);
        },
        alternarTema() {
            this.tema = this.tema === 'light' ? 'dark' : 'light';
            localStorage.setItem(CLAVE_TEMA, this.tema);
            this.aplicarTema();
        },
        alternarSidebar() {
            this.sidebarColapsado = !this.sidebarColapsado;
        },
        abrirMenuMovil() {
            this.menuMovilAbierto = true;
        },
        cerrarMenuMovil() {
            this.menuMovilAbierto = false;
        },
    },
});
