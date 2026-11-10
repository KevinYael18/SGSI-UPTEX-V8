<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoUsuario: string
{
    case Activo = 'activo';
    case Suspendido = 'suspendido';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Suspendido => 'Suspendido',
        };
    }
}