<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\RegistraBitacora;
use App\Models\Usuario;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cambio realizado por un usuario sobre SU PROPIO perfil (datos, foto o
 * contraseña). Deja traza en la bitácora mediante el listener genérico.
 * Nunca incluye contraseñas ni hashes en `cambios`.
 */
class PerfilActualizado implements RegistraBitacora
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Usuario $usuario,
        public readonly array $cambios
    ) {
    }

    public function entidad(): string { return 'usuarios'; }
    public function entidadId(): int { return $this->usuario->id; }
    public function usuarioId(): ?int { return $this->usuario->id; }
    public function accion(): string { return 'update'; }
    public function cambios(): array { return $this->cambios; }
}
