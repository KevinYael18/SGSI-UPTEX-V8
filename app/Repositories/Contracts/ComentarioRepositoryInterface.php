<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ComentarioRepositoryInterface
{
    public function paraEntidad(Model $comentable): Collection;

    public function crear(Model $comentable, int $usuarioId, string $contenido): Model;
}