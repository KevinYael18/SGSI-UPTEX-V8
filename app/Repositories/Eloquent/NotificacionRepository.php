<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Notificacion;
use App\Repositories\Contracts\NotificacionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NotificacionRepository extends BaseRepository implements NotificacionRepositoryInterface
{
    public function __construct(Notificacion $modelo)
    {
        parent::__construct($modelo);
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->where('usuario_id', $filtros['usuario_id'])
            ->when(array_key_exists('leida', $filtros), fn ($q) => $q->where('leida', $filtros['leida']))
            ->latest();
    }

    public function noLeidasDe(int $usuarioId): Collection
    {
        return $this->query()->where('usuario_id', $usuarioId)->where('leida', false)->latest()->get();
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        return $this->query()->where('usuario_id', $usuarioId)->where('leida', false)->count();
    }
}