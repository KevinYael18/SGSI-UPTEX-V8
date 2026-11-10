<?php

declare(strict_types=1);

use App\Models\ControlIso;
use App\Models\Rol;
use App\Models\Usuario;

it('actualiza el estado de un control y registra bitacora', function () {
    $rol = Rol::factory()->create(['nombre' => 'Administrador']);
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $control = ControlIso::query()->create([
        'codigo' => 'A.5.1', 'categoria' => 'organizacional', 'nombre' => 'Políticas de seguridad',
    ]);

    $respuesta = $this->actingAs($usuario)->patchJson("/api/v1/controles-iso/{$control->id}/estado", [
        'estado_implementacion' => 'implementado',
    ]);

    $respuesta->assertOk()->assertJsonPath('data.estado_implementacion', 'implementado');

    $this->assertDatabaseHas('activity_log', ['entidad' => 'controles_iso', 'entidad_id' => $control->id]);
});