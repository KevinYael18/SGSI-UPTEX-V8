<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Computadora;
use App\Models\Departamento;
use App\Models\Incidente;
use App\Models\MatrizRiesgo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Software;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;
use App\Support\CatalogoTiposActivo;

/**
 * Ficha técnica dinámica del módulo de Activos: reconocimiento automático,
 * campos por tipo, catálogo software ↔ equipos, ubicaciones y regresión de
 * CIA / criticidad / relaciones.
 */
beforeEach(function () {
    $this->admin = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['nombre' => 'Administrador'])->id]);
    $this->depto = Departamento::factory()->create();
    $this->actingAs($this->admin);
});

function activoBase(array $extra = []): array
{
    return array_merge(['tipo' => 'hardware', 'confidencialidad' => 2, 'integridad' => 4, 'disponibilidad' => 3, 'departamento_id' => test()->depto->id], $extra);
}

it('reconoce automáticamente el tipo de activo a partir del nombre', function (?string $categoria, string $nombre, ?string $esperado) {
    expect(CatalogoTiposActivo::reconocer($categoria, $nombre)['subtipo'])->toBe($esperado);
})->with([
    ['hardware', 'Computadora Dell OptiPlex', 'computadora'],
    ['hardware', 'PC Laboratorio 1', 'computadora'],
    ['hardware', 'Laptop HP ProBook', 'laptop'],
    ['hardware', 'Servidor Dell PowerEdge', 'servidor'],
    ['hardware', 'Cámara Hikvision', 'camara'],
    ['hardware', 'Impresora HP LaserJet', 'impresora'],
    ['hardware', 'Impresora 3D Creality', 'impresora_3d'],
    ['hardware', 'Switch Cisco', 'switch'],
    ['hardware', 'Router TP-Link', 'router'],
    ['hardware', 'Aire acondicionado', 'climatizacion'],
    ['hardware', 'PLC Siemens', 'plc'],
    ['hardware', 'Brazo robótico industrial', 'robot'],
    ['hardware', 'Máquina CNC', 'cnc'],
    ['software', 'Software Microsoft Office', 'productividad'],
    ['software', 'Sistema operativo Windows 11', 'sistema_operativo'],
    ['software', 'ESET Antivirus', 'antivirus'],
    ['hardware', 'Mesa de trabajo', null],             // no reconocido: registro general
    ['software', 'Computadora Dell', null],             // solo dentro de su categoría general
]);

it('expone el catálogo de tipos y el reconocimiento por API', function () {
    $this->getJson('/api/v1/activos/tipos-tecnicos')->assertOk()->assertJsonStructure(['data' => ['tipos', 'plantillas', 'controles']]);
    $this->getJson('/api/v1/activos/reconocer?tipo=hardware&nombre=Laptop%20HP')->assertOk()
        ->assertJsonPath('data.subtipo', 'laptop')->assertJsonPath('data.confianza', 'alta');
});

it('registra una computadora con su ficha técnica sin alterar CIA ni criticidad', function () {
    $resp = $this->postJson('/api/v1/activos', activoBase([
        'nombre' => 'Computadora Dell OptiPlex',
        'subtipo' => 'computadora',
        'tecnico' => [
            'fabricante' => 'Dell', 'marca' => 'Dell', 'modelo' => 'OptiPlex 7090', 'numero_serie' => 'SN-001', 'hostname' => 'LAB1-PC01',
            'procesador' => 'Intel Core i5-11500', 'generacion_procesador' => '11.ª', 'ram_gb' => 16, 'tipo_ram' => 'ddr4',
            'direccion_mac' => '001a2b3c4d5e', 'direccion_ip' => '192.168.1.20', 'conexion_red' => 'ethernet',
            'campo_inexistente' => 'se ignora',
        ],
    ]))->assertCreated()
        ->assertJsonPath('data.subtipo', 'computadora')
        ->assertJsonPath('data.criticidad_calculada', 4)   // máx(2,4,3): misma regla de siempre
        ->assertJsonPath('data.tecnico.direccion_mac', '00:1A:2B:3C:4D:5E')
        ->assertJsonPath('data.tecnico.generacion_procesador', '11.ª')
        ->assertJsonPath('data.tecnico.hostname', 'LAB1-PC01');

    $activo = Activo::query()->where('uuid', $resp->json('data.uuid'))->first();
    $ficha = $activo->computadora;
    expect($activo->nombre)->toBe('Computadora Dell OptiPlex')         // el hostname NO renombra el activo
        ->and($ficha->nombre_equipo)->toBe('LAB1-PC01')
        ->and($ficha->procesador)->toBe('Intel Core i5-11500')
        ->and($activo->caracteristicas['campos'])->toHaveKeys(['marca', 'generacion_procesador', 'tipo_ram', 'conexion_red'])
        ->and($activo->caracteristicas['campos'])->not->toHaveKey('campo_inexistente')
        ->and($activo->caracteristicas['campos'])->not->toHaveKey('procesador'); // vive en la ficha de cómputo, sin duplicar
});

