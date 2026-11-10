<?php

declare(strict_types=1);

use App\Models\Activo;
use App\Models\Departamento;
use App\Models\Rol;
use App\Models\Usuario;

it('encuentra activos por nombre en la busqueda global', function () {
    $rol = Rol::factory()->create(['nombre' => 'Administrador']);
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    Activo::factory()->create(['nombre' => 'Servidor de Correo Institucional', 'departamento_id' => Departamento::factory()]);

    $respuesta = $this->actingAs($usuario)->getJson('/api/v1/busqueda?q=Correo');

    $respuesta->assertOk()->assertJsonFragment(['modulo' => 'activo']);
});