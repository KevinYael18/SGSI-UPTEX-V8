<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Computadora;
use App\Models\ControlIso;
use App\Models\Departamento;
use App\Models\MatrizRiesgo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Software;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;
use Illuminate\Http\UploadedFile;

/**
 * Inventario tecnológico: edificios -> laboratorios -> computadoras
 * individuales, cada una como Activo (riesgos, controles ISO y bitácora
 * reutilizados), catálogo de software, mantenimiento, importación y exportación.
 */
beforeEach(function () {
    $this->admin = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['nombre' => 'Administrador'])->id]);
    $this->departamento = Departamento::factory()->create();
    $this->control = ControlIso::query()->create(['codigo' => 'A.8.7', 'nombre' => 'Protección contra malware', 'categoria' => 'tecnologico', 'estado_implementacion' => 'implementado']);
});

function crearLaboratorio(string $edificio = 'Edificio B', string $laboratorio = 'Laboratorio 1'): array
{
    $ed = test()->actingAs(test()->admin)->postJson('/api/v1/inventario/edificios', ['nombre' => $edificio])->assertCreated()->json('data');
    $lab = test()->postJson('/api/v1/inventario/laboratorios', [
        'edificio_id' => $ed['id'], 'departamento_id' => test()->departamento->id, 'nombre' => $laboratorio, 'capacidad' => 30,
    ])->assertCreated()->json('data');

    return [$ed, $lab];
}

function datosEquipo(int $labId, array $extra = []): array
{
    return array_merge([
        'laboratorio_id' => $labId,
        'nombre_equipo' => 'LAB1-PC01',
        'numero_inventario' => 'UPTEX-0001',
        'numero_serie' => 'SN-AAA-001',
        'direccion_mac' => '001a2b3c4d5e',
        'direccion_ip' => '192.168.10.21',
        'estado_operativo' => 'operativo',
        'procesador' => 'Intel Core i5-10400',
        'ram_gb' => 16,
        'so_nombre' => 'Windows 11',
        'so_version' => '23H2',
        'antivirus_nombre' => 'Antivirus institucional',
        'antivirus_instalacion' => 'instalado',
        'antivirus_proteccion' => 'activa',
        'antivirus_ultima_actualizacion' => now()->toDateString(),
        'confidencialidad' => 2, 'integridad' => 3, 'disponibilidad' => 4,
    ], $extra);
}

it('crea un laboratorio dentro de un edificio y lo registra como ubicación', function () {
    [$ed, $lab] = crearLaboratorio();

    expect($lab['edificio_id'])->toBe($ed['id']);
    $ubicacion = Ubicacion::query()->find(\App\Models\Laboratorio::find($lab['id'])->ubicacion_id);
    expect($ubicacion->nombre)->toBe('Laboratorio 1')->and($ubicacion->edificio)->toBe('Edificio B');
});

it('registra cada computadora como un activo de hardware individual con sus controles ISO', function () {
    [, $lab] = crearLaboratorio();

    $resp = $this->postJson('/api/v1/computadoras', datosEquipo($lab['id'], ['controles_iso_ids' => [$this->control->id]]))
        ->assertCreated()
        ->assertJsonPath('data.direccion_mac', '00:1A:2B:3C:4D:5E')
        ->assertJsonPath('data.laboratorio.nombre', 'Laboratorio 1');

    $computadora = Computadora::query()->where('uuid', $resp->json('data.uuid'))->firstOrFail();
    expect($computadora->activo->tipo->value)->toBe('hardware')
        ->and($computadora->activo->nombre)->toBe('LAB1-PC01')
        ->and($computadora->activo->criticidad_calculada)->toBe(4)
        ->and($computadora->activo->ubicacion_id)->toBe(\App\Models\Laboratorio::find($lab['id'])->ubicacion_id)
        ->and($computadora->activo->controlesIso->pluck('codigo')->all())->toBe(['A.8.7']);

    // Segundo equipo con el mismo número de inventario: rechazado.
    $this->postJson('/api/v1/computadoras', datosEquipo($lab['id'], ['nombre_equipo' => 'LAB1-PC02', 'numero_serie' => 'X', 'direccion_mac' => null]))
        ->assertStatus(422)->assertJsonPath('error.details.numero_inventario.0', 'Ya existe un equipo con ese número de inventario.');
});

