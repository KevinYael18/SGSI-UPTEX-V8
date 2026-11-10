<?php

declare(strict_types=1);

use App\Models\Departamento;
use App\Models\Riesgo;
use App\Models\Rol;
use App\Models\Usuario;

beforeEach(function () {
    $this->rolAdmin = Rol::factory()->create(['nombre' => 'Administrador']);
    $this->admin = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);
    $this->departamento = Departamento::factory()->create();
});

it('crea un activo autenticado y calcula su criticidad', function () {
    $respuesta = $this->actingAs($this->admin)->postJson('/api/v1/activos', [
        'nombre' => 'Servidor de Base de Datos Institucional',
        'tipo' => 'hardware',
        'confidencialidad' => 5,
        'integridad' => 4,
        'disponibilidad' => 3,
        'departamento_id' => $this->departamento->id,
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.criticidad_calculada', 5)
        ->assertJsonPath('data.cia.confidencialidad', 5);
});

it('rechaza crear activo sin autenticación', function () {
    $this->postJson('/api/v1/activos', ['nombre' => 'X'])->assertUnauthorized();
});

it('no permite eliminar un activo con riesgos abiertos', function () {
    $activo = \App\Models\Activo::factory()->create(['departamento_id' => $this->departamento->id]);
    Riesgo::factory()->create(['activo_id' => $activo->id]);

    $respuesta = $this->actingAs($this->admin)->deleteJson("/api/v1/activos/{$activo->uuid}");

    $respuesta->assertStatus(409)
        ->assertJsonPath('error.code', 'ACTIVO_CON_RIESGOS_ABIERTOS');
});