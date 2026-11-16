<?php

declare(strict_types=1);

use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\RestablecerPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Flujo completo de recuperación de contraseña con el Password Broker nativo:
 * solicitud -> correo (HTML + texto) -> validación del enlace -> nueva contraseña
 * -> inicio de sesión, más los casos de token vencido, reutilizado e inválido.
 */
beforeEach(function () {
    config(['app.url' => 'https://sgsi.example.test']);
    $this->rol = Rol::factory()->create(['nombre' => 'Auditor']);
    $this->usuario = Usuario::factory()->create([
        'nombre' => 'María Fernanda López',
        'email' => 'mfernanda@uptex.edu.mx',
        'rol_id' => $this->rol->id,
        'password_hash' => Hash::make('ContrasenaAnterior1'),
    ]);
});

function tokenDeRecuperacion(Usuario $usuario): string
{
    return Password::broker()->createToken($usuario);
}

it('envía el correo de recuperación con un token al solicitarlo', function () {
    Notification::fake();

    $this->postJson('/api/v1/olvide-password', ['email' => 'mfernanda@uptex.edu.mx'])
        ->assertOk()
        ->assertJsonStructure(['mensaje']);

    Notification::assertSentTo($this->usuario, RestablecerPasswordNotification::class);
    // El token se guarda hasheado (nunca en claro) en password_reset_tokens.
    $fila = DB::table('password_reset_tokens')->where('email', 'mfernanda@uptex.edu.mx')->first();
    expect($fila)->not->toBeNull()->and(strlen($fila->token))->toBeGreaterThan(40);
});

it('responde lo mismo para un correo inexistente y no envía nada', function () {
    Notification::fake();

    $existente = $this->postJson('/api/v1/olvide-password', ['email' => 'mfernanda@uptex.edu.mx'])->json('mensaje');
    $inexistente = $this->postJson('/api/v1/olvide-password', ['email' => 'nadie@uptex.edu.mx'])->assertOk()->json('mensaje');

    expect($inexistente)->toBe($existente);
    Notification::assertSentTimes(RestablecerPasswordNotification::class, 1);
});

it('entrega un correo real por el mailer con HTML, texto plano y logotipo incrustado', function () {
    config(['mail.default' => 'array']);

    $this->postJson('/api/v1/olvide-password', ['email' => 'mfernanda@uptex.edu.mx'])->assertOk();

    $mensajes = app('mailer')->getSymfonyTransport()->messages();
    expect($mensajes)->toHaveCount(1);

    /** @var \Symfony\Component\Mime\Email $correo */
    $correo = $mensajes[0]->getOriginalMessage();
    $html = $correo->getHtmlBody();
    $texto = $correo->getTextBody();

    expect($correo->getTo()[0]->getAddress())->toBe('mfernanda@uptex.edu.mx')
        ->and($correo->getSubject())->toBe('Restablece tu contraseña · SGSI UPTex')
        ->and($html)->toContain('RESTABLECER MI CONTRASEÑA')
        ->and($html)->toContain('Hola, María')
        ->and($html)->toContain('Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en')
        ->and($html)->toContain('https://sgsi.example.test/restablecer-password?token=')
        ->and($html)->toContain('email=mfernanda%40uptex.edu.mx')
        ->and($html)->toContain('1 hora')
        ->and($html)->toContain('te pedirá tu contraseña')
        ->and($html)->toContain('src="cid:')            // logotipo como imagen CID
        ->and($html)->not->toContain('data:image')       // sin data: URI (Gmail/Outlook las bloquean)
        ->and(strlen($html))->toBeLessThan(102 * 1024)   // Gmail recorta a partir de 102 KB
        ->and($texto)->toContain('RESTABLECIMIENTO DE CONTRASEÑA')
        ->and($texto)->toContain('https://sgsi.example.test/restablecer-password?token=')
        ->and($texto)->not->toContain('&amp;');

    // El logotipo viaja como parte en línea (inline) del mensaje.
    $inline = collect($correo->getAttachments())->filter(fn ($p) => $p->getDisposition() === 'inline');
    expect($inline)->toHaveCount(1)
        ->and($inline->first()->getMediaSubtype())->toBe('png');
});

