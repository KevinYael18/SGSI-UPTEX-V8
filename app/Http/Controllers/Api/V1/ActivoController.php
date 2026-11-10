<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivoRequest;
use App\Http\Requests\UpdateActivoRequest;
use App\Http\Resources\ActivoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Activo;
use App\Services\ActivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controller "delgado": únicamente traduce HTTP <-> Service. No contiene
 * reglas de negocio ni queries directas (SRP).
 */
class ActivoController extends Controller
{
    public function __construct(private readonly ActivoService $service)
    {
    }

    #[OA\Get(path: '/api/v1/activos', tags: ['Activos'], summary: 'Lista activos paginados, filtrable por departamento/tipo/búsqueda')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activo::class);

        $activos = $this->service->listar(
            (int) $request->integer('por_pagina', 15),
            $request->only(['departamento_id', 'tipo', 'busqueda'])
        );

        return ApiResponse::coleccion(ActivoResource::collection($activos));
    }

    #[OA\Post(path: '/api/v1/activos', tags: ['Activos'], summary: 'Crea un activo, calcula criticidad y vincula controles ISO')]
    public function store(StoreActivoRequest $request): JsonResponse
    {
        $activo = $this->service->crear($request->validated(), $request->user()->id);

        return ApiResponse::creado(new ActivoResource($activo->load(['departamento', 'ubicacion', 'responsable'])));
    }

    #[OA\Get(path: '/api/v1/activos/{activo}', tags: ['Activos'], summary: 'Detalle de un activo (por UUID)')]
    public function show(Activo $activo): JsonResponse
    {
        $this->authorize('view', $activo);

        return ApiResponse::item(new ActivoResource($activo->load(['departamento', 'ubicacion', 'responsable'])));
    }

    #[OA\Put(path: '/api/v1/activos/{activo}', tags: ['Activos'], summary: 'Actualiza un activo y recalcula criticidad si cambia CIA')]
    public function update(UpdateActivoRequest $request, Activo $activo): JsonResponse
    {
        $actualizado = $this->service->actualizar($activo, $request->validated(), $request->user()->id);

        return ApiResponse::item(new ActivoResource($actualizado));
    }

    #[OA\Delete(
        path: '/api/v1/activos/{activo}',
        tags: ['Activos'],
        summary: 'Elimina (soft delete) un activo si no tiene riesgos abiertos',
        responses: [new OA\Response(response: 409, description: 'El activo tiene riesgos abiertos asociados')]
    )]
    public function destroy(Request $request, Activo $activo): JsonResponse
    {
        $this->authorize('delete', $activo);
        $this->service->eliminar($activo);

        return ApiResponse::sinContenido();
    }
}