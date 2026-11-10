import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Build de producción: el resultado (dist/) se copia a la RAÍZ de
// public/ dentro de la imagen Docker (ver Dockerfile, etapa final:
// COPY --from=frontend-build /frontend/dist ./public), junto al
// index.php de Laravel — Apache lo sirve como estático, sin contenedor
// Node en producción. Por eso `base` es '/' (default de Vite), no
// '/build/'.
export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
    },
    server: {
        port: 5173,
        proxy: {
            // Durante `npm run dev` (fuera de Docker), reenvía las llamadas de
            // la API y la cookie CSRF de Sanctum al backend ya levantado con
            // `docker compose up`. En producción esto no aplica: Apache sirve
            // ambos desde el mismo origen (ver Dockerfile).
            '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
            '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: true },
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        css: false,
    },
});