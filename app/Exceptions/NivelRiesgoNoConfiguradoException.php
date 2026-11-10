<?php

declare(strict_types=1);

namespace App\Exceptions;

class NivelRiesgoNoConfiguradoException extends DomainException
{
    protected string $errorCode = 'RIESGO_MATRIZ_NO_CONFIGURADA';
    protected int $httpStatus = 422;

    public function __construct(int $probabilidad, int $impacto)
    {
        parent::__construct(
            "No existe una combinación configurada en la matriz de riesgo para probabilidad={$probabilidad}, impacto={$impacto}. Un Responsable SGSI debe completar la matriz antes de registrar este riesgo."
        );
        $this->details = ['probabilidad' => $probabilidad, 'impacto' => $impacto];
    }
}