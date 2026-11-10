import { defineStore } from 'pinia';

let contador = 0;

export const useToastStore = defineStore('toast', {
    state: () => ({ items: [] }),
    actions: {
        mostrar(mensaje, tipo = 'info', duracionMs = 4000) {
            const id = ++contador;
            this.items.push({ id, mensaje, tipo });
            setTimeout(() => this.cerrar(id), duracionMs);
        },
        exito(mensaje) { this.mostrar(mensaje, 'exito'); },
        error(mensaje) { this.mostrar(mensaje, 'error'); },
        cerrar(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    },
});