<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

class DashboardPolicy
{
    public function ver(Usuario $actor): bool
    {
        return $actor->tienePermiso('dashboard.ver');
    }
}