it('muestra solo los campos del tipo: una cámara guarda sus datos y valida IP y duplicados', function () {
    $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Cámara Hikvision', 'subtipo' => 'camara',
        'tecnico' => ['numero_serie' => 'CAM-1', 'tipo_camara' => 'domo', 'direccion_ip' => '10.0.0.5', 'procesador' => 'no aplica']]))
        ->assertCreated()->assertJsonPath('data.tecnico.tipo_camara', 'domo')->assertJsonMissingPath('data.tecnico.procesador');

    $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Cámara 2', 'subtipo' => 'camara', 'tecnico' => ['direccion_ip' => '999.1.1.1']]))
        ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['tecnico.direccion_ip']]]);
    $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Cámara 3', 'subtipo' => 'camara', 'tecnico' => ['numero_serie' => 'CAM-1']]))
        ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['tecnico.numero_serie']]]);
    // Si falla la ficha, no queda un activo a medias.
    expect(Activo::query()->where('nombre', 'Cámara 3')->exists())->toBeFalse();
});

it('registra impresora 3D y equipo industrial; oculta la red cuando no la usa', function () {
    $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Impresora 3D Creality', 'subtipo' => 'impresora_3d',
        'tecnico' => ['tecnologia_impresion' => 'fdm', 'volumen_impresion' => '220 × 220 × 250 mm', 'materiales' => 'PLA, PETG']]))
        ->assertCreated()->assertJsonPath('data.tecnico.materiales', 'PLA, PETG');

    $this->postJson('/api/v1/activos', activoBase(['nombre' => 'PLC Siemens', 'subtipo' => 'plc',
        'tecnico' => ['protocolos' => 'Profinet', 'conectividad' => 'ethernet', 'direccion_ip' => '192.168.0.10', 'fecha_proximo_mantenimiento' => now()->addMonth()->toDateString()]]))
        ->assertCreated()->assertJsonPath('data.tecnico.protocolos', 'Profinet');
});

it('rechaza un tipo específico que no corresponde a la clasificación general', function () {
    $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Windows 11', 'subtipo' => 'sistema_operativo']))
        ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['subtipo']]]);
});

it('registra software, lo vincula al catálogo y consulta en qué equipos está instalado', function () {
    $windows = $this->postJson('/api/v1/activos', activoBase(['tipo' => 'software', 'nombre' => 'Windows 11', 'subtipo' => 'sistema_operativo',
        'tecnico' => ['fabricante' => 'Microsoft', 'version' => '23H2', 'edicion' => 'Education', 'tipo_licencia' => 'institucional', 'cantidad_licencias' => 120]]))
        ->assertCreated()->assertJsonPath('data.tecnico.edicion', 'Education')->json('data');
    $office = $this->postJson('/api/v1/activos', activoBase(['tipo' => 'software', 'nombre' => 'Microsoft Office', 'subtipo' => 'productividad']))
        ->assertCreated()->json('data');

    $catalogoWindows = Software::query()->where('nombre', 'Windows 11')->first();
    expect($catalogoWindows->activo->uuid)->toBe($windows['uuid'])
        ->and($catalogoWindows->categoria)->toBe('sistema_operativo')
        ->and($catalogoWindows->estado_autorizacion)->toBe('pendiente_verificacion');

    // Dos computadoras con varios programas (N:M)
    $pcs = [];
    foreach (['PC Laboratorio 1', 'PC Laboratorio 2'] as $nombre) {
        $uuid = $this->postJson('/api/v1/activos', activoBase(['nombre' => $nombre, 'subtipo' => 'computadora']))->assertCreated()->json('data.uuid');
        $pcs[] = Activo::query()->where('uuid', $uuid)->first()->computadora;
    }
    foreach ($pcs as $pc) {
        $this->postJson("/api/v1/computadoras/{$pc->uuid}/software", ['software_id' => $catalogoWindows->id, 'version' => '23H2'])->assertOk();
    }
    $catalogoOffice = Software::query()->where('nombre', 'Microsoft Office')->first();
    $this->postJson("/api/v1/computadoras/{$pcs[0]->uuid}/software", ['software_id' => $catalogoOffice->id])->assertOk()
        ->assertJsonCount(2, 'data.software');                                         // escenario 11

    $this->getJson("/api/v1/activos/{$windows['uuid']}")->assertOk()->assertJsonCount(2, 'data.programa.equipos'); // escenario 12
    $this->getJson("/api/v1/software/{$catalogoWindows->id}/equipos")->assertOk()->assertJsonCount(2, 'data');

    // Desinstalar conserva el historial con estado «desinstalado».
    $this->deleteJson("/api/v1/computadoras/{$pcs[1]->uuid}/software/{$catalogoWindows->id}")->assertOk()->assertJsonCount(0, 'data.software');
    $historial = $this->getJson("/api/v1/software/{$catalogoWindows->id}/equipos")->json('data');
    expect(collect($historial)->pluck('estado_instalacion')->sort()->values()->all())->toBe(['desinstalado', 'instalado']);
    $this->getJson("/api/v1/activos/{$windows['uuid']}")->assertJsonCount(1, 'data.programa.equipos');
    // Reinstalar reutiliza el mismo registro.
    $this->postJson("/api/v1/computadoras/{$pcs[1]->uuid}/software", ['software_id' => $catalogoWindows->id])->assertOk()->assertJsonCount(1, 'data.software');
    expect($office)->not->toBeNull();
});

