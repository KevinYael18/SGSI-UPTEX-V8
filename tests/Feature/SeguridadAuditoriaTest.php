<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Activo;
use App\Models\ConfiguracionSistema;
use App\Models\Departamento;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
 * Regresión de la auditoría integral de seguridad (docs/SEGURIDAD_AUDITORIA_INTEGRAL.md).
 * Cada prueba corresponde a un hallazgo corregido.
 */

function rolSeguridad(string $nombre, array $claves): Rol
{
    $rol = Rol::query()->firstOrCreate(['nombre' => $nombre]);
    foreach ($claves as $clave) {
        $rol->permisos()->syncWithoutDetaching([Permiso::query()->firstOrCreate(['clave' => $clave], ['modulo' => explode('.', $clave)[0]])->id]);
    }

    return $rol;
}

beforeEach(function () {
    $this->rolAdmin = Rol::query()->firstOrCreate(['nombre' => 'Administrador']);
    $this->admin = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);
});

// F1 — Petición sin sesión y sin cabecera Accept JSON: 401, nunca 500.
it('responde 401 (no 500) a una petición sin sesión aunque no pida JSON', function () {
    $this->get('/api/v1/me')->assertStatus(401)->assertJsonPath('error.code', 'NO_AUTENTICADO');
});

// F2 — BOLA: el rol Consulta solo ve activos de su alcance en listados, búsqueda, catálogo y reportes.
describe('alcance por departamento del rol Consulta', function () {
    beforeEach(function () {
        $this->deptoPropio = Departamento::factory()->create();
        $this->deptoAjeno = Departamento::factory()->create();
        $this->propio = Activo::factory()->create(['departamento_id' => $this->deptoPropio->id, 'nombre' => 'Servidor propio ZZ']);
        $this->ajeno = Activo::factory()->create(['departamento_id' => $this->deptoAjeno->id, 'nombre' => 'Servidor ajeno ZZ']);
        $this->consulta = Usuario::factory()->create(['rol_id' => rolSeguridad('Consulta', ['activos.ver'])->id]);
        $this->consulta->departamentosConAlcance()->attach($this->deptoPropio->id);
    });

    it('filtra el listado de activos', function () {
        $nombres = collect($this->actingAs($this->consulta)->getJson('/api/v1/activos')->assertOk()->json('data'))->pluck('nombre');

        expect($nombres)->toContain('Servidor propio ZZ')->not->toContain('Servidor ajeno ZZ');
    });

    it('no permite ver el detalle de un activo ajeno (IDOR por UUID)', function () {
        $this->actingAs($this->consulta)->getJson("/api/v1/activos/{$this->ajeno->uuid}")->assertForbidden();
    });

    it('filtra la búsqueda global y el catálogo', function () {
        $busqueda = collect($this->actingAs($this->consulta)->getJson('/api/v1/busqueda?q=ZZ')->assertOk()->json('data'))->pluck('titulo');
        $catalogo = collect($this->actingAs($this->consulta)->getJson('/api/v1/catalogos/activos')->assertOk()->json('data'))->pluck('nombre');

        expect($busqueda)->not->toContain('Servidor ajeno ZZ')->and($catalogo)->not->toContain('Servidor ajeno ZZ')
            ->and($catalogo)->toContain('Servidor propio ZZ');
    });

    it('no altera lo que ven los demás roles', function () {
        $nombres = collect($this->actingAs($this->admin)->getJson('/api/v1/activos')->assertOk()->json('data'))->pluck('nombre');

        expect($nombres)->toContain('Servidor propio ZZ')->toContain('Servidor ajeno ZZ');
    });
});

