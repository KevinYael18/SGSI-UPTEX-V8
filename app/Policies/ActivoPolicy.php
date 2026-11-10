<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activo;
use App\Models\Usuario;

class ActivoPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('activos.ver');
    }

    /**
     * Autorización fina por registro: aplica el scoping por departamento
     * aprobado para el rol Consulta (Ronda 2, mejora #3).
     */
    public function view(Usuario $actor, Activo $activo): bool
    {
        if (!$actor->tienePermiso('activos.ver')) {
            return false;
        }

        if ($actor->rol->nombre === 'Consulta') {
            return $actor->departamentosConAlcance()->where('departamentos.id', $activo->departamento_id)->exists();
        }

        return true;
    }

    public function create(Usuario $actor): bool
    {
        return $actor->tienePermiso('activos.crear');
    }

    public function update(Usuario $actor, Activo $activo): bool
    {
        return $actor->tienePermiso('activos.editar')
            || ($actor->tienePermiso('activos.editar_propio') && $activo->responsable_id === $actor->id);
    }

    public function delete(Usuario $actor, Activo $activo): bool
    {
        return $actor->tienePermiso('activos.eliminar');
    }
}