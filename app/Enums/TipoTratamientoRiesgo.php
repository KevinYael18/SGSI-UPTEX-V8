<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cuatro tratamientos estándar de ISO/IEC 27005.
 */
enum TipoTratamientoRiesgo: string
{
    case Mitigar = 'mitigar';
    case Transferir = 'transferir';
    case Evitar = 'evitar';
    case Aceptar = 'aceptar';
}