// F3 — Escalamiento vertical de privilegios en la gestión de usuarios.
describe('gestión de usuarios', function () {
    beforeEach(function () {
        // Rol al que un administrador delegó la gestión de usuarios.
        $this->gestor = Usuario::factory()->create(['rol_id' => rolSeguridad('Responsable SGSI', ['usuarios.ver', 'usuarios.crear', 'usuarios.editar'])->id]);
        $this->rolConsulta = rolSeguridad('Consulta', ['activos.ver']);
        $this->clave = 'Segura2026Clave';
    });

    it('impide que un rol delegado cree administradores', function () {
        $this->actingAs($this->gestor)->postJson('/api/v1/usuarios', [
            'nombre' => 'Intruso', 'email' => 'intruso@uptex.edu.mx', 'password' => $this->clave, 'rol_id' => $this->rolAdmin->id,
        ])->assertForbidden();
        expect(Usuario::query()->where('email', 'intruso@uptex.edu.mx')->exists())->toBeFalse();
    });

    it('impide que un rol delegado se asigne el rol Administrador', function () {
        $this->actingAs($this->gestor)->putJson("/api/v1/usuarios/{$this->gestor->uuid}", ['rol_id' => $this->rolAdmin->id])->assertForbidden();
        expect($this->gestor->refresh()->rol_id)->not->toBe($this->rolAdmin->id);
    });

    it('impide que un rol delegado cambie la contraseña o el correo de un administrador', function () {
        $this->actingAs($this->gestor)->putJson("/api/v1/usuarios/{$this->admin->uuid}", ['password' => 'TomaDeCuenta2026'])->assertForbidden();
        expect(Hash::check('TomaDeCuenta2026', $this->admin->refresh()->password_hash))->toBeFalse();
    });

    it('permite al rol delegado gestionar cuentas que no son de administrador', function () {
        $this->actingAs($this->gestor)->postJson('/api/v1/usuarios', [
            'nombre' => 'Nueva consulta', 'email' => 'consulta.nueva@uptex.edu.mx', 'password' => $this->clave, 'rol_id' => $this->rolConsulta->id,
        ])->assertCreated();
    });

    it('exige contraseñas robustas al crear cuentas', function () {
        // En producción el Administrador tiene todos los permisos asignados (seeder).
        rolSeguridad('Administrador', ['usuarios.ver', 'usuarios.crear']);
        $this->actingAs($this->admin)->postJson('/api/v1/usuarios', [
            'nombre' => 'Débil', 'email' => 'debil@uptex.edu.mx', 'password' => 'aaaaaaaaaaaa', 'rol_id' => $this->rolConsulta->id,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDACION_FALLIDA');
    });

    it('no deja el sistema sin un Administrador activo', function () {
        $this->actingAs($this->admin)->putJson("/api/v1/usuarios/{$this->admin->uuid}", ['rol_id' => $this->rolConsulta->id])->assertStatus(422);
        expect($this->admin->refresh()->rol_id)->toBe($this->rolAdmin->id);
    });

    it('revoca los tokens de la cuenta cuando un administrador restablece su contraseña', function () {
        $objetivo = Usuario::factory()->create(['rol_id' => $this->rolConsulta->id]);
        $objetivo->createToken('dispositivo');

        $this->actingAs($this->admin)->putJson("/api/v1/usuarios/{$objetivo->uuid}", ['password' => 'NuevaClave2026x'])->assertOk();

        expect($objetivo->tokens()->count())->toBe(0);
    });
});

// F4 — Paginación acotada (OWASP API4).
it('acota el tamaño de página a 100 registros', function () {
    Activo::factory()->count(3)->create(['departamento_id' => Departamento::factory()]);

    $this->actingAs($this->admin)->getJson('/api/v1/activos?por_pagina=1000000')
        ->assertOk()->assertJsonPath('meta.per_page', 100);
});

// F5 — Inyección de fórmulas en exportaciones Excel (CWE-1236).
it('exporta como texto los valores que empiezan con «=»', function () {
    Activo::factory()->create(['departamento_id' => Departamento::factory(), 'nombre' => '=HYPERLINK("http://malicioso.example","clic")']);

    $respuesta = $this->actingAs($this->admin)->get('/api/v1/reportes/activos.xlsx')->assertOk();
    $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($archivo, $respuesta->getContent());
    $celda = IOFactory::load($archivo)->getActiveSheet()->getCell('A2');
    @unlink($archivo);

    expect($celda->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($celda->getValue())->toStartWith('=HYPERLINK');
});

// F6 — Limitador del login sin distinguir mayúsculas.
it('cuenta los intentos fallidos aunque se cambien mayúsculas del correo', function () {
    $victima = Usuario::factory()->create(['email' => 'victima@uptex.edu.mx', 'rol_id' => $this->rolAdmin->id]);
    foreach (['victima@uptex.edu.mx', 'Victima@uptex.edu.mx', 'VICTIMA@uptex.edu.mx', 'vIctima@uptex.edu.mx', 'viCtima@uptex.edu.mx'] as $email) {
        $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'incorrecta123'])->assertStatus(401);
    }

    $this->postJson('/api/v1/login', ['email' => 'VicTima@uptex.edu.mx', 'password' => 'incorrecta123'])
        ->assertStatus(401)->assertJsonPath('error.message', 'Demasiados intentos. Intente de nuevo en unos minutos.');
});

it('responde igual a un correo inexistente que a una contraseña incorrecta', function () {
    Usuario::factory()->create(['email' => 'existe@uptex.edu.mx', 'rol_id' => $this->rolAdmin->id]);

    $a = $this->postJson('/api/v1/login', ['email' => 'existe@uptex.edu.mx', 'password' => 'incorrecta123'])->assertStatus(401)->json('error.message');
    $b = $this->postJson('/api/v1/login', ['email' => 'noexiste@uptex.edu.mx', 'password' => 'incorrecta123'])->assertStatus(401)->json('error.message');

    expect($a)->toBe($b);
});

// F7 — Longitud máxima de textos libres.
it('rechaza descripciones de incidente desmesuradas', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/incidentes', [
        'titulo' => 'Prueba', 'descripcion' => str_repeat('x', 10001), 'fecha_deteccion' => now()->subMinute()->toDateTimeString(),
    ])->assertStatus(422);
});

