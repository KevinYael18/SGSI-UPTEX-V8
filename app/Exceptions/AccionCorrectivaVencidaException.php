<?php

declare(strict_types=1);

namespace App\Exceptions;

class AccionCorrectivaVencidaException extends DomainException
{
    protected string $errorCode = 'ACCION_CORRECTIVA_VENCIDA';
    protected int $httpStatus = 422;

    public function __construct(int $accionId)
    {
        parent::__construct("La acción correctiva #{$accionId} está vencida y debe reprogramarse antes de continuar su seguimiento normal.");
        $this->details = ['accion_correctiva_id' => $accionId];
    }
}