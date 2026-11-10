<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\GeneraNotificacion;
use App\Models\Riesgo;
use App\Models\Usuario;
use App\Notifications\RiesgoCriticoNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RiesgoCriticoDetectado implements GeneraNotificacion
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Riesgo $riesgo)
    {
    }

    public function destinatarios(): Collection
    {
        return Usuario::query()->whereHas('rol', fn ($q) => $q->where('nombre', 'Responsable SGSI'))->get();
    }

    public function notificacion(): Notification
    {
        return new RiesgoCriticoNotification($this->riesgo);
    }
}