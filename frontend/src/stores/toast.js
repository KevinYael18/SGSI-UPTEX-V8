import { defineStore } from 'pinia';

let contador = 0;

export const useToastStore = defineStore('toast', {
    state: () => ({ items: [] }),
    actions: {
        mostrar(mensaje, tipo = 'info', duracionMs = 4000) {
            const id = ++contador;
            // `duracion` solo se usa para la barra visual de tiempo restante.
            this.items.push({ id, mensaje, tipo, duracion: duracionMs });
            setTimeout(() => this.cerrar(id), duracionMs);
        },
        exito(mensaje) { this.mostrar(mensaje, 'exito'); },
        error(mensaje) { this.mostrar(mensaje, 'error'); },
        cerrar(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    },
});