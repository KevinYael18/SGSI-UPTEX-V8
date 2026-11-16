<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * El proveedor de IA no respondió, respondió con error o devolvió algo que no
 * se pudo interpretar. El Handler la convierte en
 * {"error": {"code": "IA_NO_DISPONIBLE", ...}} con HTTP 503.
 */
class IaNoDisponibleException extends DomainException
{
    protected string $errorCode = 'IA_NO_DISPONIBLE';
    protected int $httpStatus = 503;

    public function __construct(string $mensaje = 'El servicio de IA no está disponible en este momento.')
    {
        parent::__construct($mensaje);
    }
}
