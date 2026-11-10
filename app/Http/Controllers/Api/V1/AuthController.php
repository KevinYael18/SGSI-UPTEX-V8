<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RestablecerPasswordRequest;
use App\Http\Requests\SolicitarRecuperacionRequest;
use App\Http\Resources\UsuarioResource;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
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
        summary: 'Envía un enlace de recuperación de contraseña al correo indicado (respuesta siempre genérica, no revela si el correo existe)'
    )]
    public function solicitarRecuperacion(SolicitarRecuperacionRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return ApiResponse::mensaje('Si el correo existe en el sistema, se envió un enlace de recuperación.');
    }

    #[OA\Post(
        path: '/api/v1/restablecer-password',
        tags: ['Auth'],
        summary: 'Aplica una nueva contraseña usando el token recibido por correo',
        responses: [new OA\Response(response: 422, description: 'Token inválido, expirado, o no coincide con el correo')]
    )]
    public function restablecer(RestablecerPasswordRequest $request): JsonResponse
    {
        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($usuario, $password) {
                $usuario->forceFill(['password_hash' => Hash::make($password)])->save();
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return response()->json([
                'error' => [
                    'code' => 'TOKEN_INVALIDO',
                    'message' => 'El enlace de recuperación es inválido o ya expiró.',
                    'details' => [],
                ],
            ], 422);
        }

        return ApiResponse::mensaje('Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}