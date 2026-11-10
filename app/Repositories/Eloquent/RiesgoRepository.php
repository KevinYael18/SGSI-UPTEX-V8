<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Riesgo;
use App\Repositories\Contracts\RiesgoRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class RiesgoRepository extends BaseRepository implements RiesgoRepositoryInterface
{
    public function __construct(Riesgo $modelo)
    {
        parent::__construct($modelo);
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->with(['activo', 'amenaza', 'vulnerabilidad'])
            ->when($filtros['activo_id'] ?? null, fn ($q, $v) => $q->where('activo_id', $v))
            ->when($filtros['nivel_calculado'] ?? null, fn ($q, $v) => $q->where('nivel_calculado', $v));
    }

    public function criticosSinTratar(): int
    {
        return $this->query()
            ->where('nivel_calculado', 'critico')
            ->whereNull('tipo_tratamiento')
            ->count();
    }
}