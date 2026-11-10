<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoActivo: string
{
    case Hardware = 'hardware';
    case Software = 'software';
    case Informacion = 'informacion';
    case Servicio = 'servicio';
    case Infraestructura = 'infraestructura';
}