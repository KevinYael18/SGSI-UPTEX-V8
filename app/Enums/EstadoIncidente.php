<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoIncidente: string
{
    case Abierto = 'abierto';
    case EnAnalisis = 'en_analisis';
    case Contenido = 'contenido';
    case Resuelto = 'resuelto';
    case Cerrado = 'cerrado';

    /**
     * Transiciones válidas de estado (regla de negocio explícita, no implícita).
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Abierto => [self::EnAnalisis, self::Cerrado],
            self::EnAnalisis => [self::Contenido, self::Cerrado],
            self::Contenido => [self::Resuelto],
            self::Resuelto => [self::Cerrado],
            self::Cerrado => [],
        };
    }
}