<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusquedaGlobalRequest;
use App\Services\BusquedaGlobalService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BusquedaController extends Controller
{
    public function __construct(private readonly BusquedaGlobalService $service)
    {
    }

    #[OA\Get(
        path: '/api/v1/busqueda',
        tags: ['Busqueda'],
        summary: 'Búsqueda global sobre Activos, Riesgos, Incidentes, Auditorías, Documentos y Controles ISO'
    )]
    public function index(BusquedaGlobalRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->buscar($request->string('q')->toString())->values()]);
    }
}