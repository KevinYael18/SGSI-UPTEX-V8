<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\GeneraNotificacion;
use App\Events\Contracts\RegistraBitacora;
use App\Models\DocumentoVersion;
use App\Models\Usuario;
use App\Notifications\NuevaVersionNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NuevaVersionCreada implements RegistraBitacora, GeneraNotificacion
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly DocumentoVersion $version, public readonly int $usuarioId)
    {
    }

    public function entidad(): string { return 'documentos'; }
    public function entidadId(): int { return $this->version->documento_id; }
    public function usuarioId(): ?int { return $this->usuarioId; }
    public function accion(): string { return 'update'; }
    public function cambios(): array { return ['nueva_version' => $this->version->numero_version]; }

    public function destinatarios(): Collection
    {
        // Notifica a quienes tienen permiso de aprobar documentos.
        return Usuario::query()
            ->whereHas('rol.permisos', fn ($q) => $q->where('clave', 'documentos.editar'))
            ->get();
    }

    public function notificacion(): Notification
    {
        return new NuevaVersionNotification($this->version);
    }
}