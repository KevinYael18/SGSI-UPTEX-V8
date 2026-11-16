<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('usuarios.ver');
    }

    public function update(Usuario $actor, Usuario $objetivo): bool
    {
        // Un rol al que se le delegue `usuarios.editar` no puede tocar cuentas de
        // Administrador (cambiar su correo o contraseña = tomar la cuenta).
        // El Administrador pasa por Gate::before.
        return $actor->tienePermiso('usuarios.editar') && !self::esAdministrador($objetivo);
    }

    /** Solo un Administrador puede otorgar el rol Administrador (escalamiento vertical). */
    public static function puedeAsignarRol(Usuario $actor, mixed $rolId): bool
    {
        if ($rolId === null || $rolId === '' || self::esAdministrador($actor)) {
            return true;
        }

        return \App\Models\Rol::query()->whereKey($rolId)->value('nombre') !== 'Administrador';
    }

    public static function esAdministrador(Usuario $usuario): bool
    {
        return $usuario->rol?->nombre === 'Administrador';
    }

    public function suspender(Usuario $actor, Usuario $objetivo): bool
    {
        return $actor->tienePermiso('usuarios.suspender') && $actor->id !== $objetivo->id;
    }
}