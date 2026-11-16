<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Edificio;
use App\Models\Ubicacion;
use App\Support\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Ubicaciones institucionales (edificio → piso → espacio). Amplía la tabla
 * `ubicaciones` existente; los activos siguen apuntando a ella con ubicacion_id.
 */
class UbicacionController extends Controller
{
    #[OA\Get(path: '/api/v1/inventario/ubicaciones', tags: ['Inventario'], summary: 'Ubicaciones con edificio, piso, tipo de espacio y número de activos')]
    public function index(): JsonResponse
    {
        Gate::authorize('inventario.ver');

        $ubicaciones = Ubicacion::query()->with(['edificioRelacion', 'departamento'])->withCount('activos')
            ->orderBy('nombre')->get()
            ->map(fn (Ubicacion $u) => [
                'id' => $u->id, 'nombre' => $u->nombre, 'descripcion' => $u->descripcion(),
                'departamento_id' => $u->departamento_id, 'departamento' => $u->departamento?->nombre,
                'edificio_id' => $u->edificio_id, 'edificio' => $u->edificioRelacion?->nombre ?? $u->edificio,
                'piso' => $u->piso, 'tipo_espacio' => $u->tipo_espacio,
                'tipo_espacio_label' => Ubicacion::TIPOS_ESPACIO[$u->tipo_espacio] ?? null,
                'activos' => $u->activos_count,
            ]);

        return response()->json(['data' => $ubicaciones, 'tipos_espacio' => collect(Ubicacion::TIPOS_ESPACIO)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $ubicacion = Ubicacion::query()->create($this->validar($request));
        Bitacora::registrar('ubicaciones', $ubicacion->id, $request->user()->id, 'create', ['evento' => 'ubicacion_creada', 'descripcion' => $ubicacion->descripcion()]);

        return response()->json(['data' => $ubicacion], 201);
    }

    public function update(Request $request, Ubicacion $ubicacion): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $campos = ['nombre', 'departamento_id', 'edificio_id', 'piso', 'tipo_espacio'];
        $antes = $ubicacion->only($campos);
        $ubicacion->update($this->validar($request, $ubicacion));
        Bitacora::registrar('ubicaciones', $ubicacion->id, $request->user()->id, 'update',
            ['evento' => 'ubicacion_actualizada'] + Bitacora::diferencias($antes, $ubicacion->only($campos)));

        return response()->json(['data' => $ubicacion->fresh()]);
    }

    public function destroy(Request $request, Ubicacion $ubicacion): JsonResponse
    {
        Gate::authorize('inventario.eliminar');
        if ($ubicacion->activos()->exists() || \App\Models\Laboratorio::query()->where('ubicacion_id', $ubicacion->id)->exists()) {
            throw ValidationException::withMessages(['ubicacion' => 'No se puede eliminar: hay activos o un laboratorio asociados a esta ubicación.']);
        }
        $ubicacion->delete();
        Bitacora::registrar('ubicaciones', $ubicacion->id, $request->user()->id, 'delete', ['evento' => 'ubicacion_eliminada', 'nombre' => $ubicacion->nombre]);

        return ApiResponse::sinContenido();
    }

    private function validar(Request $request, ?Ubicacion $actual = null): array
    {
        $req = $actual ? 'sometimes' : 'required';
        $datos = $request->validate([
            'nombre' => [$req, 'string', 'max:120'],
            'departamento_id' => [$req, 'integer', 'exists:departamentos,id'],
            'edificio_id' => ['sometimes', 'nullable', 'integer', 'exists:edificios,id'],
            'piso' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tipo_espacio' => ['sometimes', 'nullable', Rule::in(array_keys(Ubicacion::TIPOS_ESPACIO))],
        ], [
            'nombre.required' => 'Escribe el nombre del espacio.',
            'departamento_id.required' => 'Selecciona el departamento.',
            '*.exists' => 'El registro seleccionado no existe.',
        ]);
        // Mantiene el texto heredado `edificio` sincronizado con el edificio elegido.
        if (array_key_exists('edificio_id', $datos)) {
            $datos['edificio'] = $datos['edificio_id'] ? Edificio::query()->find($datos['edificio_id'])?->nombre : null;
        }

        return $datos;
    }
}
