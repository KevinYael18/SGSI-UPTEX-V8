<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\CategoriaControlIso;
use Illuminate\Database\Eloquent\Collection;

interface ControlIsoRepositoryInterface extends RepositoryInterface
{
    public function porCategoria(CategoriaControlIso $categoria): Collection;

    public function conteoPorEstado(): array;
}