<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\GeneraNotificacion;
use App\Models\AccionCorrectiva;
use App\Notifications\AccionCorrectivaVencidaNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AccionCorrectivaVencida implements GeneraNotificacion
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AccionCorrectiva $accion)
    {
    }

    public function destinatarios(): Collection
    {
        return collect([$this->accion->responsable])->filter();
    }

    public function notificacion(): Notification
    {
        return new AccionCorrectivaVencidaNotification($this->accion);
    }
}