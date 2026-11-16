<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    #[OA\Get(
        path: '/api/v1/dashboard',
        tags: ['Dashboard'],
        summary: 'KPIs ejecutivos adaptados al rol del usuario autenticado'
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('ver-dashboard');

        return response()->json(['data' => $this->service->paraUsuario($request->user())]);
    }

    #[OA\Get(path: '/api/v1/dashboard/matriz-riesgo', tags: ['Dashboard'], summary: 'Datos para la matriz de riesgo 5x5 visual (heatmap)')]
    public function matrizRiesgo(Request $request): JsonResponse
    {
        $this->authorize('ver-dashboard');

        return response()->json(['data' => $this->service->matrizRiesgoVisual()]);
    }

    #[OA\Get(
        path: '/api/v1/dashboard/graficas',
        tags: ['Dashboard'],
        summary: 'Datos agregados para las gráficas de barras y listas de recientes (Dashboard, Riesgos, Auditorías)'
    )]
    public function graficas(Request $request): JsonResponse
    {
        $this->authorize('ver-dashboard');

        return response()->json(['data' => [
            'incidentes_por_estado' => $this->service->incidentesPorEstado(),
            'ultimos_incidentes' => $this->service->ultimosIncidentes(),
            'documentos_recientes' => $this->service->documentosRecientes(),
            'proximas_auditorias' => $this->service->proximasAuditorias(),
            'riesgos_por_nivel' => $this->service->riesgosPorNivel(),
            'auditorias_por_estado' => $this->service->auditoriasPorEstado(),
            'computadoras_por_laboratorio' => $request->user()->can('inventario.ver') ? $this->service->computadorasPorLaboratorio() : [],
            // La bitácora solo la ve el Administrador (misma regla que el módulo Bitácora).
            'actividad_reciente' => $request->user()->rol?->nombre === 'Administrador' ? $this->service->actividadReciente() : [],
        ]]);
    }
}