it('valida el enlace y restablece la contraseña sin tocar otros datos del usuario', function () {
    $token = tokenDeRecuperacion($this->usuario);
    $antes = $this->usuario->fresh()->only(['nombre', 'email', 'rol_id', 'estado', 'uuid']);

    $this->postJson('/api/v1/restablecer-password/validar', ['email' => 'mfernanda@uptex.edu.mx', 'token' => $token])
        ->assertOk();

    $this->postJson('/api/v1/restablecer-password', [
        'email' => 'mfernanda@uptex.edu.mx',
        'token' => $token,
        'password' => 'NuevaClaveSegura2026',
        'password_confirmation' => 'NuevaClaveSegura2026',
    ])->assertOk()->assertJsonStructure(['mensaje']);

    $despues = $this->usuario->fresh();
    expect(Hash::check('NuevaClaveSegura2026', $despues->password_hash))->toBeTrue()
        ->and(Hash::check('ContrasenaAnterior1', $despues->password_hash))->toBeFalse()
        ->and($despues->only(['nombre', 'email', 'rol_id', 'estado', 'uuid']))->toBe($antes)
        ->and(DB::table('password_reset_tokens')->where('email', 'mfernanda@uptex.edu.mx')->exists())->toBeFalse();

    // Inicio de sesión con la contraseña nueva.
    $this->postJson('/api/v1/login', ['email' => 'mfernanda@uptex.edu.mx', 'password' => 'NuevaClaveSegura2026'])
        ->assertOk();
});

it('no permite reutilizar un enlace ya utilizado', function () {
    $token = tokenDeRecuperacion($this->usuario);
    $datos = [
        'email' => 'mfernanda@uptex.edu.mx',
        'token' => $token,
        'password' => 'NuevaClaveSegura2026',
        'password_confirmation' => 'NuevaClaveSegura2026',
    ];

    $this->postJson('/api/v1/restablecer-password', $datos)->assertOk();

    $this->postJson('/api/v1/restablecer-password', [...$datos, 'password' => 'OtraClaveSegura2027', 'password_confirmation' => 'OtraClaveSegura2027'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'TOKEN_INVALIDO');
    $this->postJson('/api/v1/restablecer-password/validar', ['email' => 'mfernanda@uptex.edu.mx', 'token' => $token])
        ->assertStatus(422);

    expect(Hash::check('NuevaClaveSegura2026', $this->usuario->fresh()->password_hash))->toBeTrue();
});

it('rechaza un enlace vencido', function () {
    $token = tokenDeRecuperacion($this->usuario);

    $this->travel(61)->minutes();

    $this->postJson('/api/v1/restablecer-password/validar', ['email' => 'mfernanda@uptex.edu.mx', 'token' => $token])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'TOKEN_INVALIDO');
    $this->postJson('/api/v1/restablecer-password', [
        'email' => 'mfernanda@uptex.edu.mx',
        'token' => $token,
        'password' => 'NuevaClaveSegura2026',
        'password_confirmation' => 'NuevaClaveSegura2026',
    ])->assertStatus(422);

    expect(Hash::check('ContrasenaAnterior1', $this->usuario->fresh()->password_hash))->toBeTrue();
});

it('rechaza un token inválido o de otra cuenta', function () {
    $otro = Usuario::factory()->create(['rol_id' => $this->rol->id, 'email' => 'otra@uptex.edu.mx']);
    $tokenDeOtro = tokenDeRecuperacion($otro);
    tokenDeRecuperacion($this->usuario);

    foreach (['token-inventado', $tokenDeOtro] as $token) {
        $this->postJson('/api/v1/restablecer-password', [
            'email' => 'mfernanda@uptex.edu.mx',
            'token' => $token,
            'password' => 'NuevaClaveSegura2026',
            'password_confirmation' => 'NuevaClaveSegura2026',
        ])->assertStatus(422)->assertJsonPath('error.code', 'TOKEN_INVALIDO');
    }

    expect(Hash::check('ContrasenaAnterior1', $this->usuario->fresh()->password_hash))->toBeTrue();
});

it('exige una contraseña robusta y que la confirmación coincida', function () {
    $token = tokenDeRecuperacion($this->usuario);

    $this->postJson('/api/v1/restablecer-password', [
        'email' => 'mfernanda@uptex.edu.mx', 'token' => $token,
        'password' => 'solominusculas', 'password_confirmation' => 'solominusculas',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDACION_FALLIDA');

    $this->postJson('/api/v1/restablecer-password', [
        'email' => 'mfernanda@uptex.edu.mx', 'token' => $token,
        'password' => 'NuevaClaveSegura2026', 'password_confirmation' => 'NoCoincide2026xx',
    ])->assertStatus(422)->assertJsonPath('error.details.password.0', 'La confirmación no coincide con la nueva contraseña.');

    // El token sigue vigente tras errores de validación.
    $this->postJson('/api/v1/restablecer-password/validar', ['email' => 'mfernanda@uptex.edu.mx', 'token' => $token])->assertOk();
});

it('no responde "enviado" si el servidor SMTP no acepta el correo', function () {
    // Servidor SMTP inexistente (puerto cerrado): conexión rechazada de inmediato.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 9,
        'mail.mailers.smtp.timeout' => 3,
    ]);

    $this->postJson('/api/v1/olvide-password', ['email' => 'mfernanda@uptex.edu.mx'])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'CORREO_NO_ENVIADO');

    // Sin token huérfano: el usuario puede reintentar de inmediato.
    expect(DB::table('password_reset_tokens')->where('email', 'mfernanda@uptex.edu.mx')->exists())->toBeFalse();
});
