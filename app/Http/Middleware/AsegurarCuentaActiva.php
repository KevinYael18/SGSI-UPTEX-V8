<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\EstadoUsuario;
use App\Exceptions\CuentaSuspendidaException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control CENTRALIZADO del estado de la cuenta en cada petición autenticada.
 *
 * Se ejecuta después de `auth:sanctum`. El usuario se vuelve a leer de la base
 * de datos en cada petición (Sanctum/SessionGuard lo cargan por id), así que una
 * suspensión surte efecto en la siguiente petición aunque la sesión o el token
 * se hayan emitido antes. No depende del frontend.
 */
class AsegurarCuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && $usuario->estado !== EstadoUsuario::Activo) {
            // Sesión web (cookie Sanctum SPA): se cierra e invalida.
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            // Token Bearer (si se usara): se revoca el token con el que llegó.
            $token = $usuario->currentAccessToken();
            if ($token !== null && method_exists($token, 'delete')) {
                $token->delete();
            }

            throw new CuentaSuspendidaException();
        }

        return $next($request);
    }
}
