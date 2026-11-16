<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoIncidente;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoIncidenteRequest;
use App\Http\Requests\StoreIncidenteRequest;
use App\Http\Requests\UpdateIncidenteRequest;
use App\Http\Resources\IncidenteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Incidente;
use App\Services\IncidenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class IncidenteController extends Controller
{
    public function __construct(private readonly IncidenteService $service)
    {
    }

    #[OA\Get(path: '/api/v1/incidentes', tags: ['Incidentes'], summary: 'Lista incidentes filtrables por estado/activo')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incidente::class);

        $incidentes = $this->service->listar(
            \App\Support\Paginacion::porPagina($request, 15),
            $request->only(['estado', 'activo_id'])
        );

        return ApiResponse::coleccion(IncidenteResource::collection($incidentes));
    }

    #[OA\Post(
        path: '/api/v1/incidentes',
        tags: ['Incidentes'],
        summary: 'Registra un incidente con evidencia opcional (multipart/form-data); valida hash SHA-256 y dispara notificación en cola',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(mediaType: 'multipart/form-data')
        )
    )]
    public function store(StoreIncidenteRequest $request): JsonResponse
    {
        $incidente = $this->service->reportar(
            $request->safe()->except('evidencia'),
            $request->file('evidencia'),
            $request->user()->id
        );

        return ApiResponse::creado(new IncidenteResource($incidente->load(['activo', 'reportadoPor', 'evidencias'])));
    }

    #[OA\Get(path: '/api/v1/incidentes/{incidente}', tags: ['Incidentes'], summary: 'Detalle de incidente (por UUID) con evidencias')]
    public function show(Incidente $incidente): JsonResponse
    {
        // Antes no se autorizaba: cualquier usuario autenticado podía ver el
        // incidente y sus evidencias pidiéndolo directamente a la API.
        $this->authorize('view', $incidente);

        return ApiResponse::item(new IncidenteResource($incidente->load(['activo.responsable', 'reportadoPor', 'evidencias'])));
    }

    #[OA\Put(path: '/api/v1/incidentes/{incidente}', tags: ['Incidentes'], summary: 'Edita título, descripción o activo asociado de un incidente (nunca su estado)')]
    public function update(UpdateIncidenteRequest $request, Incidente $incidente): JsonResponse
    {
        $actualizado = $this->service->actualizar($incidente, $request->validated(), $request->user()->id);

        return ApiResponse::item(new IncidenteResource($actualizado->load(['activo.responsable', 'reportadoPor', 'evidencias'])));
    }

    #[OA\Delete(path: '/api/v1/incidentes/{incidente}', tags: ['Incidentes'], summary: 'Elimina un incidente (soft delete)')]
    public function destroy(Incidente $incidente): JsonResponse
    {
        $this->authorize('delete', $incidente);
        $this->service->eliminar($incidente);

        return ApiResponse::sinContenido();
    }

    #[OA\Patch(
        path: '/api/v1/incidentes/{incidente}/estado',
        tags: ['Incidentes'],
        summary: 'Cambia el estado del incidente respetando la máquina de estados definida',
        responses: [new OA\Response(response: 422, description: 'Transición de estado no permitida')]
    )]
    public function cambiarEstado(CambiarEstadoIncidenteRequest $request, Incidente $incidente): JsonResponse
    {
        $actualizado = $this->service->cambiarEstado(
            $incidente,
            EstadoIncidente::from($request->string('estado')->toString()),
            $request->user()->id
        );

        return ApiResponse::item(new IncidenteResource($actualizado));
    }
}