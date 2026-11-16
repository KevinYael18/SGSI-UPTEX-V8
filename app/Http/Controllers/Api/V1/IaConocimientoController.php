<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IaConocimientoRequest;
use App\Models\IaDocumentoConocimiento;
use App\Services\AI\AIKnowledgeBase;
use App\Support\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Base de conocimiento (RAG): solo quien tiene ia.conocimiento la administra. */
class IaConocimientoController extends Controller
{
    #[OA\Get(path: '/api/v1/ia/conocimiento', tags: ['IA'], summary: 'Documentos de la base de conocimiento')]
    public function index(): JsonResponse
    {
        $this->authorize('ia.conocimiento');

        $documentos = IaDocumentoConocimiento::query()->with('subidoPor:id,nombre')->latest()->get()
            ->map(fn (IaDocumentoConocimiento $d) => $this->recurso($d));

        return response()->json(['data' => $documentos]);
    }

    #[OA\Post(path: '/api/v1/ia/conocimiento', tags: ['IA'], summary: 'Carga e indexa un documento autorizado')]
    public function store(IaConocimientoRequest $request, AIKnowledgeBase $base): JsonResponse
    {
        // Leer un PDF (vía Gemini) + analizar/indexar puede superar el límite general de 60 s.
        set_time_limit(170);
        $documento = $base->cargar(
            $request->file('archivo'),
            $request->string('titulo')->toString(),
            $request->string('categoria')->toString(),
            $request->input('descripcion'),
            $request->user(),
        );

        return response()->json(['data' => $this->recurso($documento->load('subidoPor:id,nombre'))], 201);
    }

    #[OA\Patch(path: '/api/v1/ia/conocimiento/{uuid}', tags: ['IA'], summary: 'Activa o desactiva un documento como fuente')]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('ia.conocimiento');
        $datos = $request->validate(['activo' => ['required', 'boolean']]);

        $documento = IaDocumentoConocimiento::query()->where('uuid', $uuid)->firstOrFail();
        $documento->update(['activo' => $datos['activo']]);
        Bitacora::registrar('ia_documentos_conocimiento', $documento->id, $request->user()->id, 'update', [
            'evento' => $datos['activo'] ? 'ia_conocimiento_activado' : 'ia_conocimiento_desactivado',
        ]);

        return response()->json(['data' => $this->recurso($documento->load('subidoPor:id,nombre'))]);
    }

    #[OA\Delete(path: '/api/v1/ia/conocimiento/{uuid}', tags: ['IA'], summary: 'Retira un documento de la base de conocimiento')]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('ia.conocimiento');

        $documento = IaDocumentoConocimiento::query()->where('uuid', $uuid)->firstOrFail();
        $documento->fragmentos()->delete();
        $documento->delete();
        Bitacora::registrar('ia_documentos_conocimiento', $documento->id, $request->user()->id, 'delete', [
            'evento' => 'ia_conocimiento_eliminado', 'titulo' => $documento->titulo,
        ]);

        return response()->json(null, 204);
    }

    private function recurso(IaDocumentoConocimiento $d): array
    {
        return [
            'uuid' => $d->uuid,
            'titulo' => $d->titulo,
            'categoria' => $d->categoria,
            'descripcion' => $d->descripcion,
            'nombre_archivo' => $d->nombre_archivo,
            'estado' => $d->estado,
            'aviso' => $d->error,
            'total_fragmentos' => $d->total_fragmentos,
            'fragmentos_sospechosos' => $d->fragmentos_sospechosos,
            'activo' => $d->activo,
            'subido_por' => $d->subidoPor?->nombre,
            'creado_en' => $d->created_at?->toIso8601String(),
        ];
    }
}
