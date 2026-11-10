<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface ConfiguracionRepositoryInterface extends RepositoryInterface
{
    public function obtenerPorClave(string $clave): ?string;

    public function establecer(string $clave, string $valor): void;
}