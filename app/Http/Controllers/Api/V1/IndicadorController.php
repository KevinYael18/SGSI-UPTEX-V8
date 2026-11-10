<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\IndicadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class IndicadorController extends Controller
{
    public function __construct(private readonly IndicadorService $service)
    {
    }

    #[OA\Get(
        path: '/api/v1/indicadores',
        tags: ['Reportes'],
        summary: 'Indicadores ejecutivos: índice de cumplimiento, riesgos críticos, incidentes por mes, controles implementados'
    )]
    public function index(): JsonResponse
    {
        Gate::authorize('ver-dashboard');

        $resumen = $this->service->resumen();

        return response()->json(['data' => [
            ...$resumen,
            'incidentes_por_mes' => $this->service->incidentesPorMes(),
            'riesgos_por_nivel' => $this->service->riesgosPorNivel(),
        ]]);
    }
}
