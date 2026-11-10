<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Activo;
use App\Repositories\Contracts\ActivoRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ActivoRepository extends BaseRepository implements ActivoRepositoryInterface
{
    public function __construct(Activo $modelo)
    {
        parent::__construct($modelo);
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
{
    return $query
        ->with(['departamento', 'ubicacion', 'responsable'])
        ->when($filtros['departamento_id'] ?? null, fn ($q, $v) => $q->where('departamento_id', $v))
        ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
        ->when($filtros['busqueda'] ?? null, fn ($q, $v) => $q->where('nombre', 'like', "%{$v}%"));
}

    public function conRiesgosAbiertos(int $activoId): int
    {
        return $this->query()->findOrFail($activoId)->riesgos()->whereNull('deleted_at')->count();
    }

    public function paraDepartamentos(array $departamentoIds): Collection
    {
        return $this->query()->whereIn('departamento_id', $departamentoIds)->get();
    }
}