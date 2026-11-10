<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Contracts\RegistraBitacora;
use App\Models\ActivityLog;

class RegistrarActivityLogGenerico
{
    public function handle(RegistraBitacora $evento): void
    {
        ActivityLog::query()->create([
            'entidad' => $evento->entidad(),
            'entidad_id' => $evento->entidadId(),
            'usuario_id' => $evento->usuarioId(),
            'accion' => $evento->accion(),
            'cambios' => $evento->cambios(),
            'created_at' => now(),
        ]);
    }
}