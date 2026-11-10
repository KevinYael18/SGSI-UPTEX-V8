<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado genérico reutilizado por No Conformidades y Acciones Correctivas.
 */
enum EstadoAuditoriaGenerico: string
{
    case Abierta = 'abierta';
    case EnProceso = 'en_proceso';
    case Cerrada = 'cerrada';
    case Vencida = 'vencida';
}