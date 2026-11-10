<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contrato mínimo común (ISP: cada Repository concreto añade solo los
 * métodos de consulta específicos de su dominio, no hereda operaciones
 * que no necesita).
 */
interface RepositoryInterface
{
    public function encontrarOFallar(int|string $id): Model;

    public function paginar(int $porPagina = 15, array $filtros = []): LengthAwarePaginator;

    public function todos(array $filtros = []): Collection;

    public function crear(array $datos): Model;

    public function actualizar(Model $modelo, array $datos): Model;

    public function eliminar(Model $modelo): bool;
}