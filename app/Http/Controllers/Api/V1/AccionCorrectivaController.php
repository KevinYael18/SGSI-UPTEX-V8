<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccionCorrectiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AccionCorrectivaController extends Controller
{
    #[OA\Get(
        path: '/api/v1/acciones-correctivas',
        tags: ['Auditorias'],
        summary: 'Lista todas las acciones correctivas del sistema, filtrable por estado y vencidas'
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccionCorrectiva::class);

        $query = AccionCorrectiva::query()
            ->with(['responsable', 'noConformidad.hallazgo.auditoria']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->boolean('vencidas')) {
            $query->where('fecha_compromiso', '<', now())->whereNotIn('estado', ['cerrada']);
        }

        $acciones = $query->orderBy('fecha_compromiso')->paginate(\App\Support\Paginacion::porPagina($request, 15));

        return response()->json([
            'data' => collect($acciones->items())->map(fn ($a) => [
                'id' => $a->id,
                'responsable' => $a->responsable?->nombre,
                'fecha_compromiso' => $a->fecha_compromiso->toDateString(),
                'estado' => $a->estado->value,
                'auditoria_id' => $a->noConformidad?->hallazgo?->auditoria?->id,
                'auditoria_alcance' => $a->noConformidad?->hallazgo?->auditoria?->alcance,
                'hallazgo_descripcion' => $a->noConformidad?->hallazgo?->descripcion,
                'vencida' => $a->fecha_compromiso->isPast() && $a->estado->value !== 'cerrada',
            ]),
            'meta' => [
                'current_page' => $acciones->currentPage(),
                'last_page' => $acciones->lastPage(),
                'per_page' => $acciones->perPage(),
                'total' => $acciones->total(),
            ],
        ]);
    }

    #[OA\Patch(path: '/api/v1/acciones-correctivas/{accion_correctiva}/cerrar', tags: ['Auditorias'], summary: 'Marca una acción correctiva como cerrada')]
    public function cerrar(AccionCorrectiva $accionCorrectiva): JsonResponse
    {
        $this->authorize('update', $accionCorrectiva);

        $accionCorrectiva->update(['estado' => 'cerrada']);

        return response()->json(['data' => ['id' => $accionCorrectiva->id, 'estado' => 'cerrada']]);
    }
}