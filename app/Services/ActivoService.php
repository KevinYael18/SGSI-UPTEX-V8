<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ActivoActualizado;
use App\Events\ActivoCreado;
use App\Exceptions\ActivoConRiesgosAsociadosException;
use App\Models\Activo;
use App\Repositories\Contracts\ActivoRepositoryInterface;
use App\Support\CriticidadActivoCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Toda regla de negocio de Activos vive aquí. El Controller NO sabe
 * calcular criticidad ni decidir si un activo se puede borrar.
 */
class ActivoService
{
    public function __construct(
        private readonly ActivoRepositoryInterface $activos,
        private readonly CriticidadActivoCalculator $calculadora,
    ) {
    }

    public function listar(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return $this->activos->paginar($porPagina, $filtros);
    }

    public function paraDepartamentos(array $departamentoIds): Collection
    {
        return $this->activos->paraDepartamentos($departamentoIds);
    }

    public function crear(array $datos, int $usuarioId): Activo
    {
        $datos['criticidad_calculada'] = $this->calculadora->calcular(
            $datos['confidencialidad'],
            $datos['integridad'],
            $datos['disponibilidad'],
        );

        return DB::transaction(function () use ($datos, $usuarioId) {
            $activo = $this->activos->crear($datos);

            if (!empty($datos['controles_iso_ids'])) {
                $activo->controlesIso()->sync($datos['controles_iso_ids']);
            }

            event(new ActivoCreado($activo, $usuarioId));

            return $activo;
        });
    }

    public function actualizar(Activo $activo, array $datos, int $usuarioId): Activo
    {
        $original = $activo->only(['nombre', 'confidencialidad', 'integridad', 'disponibilidad']);

        if (isset($datos['confidencialidad'], $datos['integridad'], $datos['disponibilidad'])) {
            $datos['criticidad_calculada'] = $this->calculadora->calcular(
                $datos['confidencialidad'],
                $datos['integridad'],
                $datos['disponibilidad'],
            );
        }

        return DB::transaction(function () use ($activo, $datos, $usuarioId, $original) {
            $actualizado = $this->activos->actualizar($activo, $datos);

            event(new ActivoActualizado($actualizado, ['antes' => $original, 'despues' => $datos], $usuarioId));

            return $actualizado;
        });
    }

    public function eliminar(Activo $activo): void
    {
        $riesgosAbiertos = $this->activos->conRiesgosAbiertos($activo->id);

        if ($riesgosAbiertos > 0) {
            throw new ActivoConRiesgosAsociadosException($activo->id, $riesgosAbiertos);
        }

        $this->activos->eliminar($activo);
    }
}