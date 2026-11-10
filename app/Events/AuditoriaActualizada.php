<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Auditoria;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditoriaActualizada implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Auditoria $auditoria,
        private readonly string $accionRealizada,
        private readonly ?int $usuarioIdQueActuo,
    ) {
    }

    public function entidad(): string { return 'auditorias'; }
    public function entidadId(): int { return $this->auditoria->id; }
    public function usuarioId(): ?int { return $this->usuarioIdQueActuo; }
    public function accion(): string { return $this->accionRealizada; }
    public function cambios(): array { return $this->auditoria->only(['alcance', 'fecha_inicio', 'fecha_fin', 'auditor_id']); }
}