<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Implementación base reutilizada por todos los repositorios Eloquent.
 * Los repositorios concretos SOLO añaden queries específicas de su
 * dominio (p. ej. ActivoRepository::conRiesgosCriticos()); nunca deben
 * exponerse Query Builders crudos fuera de la capa Repository (DIP).
 */
abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $modelo)
    {
    }

    protected function query(): Builder
    {
        return $this->modelo->newQuery();
    }

    public function encontrarOFallar(int|string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function paginar(int $porPagina = 15, array $filtros = []): LengthAwarePaginator
    {
        return $this->aplicarFiltros($this->query(), $filtros)->paginate(\App\Support\Paginacion::acotar($porPagina));
    }

    public function todos(array $filtros = []): Collection
    {
        return $this->aplicarFiltros($this->query(), $filtros)->get();
    }

    public function crear(array $datos): Model
    {
        return $this->modelo->newQuery()->create($datos);
    }

    public function actualizar(Model $modelo, array $datos): Model
    {
        $modelo->update($datos);

        return $modelo->refresh();
    }

    public function eliminar(Model $modelo): bool
    {
        return (bool) $modelo->delete();
    }

    /**
     * Hook de extensión (OCP): cada Repository concreto sobreescribe esto
     * para aplicar sus propios filtros sin modificar el resto de la clase.
     */
    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query;
    }
}