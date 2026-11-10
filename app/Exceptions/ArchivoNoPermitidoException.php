<?php

declare(strict_types=1);

namespace App\Exceptions;

class ArchivoNoPermitidoException extends DomainException
{
    protected string $errorCode = 'ARCHIVO_NO_PERMITIDO';
    protected int $httpStatus = 422;

    public function __construct(string $mimeType)
    {
        parent::__construct("El tipo de archivo '{$mimeType}' no está permitido como evidencia.");
        $this->details = ['mime_type' => $mimeType];
    }
}