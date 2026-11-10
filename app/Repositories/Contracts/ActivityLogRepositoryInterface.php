<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ActivityLogRepositoryInterface
{
    public function historialDe(string $entidad, int $entidadId): Collection;
}