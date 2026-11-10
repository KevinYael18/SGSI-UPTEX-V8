<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Hallazgo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HallazgoRegistrado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Hallazgo $hallazgo, public readonly int $usuarioId)
    {
    }

    public function entidad(): string { return 'hallazgos'; }
    public function entidadId(): int { return $this->hallazgo->id; }
    public function usuarioId(): ?int { return $this->usuarioId; }
    public function accion(): string { return 'create'; }
    public function cambios(): array { return $this->hallazgo->only(['auditoria_id', 'severidad']); }
}