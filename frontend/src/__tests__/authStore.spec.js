import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../stores/auth';

describe('store de auth', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('no está autenticado ni es administrador sin sesión', () => {
        const store = useAuthStore();
        expect(store.autenticado).toBe(false);
        expect(store.esAdministrador).toBe(false);
        expect(store.rol).toBeNull();
    });

    it('autenticado y rol reflejan al usuario ya cargado', () => {
        const store = useAuthStore();
        store.usuario = { nombre: 'Ana', rol: 'Responsable SGSI' };
        expect(store.autenticado).toBe(true);
        expect(store.rol).toBe('Responsable SGSI');
        expect(store.esAdministrador).toBe(false);
    });

    it('esAdministrador solo es true para el rol exacto Administrador', () => {
        const store = useAuthStore();
        store.usuario = { nombre: 'Root', rol: 'Administrador' };
        expect(store.esAdministrador).toBe(true);
    });

    it('cerrarSesion limpia el usuario incluso si la petición al backend falla', async () => {
        const store = useAuthStore();
        store.usuario = { nombre: 'Ana', rol: 'Director' };
        // authApi.logout() intentará una petición HTTP real; en este entorno
        // de test (sin backend) fallará, y el finally debe limpiar igual.
        await store.cerrarSesion().catch(() => {});
        expect(store.usuario).toBeNull();
    });
});