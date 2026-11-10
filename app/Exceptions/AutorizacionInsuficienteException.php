<?php

declare(strict_types=1);

namespace App\Exceptions;

class AutorizacionInsuficienteException extends DomainException
{
    protected string $errorCode = 'AUTORIZACION_INSUFICIENTE';
    protected int $httpStatus = 403;

    public function __construct(string $accion)
    {
        parent::__construct("No tiene permisos para realizar la acción: {$accion}.");
    }
}