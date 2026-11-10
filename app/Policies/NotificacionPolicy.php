<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notificacion;
use App\Models\Usuario;

class NotificacionPolicy
{
    /**
     * Nota: Gate::before (AuthServiceProvider) da acceso total al rol
     * Administrador sobre CUALQUIER Policy, incluida esta — es la única
     * excepción real a "nadie marca notificaciones ajenas". Se documenta
     * aquí para que no sea una sorpresa al auditar permisos.
     */
    public function update(Usuario $actor, Notificacion $notificacion): bool
    {
        return $notificacion->usuario_id === $actor->id;
    }
}