// F8 — Configuración: solo claves existentes y cambios en bitácora; cambios de permisos de rol en bitácora.
it('no crea parámetros de configuración arbitrarios y registra los cambios', function () {
    ConfiguracionSistema::query()->create(['clave' => 'nombre_institucion', 'valor' => 'UPTex', 'tipo' => 'texto']);

    $this->actingAs($this->admin)->putJson('/api/v1/configuracion', ['valores' => ['clave_inventada' => 'x']])->assertStatus(422);
    expect(ConfiguracionSistema::query()->where('clave', 'clave_inventada')->exists())->toBeFalse();

    $this->actingAs($this->admin)->putJson('/api/v1/configuracion', ['valores' => ['nombre_institucion' => 'Universidad Politécnica de Texcoco']])->assertOk();
    expect(ActivityLog::query()->where('entidad', 'configuracion_sistema')->exists())->toBeTrue();
});

it('registra en bitácora los cambios de permisos de un rol', function () {
    $rol = rolSeguridad('Auditor', ['auditorias.ver']);
    $permiso = Permiso::query()->firstOrCreate(['clave' => 'documentos.ver'], ['modulo' => 'documentos']);

    $this->actingAs($this->admin)->putJson("/api/v1/roles/{$rol->id}", ['permisos' => [$permiso->id]])->assertOk();

    $log = ActivityLog::query()->where('entidad', 'roles')->where('entidad_id', $rol->id)->firstOrFail();
    expect($log->cambios['agregados'])->toContain('documentos.ver')->and($log->cambios['retirados'])->toContain('auditorias.ver');
});

// Controles ya existentes que la auditoría verificó (regresión).
it('no expone hashes de contraseña en las respuestas de usuarios', function () {
    $json = $this->actingAs($this->admin)->getJson('/api/v1/usuarios')->assertOk()->getContent();

    expect($json)->not->toContain('password')->not->toContain('$2y$');
});

it('no permite a un rol sin permisos administrar roles ni configuración', function () {
    $consulta = Usuario::factory()->create(['rol_id' => rolSeguridad('Consulta', ['activos.ver'])->id]);

    $this->actingAs($consulta)->getJson('/api/v1/roles')->assertForbidden();
    $this->actingAs($consulta)->putJson('/api/v1/configuracion', ['valores' => ['x' => 'y']])->assertForbidden();
    $this->actingAs($consulta)->getJson('/api/v1/bitacora')->assertForbidden();
    $this->actingAs($consulta)->getJson('/api/v1/usuarios')->assertForbidden();
});
