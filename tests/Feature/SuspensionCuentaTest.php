<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Incidente;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Suspensión de cuentas: el control vive en el backend (login + middleware
 * AsegurarCuentaActiva en cada petición autenticada), no en el frontend.
 */
beforeEach(function () {
    $this->rolAdmin = Rol::factory()->create(['nombre' => 'Administrador']);
    $this->rolAuditor = Rol::factory()->create(['nombre' => 'Auditor']);
    $this->admin = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id, 'email' => 'admin@uptex.edu.mx']);
    $this->usuario = Usuario::factory()->create([
        'rol_id' => $this->rolAuditor->id,
        'email' => 'auditor@uptex.edu.mx',
        'password_hash' => Hash::make('ClaveSegura2026'),
    ]);
});

/** Simula la siguiente petición real: el guard vuelve a leer al usuario de la BD. */
function siguientePeticionComo(Usuario $usuario): void
{
    app('auth')->forgetGuards();
    test()->actingAs(Usuario::query()->findOrFail($usuario->id));
}

function suspenderComoAdmin(Usuario $objetivo, ?string $motivo = 'Baja temporal'): \Illuminate\Testing\TestResponse
{
    siguientePeticionComo(test()->admin);

    return test()->patchJson("/api/v1/usuarios/{$objetivo->uuid}/suspender", ['motivo' => $motivo]);
}

it('permite iniciar sesión a un usuario activo', function () {
    $this->postJson('/api/v1/login', ['email' => 'auditor@uptex.edu.mx', 'password' => 'ClaveSegura2026'])
        ->assertOk()->assertJsonPath('data.estado', 'activo');
});

it('impide iniciar sesión a un usuario suspendido sin revelar el estado ante una contraseña incorrecta', function () {
    suspenderComoAdmin($this->usuario)->assertOk()->assertJsonPath('data.estado', 'suspendido');
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/login', ['email' => 'auditor@uptex.edu.mx', 'password' => 'ClaveSegura2026'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'CUENTA_SUSPENDIDA');

    // Con contraseña incorrecta la respuesta es la genérica (401), no "suspendida".
    $this->postJson('/api/v1/login', ['email' => 'auditor@uptex.edu.mx', 'password' => 'Incorrecta123'])
        ->assertUnauthorized();
});

it('el usuario suspendido con sesión activa pierde el acceso en su siguiente petición', function () {
    siguientePeticionComo($this->usuario);
    $this->getJson('/api/v1/me')->assertOk();

    suspenderComoAdmin($this->usuario)->assertOk();

    siguientePeticionComo($this->usuario);
    $this->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('error.code', 'CUENTA_SUSPENDIDA');
    siguientePeticionComo($this->usuario);
    $this->getJson('/api/v1/activos')->assertForbidden();
    siguientePeticionComo($this->usuario);
    $this->getJson('/api/v1/dashboard')->assertForbidden();
});

it('al suspender se cierran las sesiones guardadas y se revocan los tokens', function () {
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        'id' => 'sesion-de-prueba', 'user_id' => $this->usuario->id, 'ip_address' => '10.0.0.5',
        'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time(),
    ]);
    $token = $this->usuario->createToken('prueba')->plainTextToken;

    suspenderComoAdmin($this->usuario)->assertOk();

    expect(DB::table('sessions')->where('user_id', $this->usuario->id)->count())->toBe(0)
        ->and($this->usuario->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('un usuario reactivado puede volver a ingresar y conserva sus datos', function () {
    $antes = $this->usuario->only(['nombre', 'email', 'rol_id', 'uuid']);
    suspenderComoAdmin($this->usuario)->assertOk();
    suspenderComoAdmin($this->usuario->fresh(), null)->assertOk()->assertJsonPath('data.estado', 'activo');

    // Petición nueva sin sesión (en pruebas, auth:sanctum deja `sanctum` como guard por defecto).
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');
    $this->postJson('/api/v1/login', ['email' => 'auditor@uptex.edu.mx', 'password' => 'ClaveSegura2026'])->assertOk();

    $despues = $this->usuario->fresh();
    expect($despues->only(['nombre', 'email', 'rol_id', 'uuid']))->toBe($antes)
        ->and($despues->motivo_suspension)->toBeNull();
});

it('registra en la bitácora la suspensión (con administrador y motivo) y la reactivación', function () {
    suspenderComoAdmin($this->usuario, 'Equipo extraviado')->assertOk();
    suspenderComoAdmin($this->usuario->fresh(), null)->assertOk();

    $eventos = ActivityLog::query()->where('entidad', 'usuarios')->where('entidad_id', $this->usuario->id)->get();
    $suspension = $eventos->first(fn ($e) => ($e->cambios['evento'] ?? '') === 'cuenta_suspendida');
    $reactivacion = $eventos->first(fn ($e) => ($e->cambios['evento'] ?? '') === 'cuenta_reactivada');

    expect($suspension)->not->toBeNull()
        ->and($suspension->usuario_id)->toBe($this->admin->id)
        ->and($suspension->cambios['motivo'])->toBe('Equipo extraviado')
        ->and($suspension->created_at)->not->toBeNull()
        ->and($reactivacion)->not->toBeNull();
});

it('el administrador no puede suspender su propia cuenta', function () {
    suspenderComoAdmin($this->admin)->assertStatus(422);
    expect($this->admin->fresh()->estado->value)->toBe('activo');
});

it('un usuario sin permiso usuarios.suspender no puede suspender cuentas', function () {
    siguientePeticionComo($this->usuario);
    $this->patchJson("/api/v1/usuarios/{$this->admin->uuid}/suspender")->assertForbidden();
    expect($this->admin->fresh()->estado->value)->toBe('activo');
});

it('la bitácora no se puede modificar ni eliminar', function () {
    suspenderComoAdmin($this->usuario)->assertOk();
    $registro = ActivityLog::query()->latest('id')->first();

    expect(fn () => $registro->update(['accion' => 'delete']))->toThrow(LogicException::class)
        ->and(fn () => $registro->delete())->toThrow(LogicException::class);
});

it('no muestra un incidente a quien no tiene permiso incidentes.ver (petición directa a la API)', function () {
    $incidente = Incidente::factory()->create();

    siguientePeticionComo($this->usuario); // Auditor sin incidentes.ver
    $this->getJson("/api/v1/incidentes/{$incidente->uuid}")->assertForbidden();
    siguientePeticionComo($this->usuario);
    $this->getJson("/api/v1/incidentes/{$incidente->uuid}/timeline")->assertForbidden();

    $permiso = Permiso::query()->firstOrCreate(['clave' => 'incidentes.ver'], ['modulo' => 'incidentes']);
    $this->rolAuditor->permisos()->attach($permiso->id);
    siguientePeticionComo($this->usuario);
    $this->getJson("/api/v1/incidentes/{$incidente->uuid}")->assertOk();
});

it('no envía enlace de recuperación a cuentas suspendidas, con la misma respuesta genérica', function () {
    \Illuminate\Support\Facades\Notification::fake();
    suspenderComoAdmin($this->usuario)->assertOk();
    app('auth')->forgetGuards();

    $suspendida = $this->postJson('/api/v1/olvide-password', ['email' => 'auditor@uptex.edu.mx'])->assertOk()->json('mensaje');
    $inexistente = $this->postJson('/api/v1/olvide-password', ['email' => 'nadie@uptex.edu.mx'])->assertOk()->json('mensaje');

    expect($suspendida)->toBe($inexistente);
    \Illuminate\Support\Facades\Notification::assertNothingSent();
});
