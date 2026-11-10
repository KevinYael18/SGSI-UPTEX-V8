<?php

declare(strict_types=1);

namespace App\Enums;

enum NivelRiesgo: string
{
    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';
    case Critico = 'critico';
}