<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IaActivoRequest;
use App\Http\Requests\IaAnalisisTextoRequest;
use App\Http\Requests\IaDecisionRequest;
use App\Http\Requests\IaRiesgoRequest;
use App\Models\Activo;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\IaAnalisis;
use App\Models\Incidente;
use App\Services\AI\AIAccessControl;
use App\Services\AI\AIUsageTracker;
use App\Services\AI\Analyzers\AIAssetAnalyzer;
use App\Services\AI\Analyzers\AIAuditAnalyzer;
use App\Services\AI\Analyzers\AIDashboardAnalyzer;
use App\Services\AI\Analyzers\AIDocumentAnalyzer;
use App\Services\AI\Analyzers\AIIncidentAnalyzer;
use App\Services\AI\GeminiService;
use App\Services\AI\SGSIIntelligenceService;
use App\Services\IaRiesgoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * SGSI Intelligence Assistant — análisis contextuales de los módulos.
 * Cada endpoint: autenticación (sanctum) → permiso del módulo (Policy) →
 * ia.usar (SGSIIntelligenceService) → límite de frecuencia (throttle) →
 * presupuesto de tokens (AIUsageTracker). Ninguno modifica registros: todos
 * devuelven una PROPUESTA con `analisis_uuid` para aceptarla o rechazarla.
 */
class IaController extends Controller
{
    #[OA\Get(path: '/api/v1/ia/estado', tags: ['IA'], summary: 'Disponibilidad del asistente y capacidades del usuario')]
    public function estado(Request $request, GeminiService $gemini, AIAccessControl $acceso, AIUsageTracker $uso): JsonResponse
    {
        $usuario = $request->user();

        return response()->json(['data' => [
            'nombre' => SGSIIntelligenceService::NOMBRE,
            'disponible' => $gemini->configurado(),
            'permisos' => [
                'usar' => $acceso->puede($usuario, 'ia.usar'),
                'conocimiento' => $acceso->puede($usuario, 'ia.conocimiento'),
                'administrar' => $acceso->puede($usuario, 'ia.administrar'),
            ],
            'modulos' => $acceso->modulosPermitidos($usuario),
            'consumo_hoy' => [
                'tokens' => $uso->tokensHoy($usuario->id),
                'limite' => (int) config('sgsi.ia.limites.tokens_diarios_usuario'),
            ],
            'max_caracteres_mensaje' => (int) config('sgsi.ia.limites.max_caracteres_mensaje'),
        ]]);
    }

    #[OA\Post(
        path: '/api/v1/ia/sugerir-riesgo',
        tags: ['Riesgos'],
        summary: 'Analiza un riesgo con SGSI Intelligence Assistant (controles, P/I, amenazas, CID, tratamiento, residual)',
        responses: [
            new OA\Response(response: 200, description: 'Propuesta generada'),
            new OA\Response(response: 503, description: 'IA_NO_DISPONIBLE: el proveedor de IA no respondió o su respuesta no fue válida'),
        ]
    )]
    public function sugerirRiesgo(IaRiesgoRequest $request, IaRiesgoService $service): JsonResponse
    {
        $resultado = $service->sugerirParaRiesgo(
            $request->user(),
            $request->integer('activo_id'),
            $request->integer('amenaza_id'),
            $request->filled('vulnerabilidad_id') ? $request->integer('vulnerabilidad_id') : null,
            $request->filled('descripcion') ? $request->string('descripcion')->toString() : null,
            $request->filled('riesgo_id') ? $request->integer('riesgo_id') : null,
        );

        return response()->json(['data' => $resultado]);
    }

    #[OA\Post(path: '/api/v1/ia/analizar/activo', tags: ['IA'], summary: 'Analiza un activo registrado o el borrador del formulario')]
    public function analizarActivo(IaActivoRequest $request, AIAssetAnalyzer $analizador): JsonResponse
    {
        $activo = null;
        if ($request->filled('activo_uuid')) {
            $activo = Activo::query()->where('uuid', $request->string('activo_uuid'))->firstOrFail();
            $this->authorize('view', $activo);
        }

        return response()->json(['data' => $analizador->analizar($request->user(), $activo, (array) $request->input('borrador', []))]);
    }

    #[OA\Post(path: '/api/v1/ia/analizar/incidentes/{incidente}', tags: ['IA'], summary: 'Analiza un incidente (no cambia su estado)')]
    public function analizarIncidente(Request $request, Incidente $incidente, AIIncidentAnalyzer $analizador): JsonResponse
    {
        $this->authorize('view', $incidente);

        return response()->json(['data' => $analizador->analizar($request->user(), $incidente)]);
    }

    #[OA\Post(path: '/api/v1/ia/analizar/auditorias/{auditoria}', tags: ['IA'], summary: 'Analiza una auditoría y propone hallazgos para validación del auditor')]
    public function analizarAuditoria(IaAnalisisTextoRequest $request, Auditoria $auditoria, AIAuditAnalyzer $analizador): JsonResponse
    {
        $this->authorize('view', $auditoria);

        return response()->json(['data' => $analizador->analizar($request->user(), $auditoria, $request->input('notas'))]);
    }

    #[OA\Post(path: '/api/v1/ia/analizar/documentos/{documento}', tags: ['IA'], summary: 'Analiza la versión vigente de un documento del SGSI')]
    public function analizarDocumento(IaAnalisisTextoRequest $request, Documento $documento, AIDocumentAnalyzer $analizador): JsonResponse
    {
        $this->authorize('view', $documento);
        // Leer un PDF (vía Gemini) + analizar/indexar puede superar el límite general de 60 s.
        set_time_limit(170);

        return response()->json(['data' => $analizador->analizar($request->user(), $documento, $request->input('pregunta'))]);
    }

    #[OA\Post(path: '/api/v1/ia/analizar/dashboard', tags: ['IA'], summary: 'Resumen ejecutivo del SGSI con datos reales')]
    public function analizarDashboard(Request $request, AIDashboardAnalyzer $analizador): JsonResponse
    {
        return response()->json(['data' => $analizador->analizar($request->user())]);
    }

    #[OA\Patch(path: '/api/v1/ia/analisis/{uuid}/decision', tags: ['IA'], summary: 'Acepta, rechaza o aplica parcialmente una propuesta')]
    public function decidir(IaDecisionRequest $request, string $uuid, SGSIIntelligenceService $asistente): JsonResponse
    {
        $analisis = IaAnalisis::query()->where('uuid', $uuid)->firstOrFail();
        $analisis = $asistente->decidir($request->user(), $analisis, $request->string('decision')->toString(), (array) $request->input('elementos_aplicados', []));

        return response()->json(['data' => [
            'analisis_uuid' => $analisis->uuid,
            'estado_propuesta' => $analisis->estado_propuesta,
            'decidido_en' => $analisis->decidido_en?->toIso8601String(),
        ]]);
    }

    #[OA\Get(path: '/api/v1/ia/consumo', tags: ['IA'], summary: 'Consumo del asistente (solo administración)')]
    public function consumo(Request $request, AIUsageTracker $uso): JsonResponse
    {
        $this->authorize('ia.administrar');

        return response()->json(['data' => $uso->resumen(7)]);
    }
}
