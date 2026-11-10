<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\NivelRiesgo;
use App\Exceptions\NivelRiesgoNoConfiguradoException;
use App\Repositories\Contracts\MatrizRiesgoRepositoryInterface;

/**
 * SRP: única responsabilidad es traducir (probabilidad, impacto) -> nivel,
 * consultando la matriz configurable en BD (Fase 1.5 §3) en vez de un
 * switch/if hardcodeado. RiesgoService delega aquí el cálculo.
 */
final class MatrizRiesgoResolver
{
    public function __construct(private readonly MatrizRiesgoRepositoryInterface $matriz)
    {
    }

    public function resolver(int $probabilidad, int $impacto): NivelRiesgo
    {
        $nivel = $this->matriz->resolverNivel($probabilidad, $impacto);

        if ($nivel === null) {
            throw new NivelRiesgoNoConfiguradoException($probabilidad, $impacto);
        }

        return $nivel;
    }
}