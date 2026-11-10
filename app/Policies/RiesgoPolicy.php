<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Riesgo;
use App\Models\Usuario;

class RiesgoPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('riesgos.ver');
    }

    public function view(Usuario $actor, Riesgo $riesgo): bool
    {
        return $actor->tienePermiso('riesgos.ver');
    }

    public function create(Usuario $actor): bool
    {
        return $actor->tienePermiso('riesgos.crear');
    }

    public function update(Usuario $actor, Riesgo $riesgo): bool
    {
        return $actor->tienePermiso('riesgos.editar');
    }

    public function delete(Usuario $actor, Riesgo $riesgo): bool
    {
        return $actor->tienePermiso('riesgos.eliminar');
    }
}