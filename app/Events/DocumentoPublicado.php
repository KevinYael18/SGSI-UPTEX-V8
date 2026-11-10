<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Documento;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentoPublicado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Documento $documento, public readonly int $usuarioId)
    {
    }

    public function entidad(): string { return 'documentos'; }
    public function entidadId(): int { return $this->documento->id; }
    public function usuarioId(): ?int { return $this->usuarioId; }
    public function accion(): string { return 'update'; }
    public function cambios(): array { return ['estado' => 'publicado']; }
}