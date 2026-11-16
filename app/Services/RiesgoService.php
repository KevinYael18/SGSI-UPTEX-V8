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
        $datos = $this->conNivelResidual($datos, null);

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
        // Si cambia la probabilidad o el impacto (aunque llegue solo uno) se
        // recalcula el nivel con la matriz; antes podía quedar inconsistente.
        if (array_key_exists('probabilidad', $datos) || array_key_exists('impacto', $datos)) {
            $datos['nivel_calculado'] = $this->matrizResolver
                ->resolver((int) ($datos['probabilidad'] ?? $riesgo->probabilidad), (int) ($datos['impacto'] ?? $riesgo->impacto))
                ->value;
        }
        $datos = $this->conNivelResidual($datos, $riesgo);

        return DB::transaction(function () use ($riesgo, $datos, $usuarioId) {
            $actualizado = $this->riesgos->actualizar($riesgo, $datos);
            event(new RiesgoRecalculado($actualizado, 'update', $usuarioId));

            return $actualizado;
        });
    }

    /**
     * Riesgo residual (tras aplicar el tratamiento): su nivel se resuelve con la
     * MISMA matriz configurable. Solo se calcula si hay probabilidad e impacto
     * residuales; si se borran ambos, el nivel residual también se limpia.
     */
    private function conNivelResidual(array $datos, ?Riesgo $riesgo): array
    {
        if (!array_key_exists('probabilidad_residual', $datos) && !array_key_exists('impacto_residual', $datos)) {
            return $datos;
        }
        $p = $datos['probabilidad_residual'] ?? ($riesgo?->probabilidad_residual);
        $i = $datos['impacto_residual'] ?? ($riesgo?->impacto_residual);
        if (array_key_exists('probabilidad_residual', $datos) && $datos['probabilidad_residual'] === null) {
            $p = null;
        }
        if (array_key_exists('impacto_residual', $datos) && $datos['impacto_residual'] === null) {
            $i = null;
        }
        $datos['nivel_residual'] = ($p !== null && $i !== null)
            ? $this->matrizResolver->resolver((int) $p, (int) $i)->value
            : null;

        return $datos;
    }

    public function eliminar(Riesgo $riesgo): void
    {
        $this->riesgos->eliminar($riesgo);
        \App\Support\Bitacora::registrar('riesgos', $riesgo->id, auth()->id(), 'delete', [
            'evento' => 'riesgo_eliminado',
            'activo_id' => $riesgo->activo_id,
            'nivel_calculado' => $riesgo->nivel_calculado?->value,
        ]);
    }
}
