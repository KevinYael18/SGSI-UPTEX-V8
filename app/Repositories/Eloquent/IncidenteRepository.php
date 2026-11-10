<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Incidente;
use App\Repositories\Contracts\IncidenteRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class IncidenteRepository extends BaseRepository implements IncidenteRepositoryInterface
{
    public function __construct(Incidente $modelo)
    {
        parent::__construct($modelo);
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['activo_id'] ?? null, fn ($q, $v) => $q->where('activo_id', $v))
            ->with(['activo', 'reportadoPor']);
    }
}