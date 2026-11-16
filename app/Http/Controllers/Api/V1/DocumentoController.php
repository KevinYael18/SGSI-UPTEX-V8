<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoDocumentoRequest;
use App\Http\Requests\NuevaVersionDocumentoRequest;
use App\Http\Requests\StoreDocumentoRequest;
use App\Http\Requests\UpdateDocumentoRequest;
use App\Http\Resources\DocumentoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Documento;
use App\Services\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DocumentoController extends Controller
{
    public function __construct(private readonly DocumentoService $service)
    {
    }

    #[OA\Get(path: '/api/v1/documentos', tags: ['Documentos'], summary: 'Lista documentos, filtrable por estado')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Documento::class);

        $documentos = $this->service->listar(\App\Support\Paginacion::porPagina($request, 15), $request->only(['estado']));

        return ApiResponse::coleccion(DocumentoResource::collection($documentos));
    }

    #[OA\Post(path: '/api/v1/documentos', tags: ['Documentos'], summary: 'Sube un documento nuevo (versión 1, estado Borrador)')]
    public function store(StoreDocumentoRequest $request): JsonResponse
    {
        $documento = $this->service->crear($request->safe()->except('archivo'), $request->file('archivo'), $request->user()->id);

        return ApiResponse::creado(new DocumentoResource($documento->load('versiones')));
    }

    #[OA\Get(path: '/api/v1/documentos/{documento}', tags: ['Documentos'], summary: 'Detalle con historial de versiones')]
    public function show(Documento $documento): JsonResponse
    {
        $this->authorize('view', $documento);

        return ApiResponse::item(new DocumentoResource($documento->load(['versiones.creadoPor', 'versiones.aprobadoPor', 'publicadoPor'])));
    }

    #[OA\Post(
        path: '/api/v1/documentos/{documento}/versiones',
        tags: ['Documentos'],
        summary: 'Sube una nueva versión; el documento regresa a estado En revisión'
    )]
    public function nuevaVersion(NuevaVersionDocumentoRequest $request, Documento $documento): JsonResponse
    {
        $actualizado = $this->service->nuevaVersion($documento, $request->file('archivo'), $request->user()->id, $request->integer('dias_vigencia') ?: null);

        return ApiResponse::item(new DocumentoResource($actualizado->load('versiones')));
    }

    #[OA\Patch(
        path: '/api/v1/documentos/{documento}/estado',
        tags: ['Documentos'],
        summary: 'Avanza el workflow: Borrador -> En revisión -> Aprobado -> Publicado -> Obsoleto',
        responses: [
            new OA\Response(response: 422, description: 'Transición inválida o quien aprueba es quien subió la versión'),
        ]
    )]
    public function cambiarEstado(CambiarEstadoDocumentoRequest $request, Documento $documento): JsonResponse
    {
        $actualizado = $this->service->cambiarEstado($documento, EstadoDocumento::from($request->string('estado')->toString()), $request->user()->id);

        return ApiResponse::item(new DocumentoResource($actualizado));
    }

    #[OA\Put(path: '/api/v1/documentos/{documento}', tags: ['Documentos'], summary: 'Edita el título del documento (el contenido sigue el flujo de nueva versión)')]
    public function update(UpdateDocumentoRequest $request, Documento $documento): JsonResponse
    {
        $actualizado = $this->service->actualizarTitulo($documento, $request->string('titulo')->toString(), $request->user()->id);

        return ApiResponse::item(new DocumentoResource($actualizado->load('versiones')));
    }

    #[OA\Delete(path: '/api/v1/documentos/{documento}', tags: ['Documentos'], summary: 'Elimina un documento (soft delete)')]
    public function destroy(Documento $documento): JsonResponse
    {
        $this->authorize('delete', $documento);
        $this->service->eliminar($documento);

        return ApiResponse::sinContenido();
    }
}