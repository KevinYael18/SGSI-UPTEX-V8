<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Events\IntentoFallidoLogin;
use App\Exceptions\CuentaSuspendidaException;
use App\Support\Bitacora;
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
        // Clave normalizada: «Admin@…» y «admin@…» comparten el mismo contador
        // (MySQL compara correos sin distinguir mayúsculas).
        $throttleKey = 'login:' . mb_strtolower(trim($email)) . ':' . $ip;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw new AuthenticationException('Demasiados intentos. Intente de nuevo en unos minutos.');
        }

        $usuario = $this->usuarios->buscarPorEmail($email);

        // Tiempo constante: si la cuenta no existe se verifica igualmente contra un
        // hash de relleno, para que la latencia no revele qué correos existen
        // (enumeración por tiempo, CWE-208).
        $passwordValida = Hash::check($password, $usuario?->getAuthPassword() ?? self::hashDeRelleno());

        if ($usuario === null || $usuario->bloqueado() || !$passwordValida) {
            RateLimiter::hit($throttleKey, 60);
            event(new IntentoFallidoLogin($email, $ip));
            if ($usuario !== null) {
                // Trazabilidad: solo se registra para cuentas existentes (sin la contraseña).
                Bitacora::registrar('usuarios', $usuario->id, null, 'update', [
                    'evento' => $usuario->bloqueado() ? 'login_rechazado_cuenta_bloqueada' : 'login_fallido',
                    'ip' => $ip,
                ]);
            }
            throw new AuthenticationException('Credenciales inválidas.');
        }

        // La suspensión se comprueba DESPUÉS de validar la contraseña: así un
        // tercero no puede averiguar qué cuentas existen o están suspendidas.
        if ($usuario->estado !== EstadoUsuario::Activo) {
            Bitacora::registrar('usuarios', $usuario->id, null, 'update', [
                'evento' => 'login_rechazado_cuenta_suspendida',
                'ip' => $ip,
            ]);
            throw new CuentaSuspendidaException();
        }

        RateLimiter::clear($throttleKey);
        $this->usuarios->reiniciarIntentos($usuario);
        event(new UsuarioAutenticado($usuario));

        return $usuario;
    }

    /**
     * Hash bcrypt fijo de una cadena aleatoria desechable (nadie conoce su
     * contraseña), con el mismo costo que la aplicación: verificar contra él
     * tarda lo mismo que verificar una cuenta real.
     */
    private static function hashDeRelleno(): string
    {
        return '$2y$12$18ebhsl.W/hdUH.GB./8XOv/N8YFozpTiiPIMl58ZSnW.ljf8MMfu';
    }
}
