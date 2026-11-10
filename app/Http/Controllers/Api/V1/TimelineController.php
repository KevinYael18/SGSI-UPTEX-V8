<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TimelineService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Timeline de actividad reutilizando `activity_log` (mejora #9 Ronda 2).
 * {identificador} es UUID o ID según lo que esa entidad exponga al
 * frontend (ver App\Support\RegistroTimeline::resolverModelo) — Activos,
 * Incidentes y Documentos por UUID; Riesgos y Auditorías por ID.
 */
class TimelineController extends Controller
{
    public function __construct(private readonly TimelineService $service)
    {
    }

    #[OA\Get(
        path: '/api/v1/{tipo}/{identificador}/timeline',
        tags: ['Timeline'],
        summary: 'Historial de actividad de una entidad (activos, riesgos, incidentes, auditorias, documentos)'
    )]
    public function index(string $tipo, string $identificador): JsonResponse
    {
        return response()->json(['data' => $this->service->paraEntidad($tipo, $identificador)]);
    }
}