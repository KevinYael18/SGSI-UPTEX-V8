<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface AuditoriaRepositoryInterface extends RepositoryInterface
{
    public function proximasAVencer(int $dias): Collection;
}