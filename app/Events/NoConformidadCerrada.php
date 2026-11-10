<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\NoConformidad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoConformidadCerrada implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly NoConformidad $noConformidad, public readonly int $usuarioId)
    {
    }

    public function entidad(): string { return 'no_conformidades'; }
    public function entidadId(): int { return $this->noConformidad->id; }
    public function usuarioId(): ?int { return $this->usuarioId; }
    public function accion(): string { return 'update'; }
    public function cambios(): array { return ['estado' => 'cerrada']; }
}