<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoControlIso;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateControlIsoRequest;
use App\Http\Resources\ControlIsoResource;
use App\Http\Responses\ApiResponse;
use App\Models\ControlIso;
use App\Services\ControlIsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ControlIsoController extends Controller
{
    public function __construct(private readonly ControlIsoService $service)
    {
    }

    #[OA\Get(path: '/api/v1/controles-iso', tags: ['Cumplimiento'], summary: 'Lista los 93 controles del Anexo A, filtrable por categoría/estado')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ControlIso::class);

        $controles = $this->service->listar((int) $request->integer('por_pagina', 20), $request->only(['categoria', 'estado_implementacion']));

        return ApiResponse::coleccion(ControlIsoResource::collection($controles));
    }

    #[OA\Patch(path: '/api/v1/controles-iso/{control_iso}/estado', tags: ['Cumplimiento'], summary: 'Actualiza el estado de implementación de un control')]
    public function actualizarEstado(UpdateControlIsoRequest $request, ControlIso $controlIso): JsonResponse
    {
        $actualizado = $this->service->actualizarEstado(
            $controlIso,
            EstadoControlIso::from($request->string('estado_implementacion')->toString()),
            $request->user()->id
        );

        return ApiResponse::item(new ControlIsoResource($actualizado));
    }

    #[OA\Get(path: '/api/v1/cumplimiento/resumen', tags: ['Cumplimiento'], summary: '% general y por categoría (Organizacional/Personas/Físico/Tecnológico)')]
    public function resumen(): JsonResponse
    {
        $this->authorize('viewAny', ControlIso::class);

        return response()->json(['data' => $this->service->resumenCumplimiento()]);
    }
}