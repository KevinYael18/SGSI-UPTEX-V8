<?php

declare(strict_types=1);

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Usuario;

beforeEach(function () {
    $this->rolAdmin = Rol::factory()->create(['nombre' => 'Administrador']);
    $this->admin = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);
});

it('planea una auditoria', function () {
    $auditor = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);

    $respuesta = $this->actingAs($this->admin)->postJson('/api/v1/auditorias', [
        'alcance' => 'Controles de acceso - TI',
        'fecha_inicio' => now()->toDateString(),
        'fecha_fin' => now()->addWeek()->toDateString(),
        'auditor_id' => $auditor->id,
    ]);

    $respuesta->assertCreated()->assertJsonPath('data.alcance', 'Controles de acceso - TI');
});

it('registra un hallazgo con no conformidad y accion correctiva en cascada', function () {
    $auditoria = Auditoria::factory()->create();
    $responsable = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);

    $respuesta = $this->actingAs($this->admin)->postJson("/api/v1/auditorias/{$auditoria->id}/hallazgos", [
        'severidad' => 'mayor',
        'descripcion' => 'Contraseñas compartidas en laboratorio de cómputo',
        'no_conformidad' => [
            'accion_correctiva' => [
                'responsable_id' => $responsable->id,
                'fecha_compromiso' => now()->addDays(10)->toDateString(),
            ],
        ],
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.severidad', 'mayor')
        ->assertJsonCount(1, 'data.no_conformidades')
        ->assertJsonCount(1, 'data.no_conformidades.0.acciones_correctivas');
});

it('no cierra una no conformidad con acciones correctivas pendientes', function () {
    $auditoria = Auditoria::factory()->create();
    $hallazgo = $auditoria->hallazgos()->create(['severidad' => 'mayor', 'descripcion' => 'x']);
    $noConformidad = $hallazgo->noConformidades()->create(['estado' => 'abierta']);
    $noConformidad->accionesCorrectivas()->create([
        'responsable_id' => $this->admin->id,
        'fecha_compromiso' => now()->addDays(5),
        'estado' => 'abierta',
    ]);

    $respuesta = $this->actingAs($this->admin)->patchJson("/api/v1/no-conformidades/{$noConformidad->id}/cerrar");

    $respuesta->assertStatus(422)->assertJsonPath('error.code', 'ACCION_CORRECTIVA_VENCIDA');
});