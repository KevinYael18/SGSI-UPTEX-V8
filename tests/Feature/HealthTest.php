<?php

declare(strict_types=1);

/** El healthcheck de Docker consulta exactamente /api/health. */
it('expone el estado del sistema en /api/health como JSON', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonStructure(['estado_bd', 'estado_cola', 'estado_storage']);
});

it('responde 404 JSON (no el HTML del SPA) a rutas de API inexistentes', function () {
    $this->getJson('/api/no-existe')->assertNotFound()->assertJsonPath('error.code', 'RECURSO_NO_ENCONTRADO');
});
