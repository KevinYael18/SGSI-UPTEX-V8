<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\ControlIso;
use App\Support\CatalogoTiposActivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Catálogo de tipos específicos de activo y reconocimiento automático a
 * partir del nombre (el frontend aplica las mismas reglas mientras se escribe).
 */
class TiposActivoController extends Controller
{
    #[OA\Get(path: '/api/v1/activos/tipos-tecnicos', tags: ['Activos'], summary: 'Tipos específicos, palabras clave y plantillas de campos técnicos')]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Activo::class);

        $datos = CatalogoTiposActivo::paraFrontend();
        // Controles sugeridos con su id (solo sugerencia: vincular no implica cumplir).
        $codigos = collect($datos['tipos'])->pluck('controles_sugeridos')->flatten()->unique()->values();
        $datos['controles'] = ControlIso::query()->whereIn('codigo', $codigos)->get(['id', 'codigo', 'nombre', 'estado_implementacion']);

        return response()->json(['data' => $datos]);
    }

    #[OA\Get(path: '/api/v1/activos/reconocer', tags: ['Activos'], summary: 'Reconoce el tipo específico de activo a partir del nombre')]
    public function reconocer(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activo::class);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['nullable', 'in:hardware,software,informacion,servicio,infraestructura'],
        ]);

        return response()->json(['data' => CatalogoTiposActivo::reconocer($datos['tipo'] ?? null, $datos['nombre'])]);
    }
}
