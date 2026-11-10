<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Auditoria;
use App\Models\Usuario;

class AuditoriaPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('auditorias.ver');
    }

    public function view(Usuario $actor, Auditoria $auditoria): bool
    {
        return $actor->tienePermiso('auditorias.ver');
    }

    public function create(Usuario $actor): bool
    {
        return $actor->tienePermiso('auditorias.crear');
    }

    public function update(Usuario $actor, Auditoria $auditoria): bool
    {
        return $actor->tienePermiso('auditorias.editar') || $auditoria->auditor_id === $actor->id;
    }

    public function delete(Usuario $actor, Auditoria $auditoria): bool
    {
        return $actor->tienePermiso('auditorias.eliminar');
    }
}