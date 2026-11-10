<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface NotificacionRepositoryInterface extends RepositoryInterface
{
    public function noLeidasDe(int $usuarioId): Collection;

    public function contarNoLeidas(int $usuarioId): int;
}