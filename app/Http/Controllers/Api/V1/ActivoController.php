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
use App\Services\FichaTecnicaActivoService;
use App\Support\Bitacora;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controller "delgado": únicamente traduce HTTP <-> Service. No contiene
 * reglas de negocio ni queries directas (SRP).
 */
class ActivoController extends Controller
{
    public function __construct(
        private readonly ActivoService $service,
        private readonly FichaTecnicaActivoService $fichas,
    )
    {
    }

    #[OA\Get(path: '/api/v1/activos', tags: ['Activos'], summary: 'Lista activos paginados, filtrable por departamento/tipo/búsqueda')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activo::class);

        $activos = $this->service->listar(
            \App\Support\Paginacion::porPagina($request, 15),
            $request->only(['departamento_id', 'tipo', 'busqueda']) + ['visible_para' => $request->user()]
        );

        return ApiResponse::coleccion(ActivoResource::collection($activos));
    }

    #[OA\Post(path: '/api/v1/activos', tags: ['Activos'], summary: 'Crea un activo, calcula criticidad y vincula controles ISO')]
    public function store(StoreActivoRequest $request): JsonResponse
    {
        [$base, $ficha] = $this->separar($request->validated());

        // ActivoService crea el activo exactamente como antes (CIA, criticidad,
        // controles, eventos); la ficha técnica se guarda después en la MISMA
        // transacción: si su validación falla, no queda un activo a medias.
        $activo = DB::transaction(function () use ($base, $ficha, $request) {
            $activo = $this->service->crear($base, $request->user()->id);
            if ($ficha !== [] || $activo->tipo->value === 'software') {
                $this->fichas->guardar($activo, $ficha, $request->user());
            }

            return $activo;
        });

        return ApiResponse::creado(new ActivoResource($this->cargarDetalle($activo->fresh())));
    }

    #[OA\Get(path: '/api/v1/activos/{activo}', tags: ['Activos'], summary: 'Detalle de un activo (por UUID)')]
    public function show(Activo $activo): JsonResponse
    {
        $this->authorize('view', $activo);

        return ApiResponse::item(new ActivoResource($this->cargarDetalle($activo)));
    }

    #[OA\Put(path: '/api/v1/activos/{activo}', tags: ['Activos'], summary: 'Actualiza un activo y recalcula criticidad si cambia CIA')]
    public function update(UpdateActivoRequest $request, Activo $activo): JsonResponse
    {
        [$base, $ficha] = $this->separar($request->validated());
        $ubicacionAnterior = $activo->ubicacion()->first();

        $actualizado = DB::transaction(function () use ($activo, $base, $ficha, $request) {
            $actualizado = $base !== [] ? $this->service->actualizar($activo, $base, $request->user()->id) : $activo;
            if ($ficha !== [] || $actualizado->tipo->value === 'software') {
                $this->fichas->guardar($actualizado, $ficha, $request->user());
            }

            return $actualizado;
        });

        // Historial de ubicación: el cambio queda en la bitácora del activo.
        if (array_key_exists('ubicacion_id', $base) && (int) $base['ubicacion_id'] !== (int) $ubicacionAnterior?->id) {
            $nueva = $actualizado->ubicacion()->first();
            Bitacora::registrar('activos', $actualizado->id, $request->user()->id, 'update', [
                'evento' => 'cambio_ubicacion',
                'ubicacion' => ['antes' => $ubicacionAnterior?->descripcion(), 'despues' => $nueva?->descripcion()],
            ]);
        }

        return ApiResponse::item(new ActivoResource($this->cargarDetalle($actualizado->fresh())));
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

    /** Campos de la ficha técnica dinámica (los demás son los de siempre). */
    private const CAMPOS_FICHA = ['subtipo', 'tecnico', 'personalizados', 'ubicacion_detalle'];

    /** @return array{0: array, 1: array} [datos del activo, datos de ficha técnica] */
    private function separar(array $validados): array
    {
        $ficha = array_intersect_key($validados, array_flip(self::CAMPOS_FICHA));

        return [array_diff_key($validados, $ficha), $ficha];
    }

    private function cargarDetalle(Activo $activo): Activo
    {
        return $activo->load([
            'departamento', 'ubicacion.edificioRelacion', 'responsable', 'computadora',
            'programaCatalogo.computadoras.laboratorio.edificio', 'programaCatalogo.computadoras.activo',
        ]);
    }
}