it('no permite guardar claves de producto en la referencia de licencia', function () {
    $this->postJson('/api/v1/activos', activoBase(['tipo' => 'software', 'nombre' => 'Microsoft Office 2021', 'subtipo' => 'productividad',
        'tecnico' => ['referencia_licencia' => 'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY']]))
        ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['tecnico.referencia_licencia']]]);
});

it('permite registrar un activo no reconocido con características personalizadas', function () {
    $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Mesa de trabajo antiestática',
        'personalizados' => [['nombre' => 'Material', 'valor' => 'Acero'], ['nombre' => '', 'valor' => '']]]))
        ->assertCreated()->assertJsonPath('data.subtipo', null)->assertJsonPath('data.personalizados.0.nombre', 'Material')
        ->assertJsonCount(1, 'data.personalizados');
});

it('edita un activo anterior a la ampliación conservando sus datos y añadiendo su ficha', function () {
    $legado = Activo::factory()->create(['tipo' => 'hardware', 'nombre' => 'Servidor de base de datos', 'departamento_id' => $this->depto->id,
        'confidencialidad' => 5, 'integridad' => 4, 'disponibilidad' => 5, 'criticidad_calculada' => 5]);
    $antes = $legado->only(['nombre', 'confidencialidad', 'integridad', 'disponibilidad', 'criticidad_calculada', 'departamento_id', 'uuid']);

    $this->getJson("/api/v1/activos/{$legado->uuid}")->assertOk()->assertJsonPath('data.subtipo', null);
    $this->putJson("/api/v1/activos/{$legado->uuid}", ['subtipo' => 'servidor', 'tecnico' => ['raid' => 'raid10', 'servicios_alojados' => 'Control escolar', 'ram_gb' => 64]])
        ->assertOk()->assertJsonPath('data.tecnico.raid', 'raid10')->assertJsonPath('data.tecnico.ram_gb', 64);

    expect($legado->fresh()->only(array_keys($antes)))->toBe($antes);
    // Cambiar solo un campo conserva los demás ya guardados.
    $this->putJson("/api/v1/activos/{$legado->uuid}", ['tecnico' => ['raid' => 'raid5']])->assertOk()
        ->assertJsonPath('data.tecnico.servicios_alojados', 'Control escolar')->assertJsonPath('data.tecnico.raid', 'raid5');
});

it('conserva las relaciones con riesgos, incidentes y controles, y la bitácora registra los cambios', function () {
    MatrizRiesgo::query()->create(['probabilidad' => 3, 'impacto' => 4, 'nivel_resultante' => 'alto']);
    $uuid = $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Switch Cisco', 'subtipo' => 'switch', 'tecnico' => ['numero_puertos' => 48]]))->json('data.uuid');
    $activo = Activo::query()->where('uuid', $uuid)->first();

    $this->postJson('/api/v1/riesgos', ['activo_id' => $activo->id, 'amenaza_id' => Amenaza::factory()->create()->id,
        'vulnerabilidad_id' => Vulnerabilidad::factory()->create()->id, 'probabilidad' => 3, 'impacto' => 4])->assertCreated();
    Incidente::factory()->create(['activo_id' => $activo->id]);

    $this->putJson("/api/v1/activos/{$uuid}", ['tecnico' => ['numero_puertos' => 24, 'firmware' => '15.2']])->assertOk();
    $activo->refresh();
    expect($activo->riesgos()->count())->toBe(1)->and($activo->incidentes()->count())->toBe(1)
        ->and($activo->criticidad_calculada)->toBe(4);
    $eventos = ActivityLog::query()->where('entidad', 'activos')->where('entidad_id', $activo->id)->get()->pluck('cambios.evento')->filter()->values()->all();
    expect($eventos)->toContain('ficha_tecnica_actualizada');
});

