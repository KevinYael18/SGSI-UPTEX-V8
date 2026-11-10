<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\NivelRiesgo;
use App\Models\MatrizRiesgo;
use App\Repositories\Contracts\MatrizRiesgoRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class MatrizRiesgoRepository implements MatrizRiesgoRepositoryInterface
{
    public function resolverNivel(int $probabilidad, int $impacto): ?NivelRiesgo
    {
        // La matriz cambia poco (la edita el Responsable SGSI) -> cache corto,
        // invalidado explícitamente cuando se actualiza la matriz.
        $nivel = Cache::remember(
            "matriz_riesgo:{$probabilidad}:{$impacto}",
            now()->addHours(6),
            fn () => MatrizRiesgo::query()
                ->where('probabilidad', $probabilidad)
                ->where('impacto', $impacto)
                ->value('nivel_resultante')
        );

        if ($nivel === null) {
            return null;
        }

        return $nivel instanceof NivelRiesgo ? $nivel : NivelRiesgo::from($nivel);
    }
}