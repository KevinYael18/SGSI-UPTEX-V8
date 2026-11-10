<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Incidente;
use App\Models\Usuario;

class IncidentePolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('incidentes.ver');
    }

    public function view(Usuario $actor, Incidente $incidente): bool
    {
        return $actor->tienePermiso('incidentes.ver');
    }

    public function create(Usuario $actor): bool
    {
        return $actor->tienePermiso('incidentes.crear');
    }

    public function update(Usuario $actor, Incidente $incidente): bool
    {
        return $actor->tienePermiso('incidentes.gestionar')
            || $incidente->activo?->responsable_id === $actor->id;
    }

    public function delete(Usuario $actor, Incidente $incidente): bool
    {
        return $actor->tienePermiso('incidentes.eliminar');
    }
}