it('vincula una ficha de computadora a un activo de hardware existente sin duplicarlo', function () {
    [, $lab] = crearLaboratorio();
    $activo = Activo::factory()->create(['tipo' => 'hardware', 'departamento_id' => $this->departamento->id]);
    $totalActivos = Activo::query()->count();

    $this->postJson('/api/v1/computadoras', ['activo_id' => $activo->id, 'laboratorio_id' => $lab['id'], 'nombre_equipo' => 'PC existente'])
        ->assertCreated();

    expect(Activo::query()->count())->toBe($totalActivos)
        ->and($activo->fresh()->computadora)->not->toBeNull();

    $software = Activo::factory()->create(['tipo' => 'software', 'departamento_id' => $this->departamento->id]);
    $this->postJson('/api/v1/computadoras', ['activo_id' => $software->id, 'nombre_equipo' => 'X'])->assertStatus(422);
});

it('calcula los indicadores del laboratorio con los datos reales', function () {
    [, $lab] = crearLaboratorio();
    $this->postJson('/api/v1/computadoras', datosEquipo($lab['id']))->assertCreated();
    $this->postJson('/api/v1/computadoras', datosEquipo($lab['id'], [
        'nombre_equipo' => 'LAB1-PC02', 'numero_inventario' => 'UPTEX-0002', 'numero_serie' => 'SN-2', 'direccion_mac' => null,
        'estado_operativo' => 'mantenimiento', 'antivirus_ultima_actualizacion' => now()->subDays(40)->toDateString(), 'so_nombre' => 'Ubuntu', 'so_version' => '22.04',
    ]))->assertCreated();
    $this->postJson('/api/v1/computadoras', datosEquipo($lab['id'], [
        'nombre_equipo' => 'LAB1-PC03', 'numero_inventario' => 'UPTEX-0003', 'numero_serie' => 'SN-3', 'direccion_mac' => null,
        'estado_operativo' => 'fuera_servicio', 'antivirus_instalacion' => 'desconocido',
    ]))->assertCreated();

    $resumen = $this->getJson("/api/v1/inventario/laboratorios/{$lab['id']}")->assertOk()->json('data.resumen');

    expect($resumen['total'])->toBe(3)
        ->and($resumen['por_estado'])->toMatchArray(['operativo' => 1, 'mantenimiento' => 1, 'fuera_servicio' => 1])
        ->and($resumen['antivirus'])->toBe(['actualizado' => 1, 'desactualizado' => 1, 'sin_informacion' => 1])
        ->and($resumen['sistemas_operativos'])->toBe(['Windows 11 23H2' => 2, 'Ubuntu 22.04' => 1]);
});

it('gestiona el software instalado y detecta versiones desactualizadas sin presumir licencias ilegales', function () {
    [, $lab] = crearLaboratorio();
    $uuid = $this->postJson('/api/v1/computadoras', datosEquipo($lab['id']))->json('data.uuid');
    $chrome = $this->postJson('/api/v1/software', ['nombre' => 'Google Chrome', 'fabricante' => 'Google', 'categoria' => 'navegador', 'version_vigente' => '120.0'])
        ->assertCreated()->json('data');
    expect($chrome['estado_autorizacion'] ?? Software::find($chrome['id'])->estado_autorizacion)->toBe('pendiente_verificacion');

    $ficha = $this->postJson("/api/v1/computadoras/{$uuid}/software", ['software_id' => $chrome['id'], 'version' => '118.0.5993'])
        ->assertOk()->json('data');
    expect($ficha['software'][0]['desactualizado'])->toBeTrue()
        ->and($ficha['software'][0]['estado_licencia'])->toBe('pendiente_verificacion')
        ->and($ficha['software_pendiente'])->toBe(1);

    $ficha = $this->putJson("/api/v1/computadoras/{$uuid}/software/{$chrome['id']}", ['version' => '121.0'])->assertOk()->json('data');
    expect($ficha['software'][0]['desactualizado'])->toBeFalse();

    // No se duplica la instalación ni se puede borrar del catálogo un programa en uso.
    $this->postJson("/api/v1/computadoras/{$uuid}/software", ['software_id' => $chrome['id']])->assertStatus(422);
    $this->deleteJson("/api/v1/software/{$chrome['id']}")->assertStatus(422);

    $this->deleteJson("/api/v1/computadoras/{$uuid}/software/{$chrome['id']}")->assertOk()->assertJsonCount(0, 'data.software');
});

