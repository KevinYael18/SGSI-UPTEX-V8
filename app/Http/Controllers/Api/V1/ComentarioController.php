<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComentarioRequest;
use App\Http\Resources\ComentarioResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comentario;
use App\Services\ComentarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Endpoint transversal: /api/v1/{tipo}/{identificador}/comentarios.
 * {identificador} es UUID o ID según lo que esa entidad exponga al
 * frontend (ver App\Support\RegistroComentables::resolverModelo).
 */
class ComentarioController extends Controller
{
    public function __construct(private readonly ComentarioService $service)
    {
    }

    #[OA\Get(path: '/api/v1/{tipo}/{identificador}/comentarios', tags: ['Comentarios'], summary: 'Lista comentarios internos de una entidad')]
    public function index(Request $request, string $tipo, string $identificador): JsonResponse
    {
        $entidad = $this->service->resolverEntidadOFallar($tipo, $identificador);
        $this->authorize('view', $entidad);

        return ApiResponse::coleccion(ComentarioResource::collection($this->service->listar($entidad)));
    }

    #[OA\Post(path: '/api/v1/{tipo}/{identificador}/comentarios', tags: ['Comentarios'], summary: 'Agrega un comentario interno a la entidad')]
    public function store(StoreComentarioRequest $request, string $tipo, string $identificador): JsonResponse
    {
        $entidad = $this->service->resolverEntidadOFallar($tipo, $identificador);
        $this->authorize('view', $entidad);

        $comentario = $this->service->crear($entidad, $request->user()->id, $request->string('contenido')->toString());

        return ApiResponse::creado(new ComentarioResource($comentario->load('usuario')));
    }

    #[OA\Delete(path: '/api/v1/comentarios/{comentario}', tags: ['Comentarios'], summary: 'Elimina un comentario propio')]
    public function destroy(Comentario $comentario): JsonResponse
    {
        $this->authorize('delete', $comentario);
        $comentario->delete();

        return ApiResponse::sinContenido();
    }
}