<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Models\Usuario;
use App\Support\Bitacora;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suspensión y reactivación de cuentas.
 *
 * Suspender: cambia el estado, cierra TODAS las sesiones del usuario y revoca
 * sus tokens (efecto inmediato), y deja traza en la bitácora con el
 * administrador, la fecha y el motivo.
 * Reactivar: devuelve el acceso conservando los datos; las sesiones/tokens
 * cerrados NO se restauran (deberá iniciar sesión de nuevo).
 */
class GestionCuentasService
{
    public function suspender(Usuario $objetivo, Usuario $administrador, ?string $motivo): Usuario
    {
        // La política `suspender` ya lo prohíbe, pero Gate::before concede todo
        // al rol Administrador y la anulaba: la regla se aplica aquí explícitamente.
        if ($objetivo->is($administrador)) {
            throw ValidationException::withMessages(['usuario' => 'No puedes suspender tu propia cuenta.']);
        }
        if ($objetivo->rol?->nombre === 'Administrador' && self::administradoresActivos() <= 1) {
            throw ValidationException::withMessages(['usuario' => 'No se puede suspender al único Administrador activo.']);
        }

        return DB::transaction(function () use ($objetivo, $administrador, $motivo) {
            $objetivo->forceFill([
                'estado' => EstadoUsuario::Suspendido->value,
                'suspendido_at' => now(),
                'suspendido_por' => $administrador->id,
                'motivo_suspension' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
            ])->save();

            $sesiones = $this->cerrarSesiones($objetivo);
            $tokens = $objetivo->tokens()->delete();

            Bitacora::registrar('usuarios', $objetivo->id, $administrador->id, 'update', [
                'evento' => 'cuenta_suspendida',
                'estado' => ['antes' => EstadoUsuario::Activo->value, 'despues' => EstadoUsuario::Suspendido->value],
                'motivo' => $objetivo->motivo_suspension,
                'sesiones_cerradas' => $sesiones,
                'tokens_revocados' => $tokens,
            ]);

            return $objetivo->fresh();
        });
    }

    public function reactivar(Usuario $objetivo, Usuario $administrador): Usuario
    {
        return DB::transaction(function () use ($objetivo, $administrador) {
            $motivoAnterior = $objetivo->motivo_suspension;
            $objetivo->forceFill([
                'estado' => EstadoUsuario::Activo->value,
                'suspendido_at' => null,
                'suspendido_por' => null,
                'motivo_suspension' => null,
                // Se limpia también el bloqueo temporal por intentos fallidos.
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            Bitacora::registrar('usuarios', $objetivo->id, $administrador->id, 'update', [
                'evento' => 'cuenta_reactivada',
                'estado' => ['antes' => EstadoUsuario::Suspendido->value, 'despues' => EstadoUsuario::Activo->value],
                'motivo_suspension_previo' => $motivoAnterior,
            ]);

            return $objetivo->fresh();
        });
    }

    public static function administradoresActivos(): int
    {
        return Usuario::query()->where('estado', 'activo')->whereHas('rol', fn ($q) => $q->where('nombre', 'Administrador'))->count();
    }

    /** Cierra sesiones web y revoca tokens de una cuenta (p. ej. tras restablecer su contraseña). */
    public function cerrarTodasLasSesiones(Usuario $usuario): void
    {
        $this->cerrarSesiones($usuario);
        $usuario->tokens()->delete();
    }

    private function cerrarSesiones(Usuario $usuario): int
    {
        if (config('session.driver') !== 'database') {
            return 0; // otros drivers: el middleware AsegurarCuentaActiva corta el acceso en la siguiente petición
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
    }
}
