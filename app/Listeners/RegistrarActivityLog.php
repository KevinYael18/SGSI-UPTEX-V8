<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UsuarioAutenticado;
use App\Models\ActivityLog;

/**
 * Listener transversal: cualquier evento de dominio relevante puede
 * despachar esto para dejar traza en la bitácora técnica (activity_log),
 * separada de "Auditorías ISO" (Fase 1.5 §10).
 */
class RegistrarActivityLog
{
    public function handle(UsuarioAutenticado $event): void
    {
        ActivityLog::query()->create([
            'entidad' => 'usuarios',
            'entidad_id' => $event->usuario->id,
            'usuario_id' => $event->usuario->id,
            'accion' => 'update',
            'cambios' => ['evento' => 'login_exitoso'],
            'created_at' => now(),
        ]);
    }
}