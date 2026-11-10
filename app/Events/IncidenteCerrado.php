<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Incidente;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidenteCerrado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Incidente $incidente, private readonly ?int $usuarioIdQueActuo = null)
    {
    }

    public function entidad(): string { return 'incidentes'; }
    public function entidadId(): int { return $this->incidente->id; }
    public function usuarioId(): ?int { return $this->usuarioIdQueActuo; }
    public function accion(): string { return 'update'; }
    public function cambios(): array { return ['estado' => 'cerrado']; }
}