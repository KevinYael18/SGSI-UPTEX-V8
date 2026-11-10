<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notificacion;
use App\Repositories\Contracts\NotificacionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificacionService
{
    public function __construct(private readonly NotificacionRepositoryInterface $notificaciones)
    {
    }

    public function listar(int $usuarioId, int $porPagina, array $filtros): LengthAwarePaginator
    {
        return $this->notificaciones->paginar($porPagina, [...$filtros, 'usuario_id' => $usuarioId]);
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        return $this->notificaciones->contarNoLeidas($usuarioId);
    }

    public function marcarLeida(Notificacion $notificacion): Notificacion
    {
        return $this->notificaciones->actualizar($notificacion, ['leida' => true]);
    }

    public function marcarTodasLeidas(int $usuarioId): int
    {
        $noLeidas = $this->notificaciones->noLeidasDe($usuarioId);
        $noLeidas->each(fn ($n) => $n->update(['leida' => true]));

        return $noLeidas->count();
    }
}