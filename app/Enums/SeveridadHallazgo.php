<?php

declare(strict_types=1);

namespace App\Enums;

enum SeveridadHallazgo: string
{
    case Menor = 'menor';
    case Mayor = 'mayor';
    case Critica = 'critica';
}