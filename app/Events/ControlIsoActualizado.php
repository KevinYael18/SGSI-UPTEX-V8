<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\ControlIso;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ControlIsoActualizado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ControlIso $control,
        public readonly string $estadoAnterior,
        public readonly int $usuarioId
    ) {
    }

    public function entidad(): string { return 'controles_iso'; }
    public function entidadId(): int { return $this->control->id; }
    public function usuarioId(): ?int { return $this->usuarioId; }
    public function accion(): string { return 'update'; }
    public function cambios(): array
    {
        return ['antes' => $this->estadoAnterior, 'despues' => $this->control->estado_implementacion->value];
    }
}