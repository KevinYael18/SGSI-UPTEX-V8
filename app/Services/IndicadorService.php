<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoControlIso;
use App\Models\Incidente;
use App\Models\Riesgo;
use App\Repositories\Contracts\ControlIsoRepositoryInterface;
use App\Support\ComplianceCalculator;
use Illuminate\Support\Carbon;

/**
 * SRP: solo compone los indicadores del módulo de Reportes. Reutiliza
 * ComplianceCalculator (ya existente) en vez de recalcular el % de
 * cumplimiento por su cuenta.
 */
class IndicadorService
{
    public function __construct(
        private readonly ComplianceCalculator $compliance,
        private readonly ControlIsoRepositoryInterface $controles,
    ) {
    }

    public function resumen(): array
    {
        $conteoControles = $this->controles->conteoPorEstado();

        return [
            'indice_cumplimiento' => $this->compliance->porcentajeGeneral(),
            'riesgos_criticos' => Riesgo::query()->where('nivel_calculado', 'critico')->count(),
            'incidentes_abiertos' => Incidente::query()->whereNotIn('estado', ['cerrado'])->count(),
            'controles_implementados' => $conteoControles[EstadoControlIso::Implementado->value] ?? 0,
            'controles_totales' => array_sum($conteoControles),
        ];
    }

    /**
     * Últimos $meses meses, incluyendo el actual, en orden cronológico
     * ascendente — aunque no haya incidentes en un mes, aparece con 0
     * (para que la gráfica no "salte" meses vacíos).
     */
    public function incidentesPorMes(int $meses = 6): array
    {
        $inicio = Carbon::now()->startOfMonth()->subMonths($meses - 1);

        $conteos = Incidente::query()
            ->where('fecha_deteccion', '>=', $inicio)
            ->selectRaw("DATE_FORMAT(fecha_deteccion, '%Y-%m') as mes, count(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $resultado = [];
        for ($i = 0; $i < $meses; $i++) {
            $clave = $inicio->copy()->addMonths($i)->format('Y-m');
            $resultado[$clave] = $conteos[$clave] ?? 0;
        }

        return $resultado;
    }

    public function riesgosPorNivel(): array
    {
        return Riesgo::query()
            ->whereNotNull('nivel_calculado')
            ->selectRaw('nivel_calculado, count(*) as total')
            ->groupBy('nivel_calculado')
            ->pluck('total', 'nivel_calculado')
            ->toArray();
    }
}
