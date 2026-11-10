<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccionCorrectiva;
use App\Models\Usuario;

class AccionCorrectivaPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('auditorias.ver');
    }

    public function view(Usuario $actor, AccionCorrectiva $accion): bool
    {
        return $actor->tienePermiso('auditorias.ver') || $accion->responsable_id === $actor->id;
    }

    public function update(Usuario $actor, AccionCorrectiva $accion): bool
    {
        return $actor->tienePermiso('auditorias.editar') || $accion->responsable_id === $actor->id;
    }
}