<?php

declare(strict_types=1);

use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Departamento;
use App\Models\MatrizRiesgo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;

beforeEach(function () {
    $this->rolAdmin = Rol::factory()->create(['nombre' => 'Administrador']);
    $this->admin = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);
    $this->activo = Activo::factory()->create(['departamento_id' => Departamento::factory()]);
    $this->amenaza = Amenaza::factory()->create();
    $this->vulnerabilidad = Vulnerabilidad::factory()->create();
});

it('calcula el nivel de riesgo consultando la matriz configurable en BD', function () {
    MatrizRiesgo::query()->create(['probabilidad' => 5, 'impacto' => 5, 'nivel_resultante' => 'critico']);

    $respuesta = $this->actingAs($this->admin)->postJson('/api/v1/riesgos', [
        'activo_id' => $this->activo->id,
        'amenaza_id' => $this->amenaza->id,
        'vulnerabilidad_id' => $this->vulnerabilidad->id,
        'probabilidad' => 5,
        'impacto' => 5,
        'tipo_tratamiento' => 'mitigar',
    ]);

    $respuesta->assertCreated()->assertJsonPath('data.nivel_calculado', 'critico');
});

it('devuelve 422 si la combinación probabilidad/impacto no está en la matriz', function () {
    $respuesta = $this->actingAs($this->admin)->postJson('/api/v1/riesgos', [
        'activo_id' => $this->activo->id,
        'amenaza_id' => $this->amenaza->id,
        'vulnerabilidad_id' => $this->vulnerabilidad->id,
        'probabilidad' => 2,
        'impacto' => 2,
    ]);

    $respuesta->assertStatus(422)->assertJsonPath('error.code', 'RIESGO_MATRIZ_NO_CONFIGURADA');
});