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
        return $actor->tienePermiso('usuarios.editar');
    }

    public function suspender(Usuario $actor, Usuario $objetivo): bool
    {
        return $actor->tienePermiso('usuarios.suspender') && $actor->id !== $objetivo->id;
    }
}