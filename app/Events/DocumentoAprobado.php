<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\GeneraNotificacion;
use App\Events\Contracts\RegistraBitacora;
use App\Models\Documento;
use App\Notifications\DocumentoAprobadoNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DocumentoAprobado implements RegistraBitacora, GeneraNotificacion
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Documento $documento, public readonly int $usuarioId)
    {
    }

    public function entidad(): string { return 'documentos'; }
    public function entidadId(): int { return $this->documento->id; }
    public function usuarioId(): ?int { return $this->usuarioId; }
    public function accion(): string { return 'update'; }
    public function cambios(): array { return ['estado' => 'aprobado']; }

    public function destinatarios(): Collection
    {
        $autor = $this->documento->versionActual()?->creadoPor;

        return $autor ? collect([$autor]) : collect();
    }

    public function notificacion(): Notification
    {
        return new DocumentoAprobadoNotification($this->documento);
    }
}