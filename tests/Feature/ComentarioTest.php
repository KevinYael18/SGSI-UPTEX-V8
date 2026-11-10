<?php

declare(strict_types=1);

use App\Models\Departamento;
use App\Models\Incidente;
use App\Models\Rol;
use App\Models\Usuario;

it('agrega y lista comentarios internos de un incidente', function () {
    $rol = Rol::factory()->create(['nombre' => 'Administrador']);
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $incidente = Incidente::factory()->create();

    $this->actingAs($usuario)
        ->postJson("/api/v1/incidentes/{$incidente->uuid}/comentarios", ['contenido' => 'Se contactó al proveedor.'])
        ->assertCreated();

    $this->actingAs($usuario)
        ->getJson("/api/v1/incidentes/{$incidente->uuid}/comentarios")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rechaza tipos de entidad no registrados en la whitelist', function () {
    $rol = Rol::factory()->create(['nombre' => 'Administrador']);
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);

    $this->actingAs($usuario)
        ->getJson('/api/v1/usuarios/1/comentarios')
        ->assertNotFound();
});