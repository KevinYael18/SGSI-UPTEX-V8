<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\GeneraNotificacion;
use App\Events\Contracts\RegistraBitacora;
use App\Models\Incidente;
use App\Notifications\IncidenteReportadoNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class IncidenteReportado implements GeneraNotificacion, RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Incidente $incidente, private readonly ?int $usuarioIdQueActuo = null)
    {
    }

    public function destinatarios(): Collection
    {
        $responsable = $this->incidente->activo?->responsable;

        return $responsable ? collect([$responsable]) : collect();
    }

    public function notificacion(): Notification
    {
        return new IncidenteReportadoNotification($this->incidente);
    }

    public function entidad(): string { return 'incidentes'; }
    public function entidadId(): int { return $this->incidente->id; }
    public function usuarioId(): ?int { return $this->usuarioIdQueActuo; }
    public function accion(): string { return 'create'; }
    public function cambios(): array { return $this->incidente->only(['titulo', 'activo_id']); }
}