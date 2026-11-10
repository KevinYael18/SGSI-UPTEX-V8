<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRiesgoRequest;
use App\Http\Requests\UpdateRiesgoRequest;
use App\Http\Resources\RiesgoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Riesgo;
use App\Services\RiesgoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RiesgoController extends Controller
{
    public function __construct(private readonly RiesgoService $service)
    {
    }

    #[OA\Get(path: '/api/v1/riesgos', tags: ['Riesgos'], summary: 'Lista riesgos, filtrable por activo y nivel calculado')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Riesgo::class);

        $riesgos = $this->service->listar(
            (int) $request->integer('por_pagina', 15),
            $request->only(['activo_id', 'nivel_calculado'])
        );

        return ApiResponse::coleccion(RiesgoResource::collection($riesgos));
    }

    #[OA\Get(path: '/api/v1/riesgos/impacto-sugerido', tags: ['Riesgos'], summary: 'Impacto (1-5) sugerido a partir de la criticidad del activo')]
    public function impactoSugerido(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Riesgo::class);

        $request->validate(['activo_id' => ['required', 'integer', 'exists:activos,id']]);

        $activoId = (int) $request->integer('activo_id');

        return response()->json(['data' => [
            'activo_id' => $activoId,
            'impacto_sugerido' => $this->service->impactoSugerido($activoId),
        ]]);
    }

    #[OA\Get(path: '/api/v1/riesgos/sugerencia', tags: ['Riesgos'], summary: 'Impacto y probabilidad sugeridos para activo, amenaza y vulnerabilidad')]
    public function sugerencia(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Riesgo::class);

        $datos = $request->validate([
            'activo_id' => ['required', 'integer', 'exists:activos,id'],
            'amenaza_id' => ['nullable', 'integer', 'exists:amenazas,id'],
            'vulnerabilidad_id' => ['nullable', 'integer', 'exists:vulnerabilidades,id'],
        ]);

        return response()->json(['data' => $this->service->sugerencia(
            (int) $datos['activo_id'],
            isset($datos['amenaza_id']) ? (int) $datos['amenaza_id'] : null,
            isset($datos['vulnerabilidad_id']) ? (int) $datos['vulnerabilidad_id'] : null,
        )]);
    }

    #[OA\Post(
        path: '/api/v1/riesgos',
        tags: ['Riesgos'],
        summary: 'Crea un riesgo; el nivel se resuelve contra la matriz_riesgo configurable (no hardcodeado)',
        responses: [new OA\Response(response: 422, description: 'La combinación probabilidad/impacto no está configurada en la matriz')]
    )]
    public function store(StoreRiesgoRequest $request): JsonResponse
    {
        $riesgo = $this->service->crear($request->validated());

        return ApiResponse::creado(new RiesgoResource($riesgo->load(['activo', 'amenaza', 'vulnerabilidad', 'planesTratamiento'])));
    }

    #[OA\Put(path: '/api/v1/riesgos/{riesgo}', tags: ['Riesgos'], summary: 'Actualiza un riesgo y recalcula nivel si cambia probabilidad/impacto')]
    public function update(UpdateRiesgoRequest $request, Riesgo $riesgo): JsonResponse
    {
        $actualizado = $this->service->actualizar($riesgo, $request->validated());

        return ApiResponse::item(new RiesgoResource($actualizado->load(['activo', 'amenaza', 'vulnerabilidad'])));
    }

    #[OA\Delete(path: '/api/v1/riesgos/{riesgo}', tags: ['Riesgos'], summary: 'Elimina un riesgo (soft delete)')]
    public function destroy(Riesgo $riesgo): JsonResponse
    {
        $this->authorize('delete', $riesgo);
        $this->service->eliminar($riesgo);

        return ApiResponse::sinContenido();
    }
}
