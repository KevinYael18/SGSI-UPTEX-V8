<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoControlIso;
use App\Events\ControlIsoActualizado;
use App\Models\ControlIso;
use App\Repositories\Contracts\ControlIsoRepositoryInterface;
use App\Support\ComplianceCalculator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ControlIsoService
{
    public function __construct(
        private readonly ControlIsoRepositoryInterface $controles,
        private readonly ComplianceCalculator $calculadora,
    ) {
    }

    public function listar(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return $this->controles->paginar($porPagina, $filtros);
    }

    public function actualizarEstado(ControlIso $control, EstadoControlIso $nuevoEstado, int $usuarioId): ControlIso
    {
        $anterior = $control->estado_implementacion->value;

        return DB::transaction(function () use ($control, $nuevoEstado, $usuarioId, $anterior) {
            $actualizado = $this->controles->actualizar($control, ['estado_implementacion' => $nuevoEstado->value]);
            event(new ControlIsoActualizado($actualizado, $anterior, $usuarioId));

            return $actualizado;
        });
    }

    public function resumenCumplimiento(): array
    {
        return [
            'porcentaje_general' => $this->calculadora->porcentajeGeneral(),
            'porcentaje_por_categoria' => $this->calculadora->porcentajePorCategoria(),
        ];
    }
}