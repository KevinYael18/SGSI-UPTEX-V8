<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Contracts\GeneraNotificacion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * ShouldQueue: el envío (BD + correo vía Mailpit/SMTP) ocurre en background,
 * nunca bloquea la respuesta HTTP del endpoint que disparó el evento.
 */
class NotificarPorEventoDominio implements ShouldQueue
{
    public function handle(GeneraNotificacion $evento): void
    {
        Notification::send($evento->destinatarios(), $evento->notificacion());
    }
}