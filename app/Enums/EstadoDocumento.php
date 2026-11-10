<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Workflow documental completo (ampliado en Fase 3.2): Borrador -> En
 * revisión -> Aprobado -> Publicado -> Obsoleto.
 */
enum EstadoDocumento: string
{
    case Borrador = 'borrador';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Publicado = 'publicado';
    case Obsoleto = 'obsoleto';

    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Borrador => [self::EnRevision],
            self::EnRevision => [self::Aprobado, self::Borrador], // puede regresarse a borrador si se rechaza
            self::Aprobado => [self::Publicado, self::Obsoleto],
            self::Publicado => [self::Obsoleto],
            self::Obsoleto => [],
        };
    }
}