<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuditoriaRequest;
use App\Http\Requests\StoreHallazgoRequest;
use App\Http\Requests\UpdateAuditoriaRequest;
use App\Http\Resources\AuditoriaResource;
use App\Http\Resources\HallazgoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Auditoria;
use App\Models\NoConformidad;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuditoriaController extends Controller
{
    public function __construct(private readonly AuditoriaService $service)
    {
    }

    #[OA\Get(path: '/api/v1/auditorias', tags: ['Auditorias'], summary: 'Lista auditorías, filtrable por auditor')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Auditoria::class);

        $auditorias = $this->service->listar(\App\Support\Paginacion::porPagina($request, 15), $request->only(['auditor_id']));

        return ApiResponse::coleccion(AuditoriaResource::collection($auditorias));
    }

    #[OA\Post(path: '/api/v1/auditorias', tags: ['Auditorias'], summary: 'Planea una nueva auditoría')]
    public function store(StoreAuditoriaRequest $request): JsonResponse
    {
        $auditoria = $this->service->planear($request->validated(), $request->user()->id);

        return ApiResponse::creado(new AuditoriaResource($auditoria->load('auditor')));
    }

    #[OA\Get(path: '/api/v1/auditorias/{auditoria}', tags: ['Auditorias'], summary: 'Detalle con hallazgos, no conformidades y acciones correctivas')]
    public function show(Auditoria $auditoria): JsonResponse
    {
        $this->authorize('view', $auditoria);

        $auditoria->load(['auditor', 'hallazgos.noConformidades.accionesCorrectivas.responsable']);

        return ApiResponse::item(new AuditoriaResource($auditoria));
    }

    #[OA\Put(path: '/api/v1/auditorias/{auditoria}', tags: ['Auditorias'], summary: 'Edita alcance, fechas o auditor asignado')]
    public function update(UpdateAuditoriaRequest $request, Auditoria $auditoria): JsonResponse
    {
        $actualizada = $this->service->actualizar($auditoria, $request->validated(), $request->user()->id);

        return ApiResponse::item(new AuditoriaResource($actualizada->load('auditor')));
    }

    #[OA\Delete(path: '/api/v1/auditorias/{auditoria}', tags: ['Auditorias'], summary: 'Elimina una auditoría')]
    public function destroy(Auditoria $auditoria): JsonResponse
    {
        $this->authorize('delete', $auditoria);
        $this->service->eliminar($auditoria);

        return ApiResponse::sinContenido();
    }

    #[OA\Post(
        path: '/api/v1/auditorias/{auditoria}/hallazgos',
        tags: ['Auditorias'],
        summary: 'Registra un hallazgo y, opcionalmente, su no conformidad + acción correctiva en una sola transacción'
    )]
    public function registrarHallazgo(StoreHallazgoRequest $request, Auditoria $auditoria): JsonResponse
    {
        $hallazgo = $this->service->registrarHallazgoConSeguimiento($auditoria, $request->validated(), $request->user()->id);

        return ApiResponse::creado(new HallazgoResource($hallazgo->load('noConformidades.accionesCorrectivas')));
    }

    #[OA\Patch(
        path: '/api/v1/no-conformidades/{no_conformidad}/cerrar',
        tags: ['Auditorias'],
        summary: 'Cierra una no conformidad si todas sus acciones correctivas están cerradas',
        responses: [new OA\Response(response: 422, description: 'Hay acciones correctivas pendientes')]
    )]
    public function cerrarNoConformidad(Request $request, NoConformidad $noConformidad): JsonResponse
    {
        $this->authorize('update', $noConformidad->hallazgo->auditoria);

        $actualizada = $this->service->cerrarNoConformidad($noConformidad, $request->user()->id);

        return ApiResponse::item(new \App\Http\Resources\NoConformidadResource($actualizada));
    }
}