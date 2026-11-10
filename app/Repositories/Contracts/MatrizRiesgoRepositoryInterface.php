<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\NivelRiesgo;

interface MatrizRiesgoRepositoryInterface
{
    public function resolverNivel(int $probabilidad, int $impacto): ?NivelRiesgo;
}