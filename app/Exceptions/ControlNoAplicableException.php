<?php

declare(strict_types=1);

namespace App\Exceptions;

class ControlNoAplicableException extends DomainException
{
    protected string $errorCode = 'CONTROL_MARCADO_NO_APLICA';
    protected int $httpStatus = 422;

    public function __construct(string $codigo)
    {
        parent::__construct("El control {$codigo} está marcado como 'No aplica' y no puede vincularse a activos o riesgos. Cambie su estado primero.");
        $this->details = ['codigo' => $codigo];
    }
}