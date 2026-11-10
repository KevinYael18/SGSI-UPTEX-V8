<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * El SPA de Vue 3 se compila y se copia a la RAÍZ de public/ (ver
 * Dockerfile, etapa final: COPY --from=frontend-build /frontend/dist
 * ./public), junto al index.php de Laravel — por eso vite.config.js usa
 * base:'/' (default). Cualquier ruta GET que Laravel no reconozca (todo
 * lo que no sea /api/* o /sanctum/*) devuelve ese index.html ya
 * compilado; Vue Router (modo history) resuelve la navegación en el
 * cliente desde ahí.
 */
Route::fallback(function () {
    $index = public_path('index.html');

    if (!file_exists($index)) {
        return response(
            'Frontend no compilado todavía: falta public/index.html (ver Dockerfile, etapa frontend-build).',
            503
        );
    }

    return response(file_get_contents($index), 200)->header('Content-Type', 'text/html; charset=UTF-8');
});
