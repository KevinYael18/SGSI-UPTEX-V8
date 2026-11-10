<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Auditoria;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AuditoriaRepository extends BaseRepository implements AuditoriaRepositoryInterface
{
    public function __construct(Auditoria $modelo)
    {
        parent::__construct($modelo);
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query->with('auditor')->when($filtros['auditor_id'] ?? null, fn ($q, $v) => $q->where('auditor_id', $v));
    }

    public function proximasAVencer(int $dias): Collection
    {
        return $this->query()
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [now(), now()->addDays($dias)])
            ->get();
    }
}