it('registra mantenimientos, cambios de ubicación y deja traza en la bitácora', function () {
    [$ed] = crearLaboratorio();
    $lab2 = $this->postJson('/api/v1/inventario/laboratorios', ['edificio_id' => $ed['id'], 'departamento_id' => $this->departamento->id, 'nombre' => 'Laboratorio 2'])->json('data');
    [, $lab1] = [null, \App\Models\Laboratorio::query()->where('nombre', 'Laboratorio 1')->first()->toArray()];
    $uuid = $this->postJson('/api/v1/computadoras', datosEquipo($lab1['id']))->json('data.uuid');

    $this->postJson("/api/v1/computadoras/{$uuid}/mantenimientos", [
        'tipo' => 'correctivo', 'fecha' => now()->toDateString(), 'descripcion' => 'Cambio de fuente de poder', 'estado_resultante' => 'mantenimiento',
    ])->assertOk()->assertJsonPath('data.estado_operativo', 'mantenimiento')->assertJsonCount(1, 'data.mantenimientos');

    $this->putJson("/api/v1/computadoras/{$uuid}", ['laboratorio_id' => $lab2['id']])->assertOk()->assertJsonPath('data.laboratorio.nombre', 'Laboratorio 2');

    $computadora = Computadora::query()->where('uuid', $uuid)->first();
    expect($computadora->activo->ubicacion_id)->toBe(\App\Models\Laboratorio::find($lab2['id'])->ubicacion_id);

    $eventos = ActivityLog::query()->where('entidad', 'computadoras')->where('entidad_id', $computadora->id)->get()
        ->map(fn ($l) => $l->cambios['evento'] ?? null)->all();
    expect($eventos)->toContain('computadora_registrada', 'mantenimiento_registrado', 'cambio_ubicacion_equipo');
});

it('permite crear riesgos sobre el activo de una computadora (integración con el módulo de riesgos)', function () {
    MatrizRiesgo::query()->create(['probabilidad' => 4, 'impacto' => 4, 'nivel_resultante' => 'alto']);
    MatrizRiesgo::query()->create(['probabilidad' => 2, 'impacto' => 4, 'nivel_resultante' => 'medio']);
    MatrizRiesgo::query()->create(['probabilidad' => 2, 'impacto' => 2, 'nivel_resultante' => 'bajo']);
    [, $lab] = crearLaboratorio();
    $uuid = $this->postJson('/api/v1/computadoras', datosEquipo($lab['id']))->json('data.uuid');
    $activoId = Computadora::query()->where('uuid', $uuid)->value('activo_id');

    $riesgo = $this->postJson('/api/v1/riesgos', [
        'activo_id' => $activoId, 'amenaza_id' => Amenaza::factory()->create()->id, 'vulnerabilidad_id' => Vulnerabilidad::factory()->create()->id,
        'probabilidad' => 4, 'impacto' => 4, 'tipo_tratamiento' => 'mitigar', 'probabilidad_residual' => 2, 'impacto_residual' => 2,
    ])->assertCreated()->assertJsonPath('data.nivel_calculado', 'alto')->assertJsonPath('data.nivel_residual', 'bajo')->json('data');

    // Editar SOLO la probabilidad recalcula el nivel (antes quedaba desactualizado).
    $this->putJson("/api/v1/riesgos/{$riesgo['id']}", ['probabilidad' => 2])->assertOk()->assertJsonPath('data.nivel_calculado', 'medio');

    $this->getJson("/api/v1/computadoras/{$uuid}")->assertOk()->assertJsonPath('data.activo.riesgos', 1);
    // Con un riesgo abierto, el equipo no se puede eliminar (misma regla y código 409 que Activos).
    $this->deleteJson("/api/v1/computadoras/{$uuid}")->assertStatus(409);
});

it('respeta los permisos: sin activos.ver no hay acceso y Consulta no puede registrar equipos', function () {
    [, $lab] = crearLaboratorio();
    $sinPermisos = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['nombre' => 'Auditor'])->id]);
    $rolConsulta = Rol::factory()->create(['nombre' => 'Consulta']);
    $rolConsulta->permisos()->attach(Permiso::query()->firstOrCreate(['clave' => 'activos.ver'], ['modulo' => 'activos'])->id);
    $consulta = Usuario::factory()->create(['rol_id' => $rolConsulta->id]);

    app('auth')->forgetGuards();
    $this->actingAs($sinPermisos)->getJson('/api/v1/computadoras')->assertForbidden();
    $this->actingAs($sinPermisos)->getJson('/api/v1/inventario/resumen')->assertForbidden();
    app('auth')->forgetGuards();
    $this->actingAs($consulta)->getJson('/api/v1/computadoras')->assertOk();
    $this->actingAs($consulta)->postJson('/api/v1/computadoras', datosEquipo($lab['id']))->assertForbidden();
    $this->actingAs($consulta)->postJson('/api/v1/inventario/edificios', ['nombre' => 'Otro'])->assertForbidden();
});

