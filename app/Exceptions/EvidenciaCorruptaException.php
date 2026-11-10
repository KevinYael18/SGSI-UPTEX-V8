<?php

declare(strict_types=1);

namespace App\Exceptions;

class EvidenciaCorruptaException extends DomainException
{
    protected string $errorCode = 'EVIDENCIA_HASH_NO_COINCIDE';
    protected int $httpStatus = 422;

    public function __construct()
    {
        parent::__construct('El hash SHA-256 calculado no coincide con el archivo almacenado; la evidencia pudo corromperse en la subida.');
    }
}