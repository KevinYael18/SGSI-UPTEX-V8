<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface ActivoRepositoryInterface extends RepositoryInterface
{
    /** Activos con al menos un riesgo NO cerrado (regla usada antes de eliminar). */
    public function conRiesgosAbiertos(int $activoId): int;

    /** Para el scoping por departamento del rol Consulta. */
    public function paraDepartamentos(array $departamentoIds): Collection;
}