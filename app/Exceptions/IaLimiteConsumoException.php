<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Se alcanzó el presupuesto diario de tokens del asistente (por usuario o
 * global). El Handler la convierte en {"error": {"code": "IA_LIMITE_CONSUMO"}} con HTTP 429.
 */
class IaLimiteConsumoException extends DomainException
{
    protected string $errorCode = 'IA_LIMITE_CONSUMO';
    protected int $httpStatus = 429;

    public function __construct(string $mensaje = 'Se alcanzó el límite diario de uso de SGSI Intelligence Assistant. Intente de nuevo mañana.')
    {
        parent::__construct($mensaje);
    }
}
