<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ControlIso;
use App\Models\Usuario;

class ControlIsoPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('cumplimiento.ver');
    }

    public function update(Usuario $actor, ControlIso $control): bool
    {
        return $actor->tienePermiso('cumplimiento.editar');
    }
}