it('administra ubicaciones con edificio y piso y registra el cambio de ubicación del activo', function () {
    $edificio = $this->postJson('/api/v1/inventario/edificios', ['nombre' => 'Edificio B'])->json('data');
    $u1 = $this->postJson('/api/v1/inventario/ubicaciones', ['nombre' => 'Aula 3', 'departamento_id' => $this->depto->id, 'edificio_id' => $edificio['id'], 'piso' => 'Planta alta', 'tipo_espacio' => 'aula'])->assertCreated()->json('data');
    $u2 = $this->postJson('/api/v1/inventario/ubicaciones', ['nombre' => 'Oficina TI', 'departamento_id' => $this->depto->id, 'tipo_espacio' => 'oficina'])->assertCreated()->json('data');
    expect(Ubicacion::find($u1['id'])->descripcion())->toBe('Aula 3 · Edificio B · Planta alta');

    $uuid = $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Proyector Epson', 'ubicacion_id' => $u1['id'], 'ubicacion_detalle' => 'Techo, al frente']))
        ->assertCreated()->assertJsonPath('data.subtipo', null)->json('data.uuid');
    $this->putJson("/api/v1/activos/{$uuid}", ['ubicacion_id' => $u2['id']])->assertOk()->assertJsonPath('data.ubicacion_completa', 'Oficina TI');

    $cambio = ActivityLog::query()->where('entidad', 'activos')->get()->first(fn ($l) => ($l->cambios['evento'] ?? '') === 'cambio_ubicacion');
    expect($cambio->cambios['ubicacion'])->toBe(['antes' => 'Aula 3 · Edificio B · Planta alta', 'despues' => 'Oficina TI']);
    $this->deleteJson("/api/v1/inventario/ubicaciones/{$u2['id']}")->assertStatus(422); // tiene activos
});

it('respeta los permisos: Consulta no puede editar la ficha técnica', function () {
    $uuid = $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Router TP-Link', 'subtipo' => 'router']))->json('data.uuid');
    $rol = Rol::factory()->create(['nombre' => 'Consulta']);
    $rol->permisos()->attach(Permiso::query()->firstOrCreate(['clave' => 'activos.ver'], ['modulo' => 'activos'])->id);
    app('auth')->forgetGuards();
    $this->actingAs(Usuario::factory()->create(['rol_id' => $rol->id]))
        ->putJson("/api/v1/activos/{$uuid}", ['tecnico' => ['firmware' => 'x']])->assertForbidden();
    $this->postJson('/api/v1/inventario/ubicaciones', ['nombre' => 'X', 'departamento_id' => $this->depto->id])->assertForbidden();
});

it('acepta los campos vacíos que envía el formulario (vuelven a su valor por defecto)', function () {
    $campos = array_keys(CatalogoTiposActivo::campos('computadora'));
    $vacios = array_fill_keys($campos, null);   // así llega un formulario sin completar
    $vacios['numero_serie'] = 'SN-VACIOS-1';

    $uuid = $this->postJson('/api/v1/activos', activoBase(['nombre' => 'Computadora Dell OptiPlex', 'subtipo' => 'computadora', 'tecnico' => $vacios]))
        ->assertCreated()->json('data.uuid');
    $ficha = Activo::query()->where('uuid', $uuid)->first()->computadora;
    expect($ficha->nombre_equipo)->toBe('Computadora Dell OptiPlex')
        ->and($ficha->estado_operativo)->toBe('operativo')
        ->and($ficha->antivirus_proteccion)->toBe('desconocido')
        ->and($ficha->numero_serie)->toBe('SN-VACIOS-1');

    // Editar enviando todo vacío tampoco falla ni borra el nombre del equipo.
    $this->putJson("/api/v1/activos/{$uuid}", ['subtipo' => 'computadora', 'tecnico' => array_fill_keys($campos, null)])->assertOk();
    expect($ficha->fresh()->nombre_equipo)->toBe('Computadora Dell OptiPlex');
});

