<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RestablecerPasswordRequest;
use App\Http\Requests\SolicitarRecuperacionRequest;
use App\Http\Requests\ValidarTokenRecuperacionRequest;
use App\Http\Resources\UsuarioResource;
use App\Http\Responses\ApiResponse;
use App\Models\Usuario;
use App\Support\Bitacora;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    private const MENSAJE_RECUPERACION = 'Si el correo pertenece a una cuenta de SGSI UPTex, recibirás en unos minutos un enlace para restablecer tu contraseña. '
        . 'Si ya lo solicitaste, espera un minuto antes de pedir otro.';

    public function __construct(private readonly AuthService $authService)
    {
    }

    #[OA\Post(
        path: '/api/v1/login',
        tags: ['Auth'],
        summary: 'Inicia sesión vía Sanctum (cookie + CSRF), no tokens Bearer',
        requestBody: new OA\RequestBody(required: true),
        responses: [
            new OA\Response(response: 200, description: 'Autenticado, cookie de sesión emitida'),
            new OA\Response(response: 401, description: 'Credenciales inválidas o cuenta bloqueada'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = $this->authService->autenticar(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->ip()
        );

        Auth::login($usuario);
        $request->session()->regenerate();

        return ApiResponse::item(new UsuarioResource($usuario->load('rol.permisos')));
    }

    #[OA\Post(path: '/api/v1/logout', tags: ['Auth'], summary: 'Cierra la sesión actual')]
    public function logout(Request $request): JsonResponse
{
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return ApiResponse::mensaje('Sesión cerrada.');
}

    #[OA\Get(path: '/api/v1/me', tags: ['Auth'], summary: 'Usuario autenticado actual')]
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::item(new UsuarioResource($request->user()->load('rol.permisos')));
    }

    #[OA\Post(
        path: '/api/v1/olvide-password',
        tags: ['Auth'],
        summary: 'Envía por SMTP un enlace de recuperación de contraseña (respuesta genérica: no revela si el correo existe)',
        responses: [
            new OA\Response(response: 200, description: 'Solicitud procesada'),
            new OA\Response(response: 503, description: 'El servidor de correo rechazó o no aceptó el envío'),
        ]
    )]
    public function solicitarRecuperacion(SolicitarRecuperacionRequest $request): JsonResponse
    {
        $email = $request->string('email')->trim()->toString();

        // Cuentas suspendidas: no se envía enlace (no podrían usarlo para entrar),
        // pero la respuesta es la misma para no revelar el estado de la cuenta.
        $titular = Usuario::query()->where('email', $email)->first();
        if ($titular !== null && $titular->estaSuspendido()) {
            Bitacora::registrar('usuarios', $titular->id, null, 'update', [
                'evento' => 'recuperacion_rechazada_cuenta_suspendida',
                'ip' => $request->ip(),
            ]);

            return ApiResponse::mensaje(self::MENSAJE_RECUPERACION);
        }

        try {
            // Password Broker nativo: genera el token (se guarda HASHEADO en
            // password_reset_tokens), y llama a Usuario::sendPasswordResetNotification(),
            // que envía el correo de forma SÍNCRONA por el mailer configurado.
            $estado = Password::sendResetLink(['email' => $email]);
        } catch (TransportExceptionInterface $e) {
            // El servidor SMTP no aceptó el mensaje: NO se responde "enviado".
            Log::error('Recuperación de contraseña: el servidor de correo rechazó el envío.', [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'puerto' => config('mail.mailers.smtp.port'),
                'error' => $e->getMessage(),
            ]);

            // El token ya se había creado: se elimina para que el usuario pueda
            // reintentar de inmediato (si no, el throttle del broker haría que
            // el siguiente intento respondiera "enviado" sin enviar nada).
            $broker = Password::broker();
            if ($usuario = $broker->getUser(['email' => $email])) {
                $broker->deleteToken($usuario);
            }

            return response()->json([
                'error' => [
                    'code' => 'CORREO_NO_ENVIADO',
                    'message' => 'No fue posible enviar el correo de recuperación en este momento. Intenta de nuevo en unos minutos o contacta al administrador del sistema.',
                    'details' => [],
                ],
            ], 503);
        }

        // Rastro operativo sin datos sensibles (ni token ni correo completo).
        Log::info('Recuperación de contraseña solicitada.', [
            'estado' => $estado,
            'mailer' => config('mail.default'),
            'ip' => $request->ip(),
        ]);

        if (config('mail.default') === 'log' && $estado === Password::RESET_LINK_SENT) {
            Log::warning('MAIL_MAILER=log: el correo de recuperación se escribió en storage/logs/laravel.log y NO se entregó a ningún buzón. Configure SMTP en el .env.');
        }

        // Respuesta idéntica para correo inexistente, enviado o en espera
        // (throttle de AUTH_PASSWORD_RESET_THROTTLE s): evita enumerar cuentas.
        if ($estado === Password::RESET_LINK_SENT && $titular !== null) {
            Bitacora::registrar('usuarios', $titular->id, null, 'update', [
                'evento' => 'recuperacion_password_solicitada',
                'ip' => $request->ip(),
            ]);
        }

        return ApiResponse::mensaje(self::MENSAJE_RECUPERACION);
    }

    #[OA\Post(
        path: '/api/v1/restablecer-password/validar',
        tags: ['Auth'],
        summary: 'Comprueba (sin consumirlo) si un enlace de recuperación sigue siendo válido',
        responses: [
            new OA\Response(response: 200, description: 'Token vigente'),
            new OA\Response(response: 422, description: 'Token inválido, expirado o ya utilizado'),
        ]
    )]
    public function validarTokenRecuperacion(ValidarTokenRecuperacionRequest $request): JsonResponse
    {
        $broker = Password::broker();
        $usuario = $broker->getUser(['email' => $request->string('email')->toString()]);

        if ($usuario === null || !$broker->tokenExists($usuario, $request->string('token')->toString())) {
            return $this->respuestaTokenInvalido();
        }

        return ApiResponse::mensaje('Enlace válido.');
    }

    #[OA\Post(
        path: '/api/v1/restablecer-password',
        tags: ['Auth'],
        summary: 'Aplica una nueva contraseña usando el token recibido por correo (un solo uso)',
        responses: [new OA\Response(response: 422, description: 'Token inválido, expirado, o no coincide con el correo')]
    )]
    public function restablecer(RestablecerPasswordRequest $request): JsonResponse
    {
        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($usuario, $password) {
                // ÚNICAMENTE se modifica el hash de la contraseña: rol, permisos,
                // estado y datos personales quedan intactos.
                $usuario->forceFill(['password_hash' => Hash::make($password)])->save();

                // Cierra las sesiones abiertas con la contraseña anterior
                // (p. ej. si alguien más la conocía). El broker elimina el token
                // justo después de este callback: el enlace ya no sirve otra vez.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->getAuthIdentifier())->delete();
                }

                $usuario->tokens()->delete();

                Log::info('Contraseña restablecida mediante enlace de recuperación.', ['usuario_id' => $usuario->getAuthIdentifier()]);
                Bitacora::registrar('usuarios', (int) $usuario->getAuthIdentifier(), (int) $usuario->getAuthIdentifier(), 'update', [
                    'evento' => 'password_restablecida_por_enlace',
                    'ip' => request()->ip(),
                ]);
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return $this->respuestaTokenInvalido();
        }

        return ApiResponse::mensaje('Contraseña actualizada. Ya puedes iniciar sesión con tu nueva contraseña.');
    }

    private function respuestaTokenInvalido(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'TOKEN_INVALIDO',
                'message' => 'El enlace de recuperación no es válido, ya se utilizó o expiró. Solicita uno nuevo.',
                'details' => [],
            ],
        ], 422);
    }
}
