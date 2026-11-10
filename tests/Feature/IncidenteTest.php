<?php

declare(strict_types=1);

use App\Models\Activo;
use App\Models\Departamento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->rolAdmin = Rol::factory()->create(['nombre' => 'Administrador']);
    $this->admin = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);
    $this->activo = Activo::factory()->create(['departamento_id' => Departamento::factory()]);
});

it('registra un incidente con evidencia adjunta y valida su hash', function () {
    $archivo = UploadedFile::fake()->image('evidencia.jpg');

    $respuesta = $this->actingAs($this->admin)->postJson('/api/v1/incidentes', [
        'activo_id' => $this->activo->id,
        'titulo' => 'Acceso no autorizado detectado',
        'fecha_deteccion' => now()->toDateTimeString(),
        'evidencia' => $archivo,
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.titulo', 'Acceso no autorizado detectado')
        ->assertJsonCount(1, 'data.evidencias');
});

it('no permite transición de estado inválida', function () {
    $incidente = \App\Models\Incidente::factory()->create([
        'activo_id' => $this->activo->id,
        'reportado_por' => $this->admin->id,
        'estado' => 'cerrado',
    ]);

    $respuesta = $this->actingAs($this->admin)
        ->patchJson("/api/v1/incidentes/{$incidente->uuid}/estado", ['estado' => 'en_analisis']);

    $respuesta->assertStatus(422)->assertJsonPath('error.code', 'TRANSICION_ESTADO_INVALIDA');
});