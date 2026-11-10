<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Las 4 categorías del Anexo A de ISO/IEC 27001:2022 (93 controles: 37+8+14+34).
 */
enum CategoriaControlIso: string
{
    case Organizacional = 'organizacional';
    case Personas = 'personas';
    case Fisico = 'fisico';
    case Tecnologico = 'tecnologico';
}
