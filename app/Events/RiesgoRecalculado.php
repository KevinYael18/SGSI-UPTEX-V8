<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Riesgo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiesgoRecalculado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Riesgo $riesgo,
        private readonly string $accionRealizada,
        private readonly ?int $usuarioIdQueActuo,
    ) {
    }

    public function entidad(): string { return 'riesgos'; }
    public function entidadId(): int { return $this->riesgo->id; }
    public function usuarioId(): ?int { return $this->usuarioIdQueActuo; }
    public function accion(): string { return $this->accionRealizada; }

    public function cambios(): array
    {
        return [
            'probabilidad' => $this->riesgo->probabilidad,
            'impacto' => $this->riesgo->impacto,
            'nivel_calculado' => $this->riesgo->nivel_calculado?->value,
            'tipo_tratamiento' => $this->riesgo->tipo_tratamiento?->value,
        ];
    }
}