<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\CategoriaControlIso;
use App\Models\ControlIso;
use App\Repositories\Contracts\ControlIsoRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ControlIsoRepository extends BaseRepository implements ControlIsoRepositoryInterface
{
    public function __construct(ControlIso $modelo)
    {
        parent::__construct($modelo);
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['categoria'] ?? null, fn ($q, $v) => $q->where('categoria', $v))
            ->when($filtros['estado_implementacion'] ?? null, fn ($q, $v) => $q->where('estado_implementacion', $v));
    }

    public function porCategoria(CategoriaControlIso $categoria): Collection
    {
        return $this->query()->where('categoria', $categoria->value)->get();
    }

    public function conteoPorEstado(): array
    {
        return $this->query()
            ->selectRaw('estado_implementacion, count(*) as total')
            ->groupBy('estado_implementacion')
            ->pluck('total', 'estado_implementacion')
            ->toArray();
    }
}