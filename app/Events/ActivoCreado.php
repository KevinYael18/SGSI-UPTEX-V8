<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Activo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivoCreado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Activo $activo, public readonly int $usuarioId)
    {
    }

    public function entidad(): string { return 'activos'; }
    public function entidadId(): int { return $this->activo->id; }
    public function usuarioId(): ?int { return $this->usuarioId; }
    public function accion(): string { return 'create'; }
    public function cambios(): array { return $this->activo->only(['nombre', 'tipo', 'departamento_id']); }
}