<?php

declare(strict_types=1);

namespace App\Exceptions;

/** El documento no se puede leer para análisis (formato no soportado, vacío o ilegible). */
class IaDocumentoNoProcesableException extends DomainException
{
    protected string $errorCode = 'IA_DOCUMENTO_NO_PROCESABLE';
    protected int $httpStatus = 422;
}
