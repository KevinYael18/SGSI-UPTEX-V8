<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * La cuenta existe pero un administrador la suspendió: no puede iniciar
 * sesión ni consumir ningún endpoint protegido.
 */
class CuentaSuspendidaException extends DomainException
{
    protected string $errorCode = 'CUENTA_SUSPENDIDA';
    protected int $httpStatus = 403;

    public function __construct()
    {
        parent::__construct('Tu cuenta está suspendida. Comunícate con el administrador del sistema para más información.');
    }
}
