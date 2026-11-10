<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Clase base de todas las excepciones de reglas de negocio del dominio.
 * El Handler central intercepta esta clase (y sus hijas) para responder
 * siempre con el mismo formato JSON, sin exponer trazas internas.
 */
abstract class DomainException extends Exception
{
    protected string $errorCode;
    protected int $httpStatus = 422;
    protected array $details = [];

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function details(): array
    {
        return $this->details;
    }
}