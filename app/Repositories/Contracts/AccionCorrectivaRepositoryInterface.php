<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface AccionCorrectivaRepositoryInterface extends RepositoryInterface
{
    public function vencidasNoMarcadas(): Collection;
}