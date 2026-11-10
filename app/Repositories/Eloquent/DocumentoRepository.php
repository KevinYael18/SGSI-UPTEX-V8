<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Documento;
use App\Repositories\Contracts\DocumentoRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DocumentoRepository extends BaseRepository implements DocumentoRepositoryInterface
{
    public function __construct(Documento $modelo)
    {
        parent::__construct($modelo);
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v));
    }

    public function pendientesDeAprobacion(): Collection
    {
        return $this->query()->where('estado', 'en_revision')->get();
    }

    public function proximosAExpirar(int $dias): Collection
    {
        return $this->query()
            ->whereHas('versiones', function ($q) use ($dias) {
                $q->whereColumn('numero_version', 'documentos.version_actual')
                    ->whereNotNull('fecha_expiracion')
                    ->whereBetween('fecha_expiracion', [now()->toDateString(), now()->addDays($dias)->toDateString()]);
            })
            ->get();
    }
}