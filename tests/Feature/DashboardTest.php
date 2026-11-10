<?php

declare(strict_types=1);

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;

it('muestra una vista reducida de kpis para el rol Director', function () {
    $rolDirector = Rol::factory()->create(['nombre' => 'Director']);
    $permiso = Permiso::firstOrCreate(['clave' => 'dashboard.ver'], ['modulo' => 'dashboard']);
    $rolDirector->permisos()->attach($permiso->id);

    $director = Usuario::factory()->create(['rol_id' => $rolDirector->id]);

    $respuesta = $this->actingAs($director)->getJson('/api/v1/dashboard');

    $respuesta->assertOk()
        ->assertJsonPath('data.vista', 'director_ejecutiva')
        ->assertJsonMissingPath('data.documentos_proximos_a_vencer');
});

it('la matriz de riesgo visual devuelve 25 celdas (5x5)', function () {
    $rol = Rol::factory()->create(['nombre' => 'Responsable SGSI']);
    $permiso = Permiso::firstOrCreate(['clave' => 'dashboard.ver'], ['modulo' => 'dashboard']);
    $rol->permisos()->attach($permiso->id);

    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);

    $respuesta = $this->actingAs($usuario)->getJson('/api/v1/dashboard/matriz-riesgo');

    $respuesta->assertOk()->assertJsonCount(25, 'data');
});