<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface DocumentoRepositoryInterface extends RepositoryInterface
{
    public function pendientesDeAprobacion(): Collection;

    public function proximosAExpirar(int $dias): Collection;
}