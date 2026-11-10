<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NivelRiesgo;
use App\Events\RiesgoCriticoDetectado;
use App\Events\RiesgoRecalculado;
use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Riesgo;
use App\Models\Vulnerabilidad;
use App\Repositories\Contracts\RiesgoRepositoryInterface;
use App\Support\ImpactoRiesgoCalculator;
use App\Support\MatrizRiesgoResolver;
use App\Support\ProbabilidadRiesgoCalculator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RiesgoService
{
    public function __construct(
        private readonly RiesgoRepositoryInterface $riesgos,
        private readonly MatrizRiesgoResolver $matrizResolver,
        private readonly ImpactoRiesgoCalculator $impactoCalculator,
        private readonly ProbabilidadRiesgoCalculator $probabilidadCalculator,
    ) {
    }

    public function listar(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return $this->riesgos->paginar($porPagina, $filtros);
    }

    /**
     * Impacto sugerido para un activo (derivado de su criticidad).
     */
    public function impactoSugerido(int $activoId): int
    {
        return $this->impactoCalculator->calcular(Activo::query()->findOrFail($activoId));
    }

    /**
     * Impacto y probabilidad sugeridos. La probabilidad requiere amenaza y vulnerabilidad;
     * si falta alguna, se devuelve null.
     */
    public function sugerencia(int $activoId, ?int $amenazaId, ?int $vulnerabilidadId): array
    {
        $activo = Activo::query()->findOrFail($activoId);

        $probabilidad = null;
        if ($amenazaId !== null && $vulnerabilidadId !== null) {
            $probabilidad = $this->probabilidadCalculator->calcular(
                $activo,
                Amenaza::query()->findOrFail($amenazaId),
                Vulnerabilidad::query()->findOrFail($vulnerabilidadId),
            );
        }

        return [
            'activo_id' => $activoId,
            'impacto_sugerido' => $this->impactoCalculator->calcular($activo),
            'probabilidad_sugerida' => $probabilidad,
        ];
    }

    public function crear(array $datos, ?int $usuarioId = null): Riesgo
    {
        if (!isset($datos['impacto']) || !isset($datos['probabilidad'])) {
            $sugerido = $this->sugerencia(
                (int) $datos['activo_id'],
                (int) $datos['amenaza_id'],
                (int) $datos['vulnerabilidad_id'],
            );
            $datos['impacto'] ??= $sugerido['impacto_sugerido'];
            $datos['probabilidad'] ??= $sugerido['probabilidad_sugerida'];
        }

        $nivel = $this->matrizResolver->resolver($datos['probabilidad'], $datos['impacto']);
        $datos['nivel_calculado'] = $nivel->value;

        return DB::transaction(function () use ($datos, $nivel, $usuarioId) {
            $riesgo = $this->riesgos->crear($datos);

            if (!empty($datos['plan_tratamiento'])) {
                $riesgo->planesTratamiento()->create($datos['plan_tratamiento']);
            }

            event(new RiesgoRecalculado($riesgo, 'create', $usuarioId));

            if ($nivel === NivelRiesgo::Critico && empty($datos['tipo_tratamiento'])) {
                event(new RiesgoCriticoDetectado($riesgo));
            }

            return $riesgo;
        });
    }

    public function actualizar(Riesgo $riesgo, array $datos, ?int $usuarioId = null): Riesgo
    {
        if (isset($datos['probabilidad'], $datos['impacto'])) {
            $datos['nivel_calculado'] = $this->matrizResolver
                ->resolver($datos['probabilidad'], $datos['impacto'])
                ->value;
        }

        return DB::transaction(function () use ($riesgo, $datos, $usuarioId) {
            $actualizado = $this->riesgos->actualizar($riesgo, $datos);
            event(new RiesgoRecalculado($actualizado, 'update', $usuarioId));

            return $actualizado;
        });
    }

    public function eliminar(Riesgo $riesgo): void
    {
        $this->riesgos->eliminar($riesgo);
    }
}
