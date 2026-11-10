import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useToastStore } from '../stores/toast';

describe('store de toast', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });

    it('agrega un mensaje y lo retira automáticamente pasado el tiempo', () => {
        const store = useToastStore();
        store.exito('Guardado correctamente');
        expect(store.items).toHaveLength(1);
        expect(store.items[0].tipo).toBe('exito');

        vi.advanceTimersByTime(4001);
        expect(store.items).toHaveLength(0);
    });

    it('permite varios mensajes simultáneos con ids distintos', () => {
        const store = useToastStore();
        store.error('Falló A');
        store.error('Falló B');
        expect(store.items).toHaveLength(2);
        expect(store.items[0].id).not.toBe(store.items[1].id);
    });
});