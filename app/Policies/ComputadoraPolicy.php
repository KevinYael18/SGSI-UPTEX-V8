<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Computadora;
use App\Models\Usuario;

/** Una computadora se autoriza exactamente igual que su Activo asociado. */
class ComputadoraPolicy
{
    public function view(Usuario $actor, Computadora $computadora): bool
    {
        return $computadora->activo !== null && (new ActivoPolicy())->view($actor, $computadora->activo);
    }

    public function update(Usuario $actor, Computadora $computadora): bool
    {
        return $computadora->activo !== null && (new ActivoPolicy())->update($actor, $computadora->activo);
    }
}