it('importa desde CSV con vista previa, detecta duplicados y no sobrescribe sin autorización', function () {
    [, $lab] = crearLaboratorio();
    $this->postJson('/api/v1/computadoras', datosEquipo($lab['id']))->assertCreated();
    Software::query()->create(['nombre' => 'Visual Studio Code', 'categoria' => 'desarrollo']);

    $csv = "nombre_equipo,numero_inventario,edificio,laboratorio,estado_operativo,ram_gb,software\n"
        . "LAB1-PC01-NUEVO,UPTEX-0001,Edificio B,Laboratorio 1,operativo,8,\n"          // duplicado (inventario)
        . "LAB1-PC05,UPTEX-0005,Edificio B,Laboratorio 1,En mantenimiento,8,Visual Studio Code@1.85;Programa X\n"
        . "LAB1-PC06,UPTEX-0006,Edificio B,Laboratorio 9,operativo,8,\n"                 // laboratorio inexistente
        . ",UPTEX-0007,,,,,\n";                                                            // sin nombre
    $archivo = fn () => UploadedFile::fake()->createWithContent('equipos.csv', $csv);

    $analisis = $this->postJson('/api/v1/computadoras/importar/analizar', ['archivo' => $archivo()])->assertOk()->json('data');
    expect($analisis['resumen'])->toBe(['total' => 4, 'nuevas' => 1, 'duplicadas' => 1, 'con_errores' => 2])
        ->and($analisis['filas'][1]['avisos'][0])->toContain('Programa X');

    $resultado = $this->postJson('/api/v1/computadoras/importar', [
        'archivo' => $archivo(), 'confidencialidad' => 2, 'integridad' => 2, 'disponibilidad' => 3, 'duplicados' => 'omitir',
    ])->assertOk()->json('data');

    expect($resultado['creadas'])->toBe(1)->and($resultado['actualizadas'])->toBe(0)->and($resultado['omitidas'])->toBe(3)
        ->and(Computadora::query()->where('numero_inventario', 'UPTEX-0001')->value('nombre_equipo'))->toBe('LAB1-PC01')
        ->and(Computadora::query()->where('numero_inventario', 'UPTEX-0005')->first()->estado_operativo)->toBe('mantenimiento')
        ->and(Computadora::query()->where('numero_inventario', 'UPTEX-0005')->first()->software->pluck('nombre')->all())->toBe(['Visual Studio Code']);

    // Solo con autorización expresa se actualiza el duplicado.
    $this->postJson('/api/v1/computadoras/importar', [
        'archivo' => $archivo(), 'confidencialidad' => 2, 'integridad' => 2, 'disponibilidad' => 3, 'duplicados' => 'actualizar',
    ])->assertOk()->assertJsonPath('data.actualizadas', 2);
    expect(Computadora::query()->where('numero_inventario', 'UPTEX-0001')->value('nombre_equipo'))->toBe('LAB1-PC01-NUEVO');
});

it('exporta el inventario y la plantilla de importación en Excel', function () {
    [$ed, $lab] = crearLaboratorio();
    $this->postJson('/api/v1/computadoras', datosEquipo($lab['id']))->assertCreated();

    foreach (["/api/v1/computadoras/exportar.xlsx", "/api/v1/computadoras/exportar.xlsx?laboratorio_id={$lab['id']}",
        "/api/v1/computadoras/exportar.xlsx?edificio_id={$ed['id']}", '/api/v1/computadoras/plantilla.xlsx'] as $url) {
        $resp = $this->get($url)->assertOk();
        expect($resp->headers->get('Content-Type'))->toContain('spreadsheetml')
            ->and(substr($resp->getContent(), 0, 2))->toBe('PK');
    }

    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($ruta, $this->get("/api/v1/computadoras/exportar.xlsx?laboratorio_id={$lab['id']}")->getContent());
    $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
    expect($libro->getSheetNames())->toBe(['Equipos', 'Software instalado', 'Antivirus', 'Requieren atención'])
        ->and($libro->getSheet(0)->getCell('C2')->getValue())->toBe('LAB1-PC01');
    @unlink($ruta);
});

it('el dashboard muestra indicadores de infraestructura y cuentas calculados con datos reales', function () {
    [, $lab] = crearLaboratorio();
    $this->postJson('/api/v1/computadoras', datosEquipo($lab['id'], ['estado_operativo' => 'fuera_servicio']))->assertCreated();
    Usuario::factory()->create(['estado' => 'suspendido']);

    $kpis = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
    expect($kpis['computadoras_total'])->toBe(1)
        ->and($kpis['computadoras_fuera_servicio'])->toBe(1)
        ->and($kpis['usuarios_suspendidos'])->toBe(1)
        ->and($kpis)->toHaveKeys(['controles_implementados', 'controles_pendientes', 'no_conformidades_abiertas', 'incidentes_sin_resolver']);
});
