<?php

declare(strict_types=1);

namespace App\Exceptions;

class TransicionEstadoInvalidaException extends DomainException
{
    protected string $errorCode = 'TRANSICION_ESTADO_INVALIDA';
    protected int $httpStatus = 422;

    public function __construct(string $entidad, string $estadoActual, string $estadoDestino)
    {
        parent::__construct(
            "No se puede cambiar {$entidad} de estado '{$estadoActual}' a '{$estadoDestino}': la transición no está permitida."
        );
        $this->details = ['entidad' => $entidad, 'estado_actual' => $estadoActual, 'estado_destino' => $estadoDestino];
    }
}