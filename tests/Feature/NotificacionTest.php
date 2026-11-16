<?php

declare(strict_types=1);

use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\Usuario;

it('lista solo las notificaciones propias y permite marcarlas leidas', function () {
    $rol = Rol::factory()->create(['nombre' => 'Director']);
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $otro = Usuario::factory()->create(['rol_id' => $rol->id]);

    $propia = Notificacion::query()->create(['usuario_id' => $usuario->id, 'tipo' => 'x', 'datos' => [], 'leida' => false]);
    Notificacion::query()->create(['usuario_id' => $otro->id, 'tipo' => 'x', 'datos' => [], 'leida' => false]);

    $this->actingAs($usuario)->getJson('/api/v1/notificaciones')->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($usuario)->patchJson("/api/v1/notificaciones/{$propia->id}/leida")
        ->assertOk()->assertJsonPath('data.leida', true);
});

it('no permite marcar como leida una notificacion ajena', function () {
    // (Antes usaba withoutExceptionHandling(), que impide que la denegación se
    // convierta en 403 y hacía fallar la prueba aunque la regla funcionaba.)

    $rol = Rol::factory()->create(['nombre' => 'Director']);
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $otro = Usuario::factory()->create(['rol_id' => $rol->id]);
    $ajena = Notificacion::query()->create(['usuario_id' => $otro->id, 'tipo' => 'x', 'datos' => [], 'leida' => false]);

    $this->actingAs($usuario)->patchJson("/api/v1/notificaciones/{$ajena->id}/leida")->assertForbidden();
});