<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarEdificioRequest;
use App\Http\Requests\GuardarLaboratorioRequest;
use App\Http\Resources\ComputadoraResource;
use App\Http\Responses\ApiResponse;
use App\Models\Edificio;
use App\Models\Laboratorio;
use App\Models\Ubicacion;
use App\Services\InventarioService;
use App\Support\Bitacora;
use App\Support\InventarioCatalogos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Estructura física del inventario tecnológico: edificios y laboratorios,
 * con sus indicadores calculados sobre los equipos reales registrados.
 */
class InventarioController extends Controller
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    #[OA\Get(path: '/api/v1/inventario/catalogos', tags: ['Inventario'], summary: 'Valores permitidos y controles ISO sugeridos para equipos')]
    public function catalogos(): JsonResponse
    {
        Gate::authorize('inventario.ver');

        return response()->json(['data' => InventarioCatalogos::todos() + [
            'controles_sugeridos' => $this->inventario->controlesSugeridos(),
        ]]);
    }

    #[OA\Get(path: '/api/v1/inventario/resumen', tags: ['Inventario'], summary: 'Indicadores globales del inventario y estructura edificio → laboratorio')]
    public function resumen(Request $request): JsonResponse
    {
        Gate::authorize('inventario.ver');

        $equipos = $this->inventario->visiblesPara($request->user())->with('software')->get();
        $porLaboratorio = $equipos->groupBy('laboratorio_id');

        $edificios = Edificio::query()->with(['laboratorios' => fn ($q) => $q->orderBy('nombre')])->orderBy('nombre')->get()
            ->map(fn (Edificio $e) => [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'clave' => $e->clave,
                'descripcion' => $e->descripcion,
                'laboratorios' => $e->laboratorios->map(fn (Laboratorio $l) => [
                    'id' => $l->id,
                    'nombre' => $l->nombre,
                    'clave' => $l->clave,
                    'capacidad' => $l->capacidad,
                    'resumen' => $this->inventario->resumen($porLaboratorio->get($l->id, collect())),
                ])->values(),
            ]);

        return response()->json(['data' => [
            'global' => $this->inventario->resumen($equipos),
            'sin_laboratorio' => $porLaboratorio->get('', collect())->count() + $porLaboratorio->get(null, collect())->count(),
            'edificios' => $edificios,
        ]]);
    }

    // ---------------------------------------------------------------- Edificios

    public function guardarEdificio(GuardarEdificioRequest $request): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $edificio = Edificio::query()->create($request->validated());
        Bitacora::registrar('edificios', $edificio->id, $request->user()->id, 'create', ['evento' => 'edificio_creado'] + $edificio->only(['nombre', 'clave']));

        return response()->json(['data' => $edificio], 201);
    }

    public function actualizarEdificio(GuardarEdificioRequest $request, Edificio $edificio): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $antes = $edificio->only(['nombre', 'clave', 'descripcion']);

        DB::transaction(function () use ($request, $edificio) {
            $edificio->update($request->validated());
            // Mantiene sincronizado el nombre del edificio en las ubicaciones de sus laboratorios.
            Ubicacion::query()->where('edificio_id', $edificio->id)
                ->orWhereIn('id', $edificio->laboratorios()->whereNotNull('ubicacion_id')->pluck('ubicacion_id'))
                ->update(['edificio' => $edificio->nombre]);
        });
        Bitacora::registrar('edificios', $edificio->id, $request->user()->id, 'update',
            ['evento' => 'edificio_actualizado'] + Bitacora::diferencias($antes, $edificio->only(['nombre', 'clave', 'descripcion'])));

        return response()->json(['data' => $edificio->fresh()]);
    }

    public function eliminarEdificio(Request $request, Edificio $edificio): JsonResponse
    {
        Gate::authorize('inventario.eliminar');
        if ($edificio->laboratorios()->exists()) {
            throw ValidationException::withMessages(['edificio' => 'No se puede eliminar un edificio que tiene laboratorios registrados.']);
        }
        $edificio->delete();
        Bitacora::registrar('edificios', $edificio->id, $request->user()->id, 'delete', ['evento' => 'edificio_eliminado', 'nombre' => $edificio->nombre]);

        return ApiResponse::sinContenido();
    }

    // ------------------------------------------------------------- Laboratorios

    #[OA\Get(path: '/api/v1/inventario/laboratorios/{laboratorio}', tags: ['Inventario'], summary: 'Detalle del laboratorio: indicadores y todas sus computadoras')]
    public function laboratorio(Request $request, Laboratorio $laboratorio): JsonResponse
    {
        Gate::authorize('inventario.ver');

        $equipos = $this->inventario->visiblesPara($request->user())
            ->where('laboratorio_id', $laboratorio->id)
            ->with(['software', 'laboratorio.edificio', 'activo'])
            ->orderBy('nombre_equipo')
            ->get();

        $laboratorio->load(['edificio', 'departamento', 'responsable']);

        return response()->json(['data' => [
            'id' => $laboratorio->id,
            'nombre' => $laboratorio->nombre,
            'clave' => $laboratorio->clave,
            'capacidad' => $laboratorio->capacidad,
            'descripcion' => $laboratorio->descripcion,
            'edificio' => ['id' => $laboratorio->edificio->id, 'nombre' => $laboratorio->edificio->nombre],
            'departamento' => ['id' => $laboratorio->departamento_id, 'nombre' => $laboratorio->departamento?->nombre],
            'responsable' => $laboratorio->responsable ? ['id' => $laboratorio->responsable->id, 'nombre' => $laboratorio->responsable->nombre] : null,
            'resumen' => $this->inventario->resumen($equipos),
            'computadoras' => ComputadoraResource::collection($equipos),
        ]]);
    }

    public function guardarLaboratorio(GuardarLaboratorioRequest $request): JsonResponse
    {
        Gate::authorize('inventario.gestionar');

        $laboratorio = $this->inventario->crearLaboratorio($request->validated());

        Bitacora::registrar('laboratorios', $laboratorio->id, $request->user()->id, 'create',
            ['evento' => 'laboratorio_creado', 'edificio_id' => $laboratorio->edificio_id] + $laboratorio->only(['nombre', 'clave', 'capacidad']));

        return response()->json(['data' => $laboratorio->load('edificio')], 201);
    }

    public function actualizarLaboratorio(GuardarLaboratorioRequest $request, Laboratorio $laboratorio): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $campos = ['edificio_id', 'departamento_id', 'responsable_id', 'nombre', 'clave', 'capacidad', 'descripcion'];
        $antes = $laboratorio->only($campos);

        DB::transaction(function () use ($request, $laboratorio) {
            $laboratorio->update($request->validated());
            $laboratorio->load('edificio');
            if ($laboratorio->ubicacion_id) {
                Ubicacion::query()->whereKey($laboratorio->ubicacion_id)->update([
                    'nombre' => $laboratorio->nombre,
                    'edificio' => $laboratorio->edificio->nombre,
                    'edificio_id' => $laboratorio->edificio_id,
                    'tipo_espacio' => 'laboratorio',
                    'departamento_id' => $laboratorio->departamento_id,
                ]);
            }
        });

        Bitacora::registrar('laboratorios', $laboratorio->id, $request->user()->id, 'update',
            ['evento' => 'laboratorio_actualizado'] + Bitacora::diferencias($antes, $laboratorio->only($campos)));

        return response()->json(['data' => $laboratorio->fresh('edificio')]);
    }

    public function eliminarLaboratorio(Request $request, Laboratorio $laboratorio): JsonResponse
    {
        Gate::authorize('inventario.eliminar');
        if ($laboratorio->computadoras()->exists()) {
            throw ValidationException::withMessages(['laboratorio' => 'No se puede eliminar un laboratorio que tiene computadoras; reubícalas primero.']);
        }
        $laboratorio->delete();
        Bitacora::registrar('laboratorios', $laboratorio->id, $request->user()->id, 'delete', ['evento' => 'laboratorio_eliminado', 'nombre' => $laboratorio->nombre]);

        return ApiResponse::sinContenido();
    }
}
