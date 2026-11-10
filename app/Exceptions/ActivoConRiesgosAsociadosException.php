<?php

declare(strict_types=1);

namespace App\Exceptions;

class ActivoConRiesgosAsociadosException extends DomainException
{
    protected string $errorCode = 'ACTIVO_CON_RIESGOS_ABIERTOS';
    protected int $httpStatus = 409;

    public function __construct(int $activoId, int $riesgosAbiertos)
    {
        parent::__construct(
            "El activo #{$activoId} tiene {$riesgosAbiertos} riesgo(s) sin cerrar y no puede eliminarse. Trate o cierre los riesgos asociados primero."
        );
        $this->details = ['activo_id' => $activoId, 'riesgos_abiertos' => $riesgosAbiertos];
    }
}