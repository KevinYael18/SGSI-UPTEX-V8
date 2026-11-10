<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Notifications\Notification;

/**
 * Canal personalizado (patrón oficial de Laravel Notifications) que
 * persiste en NUESTRA tabla `notificaciones` (diseñada en Fase 2 §5),
 * en vez de la tabla `notifications` genérica que trae Laravel por
 * defecto. Así se usa el sistema real de Notifications del framework
 * (clases, canales, colas) sin romper el diccionario de datos ya
 * aprobado.
 */
class BaseDatosSgsiChannel
{
    public function send(Usuario $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toBaseDatosSgsi')) {
            return;
        }

        $datos = $notification->toBaseDatosSgsi($notifiable);

        Notificacion::query()->create([
            'usuario_id' => $notifiable->id,
            'tipo' => $datos['tipo'],
            'datos' => $datos['datos'],
            'leida' => false,
        ]);
    }
}