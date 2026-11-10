<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\IntentoFallidoLogin;
use App\Events\UsuarioAutenticado;
use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SRP: esta clase solo resuelve "¿son válidas estas credenciales?" y las
 * consecuencias de negocio de esa respuesta (bloqueo, evento, bitácora).
 * No conoce nada de HTTP; el Controller traduce su resultado a cookies/JSON.
 */
class AuthService
{
    public function __construct(private readonly UsuarioRepositoryInterface $usuarios)
    {
    }

    public function autenticar(string $email, string $password, string $ip): Usuario
    {
        $throttleKey = "login:{$email}:{$ip}";

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw new AuthenticationException('Demasiados intentos. Intente de nuevo en unos minutos.');
        }

        $usuario = $this->usuarios->buscarPorEmail($email);

        if ($usuario === null || $usuario->bloqueado() || !Hash::check($password, $usuario->getAuthPassword())) {
            RateLimiter::hit($throttleKey, 60);
            event(new IntentoFallidoLogin($email, $ip));
            throw new AuthenticationException('Credenciales inválidas.');
        }

        RateLimiter::clear($throttleKey);
        $this->usuarios->reiniciarIntentos($usuario);
        event(new UsuarioAutenticado($usuario));

        return $usuario;
    }
}