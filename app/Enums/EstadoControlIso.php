<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoControlIso: string
{
    case NoImplementado = 'no_implementado';
    case EnProceso = 'en_proceso';
    case Implementado = 'implementado';
    case NoAplica = 'no_aplica';
}