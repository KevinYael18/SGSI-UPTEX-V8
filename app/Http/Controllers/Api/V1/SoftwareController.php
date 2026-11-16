<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarSoftwareRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InstalacionSoftware;
use App\Models\Software;
use App\Support\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Catálogo centralizado de software (ISO 27001 A.8.19). Las instalaciones en
 * cada equipo se registran en ComputadoraController.
 */
class SoftwareController extends Controller
{
    #[OA\Get(path: '/api/v1/software', tags: ['Inventario'], summary: 'Catálogo de software con número de instalaciones y pendientes de actualización')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('inventario.ver');
        $buscar = trim((string) $request->input('buscar', ''));

        $programas = Software::query()
            ->with('computadoras:id')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', "%{$buscar}%")->orWhere('fabricante', 'like', "%{$buscar}%")))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->input('categoria')))
            ->when($request->filled('estado_autorizacion'), fn ($q) => $q->where('estado_autorizacion', $request->input('estado_autorizacion')))
            ->orderBy('nombre')
            ->get()
            ->map(fn (Software $s) => $s->only(['id', 'nombre', 'fabricante', 'categoria', 'tipo_licencia', 'estado_autorizacion', 'version_vigente', 'es_antivirus', 'descripcion']) + [
                'instalaciones' => $s->computadoras->count(),
                'pendientes_actualizacion' => $s->computadoras->filter(fn ($c) => InstalacionSoftware::estaDesactualizada(
                    (bool) $c->pivot->requiere_actualizacion, $c->pivot->version, $s->version_vigente))->count(),
                'sin_licencia_identificada' => $s->computadoras->whereIn('pivot.estado_licencia', ['sin_licencia_identificada', 'pendiente_verificacion'])->count(),
            ]);

        return response()->json(['data' => $programas]);
    }

    public function store(GuardarSoftwareRequest $request): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $software = Software::query()->create($request->validated());
        Bitacora::registrar('software_catalogo', $software->id, $request->user()->id, 'create',
            ['evento' => 'software_agregado_catalogo'] + $software->only(['nombre', 'fabricante', 'categoria', 'estado_autorizacion']));

        return response()->json(['data' => $software], 201);
    }

    public function update(GuardarSoftwareRequest $request, Software $software): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $campos = ['nombre', 'fabricante', 'categoria', 'tipo_licencia', 'estado_autorizacion', 'version_vigente', 'es_antivirus', 'descripcion'];
        $antes = $software->only($campos);
        $software->update($request->validated());
        Bitacora::registrar('software_catalogo', $software->id, $request->user()->id, 'update',
            ['evento' => 'software_catalogo_modificado'] + Bitacora::diferencias($antes, $software->only($campos)));

        return response()->json(['data' => $software->fresh()]);
    }

    public function destroy(Request $request, Software $software): JsonResponse
    {
        Gate::authorize('inventario.eliminar');
        if ($software->computadorasHistorico()->exists()) {
            throw ValidationException::withMessages(['software' => 'No se puede eliminar: el programa está (o estuvo) registrado en uno o más equipos y se conserva su historial.']);
        }
        $software->delete();
        Bitacora::registrar('software_catalogo', $software->id, $request->user()->id, 'delete', ['evento' => 'software_eliminado_catalogo', 'nombre' => $software->nombre]);

        return ApiResponse::sinContenido();
    }

    #[OA\Get(path: '/api/v1/software/{software}/equipos', tags: ['Inventario'], summary: 'Equipos donde el programa está instalado (y el historial de desinstalaciones)')]
    public function equipos(Request $request, Software $software): JsonResponse
    {
        Gate::authorize('inventario.ver');

        $visibles = app(\App\Services\InventarioService::class)->visiblesPara($request->user())->pluck('id');
        $equipos = $software->computadorasHistorico()->with(['laboratorio.edificio', 'activo'])
            ->whereIn('computadoras.id', $visibles)->orderBy('nombre_equipo')->get()
            ->map(fn ($c) => [
                'uuid' => $c->uuid,
                'nombre_equipo' => $c->nombre_equipo,
                'activo' => $c->activo?->nombre,
                'laboratorio' => $c->laboratorio ? $c->laboratorio->nombre . ' · ' . ($c->laboratorio->edificio?->nombre ?? '') : null,
                'estado_instalacion' => $c->pivot->estado_instalacion,
                'version' => $c->pivot->version,
                'estado_licencia' => $c->pivot->estado_licencia,
                'desactualizado' => InstalacionSoftware::estaDesactualizada((bool) $c->pivot->requiere_actualizacion, $c->pivot->version, $software->version_vigente),
            ]);

        return response()->json(['data' => $equipos]);
    }
}
