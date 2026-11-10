<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Documento;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentoCreado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Documento $documento, private readonly ?int $usuarioIdQueActuo)
    {
    }

    public function entidad(): string { return 'documentos'; }
    public function entidadId(): int { return $this->documento->id; }
    public function usuarioId(): ?int { return $this->usuarioIdQueActuo; }
    public function accion(): string { return 'create'; }
    public function cambios(): array { return $this->documento->only(['titulo', 'estado']); }
}