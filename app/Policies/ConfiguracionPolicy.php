<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

class ConfiguracionPolicy
{
    public function gestionar(Usuario $actor): bool
    {
        // Gate::before ya deja pasar a Administrador; para cualquier otro
        // rol esto siempre es false — configuración general es exclusiva
        // de Administrador, sin excepción de permiso granular.
        return false;
    }
}